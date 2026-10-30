<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RiderCompensation
{
    public function installed(): bool { return Schema::hasTable('logistics_rider_payouts'); }

    public function rider(User $actor): User
    {
        $actor=User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->account_type==='rider' && $actor->status==='approved' && !$actor->is_admin && !$actor->is_logistics,403);
        return $actor;
    }

    /** Existing operational locks precede compensation and payout locks, including historical recognition. */
    private function sourceLock(object $c,array $users=[]): void
    {
        if($c->source_type==='assignment') {
            $a=DB::table('delivery_assignments')->where('id',$c->source_id)->first();
            if($a)GcashLocks::operation([$a->shipment_id],[],array_merge($users,[$c->rider_id]));
        } elseif($c->source_type==='transfer_batch') GcashLocks::operation([],[$c->source_id],array_merge($users,[$c->rider_id]));
    }

    /** Returns authoritative completion evidence, never a price derived from current rates or shipping. */
    public function completion(object $c): ?array
    {
        if($c->source_type==='assignment' && in_array($c->leg,['pickup','delivery'])) {
            $a=DB::table('delivery_assignments')->where('id',$c->source_id)->lockForUpdate()->first();
            if(!$a || (int)$a->courier_id!==(int)$c->rider_id || $a->leg!==$c->leg || in_array($a->status,['cancelled','rejected']))return null;
            $s=Shipment::whereKey($a->shipment_id)->lockForUpdate()->first();
            if(!$s || $s->logistics_company!==$c->company_name)return null;
            if($c->leg==='pickup') {
                if((int)$s->pickup_rider_id!==(int)$c->rider_id || !$s->seller_confirmed_pickup_at || !$s->rider_confirmed_pickup_at || !$s->picked_up_at || !$s->at_sorting_center_at)return null;
                if(!in_array($a->status,['accepted','picked_up','completed']))return null;
                return ['completed_at'=>$s->at_sorting_center_at->toDateTimeString(),'shipment_id'=>$s->id,'basis'=>'two_sided_pickup_and_hub_receipt'];
            }
            if(!in_array($a->status,['delivered','completed']) || !$a->delivered_at || !$s->delivered_at || (int)$s->courier_id!==(int)$c->rider_id)return null;
            // A later return does not undo work already completed. A missing COD collection never earns delivery compensation.
            if(app(CodCollection::class)->installed())app(CodCollection::class)->assertRecorded($s->order,$s->id);
            return ['completed_at'=>$a->delivered_at,'shipment_id'=>$s->id,'basis'=>'recorded_final_delivery'];
        }
        if($c->source_type==='transfer_batch' && $c->leg==='transfer' && Schema::hasTable('hub_transfer_batches')) {
            $b=DB::table('hub_transfer_batches')->where('id',$c->source_id)->lockForUpdate()->first();
            if(!$b || $b->legacy_import || $b->status!=='completed' || !$b->dispatched_at || !$b->received_at || !$b->received_by || (int)$b->rider_id!==(int)$c->rider_id || $b->company_name!==$c->company_name)return null;
            $members=DB::table('hub_transfer_members')->where('batch_id',$b->id)->lockForUpdate()->get();
            if($members->isEmpty() || $members->contains(fn($m)=>!$m->received_at))return null;
            foreach($members as $m) {
                $leg=DB::table('shipment_hub_legs')->where('id',$m->leg_id)->lockForUpdate()->first();
                if(!$leg || $leg->transfer_batch_id!==$b->id || $leg->status!=='completed' || !$leg->completed_at || !$leg->rider_confirmed_pickup_at || (int)$leg->rider_id!==(int)$c->rider_id)return null;
            }
            return ['completed_at'=>$b->received_at,'basis'=>'whole_transfer_batch_received','member_count'=>$members->count()];
        }
        return null;
    }

    public function recognize(string $id,?User $admin=null): bool
    {
        if(!$this->installed())return false;
        return DB::transaction(function()use($id,$admin){
            $peek=DB::table('logistics_compensations')->where('id',$id)->firstOrFail();
            $this->sourceLock($peek,$admin?[$admin->id]:[]);
            if($admin){$admin=app(LogisticsFinance::class)->admin($admin);abort_unless($peek->company_name===$admin->business_name,403);}
            $c=DB::table('logistics_compensations')->where('id',$id)->lockForUpdate()->firstOrFail();
            if($c->status!=='pending')return false;
            // Never earn two rider snapshots for the same source, even after a reassignment.
            $siblings=DB::table('logistics_compensations')->where('source_type',$c->source_type)->where('source_id',$c->source_id)->lockForUpdate()->get();
            if($siblings->contains(fn($s)=>$s->id!==$c->id && $s->status==='earned'))return false;
            $e=$this->completion($c);if(!$e)return false;
            DB::table('logistics_compensations')->where('id',$id)->update(['earned_source_key'=>$c->source_type.'|'.$c->source_id,'status'=>'earned','earned_at'=>$e['completed_at'],'updated_at'=>now()]);
            app(LogisticsFinance::class)->event($c->company_name,'rider_compensation_earned','compensation',$id,$e+['amount_cents'=>(int)$c->amount_cents,'rider_id'=>$c->rider_id,'historical_review'=>(bool)$admin],$admin?->id);
            return true;
        },3);
    }

    public function shipmentCompleted(string $shipment): void
    {
        if(!$this->installed())return;
        $ids=DB::table('logistics_compensations as c')->join('delivery_assignments as a','a.id','=','c.source_id')->where('c.source_type','assignment')
            ->where('a.shipment_id',$shipment)->where('c.status','pending')->pluck('c.id');
        foreach($ids as $id)$this->recognize($id);
    }
    public function batchCompleted(string $batch): void
    {
        if(!$this->installed())return;
        foreach(DB::table('logistics_compensations')->where('source_type','transfer_batch')->where('source_id',$batch)->where('status','pending')->pluck('id') as $id)$this->recognize($id);
    }

    public function destination(User $actor,array $input): void
    {
        $data=app(LogisticsTreasury::class)->accountData($input);
        DB::transaction(function()use($actor,$data){
            $actor=$this->rider($actor);$id=(string)Str::uuid();
            DB::table('logistics_rider_destinations')->insert($data+['id'=>$id,'rider_id'=>$actor->id,'created_at'=>now()]);
            app(LogisticsFinance::class)->event($actor->business_name,'rider_destination_added','rider_destination',$id,['rider_id'=>$actor->id],$actor->id);
        },3);
    }

    public function funding(User $actor,array $input,UploadedFile $file): void
    {
        $t=app(LogisticsTreasury::class);$data=$t->receiptData($input,$file)+Validator::make($input,['account_id'=>'required|uuid','key'=>'required|uuid'])->validate();
        $amount=app(LogisticsCashDesk::class)->amount($data['amount']);if($amount<=0)$t->fail('Payroll funding must be positive.');
        DB::transaction(function()use($actor,$data,$file,$amount,$t){
            $actor=$t->admin($actor);$account=$t->account($data['account_id'],$actor->business_name);
            abort_if(DB::table('logistics_treasury_movements')->where('source_type','rider_funding')->where('source_id',$data['key'])->lockForUpdate()->exists(),409,'This payroll contribution was already recorded.');
            $reference=$t->reference($account,$data['reference'],'rider_funding',$data['key']);$t->evidence($actor,'rider_funding',$data['key'],$file);
            $t->move($actor,$account,'rider_payroll',$amount,'rider_funding',$data['key'],$reference);
            $t->event($actor,'rider_funding_verified','rider_funding',$data['key'],['amount_cents'=>$amount,'account_id'=>$account->id,'reference'=>$reference,'notes'=>$data['notes']]);
        },3);
    }

    public function available(string $account,?string $except=null): int
    {
        $reserved=DB::table('logistics_rider_payouts')->where('treasury_account_id',$account)->whereIn('status',['prepared','verified'])
            ->when($except,fn($q)=>$q->where('id','!=',$except))->lockForUpdate()->get(['amount_cents'])->sum('amount_cents');
        return app(LogisticsTreasury::class)->balance($account,'rider_payroll')-(int)$reserved;
    }

    public function prepare(User $actor,array $input): void
    {
        $d=Validator::make($input,['compensation_id'=>'required|uuid','account_id'=>'required|uuid','destination_id'=>'required|uuid'])->validate();
        DB::transaction(function()use($actor,$d){
            $peek=DB::table('logistics_compensations')->where('id',$d['compensation_id'])->firstOrFail();$this->sourceLock($peek,[$actor->id]);
            $t=app(LogisticsTreasury::class);$actor=$t->admin($actor);abort_unless($peek->company_name===$actor->business_name,403);
            $account=$t->account($d['account_id'],$actor->business_name);$t->requireGcash($account);
            $c=DB::table('logistics_compensations')->where('id',$peek->id)->lockForUpdate()->firstOrFail();
            if($c->status!=='earned' || !$c->earned_at || (int)$c->amount_cents<=0)$t->fail('Only positive, recorded earned compensation can be paid.');
            $destination=DB::table('logistics_rider_destinations')->where('id',$d['destination_id'])->where('rider_id',$c->rider_id)->lockForUpdate()->firstOrFail();
            $t->requireGcash($destination);
            $old=DB::table('logistics_rider_payouts')->where('compensation_id',$c->id)->lockForUpdate()->first();
            abort_if($old && $old->status!=='cancelled',409,'This earning already has a payout.');
            if($this->available($account->id)<(int)$c->amount_cents)$t->fail('Insufficient separately verified rider-payroll funds.');
            $id=$old?->id??(string)Str::uuid();
            $v=['company_name'=>$c->company_name,'compensation_id'=>$c->id,'rider_id'=>$c->rider_id,'amount_cents'=>$c->amount_cents,
                'treasury_account_id'=>$account->id,'destination_id'=>$destination->id,'status'=>'prepared','revision'=>($old?->revision??0)+1,
                'prepared_by'=>$actor->id,'verified_by'=>null,'verified_at'=>null,'hold_reason'=>null,'notes'=>null,'updated_at'=>now()];
            if($old)DB::table('logistics_rider_payouts')->where('id',$id)->update($v);else DB::table('logistics_rider_payouts')->insert($v+['id'=>$id,'created_at'=>now()]);
            $t->event($actor,'rider_payout_prepared','rider_payout',$id,['compensation_id'=>$c->id,'amount_cents'=>(int)$c->amount_cents,'account_id'=>$account->id,'destination_id'=>$destination->id]);
        },3);
    }

    private function locked(User $actor,string $id,int $revision): array
    {
        $peek=DB::table('logistics_rider_payouts')->where('id',$id)->firstOrFail();
        $source=DB::table('logistics_compensations')->where('id',$peek->compensation_id)->firstOrFail();$this->sourceLock($source,[$actor->id]);
        $t=app(LogisticsTreasury::class);$actor=$t->admin($actor);abort_unless($peek->company_name===$actor->business_name,403);
        $account=$t->account($peek->treasury_account_id,$actor->business_name);
        $c=DB::table('logistics_compensations')->where('id',$peek->compensation_id)->lockForUpdate()->firstOrFail();
        $p=DB::table('logistics_rider_payouts')->where('id',$id)->lockForUpdate()->firstOrFail();
        abort_unless((int)$p->revision===$revision,409,'Payout changed. Refresh before continuing.');
        abort_unless($c->status==='earned' && $c->earned_at && (int)$c->amount_cents===(int)$p->amount_cents && (int)$c->rider_id===(int)$p->rider_id && $c->company_name===$p->company_name,409,'The earning basis requires review.');
        return [$actor,$account,$p];
    }

    private function recheck(object $p): void
    {
        $t=app(LogisticsTreasury::class);
        $t->requireGcash($t->account($p->treasury_account_id,$p->company_name));
        $t->requireGcash(DB::table('logistics_rider_destinations')->where('id',$p->destination_id)->firstOrFail());
        if($p->hold_reason)$t->fail('Release the documented hold before continuing.');
        if($this->available($p->treasury_account_id,$p->id)<(int)$p->amount_cents)$t->fail('Verified payroll funds are unavailable.');
    }

    public function action(User $actor,string $id,array $input): void
    {
        $d=Validator::make($input,['revision'=>'required|integer|min:1','action'=>'required|in:verify,hold,release,cancel','notes'=>'required|string|min:3|max:2000'])->validate();
        DB::transaction(function()use($actor,$id,$d){
            [$actor,$account,$p]=$this->locked($actor,$id,(int)$d['revision']);abort_unless(in_array($p->status,['prepared','verified']),409);
            $v=['revision'=>$p->revision+1,'updated_at'=>now()];
            if($d['action']==='verify') {abort_unless($p->status==='prepared',409);$this->recheck($p);$v+=['status'=>'verified','verified_by'=>$actor->id,'verified_at'=>now()];}
            elseif($d['action']==='hold')$v['hold_reason']=$d['notes'];
            elseif($d['action']==='release')$v['hold_reason']=null;
            else $v+=['status'=>'cancelled','notes'=>$d['notes']];
            DB::table('logistics_rider_payouts')->where('id',$id)->update($v);
            app(LogisticsTreasury::class)->event($actor,'rider_payout_'.$d['action'],'rider_payout',$id,['notes'=>$d['notes'],'revision'=>$v['revision']]);
        },3);
    }

    public function pay(User $actor,string $id,array $input,UploadedFile $file): void
    {
        $d=Validator::make($input,['revision'=>'required|integer|min:1','amount'=>'required|string','reference'=>['required','string','regex:/^[A-Za-z0-9-]{6,64}$/D'],
            'sent'=>'required|accepted','notes'=>'required|string|min:3|max:2000'])->validate();
        $amount=app(LogisticsCashDesk::class)->amount($d['amount']);
        DB::transaction(function()use($actor,$id,$d,$file,$amount){
            [$actor,$account,$p]=$this->locked($actor,$id,(int)$d['revision']);abort_unless($p->status==='verified',409,'Verify the payout instruction first.');$this->recheck($p);
            $t=app(LogisticsTreasury::class);if($amount!==(int)$p->amount_cents)$t->fail('Confirm the exact snapshotted earning amount.');
            $destination=DB::table('logistics_rider_destinations')->where('id',$p->destination_id)->where('rider_id',$p->rider_id)->lockForUpdate()->firstOrFail();
            // Reserve both provider namespaces for cross-provider transfers. GCash stays globally unique.
            $reference=$t->reference($account,$d['reference'],'rider_payout',$id);
            $t->reference($destination,$d['reference'],'rider_payout',$id);$t->evidence($actor,'rider_payout',$id,$file);
            $t->move($actor,$account,'rider_payroll',-$amount,'rider_payout',$id,$reference);
            DB::table('logistics_financial_transactions')->insert(['id'=>(string)Str::uuid(),'company_name'=>$p->company_name,'kind'=>'rider_payout','rider_payout_id'=>$id,
                'actor_id'=>$actor->id,'recipient_id'=>$p->rider_id,'amount_cents'=>$amount,'currency'=>'PHP','status'=>'recorded','verification_state'=>'verified',
                'idempotency_key'=>$id,'request_hash'=>hash('sha256',$id.'|'.$reference),'details'=>json_encode(['direction'=>'out','reference'=>$reference,'compensation_id'=>$p->compensation_id]),
                'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('logistics_rider_payouts')->where('id',$id)->update(['status'=>'paid','reference'=>$reference,'paid_by'=>$actor->id,'paid_at'=>now(),'notes'=>$d['notes'],'revision'=>$p->revision+1,'updated_at'=>now()]);
            $t->event($actor,'rider_payout_paid','rider_payout',$id,['amount_cents'=>$amount,'reference'=>$reference,'notes'=>$d['notes'],'destination_id'=>$p->destination_id]);
        },3);
    }

    public function acknowledge(User $actor,string $id): void
    {
        DB::transaction(function()use($actor,$id){
            $actor=$this->rider($actor);$p=DB::table('logistics_rider_payouts')->where('id',$id)->where('rider_id',$actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($p->status==='paid',422,'Only actual recorded payments can be acknowledged.');if($p->acknowledged_at)return;
            DB::table('logistics_rider_payouts')->where('id',$id)->update(['acknowledged_at'=>now(),'updated_at'=>now()]);
            app(LogisticsFinance::class)->event($p->company_name,'rider_payout_acknowledged','rider_payout',$id,['rider_id'=>$actor->id],$actor->id);
        },3);
    }
}
