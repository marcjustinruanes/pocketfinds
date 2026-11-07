<?php

namespace Tests\Feature;

use App\Models\{GcashPurchase, Order, User};
use App\Services\{CommissionLedger, CommissionPayments, CommissionReporting, PaymentReporting, LogisticsReconciliation};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class CommissionTest extends CodSellerPayoutTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_11_08_000001_add_platform_commission_ledger.php'))->up();
        (require database_path('migrations/2026_11_08_000002_complete_commission_workflows.php'))->up();
        (require database_path('migrations/2026_11_08_000003_add_commission_account_management.php'))->up();
        (require database_path('migrations/2026_11_08_000004_add_commission_payment_messages.php'))->up();
        config(['commission.enabled' => false, 'commission.activation_at' => null]);
    }

    private function ledger(): CommissionLedger { return app(CommissionLedger::class); }
    private function active(): void { config(['commission.enabled' => true, 'commission.activation_at' => now()->subMinute()->toIso8601String()]); }
    private function platform(): User { return User::create(['username' => 'platform-'.Str::uuid(), 'account_type' => 'buyer', 'is_admin' => true, 'status' => 'approved', 'is_logistics' => false]); }
    private function obligation(?Order $order = null): object { return DB::table('commission_obligations')->where('order_id', ($order ?? $this->order)->id)->first(); }
    private function enableFixture(): void { $this->active(); DB::transaction(fn() => $this->ledger()->captureOrder($this->order)); }
    private function paidCod(): void { $this->enableFixture(); $this->fund(); $this->flow->prepare($this->admin, $this->prepareData()); $this->verify(); $this->pay('900'); }
    private function receiving(User $admin): object { app(CommissionPayments::class)->account($admin, ['account_name' => 'Platform Treasury', 'account_number' => '09111111111']); return DB::table('commission_accounts')->first(); }
    private function submitData(object $account, object $ob, string $amount = '100', string $ref = 'COMM001'): array { return ['submission_key' => (string) Str::uuid(), 'receiving_account_id' => $account->id, 'amount' => $amount, 'reference' => $ref, 'sent' => '1', 'allocations' => [$ob->id => '100']]; }
    private function verifyReceipt(User $admin, string $id, string $amount = '100', string $ref = 'COMM001'): void { app(CommissionPayments::class)->review($admin, $id, ['revision' => DB::table('commission_payments')->find($id)->revision, 'decision' => 'verify', 'reason' => 'Actual account receipt checked', 'amount' => $amount, 'reference' => $ref, 'received' => '1']); }
    private function gcash(): Order
    {
        $this->active();
        $method = DB::table('payment_methods')->insertGetId(['name' => 'GCash', 'type' => 'ewallet', 'is_active' => true]);
        $p = GcashPurchase::create(['id' => (string) Str::uuid(), 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'checkout_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'test'), 'payment_method_id' => $method,
            'snapshot' => ['items' => [['name' => 'Item A', 'qty' => 1, 'price' => 600], ['name' => 'Item B', 'qty' => 1, 'price' => 400]], 'subtotal' => '1000', 'discount_amount' => '0', 'shipping_amount' => '100', 'commerce' => ['vouchers' => []]],
            'amount_due' => '1100', 'payment_state' => 'verified', 'reservation_state' => 'paid', 'verified_amount' => '1100', 'verified_by' => $this->seller->id, 'verified_at' => now(), 'payment_reference' => 'BUYER'.Str::random(10)]);
        $order = Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'status' => 'placed', 'gcash_purchase_id' => $p->id, 'subtotal' => '1000', 'shipping_amount' => '100', 'discount_amount' => '0', 'total' => '1100', 'payment_method' => 'GCash', 'items' => $p->snapshot['items'], 'commerce_snapshot' => ['vouchers' => []]]);
        DB::table('orders')->where('id', $order->id)->update(['status' => 'completed', 'buyer_confirmed_at' => now()]);
        $order->logStatus('delivered', $this->rider->id); $order->refresh();
        DB::transaction(fn() => $this->ledger()->earn($order));
        return $order;
    }

    public function test_calculation_excludes_shipping_and_only_subtracts_seller_product_discounts(): void
    {
        $r = $this->ledger()->calculate(['items' => [['name' => 'A', 'qty' => 2, 'price' => '500.00']], 'subtotal' => '1000', 'shipping_amount' => '100', 'discount_amount' => '200', 'commerce' => ['vouchers' => [
            ['kind' => 'store', 'funding_source' => 'seller', 'amount' => '100'], ['kind' => 'platform', 'funding_source' => 'platform', 'amount' => '50'], ['kind' => 'shipping', 'funding_source' => 'seller', 'amount' => '50']]]]);
        $this->assertSame(90000, $r['basis_cents']); $this->assertSame(9000, $r['commission_cents']);
        $small = $this->ledger()->calculate(['items' => [['qty' => 1, 'price' => '0.05'], ['qty' => 1, 'price' => '0.05']], 'subtotal' => '0.10', 'discount_amount' => 0]);
        $this->assertSame(1, array_sum(array_column($small['lines'], 'commission_cents')));
        $this->assertSame([50000000000,50000000000],$this->ledger()->split(100000000000,[500000000000,500000000000]));
        $this->invalid(fn() => $this->ledger()->calculate(['items' => [['qty' => 1, 'price' => 10]], 'subtotal' => 10, 'discount_amount' => 1]));
    }

    public function test_disabled_future_activation_and_historical_purchases_are_grandfathered(): void
    {
        $this->ledger()->captureOrder($this->order); $this->assertDatabaseCount('commission_obligations', 0);
        config(['commission.enabled' => true, 'commission.activation_at' => now()->addHour()]);
        $this->ledger()->captureOrder($this->order); $this->assertDatabaseCount('commission_obligations', 0);
        $this->active(); DB::table('orders')->where('id', $this->order->id)->update(['created_at' => now()->subDays(2)]);
        $this->ledger()->captureOrder($this->order->fresh()); $this->assertDatabaseCount('commission_obligations', 0);
        $this->fund(); $this->flow->prepare($this->admin, $this->prepareData()); $this->verify(); $this->pay();
        $this->assertSame(100000, (int) DB::table('logistics_financial_transactions')->where('kind', 'seller_payout')->value('amount_cents'));
    }

    public function test_cod_net_payout_reserves_gross_and_withholding_is_not_collection(): void
    {
        $this->enableFixture(); $this->fund(); $this->flow->prepare($this->admin, $this->prepareData());
        $this->assertSame(10000, $this->treasury->available($this->account->id, 'cod'));
        $this->verify(); $this->invalid(fn() => $this->pay('1000')); $this->pay('900');
        $o = $this->obligation(); $this->assertSame(10000, (int) $o->withheld_cents); $this->assertSame(0, $this->ledger()->collected($o->id));
        $this->assertSame(10000, $this->treasury->balance($this->account->id, 'commission'));
        $this->assertSame(10000, $this->treasury->balance($this->account->id, 'cod'));
        $this->assertSame(90000, app(PaymentReporting::class)->snapshot($this->seller->id)['metrics']['seller_cod_paid']);
        $this->assertDatabaseHas('logistics_financial_transactions', ['seller_payout_id' => $this->payout()->id, 'amount_cents' => 90000]);
        $this->actingAs($this->seller)->get('/seller/payouts')->assertOk()->assertSee('900.00');
    }

    public function test_cod_remittance_is_idempotent_protects_transit_and_requires_platform_receipt(): void
    {
        $this->paidCod(); $admin = $this->platform(); $account = $this->receiving($admin); $ob = $this->obligation(); $data = $this->submitData($account, $ob); $p = app(CommissionPayments::class);
        $id = $p->prepareRemittance($this->admin, $data);
        $this->assertSame($id, $p->prepareRemittance($this->admin, $data));
        $p->sendRemittance($this->admin,$id,['revision'=>1,'action'=>'send','amount'=>'100','reference'=>'COMM001','sent'=>1,'reason'=>'Actual commission remittance'],$this->proof('commission receipt'));
        $this->assertSame(0, $this->ledger()->collected($ob->id)); $this->assertSame(0, $this->treasury->balance($this->account->id, 'commission'));
        $this->assertSame(10000, $this->treasury->balance($this->account->id, 'comm_transit'));
        $this->invalid(fn() => $p->prepareRemittance($this->admin, $this->submitData($account, $ob, '100', 'OTHER001')));
        $this->verifyReceipt($admin, $id); $this->assertSame(10000, $this->ledger()->collected($ob->id));
        $this->assertSame(0, $this->treasury->balance($this->account->id, 'comm_transit'));
        $this->assertSame(0, $this->ledger()->due($this->obligation()));
    }

    public function test_gcash_direct_seller_receipt_is_separate_from_commission_and_supports_credit(): void
    {
        $order = $this->gcash(); $o = $this->obligation($order); $this->assertNotNull($o->earned_at); $this->assertSame(0, $this->ledger()->collected($o->id));
        $admin = $this->platform(); $account = $this->receiving($admin); $p = app(CommissionPayments::class);
        $id = $p->submit($this->seller, $this->submitData($account, $o, '120'), $this->proof('Seller commission 120'));
        $this->verifyReceipt($admin, $id, '120'); $this->assertSame(2000, (int) DB::table('commission_payments')->find($id)->unapplied_cents);
        DB::transaction(fn() => $this->ledger()->reverse($order->id, 'return:partial', [0], 'Refund item A'));
        $this->assertSame(6000, (int) $this->obligation($order)->reversed_cents);
        $this->assertDatabaseHas('commission_credits', ['obligation_id' => $o->id, 'payer' => 'platform', 'amount_cents' => 6000]);
        DB::transaction(fn() => $this->ledger()->reverse($order->id, 'return:partial', [0], 'Duplicate refund'));
        $this->assertDatabaseCount('commission_credits', 1);
        DB::transaction(fn() => $this->ledger()->reverse($order->id, 'return:full', null, 'Remaining items refunded'));
        $this->assertSame(10000, (int) DB::table('commission_credits')->sum('amount_cents'));
    }

    public function test_refund_in_cod_transit_moves_repayment_responsibility_to_platform(): void
    {
        $this->paidCod(); $admin = $this->platform(); $account = $this->receiving($admin); $o = $this->obligation(); $p = app(CommissionPayments::class);
        $id = $p->prepareRemittance($this->admin, $this->submitData($account, $o));
        $p->sendRemittance($this->admin,$id,['revision'=>1,'action'=>'send','amount'=>'100','reference'=>'COMM001','sent'=>1,'reason'=>'Actual commission remittance'],$this->proof('in transit'));
        DB::transaction(fn() => $this->ledger()->reverse($this->order->id, 'cancel:transit', null, 'Full refund during transit'));
        $this->assertDatabaseHas('commission_credits', ['payer' => 'logistics', 'amount_cents' => 10000]);
        $this->verifyReceipt($admin, $id);
        $this->assertDatabaseHas('commission_credits', ['payer' => 'platform', 'amount_cents' => 10000]);
        $credit = DB::table('commission_credits')->where('payer', 'platform')->first();
        $p->repay($admin, $credit->id, ['amount' => '100', 'reference' => 'REFUND001', 'sent' => 1, 'destination_id'=>$this->destination->id, 'reason' => 'Actual Seller commission repayment'], $this->proof('platform refund'));
        $this->assertDatabaseHas('commission_credits', ['id' => $credit->id, 'status' => 'paid']);
    }

    public function test_active_order_creation_and_immutable_snapshot_and_cancellation(): void
    {
        $this->active();
        $order = Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'status' => 'placed', 'payment_method' => 'COD', 'subtotal' => 1000, 'total' => 1100, 'shipping_amount' => 100, 'discount_amount' => 0, 'items' => [['qty' => 1, 'price' => 1000]], 'commerce_snapshot' => ['vouchers' => []]]);
        $this->assertNotNull($this->obligation($order));
        $this->invalid(fn() => $order->update(['subtotal' => 2000])); $order->refresh();
        $order->update(['status' => 'cancelled']);
        $this->assertNotNull($this->obligation($order)->cancelled_at); $this->assertSame(10000, (int) $this->obligation($order)->reversed_cents);
    }

    public function test_commission_pages_scope_authorization_and_private_exports(): void
    {
        $order = $this->gcash();
        $this->actingAs($this->seller)->get('/commissions')->assertOk()->assertSee('Commission obligations')->assertSee($order->id);
        $this->get('/commissions/export')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/commissions/export/payments')->assertOk();$this->get('/commissions/export/credits')->assertOk();
        $this->actingAs($this->buyer)->get('/commissions')->assertForbidden();
        $other = $this->person('seller'); $this->actingAs($other)->get('/commissions')->assertOk()->assertDontSee($order->id);
        $this->actingAs($this->platform())->get('/commissions')->assertOk()->assertSee('Platform receiving instructions');
        $this->actingAs($this->admin)->get('/commissions')->assertOk()->assertDontSee($order->id);
        $totals = app(CommissionReporting::class)->totals(app(CommissionReporting::class)->query($this->seller));
        $this->assertSame(10000, $totals['seller_due']); $this->assertSame(0, $totals['allocated_receipts']);
    }

    public function test_partial_receipts_and_unapplied_refunds_do_not_change_reversal_amounts(): void
    {
        $order=$this->gcash();$o=$this->obligation($order);$admin=$this->platform();$account=$this->receiving($admin);$p=app(CommissionPayments::class);
        $d=$this->submitData($account,$o,'40','PARTIAL001');$d['allocations'][$o->id]='40';
        $id=$p->submit($this->seller,$d,$this->proof('partial 40'));$this->verifyReceipt($admin,$id,'40','PARTIAL001');
        $this->assertSame(6000,$this->ledger()->due($this->obligation($order)));
        $d=$this->submitData($account,$o,'80','PARTIAL002');$d['allocations'][$o->id]='60';
        $id=$p->submit($this->seller,$d,$this->proof('remaining plus credit'));$this->verifyReceipt($admin,$id,'80','PARTIAL002');
        $p->refundUnapplied($admin,$id,['amount'=>'20','reference'=>'OVERPAY001','sent'=>1,'destination_id'=>$this->destination->id,'reason'=>'Actual repayment of excess Seller transfer'],$this->proof('overpay refund'));
        $this->assertSame(0,(int)DB::table('commission_payments')->find($id)->unapplied_cents);
        DB::transaction(fn()=>$this->ledger()->reverse($order->id,'partial:after-overpay',[0],'Refund Item A'));
        $this->assertSame(6000,(int)DB::table('commission_credits')->where('kind','reversal')->sum('amount_cents'));
        $this->assertSame(2000,(int)DB::table('commission_credits')->where('kind','unapplied')->sum('amount_cents'));
    }

    public function test_refund_before_verification_leaves_actual_receipt_as_seller_credit(): void
    {
        $order=$this->gcash();$o=$this->obligation($order);$admin=$this->platform();$account=$this->receiving($admin);$p=app(CommissionPayments::class);
        $id=$p->submit($this->seller,$this->submitData($account,$o),$this->proof('pending refund receipt'));
        DB::transaction(fn()=>$this->ledger()->reverse($order->id,'refund:before-verify',[0],'Refund before platform receipt'));
        $this->verifyReceipt($admin,$id);
        $this->assertSame(4000,$this->ledger()->collected($o->id));$this->assertSame(6000,(int)DB::table('commission_payments')->find($id)->unapplied_cents);
        $this->assertDatabaseCount('commission_credits',0);
    }

    public function test_rejected_gcash_proof_does_not_collect_or_block_new_payment(): void
    {
        $order=$this->gcash();$o=$this->obligation($order);$admin=$this->platform();$account=$this->receiving($admin);$p=app(CommissionPayments::class);
        $id=$p->submit($this->seller,$this->submitData($account,$o),$this->proof('unverified receipt'));
        $p->review($admin,$id,['revision'=>1,'decision'=>'reject','reason'=>'No matching actual receipt']);
        $this->assertSame(0,$this->ledger()->collected($o->id));$this->assertSame(10000,$this->ledger()->due($o));
        $second=$p->submit($this->seller,$this->submitData($account,$o,'100','RETRY001'),$this->proof('actual new transfer'));
        $this->verifyReceipt($admin,$second,'100','RETRY001');$this->assertSame(10000,$this->ledger()->collected($o->id));
    }

    public function test_cod_credit_repayment_uses_protected_funds_and_cannot_use_transit(): void
    {
        $this->paidCod();$o=$this->obligation();
        DB::transaction(fn()=>$this->ledger()->reverse($this->order->id,'refund:cod-credit',null,'Full product refund'));
        $credit=DB::table('commission_credits')->first();$p=app(CommissionPayments::class);
        $p->repay($this->admin,$credit->id,['amount'=>'100','reference'=>'CODREFUND001','sent'=>1,'destination_id'=>$this->destination->id,'reason'=>'Actual repayment of withheld commission'],$this->proof('logistics credit repaid'));
        $this->assertSame(0,$this->treasury->balance($this->account->id,'commission'));$this->assertSame(0,$this->ledger()->collected($o->id));
        $this->assertDatabaseHas('commission_credits',['id'=>$credit->id,'status'=>'paid']);
    }

    public function test_audit_failure_rolls_back_commission_and_source_order(): void
    {
        $this->active();$before=Order::count();
        DB::unprepared("CREATE TRIGGER fail_commission BEFORE INSERT ON commission_events BEGIN SELECT RAISE(ABORT,'Synthetic audit failure'); END");
        try {
            Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'placed','payment_method'=>'COD','subtotal'=>100,'total'=>100,'shipping_amount'=>0,'discount_amount'=>0,'items'=>[['qty'=>1,'price'=>100]],'commerce_snapshot'=>['vouchers'=>[]]]);
            $this->fail('Expected audit failure');
        } catch(\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('Synthetic audit failure',$e->getMessage()); }
        $this->assertSame($before,Order::count());$this->assertDatabaseCount('commission_obligations',0);$this->assertDatabaseCount('commission_lines',0);
    }

    public function test_gcash_dispute_holds_collection(): void
    {
        $order=$this->gcash();$o=$this->obligation($order);
        DB::table('complaints')->insert(['id'=>(string)Str::uuid(),'order_id'=>$order->id,'complainant_id'=>$this->buyer->id,'respondent_id'=>$this->seller->id,'complaint_type'=>'order','subject'=>'Review','description'=>'Synthetic dispute','status'=>'open','created_at'=>now()]);
        $this->assertSame(0,$this->ledger()->due($o));
        $this->assertSame(1,(int)app(CommissionReporting::class)->query($this->seller)->where('id',$o->id)->first()->held);
        $this->assertSame(0,app(CommissionReporting::class)->totals(app(CommissionReporting::class)->query($this->seller))['seller_due']);
    }

    public function test_pre_activation_gcash_purchase_is_not_enrolled_when_placed_later(): void
    {
        $method=DB::table('payment_methods')->insertGetId(['name'=>'GCash','type'=>'ewallet','is_active'=>true]);
        $p=GcashPurchase::create(['id'=>(string)Str::uuid(),'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'checkout_id'=>(string)Str::uuid(),'request_hash'=>hash('sha256','historical'),'payment_method_id'=>$method,'snapshot'=>['items'=>[['qty'=>1,'price'=>1000]],'subtotal'=>'1000','shipping_amount'=>'100','discount_amount'=>'0'],'amount_due'=>'1100','payment_state'=>'awaiting_payment','reservation_state'=>'held']);
        $this->active();
        Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'placed','gcash_purchase_id'=>$p->id,'subtotal'=>1000,'total'=>1100,'shipping_amount'=>100,'discount_amount'=>0,'payment_method'=>'GCash','items'=>$p->snapshot['items']]);
        $this->assertDatabaseCount('commission_obligations',0);
    }

    public function test_expired_unpaid_gcash_reservation_is_never_collectible(): void
    {
        $this->active();$method=DB::table('payment_methods')->insertGetId(['name'=>'GCash','type'=>'ewallet','is_active'=>true]);
        GcashPurchase::create(['id'=>(string)Str::uuid(),'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'checkout_id'=>(string)Str::uuid(),'request_hash'=>hash('sha256','expired'),'payment_method_id'=>$method,'snapshot'=>['items'=>[['qty'=>1,'price'=>1000]],'subtotal'=>'1000','shipping_amount'=>'100','discount_amount'=>'0'],'amount_due'=>'1100','payment_state'=>'awaiting_payment','reservation_state'=>'expired']);
        $this->ledger()->sweep();$o=DB::table('commission_obligations')->first();
        $this->assertNull($o->earned_at);$this->assertSame(0,$this->ledger()->due($o));$this->assertSame(0,$this->ledger()->collected($o->id));
    }

    public function test_rate_is_fixed_and_sweep_reminders_and_earning_are_idempotent(): void
    {
        DB::table('settings')->insert(['id'=>1,'commission_rate'=>99]);
        $order=$this->gcash();$o=$this->obligation($order);$this->assertSame(10000,(int)$o->commission_cents);
        DB::table('commission_obligations')->where('id',$o->id)->update(['due_at'=>now()->subDay()]);
        $this->ledger()->sweep();$this->ledger()->sweep();
        $this->assertSame(1,DB::table('commission_events')->where('kind','earned')->count());
        $this->assertSame(1,DB::table('commission_events')->where('kind','overdue_reminder')->count());
    }

    public function test_foreign_payment_proofs_and_review_mutations_are_denied(): void
    {
        $order=$this->gcash();$o=$this->obligation($order);$admin=$this->platform();$account=$this->receiving($admin);$p=app(CommissionPayments::class);
        $id=$p->submit($this->seller,$this->submitData($account,$o),$this->proof('private evidence'));
        $other=$this->person('seller');$this->actingAs($other)->get('/commissions/evidence/payment/'.$id)->assertNotFound();
        $this->actingAs($this->seller)->get('/commissions/evidence/payment/'.$id)->assertOk();
        $this->post('/commissions/payments/'.$id.'/review',['revision'=>1,'decision'=>'verify','amount'=>'100','reference'=>'COMM001','received'=>1,'reason'=>'Forbidden Seller verification'])->assertForbidden();
        $this->assertSame(0,$this->ledger()->collected($o->id));
    }

    public function test_cod_remittance_is_reserved_before_sending_and_unsent_cancellation_releases_it(): void
    {
        $this->paidCod();$o=$this->obligation();$admin=$this->platform();$account=$this->receiving($admin);$p=app(CommissionPayments::class);
        $d=['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'allocations'=>[$o->id=>'100']];
        $id=$p->prepareRemittance($this->admin,$d);$this->assertSame($id,$p->prepareRemittance($this->admin,$d));
        $this->assertDatabaseHas('commission_payments',['id'=>$id,'status'=>'draft']);$this->assertSame(10000,$this->treasury->balance($this->account->id,'commission'));$this->assertSame(0,$this->treasury->balance($this->account->id,'comm_transit'));
        $this->invalid(fn()=>$p->prepareRemittance($this->admin,array_replace($d,['submission_key'=>(string)Str::uuid()])));
        $p->sendRemittance($this->admin,$id,['revision'=>1,'action'=>'cancel','not_sent'=>1,'reason'=>'Unsent remittance cancelled'],null);
        $d['submission_key']=(string)Str::uuid();$id=$p->prepareRemittance($this->admin,$d);
        $this->actingAs($this->admin)->get('/commissions')->assertOk()->assertSee('Record actual remittance');
        $p->sendRemittance($this->admin,$id,['revision'=>1,'action'=>'send','sent'=>1,'amount'=>'100','reference'=>'DRAFTSEND001','reason'=>'Actual reserved transfer sent'],$this->proof('actual drafted transfer'));
        $this->assertSame(0,$this->ledger()->collected($o->id));$this->assertSame(10000,$this->treasury->balance($this->account->id,'comm_transit'));
        $p->review($admin,$id,['revision'=>2,'decision'=>'verify','amount'=>'100','reference'=>'DRAFTSEND001','received'=>1,'reason'=>'Actual platform receipt checked']);
        $this->assertSame(10000,$this->ledger()->collected($o->id));$this->assertSame(0,$this->treasury->balance($this->account->id,'comm_transit'));
    }

    public function test_two_distinct_actual_gcash_transfers_are_preserved_as_receipt_and_credit(): void
    {
        $order=$this->gcash();$o=$this->obligation($order);$admin=$this->platform();$account=$this->receiving($admin);$p=app(CommissionPayments::class);
        $first=$p->submit($this->seller,$this->submitData($account,$o,'100','ACTUAL001'),$this->proof('first actual transfer'));
        $second=$p->submit($this->seller,$this->submitData($account,$o,'100','ACTUAL002'),$this->proof('second actual transfer'));
        $this->assertSame(10000,(int)DB::table('commission_allocations')->sum('requested_cents'));
        $this->verifyReceipt($admin,$first,'100','ACTUAL001');$this->verifyReceipt($admin,$second,'100','ACTUAL002');
        $this->assertSame(10000,$this->ledger()->collected($o->id));$this->assertSame(10000,(int)DB::table('commission_payments')->find($second)->unapplied_cents);
    }

    public function test_commission_workspaces_render_all_financial_actions(): void
    {
        $this->paidCod();$admin=$this->platform();$account=$this->receiving($admin);$cod=$this->obligation();$p=app(CommissionPayments::class);
        $p->prepareRemittance($this->admin,['submission_key'=>(string)Str::uuid(),'receiving_account_id'=>$account->id,'allocations'=>[$cod->id=>'100']]);
        $order=$this->gcash();$o=$this->obligation($order);
        $id=$p->submit($this->seller,$this->submitData($account,$o),$this->proof('preview Seller transfer'));$this->verifyReceipt($admin,$id);
        DB::transaction(fn()=>$this->ledger()->reverse($order->id,'preview:return',[0],'Synthetic partial refund'));
        foreach(['seller'=>$this->seller,'admin'=>$admin,'logistics'=>$this->admin] as $role=>$actor){
            $response=$this->actingAs($actor)->get('/commissions');$response->assertOk()->assertSee('Commission obligations');
            if(getenv('COMMISSION_UI_PREVIEW')==='1'){
                $dir=storage_path('app/private/commission-ui');if(!is_dir($dir))mkdir($dir,0700,true);
                file_put_contents($dir.'/'.$role.'.html',$response->getContent());
            }
        }
    }

    public function test_platform_subsidy_is_separately_funded_and_can_cover_a_low_cod_collection(): void
    {
        DB::table('orders')->where('id',$this->order->id)->update(['discount_amount'=>950,'total'=>150,'commerce_snapshot'=>json_encode(['vouchers'=>[['kind'=>'platform','funding_source'=>'platform','amount'=>950]]])]);
        DB::table('logistics_financial_transactions')->where('id',$this->collection->id)->update(['amount_cents'=>15000]);
        $this->order->refresh();$this->collection=DB::table('logistics_financial_transactions')->find($this->collection->id);$this->enableFixture();
        $this->remit('150','150','150');$h=$this->authorize('150');$this->treasury->release($this->staff,$h->id,'150');$this->treasury->receipt($this->admin,$h->id,$this->receiptData('150'),$this->proof('actual reduced COD deposit'));
        $this->invalid(fn()=>$this->flow->prepare($this->admin,$this->prepareData('0')));
        $this->treasury->adjustmentFunding($this->admin,['key'=>(string)Str::uuid(),'account_id'=>$this->account->id,'amount'=>'950','reference'=>'SUBSIDY001','received'=>1,'notes'=>'Actual platform-funded product subsidy received'],$this->proof('actual subsidy funds'));
        $this->flow->prepare($this->admin,$this->prepareData('950'));$this->verify();$this->pay('900');
        $this->assertSame(10000,$this->treasury->balance($this->account->id,'commission'));
        $this->assertSame(0,$this->treasury->balance($this->account->id,'adjustment'));
        $this->assertDatabaseHas('logistics_financial_transactions',['seller_payout_id'=>$this->payout()->id,'amount_cents'=>90000]);
    }
}
