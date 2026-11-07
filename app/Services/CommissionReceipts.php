<?php

namespace App\Services;

use Illuminate\Support\Facades\{DB,Schema};

/** One verified bank transfer, even when several unverified claims exist. */
class CommissionReceipts
{
    public function installed(): bool { return Schema::hasTable('commission_verified_receipts'); }

    public function record(string $reference,string $type,string $id,int $amount,int $actor,?string $proofHash=null): void
    {
        if(!$this->installed())return;
        $old=DB::table('commission_verified_receipts')->where('reference',$reference)->lockForUpdate()->first();
        if($old){
            if($old->source_type!==$type || $old->source_id!==$id || (int)$old->amount_cents!==$amount)app(CommissionLedger::class)->fail('This actual transfer was already verified against another receipt.');
            return;
        }
        if($proofHash && DB::table('commission_payments')->where('status','verified')->where('proof_hash',$proofHash)->when($type==='payment',fn($q)=>$q->where('id','!=',$id))->lockForUpdate()->first())app(CommissionLedger::class)->fail('This evidence already supports a verified receipt.');
        DB::table('commission_verified_receipts')->insert(['reference'=>$reference,'source_type'=>$type,'source_id'=>$id,'amount_cents'=>$amount,'verified_by'=>$actor,'verified_at'=>now(),'proof_hash'=>$proofHash]);
    }
}
