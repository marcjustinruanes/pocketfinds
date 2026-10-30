<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\LogisticsShipping;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class LogisticsShippingTest extends TestCase
{
    use WorkflowSchema;
    protected User $seller;
    protected User $buyer;
    protected User $admin;
    protected User $staff;
    protected LogisticsHub $hub;
    protected LogisticsShipping $shipping;
    protected Shipment $legacy;

    protected function setUp(): void
    {
        parent::setUp(); $this->createWorkflowSchema();
        foreach (['2026_10_14_000004_add_gcash_purchases', '2026_10_16_000001_add_registration_business_details', '2026_10_16_000002_add_logistics_registration_details',
            '2026_10_17_000001_add_buyer_commerce_features', '2026_10_28_000001_add_logistics_financial_foundation',
            '2026_10_28_000002_add_logistics_financial_transactions'] as $file) (require database_path('migrations/'.$file.'.php'))->up();
        Storage::fake('local');
        $this->hub = LogisticsHub::create(['company_name' => 'Alpha', 'municipality' => 'Santa Cruz', 'province' => 'Laguna', 'is_operational' => true]);
        $this->seller = $this->person('seller'); $this->buyer = $this->person('buyer');
        $this->admin = $this->person('admin'); $this->staff = $this->person('hub_staff');
        $this->legacy = $this->parcel($this->order());
        (require database_path('migrations/2026_10_29_000002_add_logistics_shipping_settlement.php'))->up();
        $this->shipping = app(LogisticsShipping::class);
        $this->shipping->publish($this->admin, ['region' => 'Test Region', 'revision' => 0, 'first_kg' => '40', 'additional_kg' => '20']);
        $this->shipping->configure($this->admin, ['revision' => 0, 'account_name' => 'Alpha Logistics', 'account_number' => '09123456789']);
    }

    protected function person(string $role, string $company = 'Alpha'): User
    {
        return User::create(['username' => (string) Str::uuid(), 'account_type' => in_array($role, ['admin','hub_staff']) ? 'logistics' : $role,
            'business_name' => $company, 'status' => 'approved', 'is_admin' => false, 'is_logistics' => in_array($role, ['admin','hub_staff']),
            'logistics_role' => in_array($role, ['admin','hub_staff']) ? $role : null, 'logistics_hub_id' => $this->hub->id,
            'given_names' => 'Shipping', 'last_name' => 'Test', 'municipality' => 'Santa Cruz', 'province' => 'Laguna', 'region' => 'Test Region']);
    }

    protected function order(array $override = []): Order
    {
        return Order::create(array_replace(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'payment_method' => 'GCash',
            'status' => 'preparing', 'subtotal' => '1000.00', 'shipping_amount' => '60.00', 'discount_amount' => '0.00', 'total' => '1060.00',
            'items' => [['name' => 'Parcel', 'qty' => 1, 'weight_grams' => 1200, 'price' => 1000]],
            'shipping_address' => ['region' => 'Test Region', 'municipality' => 'Santa Cruz', 'province' => 'Laguna'], 'commerce_snapshot' => ['vouchers' => []]], $override));
    }

    protected function parcel(Order $order): Shipment
    {
        return Shipment::create(['order_id' => $order->id, 'logistics_company' => 'Alpha', 'shipping_status' => 'ready_for_pickup',
            'tracking_number' => 'TEST-'.Str::random(12), 'origin_hub' => 'Santa Cruz', 'origin_province' => 'Laguna', 'destination_hub' => 'Santa Cruz', 'destination_province' => 'Laguna']);
    }

    protected function obligation(?Order $order = null): object
    {
        $order ??= $this->order(); $parcel = $this->parcel($order); $order->update(['status' => 'ready_for_pickup']);
        return DB::table('logistics_shipping_obligations')->where('shipment_id', $parcel->id)->first();
    }

    protected function proof(string $content = 'Shipping proof'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n".$content);
    }

    protected function submission(object $ob, string $ref = 'SHIPREF001'): array
    {
        return ['key' => (string) Str::uuid(), 'reference' => $ref, 'amount' => '60.00', 'revision' => $ob->revision];
    }

    protected function submit(object $ob, string $ref = 'SHIPREF001'): object
    {
        $this->shipping->submit($this->seller, $ob->id, $this->submission($ob,$ref), $this->proof($ref));
        return DB::table('logistics_shipping_payments')->where('obligation_id', $ob->id)->orderByDesc('created_at')->first();
    }

    protected function reviewData(object $ob, object $payment): array
    {
        return ['payment_id' => $payment->id, 'revision' => DB::table('logistics_shipping_obligations')->where('id',$ob->id)->value('revision'),
            'accept' => '1', 'amount' => '60.00', 'reference' => $payment->reference, 'notes' => 'Confirmed receipt in company GCash account'];
    }

    protected function invalid(callable $fn): void
    {
        try { $fn(); $this->fail('Expected validation failure.'); } catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }

    public function test_matching_rate_creates_exact_snapshotted_obligation_and_no_cash(): void
    {
        $order = $this->order(); $ob = $this->obligation($order);
        $this->assertSame(6000,(int)$ob->amount_cents); $this->assertSame(2,(int)$ob->chargeable_kg);
        $this->assertSame('pending',$ob->status); $this->assertSame('1060.00',number_format($order->fresh()->total,2,'.',''));
        $this->assertDatabaseCount('logistics_financial_transactions',0);
        $this->assertSame(1,(int)Shipment::find($ob->shipment_id)->shipping_finance_version);
    }

    public function test_missing_mismatched_or_weightless_rates_rollback_new_shipments(): void
    {
        $this->invalid(fn () => $this->parcel($this->order(['shipping_amount'=>'61.00'])));
        $this->invalid(fn () => $this->parcel($this->order(['shipping_address'=>['region'=>'Unknown','municipality'=>'Santa Cruz','province'=>'Laguna']])));
        $this->invalid(fn () => $this->parcel($this->order(['items'=>[['qty'=>1,'name'=>'Unknown weight']]])));
        $this->assertDatabaseCount('shipments',1); $this->assertDatabaseCount('logistics_shipping_obligations',0);
    }

    public function test_seller_handoff_mismatch_preserves_preparing_status_and_buyer_total(): void
    {
        $order=$this->order(['shipping_amount'=>'55.00','total'=>'1055.00']);
        $this->actingAs($this->seller)->patch('/seller/orders/'.$order->id.'/ready',['logistics_company'=>'Alpha'])->assertSessionHasErrors('shipping');
        $this->assertSame('preparing',$order->fresh()->status); $this->assertSame('1055.00',number_format($order->fresh()->total,2,'.',''));
        $this->assertDatabaseCount('shipments',1);
    }

    public function test_new_gcash_pickup_requires_verified_shipping_and_cannot_be_bypassed_by_model(): void
    {
        $ob=$this->obligation(); $shipment=Shipment::find($ob->shipment_id);
        $this->invalid(fn () => $this->shipping->gate($shipment));
        $this->invalid(fn () => $shipment->update(['pickup_approved_at'=>now()]));
        $this->actingAs($this->admin)->patch('/logistics/requests/'.$shipment->id.'/approve')->assertSessionHasErrors('shipping');
        $this->assertNull($shipment->fresh()->pickup_approved_at);
        $p=$this->submit($ob); $this->invalid(fn () => $this->shipping->gate($shipment));
        $this->shipping->review($this->admin,$ob->id,$this->reviewData($ob,$p));
        $this->actingAs($this->admin)->patch('/logistics/requests/'.$shipment->id.'/approve')->assertRedirect();
        $this->assertNotNull($shipment->fresh()->pickup_approved_at);
        $this->assertSame('ready_for_pickup',$shipment->fresh()->shipping_status);
    }

    public function test_reference_proof_and_exact_amount_submission_are_private_and_idempotent(): void
    {
        $ob=$this->obligation(); $data=$this->submission($ob); $proof=$this->proof();
        $this->shipping->submit($this->seller,$ob->id,$data,$proof); $this->shipping->submit($this->seller,$ob->id,$data,$proof);
        $this->assertDatabaseCount('logistics_shipping_payments',1); $this->assertDatabaseCount('logistics_financial_transactions',0);
        $p=DB::table('logistics_shipping_payments')->first(); Storage::disk('local')->assertExists($p->proof_path);
        $this->actingAs($this->seller)->get(route('shipping.proof',$p->id))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->actingAs($this->staff)->get(route('shipping.proof',$p->id))->assertForbidden();
        $other=$this->person('seller'); $this->actingAs($other)->get(route('shipping.proof',$p->id))->assertForbidden();
    }

    public function test_partial_overpaid_invalid_reference_and_unsupported_file_are_rejected(): void
    {
        $ob=$this->obligation();
        foreach (['59.99','60.01','1e2','-1'] as $amount) $this->invalid(fn () => $this->shipping->submit($this->seller,$ob->id,array_replace($this->submission($ob),['amount'=>$amount]),$this->proof()));
        $this->invalid(fn () => $this->shipping->submit($this->seller,$ob->id,$this->submission($ob,'bad!'),$this->proof()));
        $this->invalid(fn () => $this->shipping->submit($this->seller,$ob->id,$this->submission($ob),UploadedFile::fake()->create('bad.html',1,'text/html')));
        $this->assertDatabaseCount('logistics_shipping_payments',0);
    }

    public function test_only_company_admin_can_verify_actual_received_amount_and_reference(): void
    {
        $ob=$this->obligation(); $p=$this->submit($ob); $data=$this->reviewData($ob,$p);
        $this->actingAs($this->staff)->post('/logistics/finance/shipping/'.$ob->id.'/review',$data)->assertForbidden();
        $this->invalid(fn () => $this->shipping->review($this->admin,$ob->id,array_replace($data,['amount'=>'59'])));
        $this->invalid(fn () => $this->shipping->review($this->admin,$ob->id,array_replace($data,['reference'=>'OTHERREF'])));
        $this->shipping->review($this->admin,$ob->id,$data);
        $this->assertDatabaseHas('logistics_financial_transactions',['shipping_obligation_id'=>$ob->id,'kind'=>'gcash_shipping','amount_cents'=>6000,'verification_state'=>'verified']);
        $this->actingAs($this->admin)->post('/logistics/finance/shipping/'.$ob->id.'/review',$data)->assertConflict();
        $this->assertDatabaseCount('logistics_financial_transactions',1);
    }

    public function test_rejection_preserves_attempt_and_allows_a_new_reference_and_proof(): void
    {
        $ob=$this->obligation(); $p=$this->submit($ob);
        $this->shipping->review($this->admin,$ob->id,array_replace($this->reviewData($ob,$p),['accept'=>'0','amount'=>null,'reference'=>null,'notes'=>'No matching received transfer']));
        $fresh=DB::table('logistics_shipping_obligations')->where('id',$ob->id)->first();
        $this->submit($fresh,'SHIPREF002');
        $this->assertDatabaseCount('logistics_shipping_payments',2); $this->assertDatabaseCount('logistics_financial_transactions',0);
        $this->assertDatabaseHas('logistics_shipping_payments',['id'=>$p->id,'status'=>'rejected']);
    }

    public function test_shipping_vouchers_keep_full_entitlement_and_unverified_platform_claim(): void
    {
        $order=$this->order(['discount_amount'=>'30','total'=>'1030','commerce_snapshot'=>['vouchers'=>[
            ['id'=>1,'code'=>'SELLSHIP','kind'=>'shipping','amount'=>10,'funding_source'=>'seller'],
            ['id'=>2,'code'=>'PLATSHIP','kind'=>'shipping','amount'=>20,'funding_source'=>'platform'],
        ]]]);
        $ob=$this->obligation($order); $p=$this->submit($ob); $this->shipping->review($this->admin,$ob->id,$this->reviewData($ob,$p));
        $this->assertDatabaseHas('logistics_shipping_obligations',['id'=>$ob->id,'amount_cents'=>6000,'platform_claim_cents'=>2000,'claim_status'=>'unverified','status'=>'verified']);
        $this->assertSame(2,count(json_decode($ob->voucher_funding,true)));
        $this->assertSame('1030.00',number_format($order->fresh()->total,2,'.',''));
    }

    public function test_rate_and_account_updates_do_not_change_existing_obligations(): void
    {
        $ob=$this->obligation();
        $this->shipping->publish($this->admin,['region'=>'Test Region','revision'=>1,'first_kg'=>'50','additional_kg'=>'20']);
        $this->shipping->configure($this->admin,['revision'=>1,'account_name'=>'New authorized account','account_number'=>'09987654321']);
        $same=DB::table('logistics_shipping_obligations')->where('id',$ob->id)->first();
        $this->assertSame($ob->rate_id,$same->rate_id); $this->assertSame($ob->instructions,$same->instructions);
        $this->assertDatabaseHas('logistics_shipping_rates',['id'=>$ob->rate_id,'first_kg_cents'=>4000,'is_active'=>false]);
        $this->invalid(fn () => $this->obligation());
        $p=$this->submit($ob); $this->shipping->review($this->admin,$ob->id,$this->reviewData($ob,$p));
        $this->shipping->gate(Shipment::find($ob->shipment_id));
    }

    public function test_cod_and_historical_shipments_do_not_require_gcash_prepayment(): void
    {
        $ob=$this->obligation($this->order(['payment_method'=>'Cash on Delivery']));
        $this->shipping->gate(Shipment::find($ob->shipment_id));
        $this->shipping->gate($this->legacy->fresh());
        $this->assertSame(0,(int)$this->legacy->fresh()->shipping_finance_version);
        $this->assertSame('cod',$ob->collection_mode); $this->assertSame('unsettled',$ob->status);
        $this->assertDatabaseCount('logistics_financial_transactions',0);
    }

    public function test_preflight_blocks_only_when_all_eligible_carrier_rates_are_known_and_mismatch(): void
    {
        $quote=['items'=>[['qty'=>1,'weight_grams'=>1200]],'shipping_amount'=>'60'];
        $address=['region'=>'Test Region','municipality'=>'Santa Cruz','province'=>'Laguna'];
        $this->assertNull($this->shipping->preflight($this->seller,$address,$quote,false));
        $this->invalid(fn () => $this->shipping->preflight($this->seller,$address,array_replace($quote,['shipping_amount'=>'70']),false));
        LogisticsHub::create(['company_name'=>'Beta','municipality'=>'Santa Cruz','province'=>'Laguna','is_operational'=>true]);
        $this->assertStringContainsString('not fully approved',$this->shipping->preflight($this->seller,$address,array_replace($quote,['shipping_amount'=>'70']),false));
    }

    public function test_pages_render_with_company_and_seller_scope(): void
    {
        $ob=$this->obligation(); $this->submit($ob);
        $this->actingAs($this->seller)->get('/seller/shipping')->assertOk()->assertSee('Your shipping obligations');
        $this->actingAs($this->seller)->get('/seller/shipping/'.$ob->id)->assertOk()->assertSee('Payment history');
        $this->actingAs($this->admin)->get('/logistics/finance')->assertOk()->assertSee('Approve a new regional rate');
        $this->actingAs($this->admin)->get('/logistics/finance/shipping/'.$ob->id)->assertOk()->assertSee('Verify received payment');
        $this->actingAs($this->staff)->get('/logistics/finance/shipping/'.$ob->id)->assertForbidden();
        $this->actingAs($this->person('seller'))->get('/seller/shipping/'.$ob->id)->assertForbidden();
    }

    public function test_reference_cannot_be_reused_between_buyer_and_shipping_payments(): void
    {
        DB::transaction(fn () => $this->shipping->reserveReference('BUYREF001','buyer_gcash',(string)Str::uuid()));
        $ob=$this->obligation(); $this->invalid(fn () => $this->submit($ob,'BUYREF001'));
        $p=$this->submit($ob,'SHIPREF001');
        $this->invalid(fn () => DB::transaction(fn () => $this->shipping->reserveReference('shipref001','buyer_gcash',(string)Str::uuid())));
        $this->assertDatabaseCount('logistics_shipping_payments',1);
    }

    public function test_audit_failure_rolls_back_payment_and_removes_private_uploaded_proof(): void
    {
        $ob=$this->obligation();
        DB::unprepared("CREATE TRIGGER fail_shipping_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'shipping_payment_submitted' BEGIN SELECT RAISE(ABORT, 'shipping audit failed'); END");
        try { $this->submit($ob); $this->fail('Expected audit failure.'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('shipping audit failed',$e->getMessage()); }
        $this->assertDatabaseCount('logistics_shipping_payments',0); $this->assertDatabaseCount('logistics_payment_references',0);
        $this->assertSame([],Storage::disk('local')->allFiles('logistics-shipping-proofs'));
        $this->assertDatabaseHas('logistics_shipping_obligations',['id'=>$ob->id,'status'=>'pending','revision'=>1]);
    }

    public function test_real_commerce_gcash_payment_handoff_and_shipping_verification_work_together(): void
    {
        $this->seller->update(['shipping_fee'=>60]);
        $this->buyer->update(['postal_code'=>'4009','barangay'=>'B','street'=>'Main Street','contact_no'=>'09123456789']);
        $product=\App\Models\Product::create(['seller_id'=>$this->seller->id,'name'=>'Parcel','status'=>'active','stock'=>5,'price'=>1000,'weight_grams'=>1200]);
        $cart=\App\Models\CartItem::create(['buyer_id'=>$this->buyer->id,'product_id'=>$product->id,'seller_slug'=>$this->seller->username,'name'=>'Parcel','price'=>1000,'qty'=>1]);
        $method=\App\Models\PaymentMethod::create(['name'=>'GCash','type'=>'ewallet','is_active'=>true]);
        DB::table('commerce_vouchers')->insert(['code'=>'PLATSHIP','kind'=>'shipping','seller_id'=>null,'discount_type'=>'fixed','amount'=>20,'starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'usage_limit'=>10,'per_buyer_limit'=>1]);
        $quote=DB::transaction(fn()=>app(\App\Services\BuyerCommerce::class)->quote($this->buyer,[$cart->id],null,['PLATSHIP']));
        $this->assertSame('platform',$quote['orders'][$this->seller->id]['vouchers'][0]['funding_source']);
        $flow=app(\App\Services\GcashWorkflow::class);
        $p=$flow->create($this->buyer,[$cart->id],$method->id,(string)Str::uuid(),null,null,null,$quote)[0]->fresh();
        $p=$flow->instructions($this->seller,$p->id,$p->revision,'Seller Shop','09123456789');
        $file=$this->proof('Buyer original payment');
        $p=DB::transaction(function()use($flow,$p,$file){$path=app(\App\Services\MediaLifecycle::class)->store($file,'gcash-proofs','local');return $flow->proof($this->buyer,$p->id,(string)Str::uuid(),$path,'application/pdf',$file->getSize(),hash_file('sha256',$file->getRealPath()));});
        $p=$flow->decide($this->seller,$p->id,$p->revision,true,'1040.00',null,'BUYERREF001');
        $order=$flow->place($this->buyer,$p->id,$p->revision); $order->update(['status'=>'preparing']);
        $this->actingAs($this->seller)->patch('/seller/orders/'.$order->id.'/ready',['logistics_company'=>'Alpha'])->assertSessionHasNoErrors()->assertRedirect();
        $ob=DB::table('logistics_shipping_obligations')->where('order_id',$order->id)->first();
        $this->assertSame(6000,(int)$ob->amount_cents); $this->assertSame(2000,(int)$ob->platform_claim_cents);
        $this->invalid(fn()=>$this->submit($ob,'BUYERREF001'));
        $payment=$this->submit($ob); $this->shipping->review($this->admin,$ob->id,$this->reviewData($ob,$payment));
        $this->assertSame('1040.00',number_format($order->fresh()->total,2,'.',''));
        $this->assertDatabaseHas('logistics_payment_references',['reference'=>'BUYERREF001','subject_type'=>'buyer_gcash']);
        $this->assertDatabaseCount('logistics_financial_transactions',1);
    }

    public function test_verified_obligation_cannot_have_its_order_or_route_repriced_or_downgraded(): void
    {
        $ob=$this->obligation();
        $this->invalid(fn()=>Order::find($ob->order_id)->update(['shipping_amount'=>'1.00']));
        $this->invalid(fn()=>Order::find($ob->order_id)->update(['payment_method'=>'Cash on Delivery']));
        $this->invalid(fn()=>Shipment::find($ob->shipment_id)->forceFill(['shipping_finance_version'=>0])->save());
        $this->invalid(fn()=>Shipment::find($ob->shipment_id)->update(['destination_hub'=>'Other Town']));
    }

    public function test_explicitly_approved_zero_shipping_rate_does_not_invent_a_payment(): void
    {
        $this->shipping->publish($this->admin,['region'=>'Test Region','revision'=>1,'first_kg'=>'0','additional_kg'=>'0']);
        $ob=$this->obligation($this->order(['shipping_amount'=>'0','total'=>'1000']));
        $this->shipping->gate(Shipment::find($ob->shipment_id));
        $this->assertSame('not_required',$ob->status); $this->assertDatabaseCount('logistics_financial_transactions',0);
    }

    public function test_missing_receiving_account_and_unidentifiable_voucher_funding_hold_handoff(): void
    {
        $this->invalid(fn()=>$this->obligation($this->order(['commerce_snapshot'=>['vouchers'=>[['id'=>999,'kind'=>'shipping','code'=>'UNKNOWN','amount'=>20]]]])));
        DB::table('logistics_shipping_accounts')->delete();
        $this->invalid(fn()=>$this->obligation());
        $this->assertDatabaseCount('logistics_shipping_obligations',0);
    }

    public function test_verified_receipt_and_reference_cannot_settle_a_second_obligation(): void
    {
        $ob=$this->obligation(); $p=$this->submit($ob); $data=$this->reviewData($ob,$p);
        $this->shipping->review($this->admin,$ob->id,$data);
        $second=$this->obligation(); $this->invalid(fn()=>$this->submit($second));
        $this->actingAs($this->seller)->post('/seller/shipping/'.$second->id.'/payment',$this->submission($second,'NEWREF001')+['proof'=>$this->proof('SHIPREF001')])->assertConflict();
        $this->assertDatabaseCount('logistics_shipping_payments',1); $this->assertDatabaseCount('logistics_financial_transactions',1);
    }

    public function test_verification_audit_failure_rolls_back_settlement_and_does_not_clear_pickup_gate(): void
    {
        $ob=$this->obligation(); $p=$this->submit($ob);
        DB::unprepared("CREATE TRIGGER fail_review_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'shipping_payment_verified' BEGIN SELECT RAISE(ABORT, 'verification audit failed'); END");
        try {$this->shipping->review($this->admin,$ob->id,$this->reviewData($ob,$p));$this->fail('Expected audit failure.');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('verification audit failed',$e->getMessage());}
        $this->assertDatabaseCount('logistics_financial_transactions',0);
        $this->assertDatabaseHas('logistics_shipping_payments',['id'=>$p->id,'status'=>'submitted']);
        $this->invalid(fn()=>$this->shipping->gate(Shipment::find($ob->shipment_id)));
    }

    public function test_foreign_company_buyer_and_platform_admin_cannot_review_or_read_proof(): void
    {
        $ob=$this->obligation();$p=$this->submit($ob);$data=$this->reviewData($ob,$p);
        $outsider=$this->person('admin','Other Company');
        $platform=$this->person('buyer');$platform->forceFill(['is_admin'=>true])->save();
        foreach([$outsider,$platform,$this->buyer] as $actor){
            $this->actingAs($actor)->get(route('shipping.proof',$p->id))->assertForbidden();
        }
        $this->actingAs($outsider)->post('/logistics/finance/shipping/'.$ob->id.'/review',$data)->assertForbidden();
        $this->actingAs($this->seller)->post('/logistics/finance/shipping/'.$ob->id.'/review',$data)->assertRedirect();
        $this->assertDatabaseCount('logistics_financial_transactions',0);
    }

    public function test_cancelled_shipment_cannot_be_settled_and_stale_rate_settings_conflict(): void
    {
        $ob=$this->obligation();$p=$this->submit($ob);
        DB::table('shipments')->where('id',$ob->shipment_id)->update(['shipping_status'=>'cancelled']);
        DB::table('orders')->where('id',$ob->order_id)->update(['status'=>'cancelled']);
        $this->invalid(fn()=>$this->shipping->review($this->admin,$ob->id,$this->reviewData($ob,$p)));
        $this->actingAs($this->admin)->post('/logistics/finance/shipping-rate',['region'=>'Test Region','revision'=>0,'first_kg'=>'40','additional_kg'=>'20'])->assertConflict();
        $this->post('/logistics/finance/shipping-account',['revision'=>0,'account_name'=>'Stale Account','account_number'=>'09123456789'])->assertConflict();
        $this->assertDatabaseCount('logistics_financial_transactions',0);
    }
}
