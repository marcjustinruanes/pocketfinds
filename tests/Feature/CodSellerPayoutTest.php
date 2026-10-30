<?php

namespace Tests\Feature;

use App\Models\DeliveryAssignment;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CodSellerPayouts;
use App\Services\LogisticsCashDesk;
use App\Services\LogisticsShipping;
use App\Services\LogisticsTreasury;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class CodSellerPayoutTest extends TestCase
{
    use WorkflowSchema;
    protected User $seller;protected User $buyer;protected User $admin;protected User $staff;protected User $rider;
    protected LogisticsHub $hub;protected Order $order;protected Shipment $shipment;protected object $collection;
    protected LogisticsTreasury $treasury;protected CodSellerPayouts $flow;protected LogisticsCashDesk $desk;
    protected object $account;protected object $destination;

    protected function setUp(): void
    {
        parent::setUp();$this->createWorkflowSchema();
        foreach(['2026_10_14_000004_add_gcash_purchases','2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details',
            '2026_10_17_000001_add_buyer_commerce_features','2026_10_28_000001_add_logistics_financial_foundation','2026_10_28_000002_add_logistics_financial_transactions',
            '2026_10_29_000001_add_logistics_cash_desk','2026_10_29_000002_add_logistics_shipping_settlement','2026_10_29_000003_add_cod_seller_payouts'] as $file)(require database_path('migrations/'.$file.'.php'))->up();
        Storage::fake('local');
        $this->hub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Santa Cruz','province'=>'Laguna','is_operational'=>true]);
        $this->seller=$this->person('seller');$this->buyer=$this->person('buyer');$this->admin=$this->person('admin');$this->staff=$this->person('hub_staff');$this->rider=$this->person('rider');
        $this->treasury=app(LogisticsTreasury::class);$this->flow=app(CodSellerPayouts::class);$this->desk=app(LogisticsCashDesk::class);
        app(LogisticsShipping::class)->publish($this->admin,['region'=>'Test Region','revision'=>0,'first_kg'=>'100','additional_kg'=>'0']);
        $this->treasury->createAccount($this->admin,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Alpha Treasury','account_number'=>'09123456789']);
        $this->account=DB::table('logistics_treasury_accounts')->first();
        $this->treasury->destination($this->seller,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Seller Test','account_number'=>'09987654321']);
        $this->destination=DB::table('logistics_payout_destinations')->first();
        $this->order=Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'preparing','payment_method'=>'Cash on Delivery',
            'subtotal'=>'1000','shipping_amount'=>'100','discount_amount'=>'0','total'=>'1100','commerce_snapshot'=>['vouchers'=>[]],
            'items'=>[['name'=>'Parcel','qty'=>1,'price'=>1000,'weight_grams'=>1000]],'shipping_address'=>['region'=>'Test Region','municipality'=>'Santa Cruz','province'=>'Laguna']]);
        $this->shipment=Shipment::create(['order_id'=>$this->order->id,'logistics_company'=>'Alpha','shipping_status'=>'ready_for_pickup','origin_hub'=>'Santa Cruz','origin_province'=>'Laguna','destination_hub'=>'Santa Cruz','destination_province'=>'Laguna']);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'out_for_delivery','courier_id'=>$this->rider->id]);
        DB::table('orders')->where('id',$this->order->id)->update(['status'=>'out_for_delivery']);
        $assignment=DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'delivery','status'=>'assigned_to_rider']);
        DB::table('delivery_assignments')->where('id',$assignment->id)->update(['status'=>'out_for_delivery']);
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance',['from'=>'out_for_delivery','cod_amount'=>'1100','cod_confirmed'=>'1','collection_key'=>(string)Str::uuid()])->assertRedirect();
        DB::table('shipments')->where('id',$this->shipment->id)->update(['delivered_at'=>now()->subHours(169)]);
        $this->order->refresh();$this->shipment->refresh();
        $this->collection=DB::table('logistics_financial_transactions')->where('collection_order_id',$this->order->id)->first();
    }

    protected function person(string $role,string $company='Alpha',?int $hub=null):User
    {
        if($company!=='Alpha' && $hub===null)$hub=LogisticsHub::create(['company_name'=>$company,'municipality'=>'Other','province'=>'Other','is_operational'=>true])->id;
        return User::create(['username'=>(string)Str::uuid(),'account_type'=>in_array($role,['admin','hub_staff'])?'logistics':$role,'business_name'=>$company,
            'status'=>'approved','is_admin'=>false,'is_logistics'=>in_array($role,['admin','hub_staff']),'logistics_role'=>in_array($role,['admin','hub_staff'])?$role:null,
            'logistics_hub_id'=>$hub??$this->hub->id,'given_names'=>'Payout','last_name'=>'Test','municipality'=>'Santa Cruz','province'=>'Laguna','region'=>'Test Region']);
    }
    protected function proof(string $text):UploadedFile{return UploadedFile::fake()->createWithContent('evidence.pdf',"%PDF-1.4\n".$text);}
    protected function invalid(callable $fn):void{try{$fn();$this->fail('Expected validation failure.');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}}
    protected function remit(string $expected='1100',string $counted='1100',string $allocated='1100'):void
    {
        $h=$this->desk->submit($this->rider,['key'=>(string)Str::uuid(),'allocations'=>[$this->collection->id=>$expected]]);
        $this->desk->count($this->staff,$h->id,['counted'=>$counted,'allocations'=>[$this->collection->id=>$allocated],'notes'=>'Physical cash verified']);
    }
    protected function authorize(string $amount='1100'):object
    {
        $this->treasury->transfer($this->admin,['key'=>(string)Str::uuid(),'hub_id'=>$this->hub->id,'account_id'=>$this->account->id,'amount'=>$amount,'notes'=>'Authorized COD bank deposit']);
        return DB::table('logistics_cash_handovers')->where('kind','treasury')->orderByDesc('created_at')->first();
    }
    protected function receiptData(string $amount='1100',string $reference='DEPOSIT001'):array{return ['amount'=>$amount,'reference'=>$reference,'notes'=>'Actual company receipt verified','received'=>'1'];}
    protected function fund():void
    {
        $this->remit();$h=$this->authorize();$this->treasury->release($this->staff,$h->id,'1100');
        $this->treasury->receipt($this->admin,$h->id,$this->receiptData(),$this->proof('Treasury receipt'));
    }
    protected function prepareData(string $adjustment='0'):array{return ['order_id'=>$this->order->id,'account_id'=>$this->account->id,'destination_id'=>$this->destination->id,'adjustment'=>$adjustment,'adjustment_reason'=>'Approved separately funded addition'];}
    protected function payout():object{return DB::table('logistics_seller_payouts')->where('order_id',$this->order->id)->first();}
    protected function verify():void{$p=$this->payout();$this->flow->action($this->admin,$p->id,['revision'=>$p->revision,'action'=>'verify','notes'=>'Eligibility and destination reviewed']);}
    protected function payData(string $amount='1000'):array{return ['revision'=>$this->payout()->revision,'amount'=>$amount,'reference'=>'PAYOUT001','sent'=>'1','notes'=>'Confirmed actual transfer to seller account'];}
    protected function pay(string $amount='1000'):void{$this->flow->pay($this->admin,$this->payout()->id,$this->payData($amount),$this->proof('Actual seller transfer'));}

    public function test_successful_cod_payout_retains_shipping_and_acknowledgement_is_separate():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());
        $this->assertSame(10000,$this->treasury->available($this->account->id,'cod'));
        $this->assertSame(0,$this->desk->custody($this->hub->id));
        $this->verify();$this->pay();
        $this->assertSame(10000,$this->treasury->balance($this->account->id,'cod'));
        $this->assertDatabaseHas('logistics_financial_transactions',['seller_payout_id'=>$this->payout()->id,'amount_cents'=>100000,'recipient_id'=>$this->seller->id,'kind'=>'seller_payout']);
        $this->assertNull($this->payout()->acknowledged_at);
        $this->flow->acknowledge($this->seller,$this->payout()->id);$this->flow->acknowledge($this->seller,$this->payout()->id);
        $this->assertNotNull($this->payout()->acknowledged_at);$this->assertSame(10000,$this->treasury->balance($this->account->id,'cod'));
        $this->assertSame('delivered',$this->shipment->fresh()->shipping_status);
        $this->assertDatabaseHas('logistics_shipping_obligations',['order_id'=>$this->order->id,'status'=>'cod_retained']);
    }
    public function test_partial_remittance_and_unverified_cash_cannot_prepare_payout():void
    {
        $this->assertFalse($this->flow->eligibility($this->order,'Alpha')['eligible']);
        $this->remit('500','500','500');$this->invalid(fn()=>$this->flow->prepare($this->admin,$this->prepareData()));
        $this->assertDatabaseCount('logistics_seller_payouts',0);
    }
    public function test_hub_cash_and_cash_in_transit_are_not_treasury_funding():void
    {
        $this->remit();$this->invalid(fn()=>$this->flow->prepare($this->admin,$this->prepareData()));
        $h=$this->authorize();$this->treasury->release($this->staff,$h->id,'1100');
        $this->invalid(fn()=>$this->flow->prepare($this->admin,$this->prepareData()));
        $this->assertSame(0,$this->treasury->balance($this->account->id,'cod'));
    }
    public function test_only_qualified_cod_cash_can_be_released_and_overages_are_excluded():void
    {
        $this->remit('1100','1200','1100');$h=$this->authorize('1200');
        $this->invalid(fn()=>$this->treasury->release($this->staff,$h->id,'1200'));
        $this->assertSame(110000,$this->treasury->qualifiedHub($this->hub->id));
        $this->assertSame(120000,$this->desk->custody($this->hub->id));
    }
    public function test_receipt_shortage_or_overage_only_credits_verified_cod_portion():void
    {
        $this->remit();$h=$this->authorize();$this->treasury->release($this->staff,$h->id,'1100');
        $this->treasury->receipt($this->admin,$h->id,$this->receiptData('1200'),$this->proof('Extra treasury cash'));
        $this->assertSame(110000,$this->treasury->balance($this->account->id,'cod'));
        $this->assertDatabaseHas('logistics_cash_handovers',['id'=>$h->id,'status'=>'discrepancy','counted_cents'=>120000,'cod_received_cents'=>110000]);
        $this->assertSame(10000,$this->treasury->balance($this->account->id,'unallocated'));
    }
    public function test_before_timeout_auto_completion_is_not_buyer_confirmation():void
    {
        $this->remit();DB::table('shipments')->where('id',$this->shipment->id)->update(['delivered_at'=>now()->subHours(12)]);
        DB::table('orders')->where('id',$this->order->id)->update(['status'=>'completed']);
        $this->assertFalse($this->flow->eligibility($this->order->fresh(),'Alpha')['eligible']);
        DB::table('order_status_history')->insert(['id'=>(string)Str::uuid(),'order_id'=>$this->order->id,'status'=>'completed','changed_by'=>$this->buyer->id,'notes'=>'Buyer confirmed receipt.','created_at'=>now()]);
        $this->assertTrue($this->flow->eligibility($this->order->fresh(),'Alpha')['eligible']);
    }
    public function test_active_dispute_after_preparation_blocks_verification_and_payment():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());
        DB::table('complaints')->insert(['id'=>(string)Str::uuid(),'order_id'=>$this->order->id,'complainant_id'=>$this->buyer->id,'respondent_id'=>$this->seller->id,'status'=>'open']);
        $this->invalid(fn()=>$this->verify());
        DB::table('complaints')->update(['status'=>'resolved']);$this->verify();DB::table('complaints')->update(['status'=>'escalated']);
        $this->invalid(fn()=>$this->pay());$this->assertSame(110000,$this->treasury->balance($this->account->id,'cod'));
    }
    public function test_return_refund_and_rejected_return_appeal_window_prevent_payout():void
    {
        $this->fund();$id=(string)Str::uuid();
        DB::table('commerce_returns')->insert(['id'=>$id,'order_id'=>$this->order->id,'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'item_keys'=>'[0]','reason'=>'Damaged item','description'=>'Damaged goods received','photos'=>'[]','amount'=>1000,'refund_destination'=>'Bank','status'=>'pending']);
        foreach(['pending','approved','escalated','received','refunded'] as $status){DB::table('commerce_returns')->where('id',$id)->update(['status'=>$status]);$this->assertFalse($this->flow->eligibility($this->order,'Alpha')['eligible']);}
        DB::table('commerce_returns')->update(['status'=>'rejected','decided_at'=>now()->subDays(2)]);$this->assertFalse($this->flow->eligibility($this->order,'Alpha')['eligible']);
        DB::table('commerce_returns')->update(['decided_at'=>now()->subDays(4)]);$this->assertTrue($this->flow->eligibility($this->order,'Alpha')['eligible']);
    }
    public function test_positive_adjustments_require_separate_actual_verified_funding():void
    {
        $this->fund();$this->invalid(fn()=>$this->flow->prepare($this->admin,$this->prepareData('50')));
        $this->treasury->adjustmentFunding($this->admin,$this->receiptData('50','FUNDING001')+['account_id'=>$this->account->id,'key'=>(string)Str::uuid()],$this->proof('Additional company funds'));
        $this->flow->prepare($this->admin,$this->prepareData('50'));$this->verify();$this->pay('1050');
        $this->assertSame(0,$this->treasury->balance($this->account->id,'adjustment'));$this->assertSame(10000,$this->treasury->balance($this->account->id,'cod'));
    }
    public function test_duplicate_preparation_payment_and_reference_cannot_pay_twice():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());
        $this->actingAs($this->admin)->post('/logistics/finance/payouts/prepare',$this->prepareData())->assertConflict();
        $this->verify();$data=$this->payData();$this->pay();
        $this->actingAs($this->admin)->post('/logistics/finance/payouts/'.$this->payout()->id.'/pay',$data+['proof'=>$this->proof('Duplicate attempt')])->assertConflict();
        $this->assertSame(1,DB::table('logistics_financial_transactions')->where('kind','seller_payout')->count());
    }
    public function test_documented_holds_and_cancellation_do_not_deduct_and_release_reservations():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());$p=$this->payout();
        $this->flow->action($this->admin,$p->id,['revision'=>$p->revision,'action'=>'hold','notes'=>'Await beneficiary clarification']);$this->invalid(fn()=>$this->verify());
        $this->flow->action($this->admin,$p->id,['revision'=>$this->payout()->revision,'action'=>'cancel','notes'=>'No money sent; cancel instruction']);
        $this->assertSame(110000,$this->treasury->available($this->account->id,'cod'));
        $this->flow->prepare($this->admin,$this->prepareData());$this->assertDatabaseCount('logistics_seller_payouts',1);$this->verify();
    }
    public function test_seller_receiving_account_and_role_scopes_are_enforced():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());$this->verify();$this->pay();
        $e=DB::table('logistics_finance_evidence')->where('source_type','seller_payout')->first();
        $this->actingAs($this->seller)->get(route('finance.evidence',$e->id))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        foreach([$this->staff,$this->person('seller'),$this->person('admin','Other')] as $actor)$this->actingAs($actor)->get(route('finance.evidence',$e->id))->assertForbidden();
        $this->actingAs($this->staff)->get('/logistics/finance/payouts')->assertForbidden();
        $this->actingAs($this->seller)->get('/seller/payouts')->assertOk()->assertSee('Acknowledge receipt')
            ->assertSee('id="destination-channel"',false)->assertSee('name="account_number"',false)->assertDontSee('@include',false);
        $this->actingAs($this->admin)->get('/logistics/finance/payouts')->assertOk()->assertSee('Company treasury')
            ->assertSee('id="treasury-account-number"',false)->assertSee('id="funding-proof"',false)->assertDontSee('@include',false);
    }
    public function test_audit_failure_rolls_back_actual_payout_and_private_proof():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());$this->verify();
        $before=Storage::disk('local')->allFiles('logistics-finance-proofs');
        DB::unprepared("CREATE TRIGGER fail_payout BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'seller_payout_paid' BEGIN SELECT RAISE(ABORT, 'payout audit failed'); END");
        try{$this->pay();$this->fail('Expected audit failure.');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('payout audit failed',$e->getMessage());}
        $this->assertSame(110000,$this->treasury->balance($this->account->id,'cod'));$this->assertSame('verified',$this->payout()->status);
        $this->assertSame($before,Storage::disk('local')->allFiles('logistics-finance-proofs'));
    }

    public function test_exact_168_hour_boundary_and_actual_buyer_confirmation_are_supported():void
    {
        $this->remit();$fixed=\Carbon\CarbonImmutable::parse('2026-10-29 12:00:00','UTC');
        \Carbon\CarbonImmutable::setTestNow($fixed);
        try{
            DB::table('shipments')->where('id',$this->shipment->id)->update(['delivered_at'=>$fixed->subHours(168)->addSecond()]);
            $this->assertFalse($this->flow->eligibility($this->order,'Alpha')['eligible']);
            DB::table('shipments')->where('id',$this->shipment->id)->update(['delivered_at'=>$fixed->subHours(168)]);
            $this->assertTrue($this->flow->eligibility($this->order,'Alpha')['eligible']);
        }finally{\Carbon\CarbonImmutable::setTestNow();}
        DB::table('shipments')->where('id',$this->shipment->id)->update(['delivered_at'=>now()->subHour()]);
        $this->actingAs($this->buyer)->patch(route('buyer.orders.confirm-receipt',$this->order))->assertRedirect();
        $this->assertNotNull($this->order->fresh()->buyer_confirmed_at);
        $this->assertTrue($this->flow->eligibility($this->order->fresh(),'Alpha')['eligible']);
    }
    public function test_hub_transfer_preserves_explicit_verified_cod_qualification():void
    {
        $this->remit();$hub=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        $staff=$this->person('hub_staff','Alpha',$hub->id);
        $t=$this->desk->authorizeTransfer($this->admin,['key'=>(string)Str::uuid(),'hub_id'=>$this->hub->id,'destination_hub_id'=>$hub->id,'amount'=>'500','notes'=>'Verified COD transfer']);
        $this->desk->transfer($this->staff,$t->id,'dispatch',['counted'=>'500','cod_amount'=>'500','notes'=>'Released verified COD']);
        $this->assertSame(60000,$this->treasury->qualifiedHub($this->hub->id));
        $this->assertSame(0,$this->treasury->qualifiedHub($hub->id));
        $this->desk->transfer($staff,$t->id,'receive',['counted'=>'450','cod_amount'=>'450','notes'=>'Short by PHP 50 under investigation']);
        $this->assertSame(45000,$this->treasury->qualifiedHub($hub->id));
        $this->assertDatabaseHas('logistics_cash_handovers',['id'=>$t->id,'status'=>'discrepancy','cod_released_cents'=>50000,'cod_received_cents'=>45000]);
    }
    public function test_treasury_shortage_does_not_credit_missing_funds_or_auto_deduct_earnings():void
    {
        $this->remit();$h=$this->authorize();$this->treasury->release($this->staff,$h->id,'1100');
        $this->treasury->receipt($this->admin,$h->id,$this->receiptData('900'),$this->proof('Short treasury receipt'));
        $this->assertSame(90000,$this->treasury->balance($this->account->id,'cod'));
        $this->invalid(fn()=>$this->flow->prepare($this->admin,$this->prepareData()));
        $this->assertDatabaseHas('logistics_compensations',['rider_id'=>$this->rider->id,'amount_cents'=>4000,'status'=>'pending']);
    }
    public function test_negative_adjustments_and_missing_reasons_or_wrong_destinations_are_rejected():void
    {
        $this->fund();$this->invalid(fn()=>$this->flow->prepare($this->admin,$this->prepareData('-1')));
        $this->invalid(fn()=>$this->flow->prepare($this->admin,array_replace($this->prepareData('1'),['adjustment_reason'=>''])));
        $other=$this->person('seller');$this->treasury->destination($other,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Other Seller','account_number'=>'09011112222']);
        $dest=DB::table('logistics_payout_destinations')->where('seller_id',$other->id)->first();
        $this->actingAs($this->admin)->post('/logistics/finance/payouts/prepare',array_replace($this->prepareData(),['destination_id'=>$dest->id]))->assertNotFound();
        $this->assertDatabaseCount('logistics_seller_payouts',0);
    }
    public function test_payout_requires_verification_exact_amount_and_actual_transfer_confirmation():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());
        $this->actingAs($this->admin)->post('/logistics/finance/payouts/'.$this->payout()->id.'/pay',$this->payData()+['proof'=>$this->proof('Premature payout')])->assertConflict();
        $this->verify();$this->invalid(fn()=>$this->pay('999'));$this->invalid(fn()=>$this->pay('1001'));
        $this->invalid(fn()=>$this->flow->pay($this->admin,$this->payout()->id,array_replace($this->payData(),['sent'=>'0']),$this->proof('No actual transfer')));
        $this->assertSame(110000,$this->treasury->balance($this->account->id,'cod'));
    }
    public function test_reference_reuse_and_unauthorized_treasury_receipt_cannot_create_funds():void
    {
        $this->remit();$h=$this->authorize();
        $this->actingAs($this->admin)->post('/logistics/cash-desk/treasury/'.$h->id.'/release',['amount'=>'1100'])->assertForbidden();
        $this->treasury->release($this->staff,$h->id,'1100');
        $this->actingAs($this->staff)->post('/logistics/finance/treasury/'.$h->id.'/receipt',$this->receiptData()+['proof'=>$this->proof('Wrong receiver')])->assertForbidden();
        $this->treasury->receipt($this->admin,$h->id,$this->receiptData(),$this->proof('Verified first receipt'));
        $this->actingAs($this->admin)->post('/logistics/finance/treasury/funding',$this->receiptData('100')+['key'=>(string)Str::uuid(),'account_id'=>$this->account->id,'proof'=>$this->proof('Reused bank deposit')])->assertSessionHasErrors('reference');
        $this->assertSame(0,$this->treasury->balance($this->account->id,'adjustment'));
    }
    public function test_receipt_audit_failure_keeps_cash_in_transit_and_creates_no_treasury_credit():void
    {
        $this->remit();$h=$this->authorize();$this->treasury->release($this->staff,$h->id,'1100');
        DB::unprepared("CREATE TRIGGER fail_receipt BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'treasury_receipt_verified' BEGIN SELECT RAISE(ABORT, 'receipt audit failed'); END");
        try{$this->treasury->receipt($this->admin,$h->id,$this->receiptData(),$this->proof('Rollback receipt'));$this->fail('Expected audit failure.');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('receipt audit failed',$e->getMessage());}
        $this->assertSame(0,$this->treasury->balance($this->account->id,'cod'));
        $this->assertDatabaseHas('logistics_cash_handovers',['id'=>$h->id,'status'=>'in_transit','counted_at'=>null]);
        $this->assertSame([],Storage::disk('local')->allFiles('logistics-finance-proofs'));
    }
    public function test_acknowledgement_cannot_be_forged_or_create_a_payment():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());
        $this->actingAs($this->seller)->post('/seller/payouts/'.$this->payout()->id.'/acknowledge',['received'=>'1'])->assertStatus(422);
        $this->verify();$this->pay();
        $this->actingAs($this->person('seller'))->post('/seller/payouts/'.$this->payout()->id.'/acknowledge',['received'=>'1'])->assertNotFound();
        $this->actingAs($this->seller)->post('/seller/payouts/'.$this->payout()->id.'/acknowledge',['received'=>'0'])->assertSessionHasErrors('received');
        $this->assertNull($this->payout()->acknowledged_at);
        $this->assertSame(1,DB::table('logistics_financial_transactions')->where('kind','seller_payout')->count());
    }
    public function test_insufficient_cod_to_cover_shipping_is_held_without_negative_proceeds():void
    {
        // A fully subsidized product with a shipping voucher can collect less than gross shipping.
        DB::table('orders')->where('id',$this->order->id)->update(['subtotal'=>0,'discount_amount'=>50,'total'=>50]);
        DB::table('logistics_financial_transactions')->where('id',$this->collection->id)->update(['amount_cents'=>5000]);
        $this->remit('50','50','50');$e=$this->flow->eligibility($this->order->fresh(),'Alpha');
        $this->assertFalse($e['eligible']);$this->assertStringContainsString('cannot cover',$e['reason']);$this->assertSame(0,$e['product_cents']);
    }
    public function test_existing_reservation_prevents_a_second_order_from_spending_the_same_funds():void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());
        $second=$this->order->replicate();$second->status='preparing';$second->save();
        $shipment=Shipment::create(['order_id'=>$second->id,'logistics_company'=>'Alpha','shipping_status'=>'ready_for_pickup','origin_hub'=>'Santa Cruz','origin_province'=>'Laguna','destination_hub'=>'Santa Cruz','destination_province'=>'Laguna']);
        DB::table('orders')->where('id',$second->id)->update(['status'=>'delivered']);
        DB::table('shipments')->where('id',$shipment->id)->update(['shipping_status'=>'delivered','delivered_at'=>now()->subHours(169)]);
        $row=(array)$this->collection;$row['id']=(string)Str::uuid();$row['order_id']=$row['collection_order_id']=$second->id;$row['shipment_id']=$row['collection_shipment_id']=$shipment->id;$row['idempotency_key']=(string)Str::uuid();
        DB::table('logistics_financial_transactions')->insert($row);
        $h=$this->desk->submit($this->rider,['key'=>(string)Str::uuid(),'allocations'=>[$row['id']=>'1100']]);
        $this->desk->count($this->staff,$h->id,['counted'=>'1100','allocations'=>[$row['id']=>'1100'],'notes'=>'Second order COD verified']);
        $this->invalid(fn()=>$this->flow->prepare($this->admin,array_replace($this->prepareData(),['order_id'=>$second->id])));
        $this->assertSame(10000,$this->treasury->available($this->account->id,'cod'));
        $this->assertDatabaseCount('logistics_seller_payouts',1);
    }
}
