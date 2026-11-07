<?php
namespace Tests\Feature;

use App\Services\{CommissionLedger,CommissionPayments,CommissionRecovery,MediaLifecycle};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;

class CommissionProductionFixTest extends CommissionWorkflowFixTest
{
    public function test_production_fix_rider_can_change_validated_availability(): void
    {
        $this->actingAs($this->rider)->patch('/rider/availability',['is_available'=>true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue((bool)$this->rider->fresh()->is_available);
        $this->patch('/rider/availability',['is_available'=>false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse((bool)$this->rider->fresh()->is_available);
    }
    public function test_production_fix_accepting_existing_offer_snapshots_compensation_once(): void
    {
        $a=\App\Models\DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'pickup','status'=>'offered','requested_at'=>now()]);
        $this->assertSame(0,DB::table('logistics_compensations')->where('source_id',$a->id)->count());
        $a->update(['status'=>'accepted','accepted_at'=>now()]);$a->save();
        $this->assertSame(1,DB::table('logistics_compensations')->where('source_id',$a->id)->count());
        $this->assertDatabaseHas('logistics_compensations',['source_id'=>$a->id,'amount_cents'=>2000,'status'=>'pending']);
    }
    public function test_production_fix_carrier_quote_mismatch_is_rejected_before_payment(): void
    {
        $shipping=app(\App\Services\LogisticsShipping::class);$address=$this->order->shipping_address;
        $quote=['items'=>$this->order->items,'shipping_amount'=>'999','shipping_pending'=>false];
        $this->assertNotNull($shipping->preflight($this->seller,$address,$quote,true));
        $this->invalid(fn()=>$shipping->preflight($this->seller,$address,$quote,false));
        $quote['shipping_amount']='100';$this->assertNull($shipping->preflight($this->seller,$address,$quote,false));
    }
    public static function storageTimezones(): array { return ['UTC'=>['UTC'],'Philippines'=>['Asia/Manila']]; }
    #[\PHPUnit\Framework\Attributes\DataProvider('storageTimezones')]
    public function test_production_fix_activation_boundary_respects_explicit_timezone_without_backfill(string $storageTimezone): void
    {
        $oldTimezone=config('app.timezone');$oldPhpTimezone=date_default_timezone_get();
        try{
            config(['app.timezone'=>$storageTimezone,'commission.enabled'=>true,'commission.activation_at'=>'2026-11-08T00:00:00+08:00']);date_default_timezone_set($storageTimezone);
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-07T16:00:01Z'));
            $this->order->forceFill(['created_at'=>\Carbon\Carbon::parse('2026-11-07T15:59:59Z')->setTimezone($storageTimezone)]);app(CommissionLedger::class)->captureOrder($this->order);
            $this->assertDatabaseCount('commission_obligations',0);
            $this->order->forceFill(['created_at'=>\Carbon\Carbon::parse('2026-11-07T16:00:00Z')->setTimezone($storageTimezone)]);app(CommissionLedger::class)->captureOrder($this->order);
            $this->assertDatabaseCount('commission_obligations',1);$this->assertSame(10000,(int)DB::table('commission_obligations')->value('commission_cents'));
            config(['commission.enabled'=>false]);$this->assertNull(app(CommissionLedger::class)->activation());
        }finally{\Carbon\Carbon::setTestNow();config(['app.timezone'=>$oldTimezone]);date_default_timezone_set($oldPhpTimezone);}
    }
    private function fixture(string $name,...$args): mixed{return (new \ReflectionMethod(CommissionTest::class,$name))->invoke($this,...$args);}
    public function test_production_fix_rejected_reference_removes_only_the_rolled_back_upload(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);
        $this->actingAs($this->seller)->post('/commissions/payments',$this->fixture('submitData',$account,$o)+['proof'=>$this->proof('accepted private receipt')])->assertRedirect()->assertSessionHasNoErrors();
        $before=Storage::disk('local')->allFiles('commission-proofs');
        $this->post('/commissions/payments',$this->fixture('submitData',$account,$o)+['proof'=>$this->proof('duplicate reference upload')])->assertRedirect()->assertSessionHasErrors();
        $this->assertSame($before,Storage::disk('local')->allFiles('commission-proofs'));$this->assertDatabaseCount('commission_payments',1);
        DB::beginTransaction();$path=app(MediaLifecycle::class)->store($this->proof('outer transaction receipt'),'commission-proofs','local');DB::rollBack();
        Storage::disk('local')->assertMissing($path);Storage::disk('local')->assertExists($before[0]);
    }
    public function test_production_fix_referenced_commission_proofs_survive_cleanup(): void
    {
        $order=$this->fixture('gcash');$o=$this->fixture('obligation',$order);$platform=$this->fixture('platform');$account=$this->fixture('receiving',$platform);$payments=app(CommissionPayments::class);
        $id=$payments->submit($this->seller,$this->fixture('submitData',$account,$o),$this->proof('retained actual payment'));$this->fixture('verifyReceipt',$platform,$id);
        DB::transaction(fn()=>app(CommissionLedger::class)->reverse($order->id,'production:partial',[0],'Actual product refund'));
        $credit=DB::table('commission_credits')->first();$payments->repay($platform,$credit->id,['amount'=>'60','reference'=>'PRODCREDIT001','destination_id'=>$this->destination->id,'sent'=>1,'reason'=>'Actual credit repayment'],$this->proof('retained credit repayment'));
        $recovery=app(CommissionRecovery::class);$r=$recovery->submit($this->seller,['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'amount'=>'20','reference'=>'PRODRECOVERY001','sent'=>1,'reason'=>'Actual excess sent'],$this->proof('retained recovery receipt'));
        $recovery->review($platform,$r,['revision'=>1,'decision'=>'verify','amount'=>'20','reference'=>'PRODRECOVERY001','received'=>1,'ownership_checked'=>1,'reason'=>'Actual received amount and owner checked']);
        $recovery->refund($platform,$r,['amount'=>'20','reference'=>'PRODRECREF001','destination_id'=>$this->destination->id,'sent'=>1,'reason'=>'Actual excess repayment'],$this->proof('retained recovery repayment'));
        foreach(['commission_payments','commission_credits','commission_recoveries','commission_recovery_refunds'] as $table){
            $path=DB::table($table)->whereNotNull('proof_path')->value('proof_path');$this->assertNotNull($path);
            DB::transaction(fn()=>app(MediaLifecycle::class)->deleteAfterCommit($path,'local'));Storage::disk('local')->assertExists($path);
        }
    }
}
