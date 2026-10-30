<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogisticsTreasury
{
    public function installed(): bool { return Schema::hasTable('logistics_seller_payouts') && Schema::hasTable('logistics_finance_evidence'); }

    public function fail(string $message): never { throw ValidationException::withMessages(['finance'=>$message]); }

    public function admin(User $actor): User
    {
        abort_unless($this->installed(),503,'The seller payout migration is required.');
        return app(LogisticsFinance::class)->admin(User::whereKey($actor->id)->lockForUpdate()->firstOrFail());
    }

    public function account(string $id,string $company): object
    {
        return DB::table('logistics_treasury_accounts')->where('id',$id)->where('company_name',$company)->lockForUpdate()->firstOrFail();
    }

    public function accountData(array $input): array
    {
        foreach(['institution','account_name','account_number'] as $key) if(is_string($input[$key]??null)) $input[$key]=trim($input[$key]);
        $data=Validator::make($input,['channel'=>'required|in:gcash','institution'=>'required|string|max:100',
            'account_name'=>'required|string|min:2|max:150','account_number'=>['required','string','regex:/^[A-Za-z0-9 -]{4,64}$/D']])->validate();
        if($data['channel']==='gcash' && !preg_match('/^09[0-9]{9}$/D',$data['account_number'])) $this->fail('Enter an 11-digit Philippine GCash mobile number.');
        if($data['channel']==='gcash') $data['institution']='GCash';
        return $data;
    }

    public function requireGcash(object $account): void
    {
        if($account->channel !== 'gcash' || !preg_match('/^09[0-9]{9}$/D',(string)$account->account_number))
            $this->fail('Only GCash accounts are available for new funding, transfers and payouts. Historical accounts remain unchanged.');
    }

    public function createAccount(User $actor,array $input): void
    {
        $data=$this->accountData($input);
        DB::transaction(function()use($actor,$data){
            $actor=$this->admin($actor);$id=(string)Str::uuid();
            DB::table('logistics_treasury_accounts')->insert($data+['id'=>$id,'company_name'=>$actor->business_name,'created_by'=>$actor->id,'created_at'=>now()]);
            $this->event($actor,'treasury_account_authorized','treasury_account',$id,['channel'=>$data['channel'],'institution'=>$data['institution']]);
        },3);
    }

    public function destination(User $actor,array $input): void
    {
        $data=$this->accountData($input);
        DB::transaction(function()use($actor,$data){
            $actor=User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->account_type==='seller' && $actor->status==='approved' && !$actor->is_admin && !$actor->is_logistics,403);
            $id=(string)Str::uuid();
            DB::table('logistics_payout_destinations')->insert($data+['id'=>$id,'seller_id'=>$actor->id,'created_at'=>now()]);
            // Destinations are append-only and every payout retains the seller-provided destination ID.
        },3);
    }

    public function qualifiedHub(int $hub): int
    {
        $lock=DB::transactionLevel()>0;
        $receipts=DB::table('logistics_cash_allocations as a')->join('logistics_cash_handovers as h','h.id','=','a.handover_id')
            ->where('h.hub_id',$hub)->where('h.kind','remittance')->whereNotNull('h.counted_at')
            ->when($lock,fn($q)=>$q->lockForUpdate())->get(['a.verified_cents'])->sum('verified_cents');
        $received=DB::table('logistics_cash_handovers')->where('destination_hub_id',$hub)->where('kind','transfer')->whereNotNull('counted_at')
            ->when($lock,fn($q)=>$q->lockForUpdate())->get(['cod_received_cents'])->sum('cod_received_cents');
        $released=DB::table('logistics_cash_handovers')->where('hub_id',$hub)->whereNotNull('dispatched_at')
            ->when($lock,fn($q)=>$q->lockForUpdate())->get(['cod_released_cents','expected_cents'])->sum(fn($h)=>$h->cod_released_cents===null?(int)$h->expected_cents:(int)$h->cod_released_cents);
        return max(0,min(app(LogisticsCashDesk::class)->custody($hub),(int)$receipts+(int)$received-(int)$released));
    }

    public function transfer(User $actor,array $input): void
    {
        $data=Validator::make($input,['key'=>'required|uuid','hub_id'=>'required|integer','account_id'=>'required|uuid','amount'=>'required|string','notes'=>'required|string|min:3|max:2000'])->validate();
        $amount=app(LogisticsCashDesk::class)->amount($data['amount']);if($amount<=0)$this->fail('Enter a positive treasury transfer amount.');
        DB::transaction(function()use($actor,$data,$amount){
            $actor=$this->admin($actor);
            $hub=DB::table('logistics_hubs')->where('id',$data['hub_id'])->where('company_name',$actor->business_name)->where('is_operational',true)->lockForUpdate()->firstOrFail();
            $account=$this->account($data['account_id'],$actor->business_name);$this->requireGcash($account);
            $hash=hash('sha256',json_encode([$actor->id,$hub->id,$account->id,$amount,$data['notes']]));
            $old=DB::table('logistics_cash_handovers')->where('company_name',$actor->business_name)->where('idempotency_key',$data['key'])->lockForUpdate()->first();
            if($old){abort_unless($old->request_hash===$hash,409);return;}
            $id=(string)Str::uuid();
            DB::table('logistics_cash_handovers')->insert(['id'=>$id,'company_name'=>$actor->business_name,'kind'=>'treasury','hub_id'=>$hub->id,
                'treasury_account_id'=>$account->id,'actor_id'=>$actor->id,'expected_cents'=>$amount,'status'=>'authorized','idempotency_key'=>$data['key'],
                'request_hash'=>$hash,'notes'=>$data['notes'],'created_at'=>now(),'updated_at'=>now()]);
            $this->event($actor,'treasury_transfer_authorized','cash_handover',$id,['hub_id'=>$hub->id,'account_id'=>$account->id,'amount_cents'=>$amount,'notes'=>$data['notes']]);
        },3);
    }

    public function release(User $actor,string $id,string $amount): void
    {
        $cents=app(LogisticsCashDesk::class)->amount($amount);
        DB::transaction(function()use($actor,$id,$cents){
            $actor=app(LogisticsCashDesk::class)->actor($actor);
            $h=DB::table('logistics_cash_handovers')->where('id',$id)->where('company_name',$actor->business_name)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isHubStaff() && (int)$actor->logistics_hub_id===(int)$h->hub_id,403);
            $hub=DB::table('logistics_hubs')->where('id',$h->hub_id)->where('company_name',$actor->business_name)->where('is_operational',true)->lockForUpdate()->firstOrFail();
            abort_unless($h->kind==='treasury',422);
            $this->requireGcash($this->account($h->treasury_account_id,$actor->business_name));
            if($h->dispatched_at){abort_unless((int)$h->cod_released_cents===$cents,409);return;}
            abort_unless($h->status==='authorized',409);
            if($cents!==(int)$h->expected_cents || $cents>$this->qualifiedHub($hub->id))$this->fail('Release the authorized amount using available verified COD cash only. Overages and in-transit funds are excluded.');
            DB::table('logistics_cash_handovers')->where('id',$id)->update(['status'=>'in_transit','cod_released_cents'=>$cents,'dispatched_at'=>now(),'updated_at'=>now()]);
            DB::table('logistics_cash_movements')->insert(['handover_id'=>$id,'hub_id'=>$h->hub_id,'kind'=>'treasury_dispatched','delta_cents'=>-$cents,'actor_id'=>$actor->id,'created_at'=>now()]);
            $this->event($actor,'treasury_cash_released','cash_handover',$id,['amount_cents'=>$cents,'account_id'=>$h->treasury_account_id]);
        },3);
    }

    public function receipt(User $actor,string $id,array $input,UploadedFile $file): void
    {
        $data=$this->receiptData($input,$file);$amount=app(LogisticsCashDesk::class)->amount($data['amount']);
        DB::transaction(function()use($actor,$id,$data,$file,$amount){
            $actor=$this->admin($actor);
            $h=DB::table('logistics_cash_handovers')->where('id',$id)->where('company_name',$actor->business_name)->lockForUpdate()->firstOrFail();
            abort_unless($h->kind==='treasury',422);
            if($h->counted_at)abort(409,'This treasury receipt was already recorded.');
            abort_unless($h->status==='in_transit',409,'Hub Staff must confirm cash release first.');
            $account=$this->account($h->treasury_account_id,$actor->business_name);
            // An overage is recorded as a discrepancy, but never converted into verified COD funding.
            $qualified=min($amount,(int)$h->cod_released_cents);
            $reference=$this->reference($account,$data['reference'],'treasury_receipt',$id);
            $this->evidence($actor,'treasury_receipt',$id,$file);
            $this->move($actor,$account,'cod',$qualified,'treasury_receipt',$id,$reference);
            if($amount>$qualified)$this->move($actor,$account,'unallocated',$amount-$qualified,'treasury_receipt',$id);
            DB::table('logistics_cash_handovers')->where('id',$id)->update(['status'=>$amount===(int)$h->expected_cents?'received':'discrepancy',
                'counted_cents'=>$amount,'cod_received_cents'=>$qualified,'counted_by'=>$actor->id,'counted_at'=>now(),'updated_at'=>now()]);
            $this->event($actor,'treasury_receipt_verified','cash_handover',$id,['counted_cents'=>$amount,'qualified_cod_cents'=>$qualified,'reference'=>$reference,'notes'=>$data['notes']]);
        },3);
    }

    public function adjustmentFunding(User $actor,array $input,UploadedFile $file): void
    {
        $data=$this->receiptData($input,$file)+Validator::make($input,['account_id'=>'required|uuid','key'=>'required|uuid'])->validate();
        $amount=app(LogisticsCashDesk::class)->amount($data['amount']);if($amount<=0)$this->fail('Adjustment funding must be positive.');
        DB::transaction(function()use($actor,$data,$file,$amount){
            $actor=$this->admin($actor);$account=$this->account($data['account_id'],$actor->business_name);$this->requireGcash($account);
            abort_if(DB::table('logistics_treasury_movements')->where('source_type','adjustment_funding')->where('source_id',$data['key'])->lockForUpdate()->exists(),409,'This funding was already recorded.');
            $reference=$this->reference($account,$data['reference'],'adjustment_funding',$data['key']);
            $this->evidence($actor,'adjustment_funding',$data['key'],$file);
            $this->move($actor,$account,'adjustment',$amount,'adjustment_funding',$data['key'],$reference);
            $this->event($actor,'adjustment_funding_verified','adjustment_funding',$data['key'],['amount_cents'=>$amount,'account_id'=>$account->id,'reference'=>$reference,'notes'=>$data['notes']]);
        },3);
    }

    public function receiptData(array $input,UploadedFile $file): array
    {
        return Validator::make($input+['proof'=>$file],['amount'=>'required|string','reference'=>['required','string','regex:/^[A-Za-z0-9-]{6,64}$/D'],
            'notes'=>'required|string|min:3|max:2000','received'=>'required|accepted','proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
    }

    public function evidence(User $actor,string $type,string $id,UploadedFile $file): void
    {
        Validator::make(['proof'=>$file],['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
        $hash=hash_file('sha256',$file->getRealPath());
        if(DB::table('logistics_finance_evidence')->where('content_hash',$hash)->lockForUpdate()->exists())$this->fail('This evidence is already attached to another financial record.');
        $path=app(MediaLifecycle::class)->store($file,'logistics-finance-proofs','local');
        DB::table('logistics_finance_evidence')->insert(['id'=>(string)Str::uuid(),'company_name'=>$actor->business_name,'source_type'=>$type,'source_id'=>$id,
            'path'=>$path,'mime'=>$file->getMimeType(),'content_hash'=>$hash,'actor_id'=>$actor->id,'created_at'=>now()]);
    }

    public function reference(object $account,string $reference,string $type,string $id): string
    {
        $reference=strtoupper(trim($reference));
        if(!preg_match('/^[A-Z0-9-]{6,64}$/D',$reference))$this->fail('Enter a valid payment reference.');
        $this->requireGcash($account);
        app(LogisticsShipping::class)->reserveReference($reference,$type,$id);
        return $reference;
    }

    public function balance(string $account,string $bucket): int
    {
        return (int)DB::table('logistics_treasury_movements')->where('account_id',$account)->where('bucket',$bucket)
            ->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->get(['delta_cents'])->sum('delta_cents');
    }

    public function available(string $account,string $bucket,?string $except=null): int
    {
        $column=$bucket==='cod'?'product_cents':'adjustment_cents';
        $reserved=DB::table('logistics_seller_payouts')->where('treasury_account_id',$account)->whereIn('status',['prepared','verified'])
            ->when($except,fn($q)=>$q->where('id','!=',$except))->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->get([$column])->sum($column);
        return $this->balance($account,$bucket)-(int)$reserved;
    }

    public function move(User $actor,object $account,string $bucket,int $amount,string $type,string $id,?string $reference=null): void
    {
        DB::table('logistics_treasury_movements')->insert(['account_id'=>$account->id,'bucket'=>$bucket,'delta_cents'=>$amount,
            'source_type'=>$type,'source_id'=>$id,'reference'=>$reference,'actor_id'=>$actor->id,'created_at'=>now()]);
    }

    public function event(User $actor,string $event,string $type,string $id,array $details): void
    {
        app(LogisticsFinance::class)->event($actor->business_name,$event,$type,$id,$details,$actor->id);
        if($type==='cash_handover')app(LogisticsCashDesk::class)->notifyHandover($actor,$event,$id);
    }
}
