<?php

namespace Tests\Feature;

use App\Models\{GcashPurchase, Order, User};
use App\Services\{CommissionEnrollment, CommissionLedger, CommissionPayments};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

class CommissionEnrollmentTest extends CodSellerPayoutTest
{
    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['000001_add_platform_commission_ledger','000002_complete_commission_workflows','000003_add_commission_account_management','000004_add_commission_payment_messages','000005_add_commission_enrollment_settings'] as $name) {
            (require database_path('migrations/2026_11_08_'.$name.'.php'))->up();
        }
        $this->platform=User::create(['username'=>'platform-'.Str::uuid(),'account_type'=>'buyer','is_admin'=>true,'is_logistics'=>false,'status'=>'approved']);
        app(CommissionPayments::class)->account($this->platform,['account_name'=>'Synthetic Official Account','account_number'=>'09111111111']);
    }

    private function change(string $action, array $extra = [])
    {
        return $this->actingAs($this->platform)->post(route('admin.settings.commission.update'),$extra+[
            'action'=>$action,'revision'=>app(CommissionEnrollment::class)->state()->revision,
            'confirmed'=>1,'reason'=>'Synthetic activation approval',
        ]);
    }

    private function order(): Order
    {
        return Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'placed','payment_method'=>'Cash on Delivery','items'=>[['name'=>'Synthetic','qty'=>1,'price'=>1000]],'subtotal'=>1000,'shipping_amount'=>100,'discount_amount'=>0,'total'=>1100,'commerce_snapshot'=>['vouchers'=>[]]]);
    }

    public function test_database_default_is_disabled_even_with_cached_environment_activation(): void
    {
        config(['commission.enabled'=>true,'commission.activation_at'=>now()->subYear()]);
        $this->assertNull(app(CommissionLedger::class)->activation());
        $this->order();
        $this->assertDatabaseCount('commission_obligations',0);
        $this->assertDatabaseCount('commission_enrollment_events',0);
    }

    public function test_activation_deactivation_and_reactivation_are_prospective_and_audited(): void
    {
        $this->freezeTime();
        $old=$this->order();
        $this->change('activate')->assertRedirect(route('admin.settings'))->assertSessionHasNoErrors();
        $first=app(CommissionEnrollment::class)->state();
        $this->assertTrue((bool)$first->enabled);
        $this->assertSame(now()->addSecond()->toDateTimeString(),$first->activation_at);
        $this->order(); // Before the next whole-second cutoff.
        $this->travel(1)->seconds();
        $enrolled=$this->order();
        $ob=DB::table('commission_obligations')->where('order_id',$enrolled->id)->first();
        $this->assertSame(10000,(int)$ob->commission_cents);
        $this->change('deactivate')->assertSessionHasNoErrors();
        $this->assertSame($first->activation_at,app(CommissionEnrollment::class)->state()->activation_at);
        $gap=$this->order();
        DB::table('orders')->where('id',$enrolled->id)->update(['status'=>'delivered','buyer_confirmed_at'=>now()]);
        $enrolled->logStatus('delivered',$this->rider->id);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $this->assertNotNull(DB::table('commission_obligations')->find($ob->id)->earned_at);
        $this->assertSame(0,app(CommissionLedger::class)->collected($ob->id));
        $preserved=(array)DB::table('commission_obligations')->find($ob->id);
        $this->travel(10)->seconds();
        $this->change('activate')->assertSessionHasNoErrors();
        $this->assertNotSame($first->activation_at,app(CommissionEnrollment::class)->state()->activation_at);
        $this->travel(1)->seconds();
        $this->order();
        foreach ([$old,$gap] as $historical) app(CommissionLedger::class)->captureOrder($historical);
        $this->assertDatabaseCount('commission_obligations',2);
        $this->assertSame($preserved,(array)DB::table('commission_obligations')->find($ob->id));
        $this->assertDatabaseCount('commission_enrollment_events',3);
        $this->assertSame(['activate','deactivate','activate'],DB::table('commission_enrollment_events')->orderBy('id')->pluck('action')->all());
    }

    public function test_only_current_approved_platform_admin_can_change_enrollment(): void
    {
        $data=['action'=>'activate','revision'=>1,'confirmed'=>1,'reason'=>'Unauthorized attempt'];
        foreach ([$this->seller,$this->buyer,$this->rider,$this->staff,$this->admin] as $actor) {
            $this->actingAs($actor)->post(route('admin.settings.commission.update'),$data)->assertRedirect(route('login'));
        }
        $this->actingAs($this->platform);
        DB::table('users')->where('id',$this->platform->id)->update(['status'=>'suspended']);
        $this->post(route('admin.settings.commission.update'),$data)->assertForbidden();
        DB::table('users')->where('id',$this->platform->id)->update(['status'=>'approved','is_admin'=>false]);
        $this->post(route('admin.settings.commission.update'),$data)->assertForbidden();
        $this->assertDatabaseCount('commission_enrollment_events',0);
    }

    public function test_confirmation_revision_fixed_rate_and_account_are_enforced(): void
    {
        $this->change('activate',['confirmed'=>0])->assertSessionHasErrors('confirmed');
        $this->change('activate',['commission_rate'=>5])->assertSessionHasErrors('commission_rate');
        $this->change('activate',['activation_at'=>now()->subYear()->toDateTimeString()])->assertSessionHasErrors('activation_at');
        DB::table('commission_account_settings')->update(['active_account_id'=>null]);
        $this->change('activate')->assertStatus(422);
        $this->assertDatabaseCount('commission_enrollment_events',0);
        app(CommissionPayments::class)->account($this->platform,['account_name'=>'Synthetic Official','account_number'=>'09111111111']);
        $this->change('activate')->assertSessionHasNoErrors();
        $this->change('activate',['revision'=>1])->assertStatus(409);
        $this->change('activate')->assertStatus(409);
        $this->assertDatabaseCount('commission_enrollment_events',1);
    }

    public function test_audit_failure_rolls_back_setting_and_missing_installation_cannot_toggle(): void
    {
        DB::unprepared("CREATE TRIGGER reject_enrollment_event BEFORE INSERT ON commission_enrollment_events BEGIN SELECT RAISE(ABORT, 'synthetic audit failure'); END");
        try { app(CommissionEnrollment::class)->change($this->platform,['action'=>'activate','revision'=>1,'confirmed'=>1,'reason'=>'Synthetic rollback']); $this->fail('Expected audit failure'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('synthetic audit failure',$e->getMessage()); }
        $this->assertFalse((bool)app(CommissionEnrollment::class)->state()->enabled);
        $this->assertSame(1,(int)app(CommissionEnrollment::class)->state()->revision);
        Schema::drop('commission_enrollment_events');
        $this->change('activate')->assertStatus(503);
    }

    public function test_settings_control_shows_confirmation_status_and_escaped_audit(): void
    {
        $this->change('activate',['reason'=>'<script>synthetic</script>'])->assertSessionHasNoErrors();
        $state=app(CommissionEnrollment::class)->state();
        $this->view('admin.partials.commission-enrollment',[
            'commissionEnrollment'=>$state,
            'commissionEnrollmentEvents'=>DB::table('commission_enrollment_events')->paginate(20),
        ])->assertSee('Deactivate Commission')->assertSee('10% (fixed)')->assertSee('confirm(')->assertSee('PHT')->assertSee('&lt;script&gt;synthetic&lt;/script&gt;',false)->assertDontSee('<script>synthetic</script>',false);
    }

    public function test_existing_admin_settings_page_includes_the_disabled_control(): void
    {
        $this->actingAs($this->platform)->get(route('admin.settings'))->assertOk()->assertSee('Activate Commission')->assertSee('Inactive')->assertSee('10% (fixed)')->assertDontSee('name="commission_rate"',false);
    }

    public function test_existing_gcash_obligation_can_be_paid_and_verified_while_disabled(): void
    {
        $this->freezeTime();
        $this->change('activate')->assertSessionHasNoErrors();
        $this->travel(1)->seconds();
        $method=DB::table('payment_methods')->insertGetId(['name'=>'GCash','type'=>'ewallet','is_active'=>true]);
        $p=GcashPurchase::create(['id'=>(string)Str::uuid(),'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'checkout_id'=>(string)Str::uuid(),'request_hash'=>hash('sha256','synthetic-paid'),'payment_method_id'=>$method,'snapshot'=>['items'=>[['qty'=>1,'price'=>1000]],'subtotal'=>1000,'discount_amount'=>0],'amount_due'=>1000,'payment_state'=>'verified','reservation_state'=>'paid','verified_at'=>now(),'payment_reference'=>'SYNTHETIC-BUYER']);
        $order=Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'gcash_purchase_id'=>$p->id,'status'=>'placed','payment_method'=>'GCash','items'=>$p->snapshot['items'],'subtotal'=>1000,'shipping_amount'=>0,'discount_amount'=>0,'total'=>1000]);
        $this->change('deactivate')->assertSessionHasNoErrors();
        DB::table('orders')->where('id',$order->id)->update(['status'=>'delivered','buyer_confirmed_at'=>now()]);
        $order->logStatus('delivered',$this->rider->id);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $ob=DB::table('commission_obligations')->where('order_id',$order->id)->first();
        $flow=app(CommissionPayments::class);
        $id=$flow->submit($this->seller,['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>DB::table('commission_accounts')->value('id'),'amount'=>'100','reference'=>'SYNTHETIC-COMMISSION','sent'=>1,'allocations'=>[$ob->id=>'100']],$this->proof('Synthetic manual receipt'));
        $this->assertSame(0,app(CommissionLedger::class)->collected($ob->id));
        $flow->review($this->platform,$id,['revision'=>1,'decision'=>'verify','amount'=>'100','reference'=>'SYNTHETIC-COMMISSION','received'=>1,'reason'=>'Synthetic actual receipt verification']);
        $this->assertSame(10000,app(CommissionLedger::class)->collected($ob->id));
        $this->assertFalse((bool)app(CommissionEnrollment::class)->state()->enabled);
    }

    public function test_pre_activation_gcash_purchase_stays_grandfathered_when_order_is_created_later(): void
    {
        $this->freezeTime();
        $method=DB::table('payment_methods')->insertGetId(['name'=>'GCash','type'=>'ewallet','is_active'=>true]);
        $p=GcashPurchase::create(['id'=>(string)Str::uuid(),'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'checkout_id'=>(string)Str::uuid(),'request_hash'=>hash('sha256','synthetic'),'payment_method_id'=>$method,'snapshot'=>['items'=>[['qty'=>1,'price'=>1000]],'subtotal'=>1000,'discount_amount'=>0],'amount_due'=>1000,'payment_state'=>'awaiting_payment','reservation_state'=>'held']);
        $this->change('activate')->assertSessionHasNoErrors();
        $this->travel(2)->seconds();
        Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'gcash_purchase_id'=>$p->id,'status'=>'placed','payment_method'=>'GCash','items'=>$p->snapshot['items'],'subtotal'=>1000,'shipping_amount'=>0,'discount_amount'=>0,'total'=>1000]);
        $this->assertDatabaseCount('commission_obligations',0);
    }
}
