<?php

namespace Tests\Feature;

use App\Models\{GcashPurchase, Order};
use App\Services\CommissionLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommissionAutomaticReconciliationTest extends CommissionTest
{
    private function provisional(bool $gcash = false): object
    {
        config(['commission.enabled'=>true,'commission.activation_at'=>now()->subMinute()->toIso8601String()]);
        if ($gcash) {
            $method=DB::table('payment_methods')->insertGetId(['name'=>'GCash','type'=>'ewallet','is_active'=>true]);
            $p=GcashPurchase::create(['id'=>(string)Str::uuid(),'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'checkout_id'=>(string)Str::uuid(),'request_hash'=>hash('sha256','automatic'), 'payment_method_id'=>$method,
                'snapshot'=>['items'=>[['qty'=>1,'price'=>1000]],'subtotal'=>'1000','shipping_amount'=>'100','discount_amount'=>'0','commerce'=>['vouchers'=>[]]],
                'amount_due'=>'1100','payment_state'=>'verified','reservation_state'=>'paid','verified_at'=>now(),'payment_reference'=>'SYNTHETIC-AUTO']);
            $order=Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'gcash_purchase_id'=>$p->id,'payment_method'=>'GCash','status'=>'placed','items'=>$p->snapshot['items'],'subtotal'=>1000,'shipping_amount'=>100,'discount_amount'=>0,'total'=>1100]);
            $order->logStatus('delivered',$this->rider->id);
            DB::table('orders')->where('id',$order->id)->update(['status'=>'delivered']);
            DB::table('order_status_history')->where('order_id',$order->id)->where('status','delivered')->update(['created_at'=>now()->subHours(168)->addSecond()]);
        } else {
            $order=$this->order;
            DB::transaction(fn()=>app(CommissionLedger::class)->captureOrder($order));
            DB::table('shipments')->where('order_id',$order->id)->update(['delivered_at'=>now()->subHours(168)->addSecond()]);
        }
        config(['commission.enabled'=>false,'commission.activation_at'=>null]);
        return DB::table('commission_obligations')->where('order_id',$order->id)->first();
    }

    public function test_automatic_cod_and_gcash_earn_at_exact_168_hours_while_enrollment_is_disabled(): void
    {
        $this->freezeTime();
        foreach ([false,true] as $gcash) {
            $ob=$this->provisional($gcash);
            $this->artisan('commissions:reconcile')->assertExitCode(0);
            $this->assertNull(DB::table('commission_obligations')->find($ob->id)->earned_at);
            $this->travel(1)->seconds();
            $this->artisan('commissions:reconcile')->expectsOutput('1 eligible commission obligations reconciled.')->assertExitCode(0);
            $this->artisan('commissions:reconcile')->expectsOutput('0 eligible commission obligations reconciled.')->assertExitCode(0);
            $earned=DB::table('commission_obligations')->find($ob->id);
            $this->assertNotNull($earned->earned_at);
            $this->assertSame(10000,(int)$earned->commission_cents);
            $this->assertSame(0,app(CommissionLedger::class)->collected($ob->id));
            $this->assertSame(1,DB::table('commission_events')->where('obligation_id',$ob->id)->where('kind','earned')->count());
        }
        $this->assertDatabaseCount('commission_payments',0);
        $this->assertDatabaseCount('commission_allocations',0);
    }

    public function test_automatic_confirmation_dispute_hold_and_resolution(): void
    {
        $ob=$this->provisional();
        DB::table('orders')->where('id',$ob->order_id)->update(['buyer_confirmed_at'=>now()]);
        $id=(string)Str::uuid();
        DB::table('complaints')->insert(['id'=>$id,'order_id'=>$ob->order_id,'complainant_id'=>$this->buyer->id,'description'=>'Synthetic dispute','status'=>'open']);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->assertNull(DB::table('commission_obligations')->find($ob->id)->earned_at);
        DB::table('complaints')->where('id',$id)->update(['status'=>'resolved']);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->assertNotNull(DB::table('commission_obligations')->find($ob->id)->earned_at);
    }

    public function test_automatic_bulk_cancellation_and_refund_are_reconciled_once(): void
    {
        $ob=$this->provisional();
        DB::table('orders')->where('id',$ob->order_id)->update(['status'=>'cancelled']);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $cancelled=DB::table('commission_obligations')->find($ob->id);
        $this->assertNull($cancelled->earned_at);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(10000,(int)$cancelled->reversed_cents);
        $this->assertSame(1,DB::table('commission_events')->where('kind','line_reversed')->count());

        $gcash=$this->provisional(true);
        DB::table('orders')->where('id',$gcash->order_id)->update(['buyer_confirmed_at'=>now()]);
        DB::table('commerce_returns')->insert(['id'=>(string)Str::uuid(),'order_id'=>$gcash->order_id,'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'item_keys'=>'[0]','reason'=>'Synthetic','description'=>'Synthetic refund','photos'=>'[]','status'=>'pending','amount'=>1000,'refund_destination'=>'original']);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->assertNull(DB::table('commission_obligations')->find($gcash->id)->earned_at);
        DB::table('commerce_returns')->where('order_id',$gcash->order_id)->update(['status'=>'refunded']);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $refunded=DB::table('commission_obligations')->find($gcash->id);
        $this->assertSame(10000,(int)$refunded->reversed_cents);
        $this->assertSame(0,app(CommissionLedger::class)->due($refunded));
        $this->assertSame(1,DB::table('commission_events')->where('obligation_id',$gcash->id)->where('kind','line_reversed')->count());
    }

    public function test_automatic_gcash_requires_verified_buyer_payment_and_reference(): void
    {
        $ob=$this->provisional(true);
        DB::table('orders')->where('id',$ob->order_id)->update(['buyer_confirmed_at'=>now()]);
        DB::table('gcash_purchases')->where('id',$ob->purchase_id)->update(['verified_at'=>null]);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->assertNull(DB::table('commission_obligations')->find($ob->id)->earned_at);
        DB::table('gcash_purchases')->where('id',$ob->purchase_id)->update(['verified_at'=>now(),'payment_reference'=>null]);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->assertNull(DB::table('commission_obligations')->find($ob->id)->earned_at);
        DB::table('gcash_purchases')->where('id',$ob->purchase_id)->update(['payment_reference'=>'SYNTHETIC-VERIFIED']);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->assertNotNull(DB::table('commission_obligations')->find($ob->id)->earned_at);
        $this->assertSame(0,app(CommissionLedger::class)->collected($ob->id));
    }

    public function test_automatic_rejected_return_waits_for_exact_72_hour_appeal_deadline(): void
    {
        $this->freezeTime();
        $ob=$this->provisional();
        DB::table('orders')->where('id',$ob->order_id)->update(['buyer_confirmed_at'=>now()]);
        DB::table('commerce_returns')->insert(['id'=>(string)Str::uuid(),'order_id'=>$ob->order_id,'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'item_keys'=>'[0]','reason'=>'Synthetic','description'=>'Rejected return','photos'=>'[]','status'=>'rejected','amount'=>1000,'refund_destination'=>'original','decided_at'=>now()->subHours(72)->addSecond()]);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->assertNull(DB::table('commission_obligations')->find($ob->id)->earned_at);
        $this->travel(1)->seconds();
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $earned=DB::table('commission_obligations')->find($ob->id);
        $this->assertNotNull($earned->earned_at);
        $this->assertSame(0,(int)$earned->reversed_cents);
    }
}
