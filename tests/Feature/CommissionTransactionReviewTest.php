<?php

namespace Tests\Feature;

use App\Services\{CommissionLedger, CommissionReporting, LogisticsReconciliation};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Transaction review only: inherited setup requires in-memory SQLite and fakes evidence storage. */
class CommissionTransactionReviewTest extends CommissionStagingEndToEndTest
{
    private function fixture(string $name, ...$args): mixed
    {
        return (new \ReflectionMethod(CommissionTest::class, $name))->invoke($this, ...$args);
    }

    private function recordedRefund(object $platform, object $order, int $line, int $amount, string $reference): void
    {
        $id=(string)Str::uuid();
        DB::table('commerce_returns')->insert(['id'=>$id,'order_id'=>$order->id,'buyer_id'=>$this->buyer->id,
            'seller_id'=>$this->seller->id,'item_keys'=>json_encode([$line]),'reason'=>'Damaged item',
            'description'=>'Synthetic recorded product return','photos'=>'[]','amount'=>$amount,
            'refund_destination'=>'GCash','status'=>'received']);
        $this->actingAs($platform)->post('/commerce/returns/'.$id,['action'=>'refunded','reference'=>$reference])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_transaction_gcash_rejected_claim_correction_partial_payment_overpayment_and_refund(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);
        $platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);$ledger=app(CommissionLedger::class);
        $this->actingAs($this->seller)->post('/commissions/payments',$this->fixture('submitData',$account,$o)+[
            'proof'=>$this->proof('SIMULATED incorrect claimed amount; no real transfer'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $payment=DB::table('commission_payments')->first();
        $this->actingAs($platform)->post('/commissions/payments/'.$payment->id.'/review',[
            'revision'=>1,'decision'=>'reject','reason'=>'Synthetic receipt amount and evidence need correction',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $original=(array)DB::table('commission_payments')->find($payment->id);
        $this->assertSame(0,$ledger->collected($o->id));
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('Correct this payment claim')->assertSee('Do not send the same payment again');
        $this->actingAs($this->seller)->post('/commissions/recoveries',[
            'submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,
            'reference'=>'COMM001','amount'=>'40','sent'=>1,'reason'=>'Correct synthetic receipt amount and replacement proof',
            'proof'=>$this->proof('SIMULATED corrected receipt for forty pesos'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $recovery=DB::table('commission_recoveries')->first();
        $this->assertSame(0,$ledger->collected($o->id));
        $this->actingAs($platform)->post('/commissions/recoveries/'.$recovery->id.'/review',[
            'revision'=>1,'decision'=>'verify','amount'=>'40','reference'=>'COMM001','received'=>1,'ownership_checked'=>1,
            'reason'=>'Simulated destination, amount and rightful Seller verified',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/commissions/recoveries/'.$recovery->id.'/allocate',['obligation_id'=>$o->id,'amount'=>'40'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4000,$ledger->collected($o->id));
        $this->assertSame(6000,$ledger->due($this->fixture('obligation',$order)));
        $data=$this->fixture('submitData',$account,$o,'80','REMAINING001');$data['allocations'][$o->id]='60';
        $this->actingAs($this->seller)->post('/commissions/payments',$data+['proof'=>$this->proof('SIMULATED eighty pesos sent')])
            ->assertRedirect()->assertSessionHasNoErrors();
        $second=DB::table('commission_payments')->where('reference','REMAINING001')->first();
        $this->assertSame(4000,$ledger->collected($o->id));
        $this->actingAs($platform)->post('/commissions/payments/'.$second->id.'/review',[
            'revision'=>1,'decision'=>'verify','amount'=>'80','reference'=>'REMAINING001','received'=>1,'reason'=>'Simulated exact receipt verified',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10000,$ledger->collected($o->id));
        $this->assertSame(0,$ledger->due($this->fixture('obligation',$order)));
        $this->assertSame(2000,(int)DB::table('commission_payments')->find($second->id)->unapplied_cents);
        $this->assertSame($original,(array)DB::table('commission_payments')->find($payment->id));
        $this->recordedRefund($platform,$order,0,600,'GCASHRETURN001');
        $this->assertSame(6000,(int)DB::table('commission_credits')->where('kind','reversal')->sum('amount_cents'));
        $report=app(CommissionReporting::class)->totals(app(CommissionReporting::class)->query($this->seller));
        $this->assertSame(4000,$report['earned_net']);$this->assertSame(0,$report['seller_due']);
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('REMAINING001')->assertSee('Rejected')->assertSee('Verified');
        $this->actingAs($platform)->get('/commissions')->assertOk()->assertSee('Record credit repayment')->assertSee('Repay unapplied Seller credit');
    }

    public function test_transaction_cod_partial_remittances_refund_in_transit_and_platform_reconciliation(): void
    {
        // Whole-line returns must use original lines matching the actual refunded merchandise.
        DB::table('orders')->where('id',$this->order->id)->update(['items'=>json_encode([
            ['name'=>'Returned item','qty'=>1,'price'=>600,'weight_grams'=>500],
            ['name'=>'Retained item','qty'=>1,'price'=>400,'weight_grams'=>500],
        ])]);
        $this->order->refresh();
        $this->fixture('paidCod');$o=$this->fixture('obligation');$ledger=app(CommissionLedger::class);
        $platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);
        foreach([['40','PARTCOD001'],['60','PARTCOD002']] as $index=>[$amount,$reference]){
            $this->actingAs($this->admin)->post('/commissions/remittances',[
                'submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'allocations'=>[$o->id=>$amount],
            ])->assertRedirect()->assertSessionHasNoErrors();
            $payment=DB::table('commission_payments')->where('status','draft')->first();
            $this->post('/commissions/remittances/'.$payment->id,[
                'revision'=>1,'action'=>'send','sent'=>1,'amount'=>$amount,'reference'=>$reference,
                'reason'=>'Synthetic partial remittance','proof'=>$this->proof('SIMULATED partial remittance '.$index),
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($index===0?0:4000,$ledger->collected($o->id));
            if($index===1){
                $this->recordedRefund($platform,$this->order,0,600,'CODRETURN001');
                $this->assertDatabaseHas('commission_credits',['obligation_id'=>$o->id,'payer'=>'logistics','amount_cents'=>6000]);
            }
            $this->actingAs($platform)->post('/commissions/payments/'.$payment->id.'/review',[
                'revision'=>2,'decision'=>'verify','amount'=>$amount,'reference'=>$reference,'received'=>1,
                'reason'=>'Simulated exact partial receipt verified',
            ])->assertRedirect()->assertSessionHasNoErrors();
            if($index===0){
                $this->assertSame(6000,$ledger->due($this->fixture('obligation')));
                $this->assertSame(6000,$this->treasury->balance($this->account->id,'commission'));
            }
        }
        $this->assertSame(10000,$ledger->collected($o->id));
        $this->assertSame(0,$ledger->due($this->fixture('obligation')));
        $this->assertSame(0,$this->treasury->balance($this->account->id,'comm_transit'));
        $this->assertSame(0,$this->treasury->balance($this->account->id,'commission'));
        $this->assertSame(10000,$this->treasury->balance($this->account->id,'cod'));
        $this->assertSame(6000,(int)DB::table('commission_credits')->where('payer','platform')->sum('amount_cents'));
        $this->assertSame(0,(int)DB::table('commission_credits')->where('payer','logistics')->sum('amount_cents'));
        $totals=app(CommissionReporting::class)->totals(app(CommissionReporting::class)->query($this->admin));
        $this->assertSame(4000,$totals['earned_net']);$this->assertSame(0,$totals['logistics_due']);
        $reconciliation=app(LogisticsReconciliation::class)->snapshot($this->admin,now()->subDays(30)->toDateString(),now()->toDateString());
        $this->assertSame(0,$reconciliation['conservation']['residual_cents']);
        $this->actingAs($this->admin)->get('/commissions')->assertOk()->assertSee('PARTCOD001')->assertSee('PARTCOD002');
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('100.00')->assertSee('60.00');
        $this->actingAs($platform)->get('/commissions')->assertOk()->assertSee('Record credit repayment');
    }
}
