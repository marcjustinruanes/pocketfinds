<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Schema,Validator};
use Illuminate\Support\Str;

class CommissionRecovery
{
    public function installed(): bool { return Schema::hasTable('commission_recoveries'); }
    private function ledger(): CommissionLedger { return app(CommissionLedger::class); }
    private function actor(User $user,bool $admin=false): User
    {
        abort_unless($this->installed(),503,'Commission recovery installation is required.');
        $u=app(CommissionPayments::class)->actor($user);
        abort_unless($admin ? $u->is_admin : (!$u->is_admin && $u->account_type==='seller'),403);return $u;
    }
    private function proof(UploadedFile $file): array
    {
        Validator::make(['proof'=>$file],['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
        return ['proof_path'=>app(MediaLifecycle::class)->store($file,'commission-proofs','local'),'proof_mime'=>$file->getMimeType(),'proof_hash'=>hash_file('sha256',$file->getRealPath())];
    }
    private function event(string $id,User $actor,string $kind,array $details): void
    {
        DB::table('commission_recovery_events')->insert(['recovery_id'=>$id,'actor_id'=>$actor->id,'kind'=>$kind,'details'=>json_encode($details,JSON_THROW_ON_ERROR),'created_at'=>now()]);
    }
    public function submit(User $user,array $input,UploadedFile $file): string
    {
        $u=$this->actor($user);
        $d=Validator::make($input,['submission_key'=>'required|uuid','receiving_account_id'=>'required|uuid','amount'=>'required|string','reference'=>['required','regex:/^[A-Za-z0-9-]{6,64}$/D'],'reason'=>'required|string|min:3|max:2000','sent'=>'required|accepted'])->validate();
        $amount=$this->ledger()->cents($d['amount']);if($amount<=0)$this->ledger()->fail('Record the positive amount actually sent.');
        $ref=strtoupper(trim($d['reference']));$hash=hash('sha256',json_encode([$u->id,$d['receiving_account_id'],$amount,$ref,$d['reason'],hash_file('sha256',$file->getRealPath())]));
        return DB::transaction(function()use($u,$d,$amount,$ref,$hash,$file){
            DB::table('users')->where('id',$u->id)->lockForUpdate()->first();
            $old=DB::table('commission_recoveries')->where('submission_key',$d['submission_key'])->lockForUpdate()->first();
            if($old){abort_unless($old->request_hash===$hash && (int)$old->seller_id===$u->id,409);return $old->id;}
            abort_unless(DB::table('commission_accounts')->where('id',$d['receiving_account_id'])->exists(),422);
            $id=(string)Str::uuid();DB::table('commission_recoveries')->insert($this->proof($file)+['id'=>$id,'submission_key'=>$d['submission_key'],'request_hash'=>$hash,'seller_id'=>$u->id,'receiving_account_id'=>$d['receiving_account_id'],'amount_cents'=>$amount,'reference'=>$ref,'reason'=>$d['reason'],'created_at'=>now(),'updated_at'=>now()]);
            $this->event($id,$u,'submitted',['reference'=>$ref,'amount_cents'=>$amount]);
            app(CommissionConversations::class)->event($u,'recovery',$id,'submitted',$d['reason'],'recovery:'.$id.':submitted');return $id;
        },3);
    }
    public function review(User $user,string $id,array $input): void
    {
        $u=$this->actor($user,true);
        $d=Validator::make($input,['revision'=>'required|integer|min:1','decision'=>'required|in:verify,reject,review,correction','reason'=>'required|string|min:3|max:2000','amount'=>'required_if:decision,verify|nullable|string','reference'=>'required_if:decision,verify|nullable|string','received'=>'exclude_unless:decision,verify|required|accepted','ownership_checked'=>'exclude_unless:decision,verify|required|accepted'])->validate();
        DB::transaction(function()use($u,$id,$d){
            $r=DB::table('commission_recoveries')->where('id',$id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($r->status,['pending','review','rejected']) && (int)$r->revision===(int)$d['revision'],409,'Recovery receipt changed or was already verified.');
            $values=['revision'=>$r->revision+1,'reviewed_by'=>$u->id,'review_reason'=>$d['reason'],'updated_at'=>now()];
            $conversation=app(CommissionConversations::class);if($conversation->installed())$values['review_decision']=$d['decision'];
            if($d['decision']!=='verify'){
                DB::table('commission_recoveries')->where('id',$id)->update($values+['status'=>$d['decision']==='reject'?'rejected':'review']);$this->event($id,$u,'reviewed',$d);
                $conversation->event($u,'recovery',$id,match($d['decision']){'reject'=>'rejected','correction'=>'corrections_requested',default=>'review_requested'},$d['reason'],'recovery:'.$id.':review:'.$r->revision);return;
            }
            if($this->ledger()->cents($d['amount'])!==(int)$r->amount_cents || strtoupper(trim($d['reference']))!==$r->reference)$this->ledger()->fail('Verify the exact receipt amount and reference.');
            // Lock the prior payment before its reference, matching ordinary receipt verification.
            $peek=DB::table('logistics_payment_references')->where('reference',$r->reference)->first();$prior=null;
            if($peek && $peek->subject_type==='commission_payment')$prior=DB::table('commission_payments')->where('id',$peek->subject_id)->lockForUpdate()->first();
            $ref=DB::table('logistics_payment_references')->where('reference',$r->reference)->lockForUpdate()->first();
            if($ref){
                if($ref->subject_type==='commission_payment' && $prior && $ref->subject_id===$prior->id){
                    if($prior->method!=='gcash' || $prior->status!=='rejected' || $prior->verified_at || (int)$prior->unapplied_cents!==0 || DB::table('commission_allocations')->where('payment_id',$prior->id)->lockForUpdate()->get(['allocated_cents'])->sum('allocated_cents')>0)$this->ledger()->fail('The prior GCash claim must be rejected and entirely uncollected before reference recovery.');
                    $values['previous_payment_id']=$prior->id;
                }elseif($ref->subject_type!=='commission_recovery' || $ref->subject_id!==$id)$this->ledger()->fail('This reference belongs to another financial transaction and cannot be recovered here.');
            }else app(LogisticsShipping::class)->reserveReference($r->reference,'commission_recovery',$id);
            app(CommissionReceipts::class)->record($r->reference,'recovery',$id,(int)$r->amount_cents,$u->id,$r->proof_hash);
            DB::table('commission_recoveries')->where('id',$id)->update($values+['status'=>'verified','verified_at'=>now(),'unapplied_cents'=>$r->amount_cents]);
            $this->event($id,$u,'verified',['reference'=>$r->reference,'amount_cents'=>$r->amount_cents,'previous_payment_id'=>$prior?->id,'ownership_reason'=>$d['reason'],'receiving_account_id'=>$r->receiving_account_id]);
            $conversation->event($u,'recovery',$id,'verified',$d['reason'],'recovery:'.$id.':review:'.$r->revision);
        },3);
    }
    public function allocate(User $user,string $id,array $input): void
    {
        $u=$this->actor($user,true);$d=Validator::make($input,['obligation_id'=>'required|uuid','amount'=>'required|string'])->validate();$amount=$this->ledger()->cents($d['amount']);
        DB::transaction(function()use($u,$id,$d,$amount){
            $peek=DB::table('commission_obligations')->find($d['obligation_id']);abort_unless($peek,404);
            if($peek->order_id)DB::table('orders')->where('id',$peek->order_id)->lockForUpdate()->first();
            $o=DB::table('commission_obligations')->where('id',$peek->id)->lockForUpdate()->first();
            $r=DB::table('commission_recoveries')->where('id',$id)->lockForUpdate()->firstOrFail();
            abort_unless($r->status==='verified' && $o->method==='gcash' && (int)$r->seller_id===(int)$o->seller_id,422);
            if($amount<=0 || $amount>(int)$r->unapplied_cents || $amount>$this->ledger()->due($o))$this->ledger()->fail('Allocation exceeds verified Seller credit or commission due.');
            DB::table('commission_recovery_allocations')->insert(['recovery_id'=>$id,'obligation_id'=>$o->id,'amount_cents'=>$amount,'created_at'=>now()]);
            DB::table('commission_recoveries')->where('id',$id)->decrement('unapplied_cents',$amount);
            $this->ledger()->event($o->id,'recovery-allocation:'.Str::uuid(),'credit_allocated',$amount,['recovery_id'=>$id],$u->id);$this->event($id,$u,'allocated',['obligation_id'=>$o->id,'amount_cents'=>$amount]);
        },3);
    }
    public function refund(User $user,string $id,array $input,UploadedFile $file): void
    {
        $u=$this->actor($user,true);$d=Validator::make($input,['amount'=>'required|string','reference'=>['required','regex:/^[A-Za-z0-9-]{6,64}$/D'],'destination_id'=>'required|uuid','sent'=>'required|accepted','reason'=>'required|string|min:3|max:2000'])->validate();$amount=$this->ledger()->cents($d['amount']);
        DB::transaction(function()use($u,$id,$d,$amount,$file){
            $r=DB::table('commission_recoveries')->where('id',$id)->lockForUpdate()->firstOrFail();
            if($r->status!=='verified' || $amount<=0 || $amount>(int)$r->unapplied_cents)$this->ledger()->fail('Repayment exceeds verified unapplied recovery credit.');
            $destination=DB::table('logistics_payout_destinations')->where('id',$d['destination_id'])->where('seller_id',$r->seller_id)->firstOrFail();app(LogisticsTreasury::class)->requireGcash($destination);
            $refund=(string)Str::uuid();$ref=strtoupper(trim($d['reference']));app(LogisticsShipping::class)->reserveReference($ref,'comm_recovery_refund',$refund);$proof=$this->proof($file);
            DB::table('commission_recovery_refunds')->insert(['id'=>$refund,'recovery_id'=>$id,'amount_cents'=>$amount,'reference'=>$ref,'destination'=>json_encode(['id'=>$destination->id,'name'=>$destination->account_name,'number'=>$destination->account_number]),'proof_path'=>$proof['proof_path'],'proof_mime'=>$proof['proof_mime'],'reason'=>$d['reason'],'paid_by'=>$u->id,'paid_at'=>now()]);
            DB::table('commission_recoveries')->where('id',$id)->decrement('unapplied_cents',$amount);$this->event($id,$u,'repaid',['refund_id'=>$refund,'amount_cents'=>$amount,'reference'=>$ref]);
        },3);
    }
}
