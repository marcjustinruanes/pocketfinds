<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage, Validator};
use Illuminate\Support\Str;

class CommissionPayments
{
    public function __construct(private CommissionLedger $ledger) {}

    public function actor(User $actor): User
    {
        $actor = $actor->fresh();
        abort_unless($actor && $actor->status === 'approved', 403);
        abort_unless($actor->is_admin || ($actor->account_type === 'seller' && !$actor->is_logistics) || ($actor->account_type === 'logistics' && $actor->isLogisticsAdmin() && $actor->is_logistics && filled($actor->business_name)), 403);
        return $actor;
    }

    public function admin(User $actor): User
    {
        $actor = $this->actor($actor); abort_unless($actor->is_admin, 403); return $actor;
    }

    public function scoped($query, User $actor)
    {
        if ($actor->is_admin) return $query;
        return $actor->account_type === 'seller' ? $query->where('seller_id', $actor->id) : $query->where('company_name', $actor->business_name)->where('method', 'cod');
    }

    private function lockOrders(array $obligationIds): void
    {
        $ids=DB::table('commission_obligations')->whereIn('id',$obligationIds)->whereNotNull('order_id')->pluck('order_id');
        DB::table('orders')->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    private function currentBalance(string $account, string $bucket): int
    {
        return (int)DB::table('logistics_treasury_movements')->where('account_id',$account)->where('bucket',$bucket)->lockForUpdate()->get(['delta_cents'])->sum('delta_cents');
    }

    public function account(User $actor, array $input): void
    {
        app(CommissionAccounts::class)->manage($actor, $input);
    }

    public function prepareRemittance(User $actor, array $input): string
    {
        $actor=$this->actor($actor);abort_unless(!$actor->is_admin && $actor->account_type==='logistics',403);
        $d=Validator::make($input,['submission_key'=>'required|uuid','receiving_account_id'=>'required|uuid','allocations'=>'required|array|min:1|max:100','allocations.*'=>'required|string'])->validate();
        $alloc=[];foreach($d['allocations'] as $id=>$v){if(!Str::isUuid($id))$this->ledger->fail('Invalid obligation.');$c=$this->ledger->cents($v);if($c>0)$alloc[$id]=$c;}ksort($alloc);
        if(!$alloc)$this->ledger->fail('Choose positive withheld commissions to reserve before sending.');
        $hash=hash('sha256',json_encode([$actor->id,$d['receiving_account_id'],$alloc]));
        return DB::transaction(function()use($actor,$d,$alloc,$hash){
            DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            $old=DB::table('commission_payments')->where('submission_key',$d['submission_key'])->first();
            if($old){abort_unless($old->request_hash===$hash && $old->submitted_by===$actor->id,409);return $old->id;}
            app(CommissionAccounts::class)->requireActive($d['receiving_account_id']);
            $this->lockOrders(array_keys($alloc));
            $treasury=null;
            foreach($alloc as $id=>$c){
                $o=$this->scoped(DB::table('commission_obligations'),$actor)->where('id',$id)->lockForUpdate()->firstOrFail();
                $reserved=(int)DB::table('commission_allocations as a')->join('commission_payments as p','p.id','=','a.payment_id')->where('a.obligation_id',$id)->whereIn('p.status',['draft','pending','review'])->lockForUpdate()->get(['a.requested_cents'])->sum('requested_cents');
                if($c>$this->ledger->due($o)-$reserved)$this->ledger->fail('Commission is already reserved, held or no longer due.');
                if($treasury!==null && $treasury!==$o->treasury_account_id)$this->ledger->fail('Choose one source treasury account per remittance.');
                $treasury=$o->treasury_account_id;
            }
            $t=app(LogisticsTreasury::class);$account=$t->account($treasury,$actor->business_name);
            $otherDrafts=(int)DB::table('commission_payments')->where('treasury_account_id',$treasury)->where('status','draft')->lockForUpdate()->get(['amount_cents'])->sum('amount_cents');
            $amount=array_sum($alloc);if($this->currentBalance($account->id,'commission')-$otherDrafts<$amount)$this->ledger->fail('Insufficient unreserved commission funds.');
            $id=(string)Str::uuid();DB::table('commission_payments')->insert(['id'=>$id,'submission_key'=>$d['submission_key'],'request_hash'=>$hash,'receiving_account_id'=>$d['receiving_account_id'],'submitted_by'=>$actor->id,'company_name'=>$actor->business_name,'treasury_account_id'=>$treasury,'method'=>'cod','amount_cents'=>$amount,'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
            foreach($alloc as $oid=>$c){DB::table('commission_allocations')->insert(['payment_id'=>$id,'obligation_id'=>$oid,'requested_cents'=>$c]);$this->ledger->event($oid,'remit-prepared:'.$id.':'.$oid,'remittance_prepared',0,['payment_id'=>$id,'reserved_cents'=>$c],$actor->id);}
            return $id;
        },3);
    }

    public function sendRemittance(User $actor, string $id, array $input, ?UploadedFile $file): void
    {
        $actor=$this->actor($actor);abort_unless(!$actor->is_admin && $actor->account_type==='logistics',403);
        $note=app(CommissionConversations::class)->optionalMessage($input);
        $d=Validator::make($input,['revision'=>'required|integer|min:1','action'=>'required|in:send,cancel','amount'=>'required_if:action,send|nullable|string','reference'=>'required_if:action,send|nullable|regex:/^[A-Za-z0-9-]{6,64}$/D','sent'=>'exclude_unless:action,send|required|accepted','not_sent'=>'exclude_unless:action,cancel|required|accepted','reason'=>'required|string|min:3|max:2000'])->validate();
        DB::transaction(function()use($actor,$id,$d,$file,$note){
            $ids=DB::table('commission_allocations')->where('payment_id',$id)->orderBy('obligation_id')->pluck('obligation_id');
            $this->lockOrders($ids->all());
            $obs=DB::table('commission_obligations')->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
            $p=DB::table('commission_payments')->where('id',$id)->where('company_name',$actor->business_name)->where('method','cod')->lockForUpdate()->firstOrFail();
            abort_unless($p->status==='draft' && (int)$p->revision===(int)$d['revision'],409,'This remittance was already sent or changed.');
            if($d['action']==='cancel'){
                DB::table('commission_payments')->where('id',$id)->update(['status'=>'cancelled','revision'=>$p->revision+1,'review_reason'=>$d['reason'],'updated_at'=>now()]);
                foreach($obs as $o)$this->ledger->event($o->id,'remit-cancelled:'.$id.':'.$o->id,'remittance_cancelled',0,['reason'=>$d['reason']],$actor->id);
                return;
            }
            if(!$file)$this->ledger->fail('Attach proof of the actual external remittance.');
            if($this->ledger->cents($d['amount'])!==(int)$p->amount_cents)$this->ledger->fail('Confirm the exact reserved remittance amount.');
            $t=app(LogisticsTreasury::class);$account=$t->account($p->treasury_account_id,$actor->business_name);
            if($this->currentBalance($account->id,'commission')<(int)$p->amount_cents)$this->ledger->fail('Reserved commission funds require reconciliation.');
            $ref=$t->reference($account,$d['reference'],'commission_remit',$id);$proof=$this->proof($file);
            DB::table('commission_payments')->where('id',$id)->update($proof+['status'=>'pending','revision'=>$p->revision+1,'reference'=>$ref,'updated_at'=>now()]);
            $t->move($actor,$account,'commission',-(int)$p->amount_cents,'commission_remit',$id,$ref);
            $t->move($actor,$account,'comm_transit',(int)$p->amount_cents,'commission_remit',$id);
            foreach($obs as $o)$this->ledger->event($o->id,'remit-sent:'.$id.':'.$o->id,'remittance_sent',0,['reason'=>$d['reason'],'reference'=>$ref],$actor->id);
            app(CommissionConversations::class)->event($actor,'payment',$id,'submitted',$note??'Manual COD remittance submitted for actual receipt verification.','payment:'.$id.':submitted');
        },3);
    }

    private function proof(UploadedFile $file): array
    {
        Validator::make(['proof' => $file], ['proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
        return ['proof_path' => app(MediaLifecycle::class)->store($file, 'commission-proofs', 'local'), 'proof_mime' => $file->getMimeType(), 'proof_hash' => hash_file('sha256', $file->getRealPath())];
    }

    /** Record a Seller's external GCash transfer. Logistics uses reserved remittances. */
    public function submit(User $actor, array $input, UploadedFile $file): string
    {
        $actor=$this->actor($actor);abort_unless(!$actor->is_admin && $actor->account_type==='seller',403);
        $note=app(CommissionConversations::class)->optionalMessage($input);
        $d=Validator::make($input,['submission_key'=>'required|uuid','receiving_account_id'=>'required|uuid','amount'=>'required|string','reference'=>['required','regex:/^[A-Za-z0-9-]{6,64}$/D'],'sent'=>'required|accepted','allocations'=>'required|array|min:1|max:100','allocations.*'=>'required|string'])->validate();
        $amount=$this->ledger->cents($d['amount']);$claims=[];
        foreach($d['allocations'] as $id=>$value){if(!Str::isUuid($id))$this->ledger->fail('Invalid obligation.');$c=$this->ledger->cents($value);if($c>0)$claims[$id]=$c;}ksort($claims);
        if(!$claims || $amount<=0 || array_sum($claims)>$amount)$this->ledger->fail('Select positive allocations within the amount actually sent.');
        $reference=strtoupper(trim($d['reference']));$payload=[$actor->id,$d['receiving_account_id'],$amount,$reference,$claims,hash_file('sha256',$file->getRealPath())];if($note!==null)$payload[]=['message'=>$note];$hash=hash('sha256',json_encode($payload));
        return DB::transaction(function()use($actor,$d,$amount,$claims,$reference,$hash,$file,$note){
            DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            $old=DB::table('commission_payments')->where('submission_key',$d['submission_key'])->first();
            if($old){abort_unless(hash_equals($old->request_hash,$hash),409,'Retry details differ.');return $old->id;}
            app(CommissionAccounts::class)->requireActive($d['receiving_account_id']);
            $this->lockOrders(array_keys($claims));
            $allocations=[];
            foreach($claims as $id=>$c){
                $o=$this->scoped(DB::table('commission_obligations'),$actor)->where('id',$id)->where('method','gcash')->lockForUpdate()->firstOrFail();
                $reserved=(int)DB::table('commission_allocations as a')->join('commission_payments as p','p.id','=','a.payment_id')->where('a.obligation_id',$id)->whereIn('p.status',['pending','review'])->lockForUpdate()->get(['a.requested_cents'])->sum('requested_cents');
                // Preserve an actual transfer claim even when another receipt or refund changed the debt.
                $allocations[$id]=min($c,max(0,$this->ledger->due($o)-$reserved));
            }
            $id=(string)Str::uuid();$proof=$this->proof($file);
            app(LogisticsShipping::class)->reserveReference($reference,'commission_payment',$id);
            DB::table('commission_payments')->insert($proof+['id'=>$id,'submission_key'=>$d['submission_key'],'request_hash'=>$hash,'receiving_account_id'=>$d['receiving_account_id'],'submitted_by'=>$actor->id,'seller_id'=>$actor->id,'method'=>'gcash','amount_cents'=>$amount,'reference'=>$reference,'created_at'=>now(),'updated_at'=>now()]);
            foreach($allocations as $oid=>$c){
                DB::table('commission_allocations')->insert(['payment_id'=>$id,'obligation_id'=>$oid,'requested_cents'=>$c]);
                $this->ledger->event($oid,'submitted:'.$id.':'.$oid,'payment_submitted',0,['payment_id'=>$id,'requested_cents'=>$c,'original_claim_cents'=>$claims[$oid],'reference'=>$reference],$actor->id);
            }
            app(CommissionConversations::class)->event($actor,'payment',$id,'submitted',$note??'Manual Seller GCash commission payment submitted for actual receipt verification.','payment:'.$id.':submitted');
            return $id;
        },3);
    }
    public function review(User $actor, string $id, array $input): void
    {
        $actor = $this->admin($actor);
        $d = Validator::make($input, ['revision' => 'required|integer|min:1', 'decision' => 'required|in:verify,review,correction,reject', 'reason' => 'required|string|min:3|max:2000', 'amount' => 'required_if:decision,verify|nullable|string', 'reference' => 'required_if:decision,verify|nullable|string', 'received' => 'exclude_unless:decision,verify|required|accepted'])->validate();
        DB::transaction(function () use ($actor, $id, $d) {
            $peek = DB::table('commission_payments')->find($id); abort_unless($peek, 404);
            // Lock obligations before the payment, consistently with submit/reversal.
            $ids = DB::table('commission_allocations')->where('payment_id', $id)->orderBy('obligation_id')->pluck('obligation_id');
            $this->lockOrders($ids->all());
            $obligations = DB::table('commission_obligations')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $p = DB::table('commission_payments')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $p->revision === (int) $d['revision'] && in_array($p->status, ['pending', 'review', 'rejected']), 409, 'This receipt changed or was already verified.');
            $values = ['revision' => $p->revision + 1, 'review_reason' => $d['reason'], 'reviewed_by' => $actor->id, 'updated_at' => now()];
            $conversation=app(CommissionConversations::class);
            if($conversation->installed())$values['review_decision']=$d['decision'];
            $messageKind=match($d['decision']){'verify'=>'verified','reject'=>'rejected','correction'=>'corrections_requested',default=>'review_requested'};
            foreach($obligations as $o) $this->ledger->event($o->id,'review:'.$p->id.':'.$p->revision.':'.$o->id,'receipt_review',0,['decision'=>$d['decision'],'reason'=>$d['reason'],'payment_id'=>$p->id],$actor->id);
            if (in_array($d['decision'],['review','correction'])) { DB::table('commission_payments')->where('id', $id)->update($values + ['status' => 'review']);$conversation->event($actor,'payment',$id,$messageKind,$d['reason'],'payment:'.$id.':review:'.$p->revision); return; }
            if ($d['decision'] === 'reject') {
                // Rejecting proof cannot undo an actual COD transfer or release its reservations.
                DB::table('commission_payments')->where('id', $id)->update($values + ['status' => $p->method==='cod'?'review':'rejected']);
                $conversation->event($actor,'payment',$id,$messageKind,$d['reason'],'payment:'.$id.':review:'.$p->revision); return;
            }
            if ($this->ledger->cents($d['amount']) !== (int) $p->amount_cents || strtoupper(trim($d['reference'])) !== $p->reference) $this->ledger->fail('Confirm the exact actual platform receipt and reference.');
            app(CommissionReceipts::class)->record($p->reference,'payment',$p->id,(int)$p->amount_cents,$actor->id,$p->proof_hash);
            $allocated = 0; $transitDifferences = [];
            foreach (DB::table('commission_allocations')->where('payment_id', $id)->get() as $a) {
                $o = $obligations[$a->obligation_id];
                $currentDue = $this->ledger->due($o);
                $value = $p->method === 'cod' ? (int) $a->requested_cents : min((int) $a->requested_cents, $currentDue);
                $transitDifferences[$a->obligation_id] = max(0, (int) $a->requested_cents - $currentDue);
                DB::table('commission_allocations')->where('id', $a->id)->update(['allocated_cents' => $value]);
                $allocated += $value;
                $this->ledger->event($o->id, 'receipt:'.$id.':'.$o->id, 'collected', $value, ['payment_id' => $id, 'reference' => $p->reference],$actor->id);
            }
            DB::table('commission_payments')->where('id', $id)->update($values + ['status' => 'verified', 'verified_at' => now(), 'unapplied_cents' => (int) $p->amount_cents - $allocated]);
            if ($p->method === 'cod') {
                $t = app(LogisticsTreasury::class); $account = $t->account($p->treasury_account_id, $p->company_name);
                $t->move($actor, $account, 'comm_transit', -(int) $p->amount_cents, 'commission_received', $p->id);
                // A refund during transit is now funded by the platform, not Logistics.
                $excess = array_sum($transitDifferences);
                if ($excess) foreach (DB::table('commission_allocations')->where('payment_id', $id)->get() as $a) {
                    $difference = $transitDifferences[$a->obligation_id];
                    if (!$difference) continue;
                    $o = $obligations[$a->obligation_id];
                    $credits = DB::table('commission_credits')->where('obligation_id', $o->id)->where('payer', 'logistics')->where('kind', 'reversal')->where('status', 'pending')->orderBy('id')->lockForUpdate()->get();
                    foreach ($credits as $credit) {
                        $take = min($difference, (int) $credit->amount_cents);
                        if (!$take) break;
                        DB::table('commission_credits')->where('id', $credit->id)->update(['amount_cents' => (int) $credit->amount_cents - $take]);
                        DB::table('commission_credits')->insert(['id' => (string) Str::uuid(), 'obligation_id' => $o->id, 'source_key' => 'transit-credit:'.$p->id.':'.$credit->id, 'payer' => 'platform', 'amount_cents' => $take, 'reason' => 'Reversed commission received while remittance was in transit', 'created_at' => now(), 'updated_at' => now()]);
                        $difference -= $take;
                        $this->ledger->event($o->id, 'credit-transfer:'.$p->id.':'.$credit->id, 'credit_payer_changed', $take, ['from' => 'logistics', 'to' => 'platform', 'credit_id' => $credit->id],$actor->id);
                    }
                }
                // Excess is accounted as the seller credits above, not reusable company credit.
                if ($excess) DB::table('commission_payments')->where('id', $id)->update(['unapplied_cents' => 0]);
            }
            $conversation->event($actor,'payment',$id,'verified',$d['reason'],'payment:'.$id.':review:'.$p->revision);
        }, 3);
    }

    public function allocateCredit(User $actor, string $paymentId, string $obligationId, string $amount): void
    {
        $actor = $this->admin($actor); $cents = $this->ledger->cents($amount);
        DB::transaction(function () use ($actor, $paymentId, $obligationId, $cents) {
            $this->lockOrders([$obligationId]);
            $o = DB::table('commission_obligations')->where('id', $obligationId)->lockForUpdate()->firstOrFail();
            $p = DB::table('commission_payments')->where('id', $paymentId)->lockForUpdate()->firstOrFail();
            abort_unless($p->status === 'verified' && $p->method === 'gcash' && $o->method === 'gcash' && $p->seller_id === $o->seller_id, 422);
            if ($cents <= 0 || $cents > (int) $p->unapplied_cents || $cents > $this->ledger->due($o)) $this->ledger->fail('Credit allocation exceeds available credit or commission due.');
            $a = DB::table('commission_allocations')->where('payment_id', $p->id)->where('obligation_id', $o->id)->lockForUpdate()->first();
            if ($a) DB::table('commission_allocations')->where('id', $a->id)->increment('allocated_cents', $cents);
            else DB::table('commission_allocations')->insert(['payment_id' => $p->id, 'obligation_id' => $o->id, 'requested_cents' => 0, 'allocated_cents' => $cents]);
            DB::table('commission_payments')->where('id', $p->id)->decrement('unapplied_cents', $cents);
            $this->ledger->event($o->id, 'credit-allocation:'.Str::uuid(), 'credit_allocated', $cents, ['payment_id' => $p->id],$actor->id);
        }, 3);
    }

    public function repay(User $actor, string $id, array $input, UploadedFile $file): void
    {
        $actor = $this->actor($actor);
        $d = Validator::make($input, ['sent' => 'required|accepted', 'amount' => 'required|string', 'reference' => ['required', 'regex:/^[A-Za-z0-9-]{6,64}$/D'], 'reason' => 'required|string|min:3|max:2000', 'destination_id' => 'required|uuid'])->validate();
        DB::transaction(function () use ($actor, $id, $d, $file) {
            $peek = DB::table('commission_credits')->find($id); abort_unless($peek, 404);
            $this->lockOrders([$peek->obligation_id]);
            $o = DB::table('commission_obligations')->where('id', $peek->obligation_id)->lockForUpdate()->firstOrFail();
            $credit = DB::table('commission_credits')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($credit->payer === 'platform' ? $actor->is_admin : (!$actor->is_admin && $actor->account_type === 'logistics' && $actor->business_name === $o->company_name), 403);
            abort_unless($credit->status === 'pending', 409);
            if ($this->ledger->cents($d['amount']) !== (int) $credit->amount_cents || (int) $credit->amount_cents <= 0) $this->ledger->fail('Confirm the exact credit repayment amount.');
            $destination = DB::table('logistics_payout_destinations')->where('id', $d['destination_id'])->where('seller_id', $o->seller_id)->where('channel', 'gcash')->firstOrFail();
            app(LogisticsTreasury::class)->requireGcash($destination);
            $proof = $this->proof($file); $ref = strtoupper(trim($d['reference']));
            if ($credit->payer === 'logistics') {
                $inTransit = DB::table('commission_allocations as a')->join('commission_payments as p','p.id','=','a.payment_id')->where('a.obligation_id',$o->id)->whereIn('p.status',['draft','pending','review'])->lockForUpdate()->first(['p.id']);
                if ($inTransit) $this->ledger->fail('Resolve the in-transit remittance before repaying this credit.');
                $t = app(LogisticsTreasury::class); $account = $t->account($o->treasury_account_id, $o->company_name);
                if ($this->currentBalance($account->id, 'commission') < (int) $credit->amount_cents) $this->ledger->fail('Commission is in transit or unavailable; resolve the remittance before repayment.');
                $t->reference($account, $ref, 'commission_refund', $id);
                $t->move($actor, $account, 'commission', -(int) $credit->amount_cents, 'commission_refund', $id, $ref);
            } else app(LogisticsShipping::class)->reserveReference($ref, 'commission_refund', $id);
            DB::table('commission_credits')->where('id', $id)->update(['status' => 'paid', 'reference' => $ref, 'proof_path' => $proof['proof_path'], 'proof_mime' => $proof['proof_mime'], 'destination' => json_encode(['id' => $destination->id, 'name' => $destination->account_name, 'number' => $destination->account_number]), 'paid_by' => $actor->id, 'paid_at' => now(), 'updated_at' => now()]);
            $this->ledger->event($o->id, 'credit-paid:'.$id, 'credit_repaid', -(int) $credit->amount_cents, ['reference' => $ref, 'reason' => $d['reason'], 'payer' => $credit->payer],$actor->id);
        }, 3);
    }

    public function refundUnapplied(User $actor, string $paymentId, array $input, UploadedFile $file): void
    {
        $actor = $this->admin($actor);
        $d = Validator::make($input, ['sent'=>'required|accepted','amount'=>'required|string','reference'=>['required','regex:/^[A-Za-z0-9-]{6,64}$/D'],'reason'=>'required|string|min:3|max:2000','destination_id'=>'required|uuid'])->validate();
        DB::transaction(function () use ($actor,$paymentId,$d,$file) {
            $peek = DB::table('commission_allocations')->where('payment_id',$paymentId)->orderBy('obligation_id')->first(); abort_unless($peek,404);
            $this->lockOrders([$peek->obligation_id]);
            $o = DB::table('commission_obligations')->where('id',$peek->obligation_id)->lockForUpdate()->firstOrFail();
            $p = DB::table('commission_payments')->where('id',$paymentId)->lockForUpdate()->firstOrFail();
            $amount = $this->ledger->cents($d['amount']);
            if ($p->status!=='verified' || $p->method!=='gcash' || $amount<=0 || $amount>(int)$p->unapplied_cents) $this->ledger->fail('Repayment exceeds verified unapplied Seller credit.');
            $destination = DB::table('logistics_payout_destinations')->where('id',$d['destination_id'])->where('seller_id',$p->seller_id)->firstOrFail(); app(LogisticsTreasury::class)->requireGcash($destination);
            $id = (string)Str::uuid(); $ref = strtoupper(trim($d['reference'])); $proof = $this->proof($file);
            app(LogisticsShipping::class)->reserveReference($ref,'commission_refund',$id);
            DB::table('commission_credits')->insert(['id'=>$id,'obligation_id'=>$o->id,'source_key'=>'unapplied:'.$id,'kind'=>'unapplied','payer'=>'platform','amount_cents'=>$amount,'status'=>'paid','reference'=>$ref,'proof_path'=>$proof['proof_path'],'proof_mime'=>$proof['proof_mime'],'destination'=>json_encode(['id'=>$destination->id,'name'=>$destination->account_name,'number'=>$destination->account_number]),'reason'=>$d['reason'],'paid_by'=>$actor->id,'paid_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('commission_payments')->where('id',$p->id)->decrement('unapplied_cents',$amount);
            $this->ledger->event($o->id,'unapplied-refund:'.$id,'unapplied_repaid',-$amount,['payment_id'=>$p->id,'reference'=>$ref],$actor->id);
        },3);
    }
}
