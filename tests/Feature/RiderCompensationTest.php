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

class RiderCompensationTest extends TestCase
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
            '2026_10_29_000001_add_logistics_cash_desk','2026_10_29_000002_add_logistics_shipping_settlement','2026_10_29_000003_add_cod_seller_payouts','2026_10_29_000004_add_rider_compensation_payouts'] as $file)(require database_path('migrations/'.$file.'.php'))->up();
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


    protected function riderFlow(): \App\Services\RiderCompensation { return app(\App\Services\RiderCompensation::class); }
    protected function earning(): object { return DB::table('logistics_compensations')->where('leg','delivery')->first(); }
    protected function riderDestination(): object
    {
        $this->riderFlow()->destination($this->rider,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Rider Test','account_number'=>'09554433221']);
        return DB::table('logistics_rider_destinations')->where('rider_id',$this->rider->id)->first();
    }
    protected function payroll(string $amount='100',string $reference='PAYROLL001'): void
    {
        $this->riderFlow()->funding($this->admin,$this->receiptData($amount,$reference)+['key'=>(string)Str::uuid(),'account_id'=>$this->account->id],$this->proof($reference));
    }
    protected function riderPrepareData(): array
    {
        $d=DB::table('logistics_rider_destinations')->where('rider_id',$this->rider->id)->first()??$this->riderDestination();
        return ['compensation_id'=>$this->earning()->id,'account_id'=>$this->account->id,'destination_id'=>$d->id];
    }
    protected function riderPayout(): object { return DB::table('logistics_rider_payouts')->first(); }
    protected function riderVerify(): void
    {
        $p=$this->riderPayout();$this->riderFlow()->action($this->admin,$p->id,['revision'=>$p->revision,'action'=>'verify','notes'=>'Verified payroll and receiving instructions']);
    }
    protected function riderPayData(): array { return ['revision'=>$this->riderPayout()->revision,'amount'=>'40','reference'=>'RIDERSENT001','sent'=>'1','notes'=>'Actual rider transfer confirmed']; }
    protected function riderPay(): void { $this->riderFlow()->pay($this->admin,$this->riderPayout()->id,$this->riderPayData(),$this->proof('Actual rider payment')); }

    public function test_delivery_earns_exact_snapshot_without_waiting_for_cash_remittance(): void
    {
        $c=$this->earning();$this->assertSame('earned',$c->status);$this->assertSame(4000,(int)$c->amount_cents);
        $this->assertSame(0,$this->desk->balances($this->collection)['verified']);
        $this->assertFalse($this->riderFlow()->recognize($c->id,$this->admin));
        $this->assertSame(1,DB::table('logistics_financial_events')->where('event','rider_compensation_earned')->count());
        $this->assertDatabaseCount('logistics_rider_payouts',0);
    }
    public function test_rider_payout_and_acknowledgement_leave_cod_shipping_and_seller_funds_untouched(): void
    {
        $this->payroll();$this->riderFlow()->prepare($this->admin,$this->riderPrepareData());
        $this->assertSame(6000,$this->riderFlow()->available($this->account->id));$this->riderVerify();$this->riderPay();
        $this->assertSame(6000,$this->treasury->balance($this->account->id,'rider_payroll'));
        $this->assertDatabaseHas('logistics_financial_transactions',['kind'=>'rider_payout','rider_payout_id'=>$this->riderPayout()->id,'amount_cents'=>4000]);
        $this->assertNull($this->riderPayout()->acknowledged_at);
        $this->riderFlow()->acknowledge($this->rider,$this->riderPayout()->id);$this->riderFlow()->acknowledge($this->rider,$this->riderPayout()->id);
        $this->assertNotNull($this->riderPayout()->acknowledged_at);$this->assertSame(0,$this->treasury->balance($this->account->id,'cod'));
        $this->assertSame(0,$this->desk->balances($this->collection)['verified']);$this->assertSame('delivered',$this->shipment->fresh()->shipping_status);
        $this->assertDatabaseCount('logistics_seller_payouts',0);
    }
    public function test_cod_and_seller_adjustment_funds_cannot_fund_riders(): void
    {
        $this->fund();$this->treasury->adjustmentFunding($this->admin,$this->receiptData('100','ADJUST001')+['account_id'=>$this->account->id,'key'=>(string)Str::uuid()],$this->proof('Seller adjustment funding'));
        $this->invalid(fn()=>$this->riderFlow()->prepare($this->admin,$this->riderPrepareData()));$this->assertDatabaseCount('logistics_rider_payouts',0);
    }
    public function test_holds_prevent_payment_and_cancellation_releases_only_reservations(): void
    {
        $f=$this->riderFlow();$this->payroll();$f->prepare($this->admin,$this->riderPrepareData());
        $f->action($this->admin,$this->riderPayout()->id,['revision'=>1,'action'=>'hold','notes'=>'Review receiving account']);
        $this->invalid(fn()=>$this->riderVerify());$this->assertSame(6000,$f->available($this->account->id));
        $f->action($this->admin,$this->riderPayout()->id,['revision'=>2,'action'=>'release','notes'=>'Recipient confirmed account']);$this->riderVerify();
        $f->action($this->admin,$this->riderPayout()->id,['revision'=>4,'action'=>'hold','notes'=>'Documented payment hold']);
        $this->invalid(fn()=>$this->riderPay());
        $f->action($this->admin,$this->riderPayout()->id,['revision'=>5,'action'=>'cancel','notes'=>'Confirmed no external transfer sent']);
        $this->assertSame(10000,$f->available($this->account->id));$this->assertSame('earned',$this->earning()->status);
        $f->prepare($this->admin,$this->riderPrepareData());$this->assertDatabaseCount('logistics_rider_payouts',1);
    }
    public function test_incomplete_cancelled_and_wrong_rider_snapshots_cannot_earn(): void
    {
        $c=$this->earning();DB::table('logistics_compensations')->where('id',$c->id)->update(['status'=>'pending','earned_at'=>null,'earned_source_key'=>null]);
        DB::table('delivery_assignments')->where('id',$c->source_id)->update(['status'=>'out_for_delivery','delivered_at'=>null]);
        $this->assertFalse($this->riderFlow()->recognize($c->id,$this->admin));
        DB::table('delivery_assignments')->where('id',$c->source_id)->update(['status'=>'cancelled','delivered_at'=>now()]);
        $this->assertFalse($this->riderFlow()->recognize($c->id,$this->admin));
        $other=$this->person('rider');DB::table('delivery_assignments')->where('id',$c->source_id)->update(['status'=>'delivered','courier_id'=>$other->id]);
        $this->assertFalse($this->riderFlow()->recognize($c->id,$this->admin));
    }
    public function test_historical_recognition_uses_existing_rate_not_current_rate(): void
    {
        $c=$this->earning();DB::table('logistics_compensations')->where('id',$c->id)->update(['status'=>'pending','earned_at'=>null,'earned_source_key'=>null]);
        $settings=DB::table('logistics_finance_settings')->first();app(\App\Services\LogisticsFinance::class)->configure($this->admin,['revision'=>$settings->revision,'pickup'=>'80','transfer'=>'200','delivery'=>'90']);
        $this->assertTrue($this->riderFlow()->recognize($c->id,$this->admin));$this->assertSame(4000,(int)$this->earning()->amount_cents);
        $this->assertSame(1,DB::table('logistics_compensations')->count());
    }
    public function test_pickup_requires_two_sided_handover_and_actual_hub_receipt(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['pickup_rider_id'=>$this->rider->id]);
        $a=DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'pickup','status'=>'accepted']);
        $c=DB::table('logistics_compensations')->where('source_id',$a->id)->first();
        $this->assertFalse($this->riderFlow()->recognize($c->id,$this->admin));
        DB::table('shipments')->where('id',$this->shipment->id)->update(['seller_confirmed_pickup_at'=>now(),'rider_confirmed_pickup_at'=>now(),'picked_up_at'=>now()]);
        $this->assertFalse($this->riderFlow()->recognize($c->id,$this->admin));
        DB::table('shipments')->where('id',$this->shipment->id)->update(['at_sorting_center_at'=>now()]);
        $this->assertTrue($this->riderFlow()->recognize($c->id,$this->admin));$this->assertDatabaseHas('logistics_compensations',['id'=>$c->id,'status'=>'earned','amount_cents'=>2500]);
    }
    public function test_exact_amount_and_actual_sent_confirmation_are_required(): void
    {
        $this->payroll();$f=$this->riderFlow();$f->prepare($this->admin,$this->riderPrepareData());$this->riderVerify();
        foreach(['39.99','40.01','-1'] as $amount)$this->invalid(fn()=>$f->pay($this->admin,$this->riderPayout()->id,array_replace($this->riderPayData(),['amount'=>$amount]),$this->proof($amount)));
        $this->invalid(fn()=>$f->pay($this->admin,$this->riderPayout()->id,array_replace($this->riderPayData(),['sent'=>'0']),$this->proof('Not sent')));
        $this->assertSame(10000,$this->treasury->balance($this->account->id,'rider_payroll'));
    }
    public function test_duplicate_payment_and_stale_review_cannot_debit_again(): void
    {
        $this->payroll();$f=$this->riderFlow();$data=$this->riderPrepareData();$f->prepare($this->admin,$data);
        $this->actingAs($this->admin)->post(route('logistics.finance.riders.prepare'),$data)->assertStatus(409);
        $this->riderVerify();$stale=$this->riderPayData();$this->riderPay();
        $this->actingAs($this->admin)->post(route('logistics.finance.riders.pay',$this->riderPayout()->id),$stale+['proof'=>$this->proof('Duplicate')])->assertStatus(409);
        $this->assertDatabaseCount('logistics_rider_payouts',1);$this->assertSame(1,DB::table('logistics_financial_transactions')->where('kind','rider_payout')->count());
        $this->assertSame(6000,$this->treasury->balance($this->account->id,'rider_payroll'));
    }
    public function test_role_company_destination_and_private_evidence_scopes(): void
    {
        $this->payroll();$this->riderFlow()->prepare($this->admin,$this->riderPrepareData());$this->riderVerify();$this->riderPay();
        $id=DB::table('logistics_finance_evidence')->where('source_type','rider_payout')->value('id');
        $this->actingAs($this->rider)->get(route('finance.evidence',$id))->assertOk()->assertHeader('Cache-Control','no-store, private');
        foreach([$this->staff,$this->seller,$this->person('rider'),$this->person('admin','Beta')] as $other)$this->actingAs($other)->get(route('finance.evidence',$id))->assertForbidden();
        $this->actingAs($this->staff)->get(route('logistics.finance.riders'))->assertForbidden();
        $this->actingAs($this->rider)->post(route('logistics.finance.riders.prepare'),$this->riderPrepareData())->assertRedirect();
        $other=$this->person('rider');$this->actingAs($other)->post(route('rider.earnings.acknowledge',$this->riderPayout()->id),['received'=>'1'])->assertNotFound();
    }
    public function test_page_shows_real_forms_and_replaces_shipping_fee_income(): void
    {
        $this->payroll();$this->riderFlow()->prepare($this->admin,$this->riderPrepareData());$this->riderVerify();
        $this->actingAs($this->admin)->get(route('logistics.finance.riders'))->assertOk()->assertSee('id="rider-pay-'.$this->riderPayout()->id.'-proof"',false)->assertSee('id="payroll-funding-proof"',false)->assertDontSee('@include')->assertSee('Rider Earnings &amp; Payouts',false);
        $this->actingAs($this->rider)->get(route('rider.profit'))->assertOk()->assertSee('PHP 40.00')->assertSee('id="rider-destination-number"',false)->assertDontSee('shipping fee collected on that order')->assertDontSee('Record verified payroll contribution');
    }
    public function test_funding_and_payment_audit_failures_roll_back_money_and_private_proofs(): void
    {
        $this->payroll();$this->riderFlow()->prepare($this->admin,$this->riderPrepareData());$this->riderVerify();
        DB::statement("CREATE TRIGGER fail_rider_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'rider_payout_paid' BEGIN SELECT RAISE(ABORT, 'audit failed'); END");
        try{$this->riderPay();$this->fail('Expected audit failure');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('audit failed',$e->getMessage());}
        $this->assertSame('verified',$this->riderPayout()->status);$this->assertSame(10000,$this->treasury->balance($this->account->id,'rider_payroll'));
        $this->assertSame(0,DB::table('logistics_finance_evidence')->where('source_type','rider_payout')->count());
        $this->assertSame(1,count(Storage::disk('local')->allFiles('logistics-finance-proofs')));
    }
    public function test_whole_transfer_batch_earns_once_and_partial_receipt_earns_nothing(): void
    {
        $to=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Other City','province'=>'Laguna','is_operational'=>true]);
        $receiver=$this->person('hub_staff','Alpha',$to->id);
        $transferRider=$this->person('rider');
        $transferRider->update(['vehicle_type'=>'four_wheels','vehicle_ownership'=>'own']);
        DB::table('delivery_assignments')->update(['status'=>'completed']);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'at_sorting_center','destination_hub'=>'Other City','delivered_at'=>null]);
        DB::table('orders')->where('id',$this->order->id)->update(['status'=>'at_sorting_center']);
        $secondOrder=$this->order->replicate();$secondOrder->id=(string)Str::uuid();$secondOrder->status='at_sorting_center';$secondOrder->save();
        $s=$this->shipment->getRawOriginal();$s['id']=(string)Str::uuid();$s['order_id']=$secondOrder->id;$s['tracking_number']='SECOND-'.Str::uuid();$s['shipping_status']='at_sorting_center';$s['destination_hub']='Other City';DB::table('shipments')->insert($s);
        $shipments=[$this->shipment->id,$s['id']];
        foreach($shipments as $id)\App\Models\ShipmentHubLeg::create(['shipment_id'=>$id,'sequence'=>1,'leg_type'=>'direct','from_hub'=>'Santa Cruz','from_province'=>'Laguna','to_hub'=>'Other City','to_province'=>'Laguna','status'=>'pending']);
        $flow=app(\App\Services\HubTransferWorkflow::class);
        $b=$flow->request($this->staff,$this->hub->id,$to->id,$shipments);
        $flow->decide($this->admin,$b->id,'approved');
        $flow->dispatch($this->admin,$b->id,$transferRider->id);
        $c=DB::table('logistics_compensations')->where('source_type','transfer_batch')->first();$this->assertSame(15000,(int)$c->amount_cents);
        foreach(DB::table('shipment_hub_legs')->where('transfer_batch_id',$b->id)->pluck('id') as $id)$flow->confirmPickup($transferRider,$id);
        $flow->receive($receiver,$b->id,[$shipments[0]]);$this->assertDatabaseHas('logistics_compensations',['id'=>$c->id,'status'=>'pending']);
        $flow->receive($receiver,$b->id,[$shipments[1]]);$this->assertDatabaseHas('logistics_compensations',['id'=>$c->id,'status'=>'earned']);
        $this->assertFalse($this->riderFlow()->recognize($c->id,$this->admin));
        $this->assertSame(1,DB::table('logistics_compensations')->where('leg','transfer')->count());
        $this->assertSame(1,DB::table('logistics_financial_events')->where('subject_id',$c->id)->where('event','rider_compensation_earned')->count());
    }
    public function test_wrong_recipient_cannot_be_prepared_and_revoked_admin_cannot_pay(): void
    {
        $this->payroll();$other=$this->person('rider');$this->riderFlow()->destination($other,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Other Rider','account_number'=>'09099998888']);
        $data=$this->riderPrepareData();$wrong=DB::table('logistics_rider_destinations')->where('rider_id',$other->id)->value('id');
        $this->actingAs($this->admin)->post(route('logistics.finance.riders.prepare'),array_replace($data,['destination_id'=>$wrong]))->assertNotFound();
        $this->riderFlow()->prepare($this->admin,$data);$this->riderVerify();$this->admin->update(['status'=>'suspended']);
        $this->actingAs($this->admin)->post(route('logistics.finance.riders.pay',$this->riderPayout()->id),$this->riderPayData()+['proof'=>$this->proof('Revoked admin')])->assertForbidden();
        $this->assertSame('verified',$this->riderPayout()->status);
    }
    public function test_funding_reference_evidence_and_submission_key_cannot_credit_twice(): void
    {
        $f=$this->riderFlow();$d=$this->receiptData('100','FUNDONCE01')+['account_id'=>$this->account->id,'key'=>(string)Str::uuid()];
        $f->funding($this->admin,$d,$this->proof('Once only'));
        $this->actingAs($this->admin)->post(route('logistics.finance.riders.funding'),$d+['proof'=>$this->proof('Different image same key')])->assertStatus(409);
        $this->invalid(fn()=>$f->funding($this->admin,array_replace($d,['key'=>(string)Str::uuid(),'reference'=>'FUNDONCE02']),$this->proof('Once only')));
        $this->actingAs($this->admin)->post(route('logistics.finance.riders.funding'),array_replace($d,['key'=>(string)Str::uuid()])+['proof'=>$this->proof('Different image same ref')])->assertSessionHasErrors();
        $this->assertSame(10000,$this->treasury->balance($this->account->id,'rider_payroll'));
    }

    public function test_live_hub_receipt_earns_pickup_and_audit_failure_rolls_back_receipt(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'picked_up','pickup_rider_id'=>$this->rider->id,'seller_confirmed_pickup_at'=>now(),'rider_confirmed_pickup_at'=>now(),'picked_up_at'=>now(),'at_sorting_center_at'=>null]);
        DB::table('orders')->where('id',$this->order->id)->update(['status'=>'picked_up']);
        $a=DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'pickup','status'=>'accepted']);
        DB::statement("CREATE TRIGGER fail_pickup_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'rider_compensation_earned' BEGIN SELECT RAISE(ABORT, 'earning audit failed'); END");
        $this->actingAs($this->staff)->patchJson('/logistics/scan/'.$this->shipment->id.'/receive')->assertStatus(500);
        $this->assertDatabaseHas('shipments',['id'=>$this->shipment->id,'shipping_status'=>'picked_up','at_sorting_center_at'=>null]);
        DB::statement('DROP TRIGGER fail_pickup_audit');
        $this->actingAs($this->staff)->patchJson('/logistics/scan/'.$this->shipment->id.'/receive')->assertOk();
        $this->assertDatabaseHas('logistics_compensations',['source_id'=>$a->id,'status'=>'earned','amount_cents'=>2500]);
    }
    public function test_unrecorded_cod_collection_cannot_be_recognized_as_delivery_earnings(): void
    {
        $c=$this->earning();DB::table('logistics_compensations')->where('id',$c->id)->update(['status'=>'pending','earned_at'=>null,'earned_source_key'=>null]);
        DB::table('logistics_financial_transactions')->where('id',$this->collection->id)->delete();
        $this->actingAs($this->admin)->post(route('logistics.finance.riders.recognize',$c->id))->assertStatus(422);
        $this->assertSame('pending',$this->earning()->status);
    }
    public function test_funding_audit_failure_rolls_back_bucket_reference_and_evidence(): void
    {
        DB::statement("CREATE TRIGGER fail_funding_audit BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'rider_funding_verified' BEGIN SELECT RAISE(ABORT, 'funding audit failed'); END");
        try{$this->payroll();$this->fail('Expected audit failure');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('funding audit failed',$e->getMessage());}
        $this->assertSame(0,$this->treasury->balance($this->account->id,'rider_payroll'));$this->assertDatabaseCount('logistics_finance_evidence',0);
        $this->assertDatabaseCount('logistics_payment_references',0);$this->assertSame([],Storage::disk('local')->allFiles('logistics-finance-proofs'));
    }
    public function test_cancelled_snapshot_and_zero_rate_never_create_payable_earnings(): void
    {
        $c=$this->earning();DB::table('logistics_compensations')->where('id',$c->id)->update(['status'=>'cancelled','earned_at'=>null,'earned_source_key'=>null]);
        $this->assertFalse($this->riderFlow()->recognize($c->id,$this->admin));
        $this->payroll();$this->invalid(fn()=>$this->riderFlow()->prepare($this->admin,$this->riderPrepareData()));
        DB::table('logistics_compensations')->where('id',$c->id)->update(['status'=>'pending','amount_cents'=>0]);
        $this->assertTrue($this->riderFlow()->recognize($c->id,$this->admin));
        $this->invalid(fn()=>$this->riderFlow()->prepare($this->admin,$this->riderPrepareData()));$this->assertDatabaseCount('logistics_rider_payouts',0);
    }
    public function test_paid_earnings_cannot_be_cancelled_or_acknowledged_before_payment(): void
    {
        $this->payroll();$f=$this->riderFlow();$f->prepare($this->admin,$this->riderPrepareData());
        $this->actingAs($this->rider)->post(route('rider.earnings.acknowledge',$this->riderPayout()->id),['received'=>'1'])->assertStatus(422);
        $this->riderVerify();$this->riderPay();
        $this->actingAs($this->admin)->post(route('logistics.finance.riders.action',$this->riderPayout()->id),['revision'=>3,'action'=>'cancel','notes'=>'Must never cancel a sent payout'])->assertStatus(409);
        $this->assertSame('paid',$this->riderPayout()->status);
    }
    public function test_company_changes_do_not_expose_other_rider_earnings(): void
    {
        $other=$this->person('rider');$this->actingAs($other)->get(route('rider.profit'))->assertOk()->assertDontSee($this->earning()->source_id);
        $beta=$this->person('admin','Beta');$this->actingAs($beta)->get(route('logistics.finance.riders'))->assertOk()->assertDontSee($this->earning()->source_id);
        $this->actingAs($beta)->post(route('logistics.finance.riders.recognize',$this->earning()->id))->assertForbidden();
    }

    public function test_cross_provider_gcash_reference_cannot_reuse_a_buyer_payment(): void
    {
        $this->payroll();$this->riderFlow()->destination($this->rider,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Rider Test','account_number'=>'09123456789']);
        $data=$this->riderPrepareData();$data['destination_id']=DB::table('logistics_rider_destinations')->where('channel','gcash')->value('id');
        $this->riderFlow()->prepare($this->admin,$data);$this->riderVerify();
        DB::transaction(fn()=>app(LogisticsShipping::class)->reserveReference('RIDERSENT001','buyer_gcash',(string)Str::uuid()));
        $this->invalid(fn()=>$this->riderPay());$this->assertSame('verified',$this->riderPayout()->status);$this->assertSame(10000,$this->treasury->balance($this->account->id,'rider_payroll'));
    }

    public function test_delivery_earning_audit_failure_rolls_back_cod_collection_and_delivery(): void
    {
        $c=$this->earning();DB::table('logistics_compensations')->where('id',$c->id)->update(['status'=>'pending','earned_at'=>null,'earned_source_key'=>null]);
        DB::table('logistics_financial_transactions')->where('id',$this->collection->id)->delete();
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'out_for_delivery','delivered_at'=>null]);
        DB::table('orders')->where('id',$this->order->id)->update(['status'=>'out_for_delivery']);
        DB::table('delivery_assignments')->where('id',$c->source_id)->update(['status'=>'out_for_delivery','delivered_at'=>null]);
        DB::statement("CREATE TRIGGER fail_delivery_earning BEFORE INSERT ON logistics_financial_events WHEN NEW.event = 'rider_compensation_earned' BEGIN SELECT RAISE(ABORT, 'earning audit failed'); END");
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$this->shipment->id.'/advance',['from'=>'out_for_delivery','cod_amount'=>'1100','cod_confirmed'=>'1','collection_key'=>(string)Str::uuid()])->assertStatus(500);
        $this->assertDatabaseHas('shipments',['id'=>$this->shipment->id,'shipping_status'=>'out_for_delivery','delivered_at'=>null]);
        $this->assertDatabaseHas('delivery_assignments',['id'=>$c->source_id,'status'=>'out_for_delivery','delivered_at'=>null]);
        $this->assertSame('pending',$this->earning()->status);$this->assertDatabaseCount('logistics_financial_transactions',0);
    }

    public function test_admin_cannot_attest_origin_receipt_or_forge_delivery_earnings(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'picked_up','pickup_rider_id'=>$this->rider->id,'seller_confirmed_pickup_at'=>now(),'rider_confirmed_pickup_at'=>now(),'picked_up_at'=>now(),'at_sorting_center_at'=>null]);
        $a=DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'pickup','status'=>'accepted']);
        $this->actingAs($this->admin)->patchJson('/logistics/scan/'.$this->shipment->id.'/receive')->assertForbidden();
        $this->assertDatabaseHas('shipments',['id'=>$this->shipment->id,'shipping_status'=>'picked_up','at_sorting_center_at'=>null]);
        $this->assertDatabaseHas('logistics_compensations',['source_id'=>$a->id,'status'=>'pending']);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'out_for_delivery','delivered_at'=>null]);
        DB::table('orders')->where('id',$this->order->id)->update(['payment_method'=>'GCash']);
        foreach([$this->admin,$this->staff] as $actor) {
            $this->actingAs($actor)->patchJson('/logistics/status/'.$this->shipment->id,['status'=>'delivered','reason'=>'Emergency override attempt'])->assertForbidden();
        }
        $this->assertDatabaseHas('shipments',['id'=>$this->shipment->id,'shipping_status'=>'out_for_delivery','delivered_at'=>null]);
    }
    public function test_delivery_rechecks_a_revoked_rider_even_with_a_stale_authenticated_instance(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'out_for_delivery','delivered_at'=>null]);
        DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        $this->actingAs($this->rider)->patchJson('/rider/deliveries/'.$this->shipment->id.'/advance',['from'=>'out_for_delivery','cod_amount'=>'1100','cod_confirmed'=>'1','collection_key'=>(string)Str::uuid()])->assertForbidden();
        $this->assertDatabaseHas('shipments',['id'=>$this->shipment->id,'shipping_status'=>'out_for_delivery','delivered_at'=>null]);
    }
}
