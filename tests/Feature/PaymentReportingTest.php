<?php
namespace Tests\Feature;

use App\Models\{Order,User};
use App\Services\PaymentReporting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Support\Str;

class PaymentReportingTest extends LogisticsReconciliationTest
{
    private function snapshot(): array { return app(PaymentReporting::class)->snapshot($this->seller->id,now()->startOfDay(),now()->endOfDay()); }

    public function test_reporting_cod_collection_reservation_payment_and_shipping_are_not_added_twice(): void
    {
        $this->order->update(['status'=>'completed']);$this->fund();$this->flow->prepare($this->admin,$this->prepareData());
        $r=$this->snapshot();$m=$r['metrics'];
        $this->assertSame(100000,$m['item_sales']);$this->assertSame(110000,$m['buyer_order_value']);$this->assertSame(10000,$m['quoted_shipping']);
        $this->assertSame(110000,$m['cod_collected']);$this->assertSame(110000,$m['cod_verified']);$this->assertSame(100000,$m['pending_cod_payouts']);$this->assertSame(0,$m['seller_cod_paid']);
        $this->flow->action($this->admin,$this->payout()->id,['revision'=>$this->payout()->revision,'action'=>'hold','notes'=>'Documented payment review']);
        $this->assertSame(100000,$this->snapshot()['metrics']['held_cod_payouts']);
        $this->flow->action($this->admin,$this->payout()->id,['revision'=>$this->payout()->revision,'action'=>'release','notes'=>'Documented review completed']);
        $this->verify();$this->pay();$m=$this->snapshot()['metrics'];
        $this->assertSame(100000,$m['seller_cod_paid']);$this->assertSame(10000,$m['cod_shipping_retained']);$this->assertSame(0,$m['pending_cod_payouts']);$this->assertSame(0,$m['buyer_gcash_verified']);
        $this->actingAs($this->seller)->get('/seller/reports')->assertOk()->assertDontSee('Net Profit')->assertSee('Actual recorded COD payouts')->assertViewHas('paymentReport',fn($r)=>$r['metrics']['seller_cod_paid']===100000);
    }

    public function test_reporting_direct_gcash_and_platform_claims_are_separate_from_logistics_receipts(): void
    {
        $this->test_complete_buyer_gcash_to_delivery_preserves_direct_seller_payment_and_company_receipt();
        $m=$this->snapshot()['metrics'];
        $this->assertSame(100000,$m['item_sales']);$this->assertSame(108000,$m['buyer_order_value']);$this->assertSame(2000,$m['discounts']);
        $this->assertSame(108000,$m['buyer_gcash_verified']);$this->assertSame(10000,$m['shipping_gcash_paid']);$this->assertSame(2000,$m['unverified_platform_claims']);$this->assertSame(0,$m['seller_cod_paid']);
    }

    public function test_reporting_dates_and_seller_scope_do_not_hide_later_payments_or_include_foreign_orders(): void
    {
        $this->order->update(['status'=>'completed']);$this->fund();$this->flow->prepare($this->admin,$this->prepareData());$this->verify();$this->pay();
        DB::table('orders')->where('id',$this->order->id)->update(['updated_at'=>now()->subDays(40)]);
        $other=$this->person('seller');Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$other->id,'status'=>'completed','subtotal'=>9999,'shipping_amount'=>0,'discount_amount'=>0,'total'=>9999,'items'=>[]]);
        $r=$this->snapshot();$this->assertCount(0,$r['orders']);$this->assertSame(0,$r['metrics']['buyer_order_value']);$this->assertSame(100000,$r['metrics']['seller_cod_paid']);
        $old=app(PaymentReporting::class)->snapshot($this->seller->id,now()->subDays(41),now()->subDays(39));
        $this->assertSame(110000,$old['metrics']['buyer_order_value']);$this->assertSame(0,$old['metrics']['seller_cod_paid']);
        $this->actingAs($this->seller)->get('/seller/reports?from='.now()->toDateString().'&to='.now()->toDateString().'&seller_id='.$other->id)->assertOk()->assertViewHas('paymentReport',fn($r)=>$r['metrics']['buyer_order_value']===0);
        DB::table('orders')->where('id',$this->order->id)->update(['status'=>'returned']);
        $this->assertSame(100000,$this->snapshot()['metrics']['seller_cod_paid']);
    }

    public function test_reporting_admin_exports_use_orders_and_preserve_legacy_commission_archive(): void
    {
        Schema::create('commissions',function(Blueprint $t){$t->string('id')->primary();$t->string('order_id')->nullable();$t->integer('seller_id');foreach(['order_amount','commission_rate','commission_amount','seller_earnings'] as $c)$t->decimal($c,12,2);$t->timestamp('created_at')->nullable();});
        $id=(string)Str::uuid();DB::table('commissions')->insert(['id'=>$id,'order_id'=>$this->order->id,'seller_id'=>$this->seller->id,'order_amount'=>1100,'commission_rate'=>10,'commission_amount'=>110,'seller_earnings'=>990,'created_at'=>now()]);
        $this->order->update(['status'=>'completed']);
        // Simulate malformed imported display data, without weakening immutable order guards.
        DB::table('orders')->where('id',$this->order->id)->update(['payment_method'=>'=UNTRUSTED()']);
        $admin=User::create(['username'=>'report-platform-admin','given_names'=>'Report','last_name'=>'Admin','account_type'=>'admin','is_admin'=>true,'is_logistics'=>false,'status'=>'approved']);
        $this->actingAs($admin)->get('/admin/reports')->assertOk()->assertDontSee('Total Commission Collected')->assertSee('Payment summary CSV');
        $this->get('/admin/commission')->assertOk()->assertSee('Historical commission entries')->assertSee('not proof of collected fees');
        $csv=$this->get('/admin/reports/export/sales')->assertOk();$this->assertStringContainsString('no-store',$csv->headers->get('Cache-Control'));
        $this->assertStringContainsString("'=UNTRUSTED()",$csv->getContent());$this->assertStringNotContainsString('Commission Rate',$csv->getContent());
        $this->get('/admin/reports/export/commission')->assertOk()->assertSee('buyer_order_value')->assertDontSee('commission_amount');
        $pdf=$this->get('/admin/reports/export/sales-pdf')->assertOk();$this->assertStringStartsWith('%PDF',$pdf->getContent());
        $this->assertDatabaseHas('commissions',['id'=>$id,'commission_amount'=>110,'seller_earnings'=>990]);
        $this->actingAs($this->seller)->get('/admin/reports/export/sales')->assertRedirect('/login');
    }

    public function test_reporting_missing_sources_are_unavailable_and_snapshots_do_not_change_finances(): void
    {
        $before=DB::table('logistics_financial_events')->count();$tx=DB::table('logistics_financial_transactions')->get()->toJson();
        $this->snapshot();$this->assertSame($before,DB::table('logistics_financial_events')->count());$this->assertSame($tx,DB::table('logistics_financial_transactions')->get()->toJson());
        $this->actingAs($this->seller)->get('/seller/reports?from=not-a-date')->assertRedirect();
        Schema::rename('logistics_cash_allocations','unavailable_cash_allocations');
        $this->assertFalse($this->snapshot()['available']);
        $this->actingAs($this->seller)->get('/seller/reports')->assertOk()->assertSee('Unavailable');
    }
}
