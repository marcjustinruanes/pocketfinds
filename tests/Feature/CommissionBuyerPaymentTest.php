<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\{CommissionLedger, CommissionPayments};
use Illuminate\Support\Facades\{DB, Http, Mail};
use Illuminate\Support\Str;

class CommissionBuyerPaymentTest extends LogisticsReconciliationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite',DB::getDriverName());
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        foreach(['2026_11_08_000001_add_platform_commission_ledger','2026_11_08_000002_complete_commission_workflows','2026_11_08_000003_add_commission_account_management','2026_11_08_000004_add_commission_payment_messages'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        config(['commission.enabled'=>true,'commission.activation_at'=>now()->subMinute()->toIso8601String()]);
        Http::preventStrayRequests();Mail::fake();
    }

    public function test_transaction_buyer_payment_to_seller_does_not_collect_platform_commission(): void
    {
        // Reuse the existing real checkout, buyer proof, Seller verification, fulfillment and buyer confirmation path.
        parent::test_complete_buyer_gcash_to_delivery_preserves_direct_seller_payment_and_company_receipt();
        $o=DB::table('commission_obligations')->where('method','gcash')->first();
        $this->assertNotNull($o);
        // Eligibility is recognized by the existing periodic reconciler, not by buyer payment verification.
        $this->assertNull($o->earned_at);
        $this->artisan('commissions:reconcile')->assertExitCode(0);
        $o=DB::table('commission_obligations')->find($o->id);
        $this->assertNotNull($o->earned_at);
        $this->assertSame(100000,(int)$o->basis_cents);$this->assertSame(10000,(int)$o->commission_cents);
        $purchase=(array)DB::table('gcash_purchases')->first();
        $this->assertSame('order_placed',$purchase['payment_state']);
        $this->assertNotNull($purchase['verified_at']);
        $this->assertSame($this->seller->id,(int)$purchase['verified_by']);
        $this->assertSame('RECONBUYER001',$purchase['payment_reference']);
        $ledger=app(CommissionLedger::class);
        $this->assertSame(0,$ledger->collected($o->id));$this->assertSame(10000,$ledger->due($o));
        $notice=DB::table('notifications')->where('user_id',$this->seller->id)->where('notification_type','commission')->first();
        $this->assertNotNull($notice);
        $this->actingAs($this->seller)->get(route('seller.notifications.open',$notice->id))->assertRedirect(route('commissions.index'));
        $platform=User::create(['username'=>'synthetic-platform-'.Str::uuid(),'account_type'=>'buyer','is_admin'=>true,'is_logistics'=>false,'status'=>'approved']);
        app(CommissionPayments::class)->account($platform,['account_name'=>'SIMULATED Platform','account_number'=>'09111111111']);
        $account=DB::table('commission_accounts')->first();
        $this->actingAs($this->seller)->post('/commissions/payments',[
            'submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'amount'=>'100','reference'=>'SELLERCOMM001',
            'sent'=>1,'allocations'=>[$o->id=>'100'],'proof'=>$this->proof('SIMULATED independent Seller commission receipt'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $payment=DB::table('commission_payments')->first();
        $this->assertSame(0,$ledger->collected($o->id));
        $this->assertDatabaseHas('notifications',['user_id'=>$platform->id,'notification_type'=>'commission_payment','reference_id'=>$payment->id]);
        $this->actingAs($platform)->get(route('commissions.conversation',['payment',$payment->id]))->assertOk()->assertSee('Manual Seller GCash commission payment submitted');
        $this->post(route('commissions.message',['payment',$payment->id]),['submission_key'=>(string)Str::uuid(),'message'=>'Synthetic Platform reply: checking the actual receipt'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->seller)->get(route('commissions.conversation',['payment',$payment->id]))->assertOk()->assertSee('checking the actual receipt');
        $this->assertSame(0,$ledger->collected($o->id));
        $this->actingAs($platform)->post('/commissions/payments/'.$payment->id.'/review',[
            'revision'=>1,'decision'=>'verify','amount'=>'100','reference'=>'SELLERCOMM001','received'=>1,
            'reason'=>'SIMULATED separate platform receipt verified',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10000,$ledger->collected($o->id));
        $this->assertSame(0,$ledger->due(DB::table('commission_obligations')->find($o->id)));
        $this->assertSame($purchase,(array)DB::table('gcash_purchases')->first());
    }

    public function test_cod_seller_and_platform_vouchers_preserve_shipping_and_cash_only_seller_proceeds(): void
    {
        // This inherited baseline pays gross product proceeds. Keep its original
        // unenrolled policy; CommissionTest covers enrolled net payout/subsidy.
        config(['commission.enabled'=>false,'commission.activation_at'=>null]);
        parent::test_cod_seller_and_platform_vouchers_preserve_shipping_and_cash_only_seller_proceeds();
        $this->assertDatabaseCount('commission_obligations',0);
    }
}
