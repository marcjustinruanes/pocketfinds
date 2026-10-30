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

class LogisticsReconciliationTest extends TestCase
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


    protected function report(): array { return app(\App\Services\LogisticsReconciliation::class)->snapshot($this->admin,now()->subDays(30)->toDateString(),now()->addDay()->toDateString()); }
    protected function balanced(array $r): void { $this->assertSame(0,$r['conservation']['residual_cents']); }
    public function test_cod_lifecycle_reservations_payouts_and_payroll_do_not_double_count_cash(): void
    {
        $r=$this->report();$this->assertSame(110000,$r['metrics']['cod_collected']);$this->assertSame(0,$r['metrics']['cod_verified']);$this->balanced($r);
        $this->remit();$r=$this->report();$this->assertSame(110000,$r['metrics']['hub_cash']);$this->assertSame(110000,$r['metrics']['cod_verified']);$this->balanced($r);
        $h=$this->authorize();$this->treasury->release($this->staff,$h->id,'1100');$r=$this->report();$this->assertSame(0,$r['metrics']['hub_cash']);$this->assertSame(110000,$r['metrics']['in_transit']);$this->balanced($r);
        $this->treasury->receipt($this->admin,$h->id,$this->receiptData(),$this->proof('Company bank deposit'));$r=$this->report();$this->assertSame(110000,$r['buckets']['cod']);$this->assertSame(0,$r['metrics']['in_transit']);$this->balanced($r);
        $this->flow->prepare($this->admin,$this->prepareData());$r=$this->report();$this->assertSame(100000,$r['reserved']['cod']);$this->assertSame(110000,$r['buckets']['cod']);
        $this->verify();$this->pay();$this->payroll();$this->riderFlow()->prepare($this->admin,$this->riderPrepareData());$this->riderVerify();$this->riderPay();
        $r=$this->report();$this->assertSame(10000,$r['buckets']['cod']);$this->assertSame(6000,$r['buckets']['rider_payroll']);$this->assertSame(0,$r['reserved']['cod']);
        $this->assertSame(10000,$r['metrics']['cod_shipping_retained']);$this->assertSame(100000,$r['metrics']['seller_paid']);$this->assertSame(4000,$r['metrics']['rider_earned']);$this->assertSame(4000,$r['metrics']['rider_paid']);$this->assertSame(0,$r['metrics']['rider_unpaid']);$this->assertSame(0,$r['metrics']['seller_product_unpaid']);$this->balanced($r);
        $this->assertSame([],array_values(array_filter($r['issues'],fn($i)=>!in_array($i['code'],['seller_eligibility']))));
    }
    public function test_partial_short_and_over_remittances_distinguish_outstanding_and_custody(): void
    {
        $this->remit('500','400','400');$r=$this->report();$this->assertSame(40000,$r['metrics']['cod_verified']);$this->assertSame(70000,$r['metrics']['cod_outstanding']);$this->assertSame(40000,$r['metrics']['hub_cash']);$this->assertSame(1,$r['metrics']['discrepancies_open']);$this->balanced($r);
        $this->remit('700','750','700');$r=$this->report();$this->assertSame(110000,$r['metrics']['cod_verified']);$this->assertSame(0,$r['metrics']['cod_outstanding']);$this->assertSame(115000,$r['metrics']['hub_cash']);$this->assertSame(5000,$r['metrics']['remittance_overages']);$this->balanced($r);
        $id=DB::table('logistics_cash_handovers')->where('status','discrepancy')->value('id');$this->desk->resolve($this->admin,$id,'Investigation documented without balance changes');
        $r=$this->report();$this->assertSame(1,$r['metrics']['discrepancies_resolved']);$this->assertSame(115000,$r['metrics']['hub_cash']);$this->balanced($r);
    }
    public function test_submitted_cash_is_pending_not_verified_and_overdue_remains_separate(): void
    {
        DB::table('logistics_financial_transactions')->where('id',$this->collection->id)->update(['recorded_at'=>now()->subDays(2)]);
        $this->desk->submit($this->rider,['key'=>(string)Str::uuid(),'allocations'=>[$this->collection->id=>'500']]);$r=$this->report();
        $this->assertSame(50000,$r['metrics']['cod_reserved']);$this->assertSame(110000,$r['metrics']['cod_outstanding']);$this->assertSame(110000,$r['metrics']['cod_overdue']);$this->assertSame(0,$r['metrics']['hub_cash']);$this->balanced($r);
    }
    public function test_treasury_shortage_and_overage_are_accounted_without_inventing_cash(): void
    {
        $this->remit();$h=$this->authorize();$this->treasury->release($this->staff,$h->id,'1100');$this->treasury->receipt($this->admin,$h->id,$this->receiptData('1150'),$this->proof('Bank overage'));
        $r=$this->report();$this->assertSame(110000,$r['buckets']['cod']);$this->assertSame(5000,$r['buckets']['unallocated']);$this->assertSame(5000,$r['metrics']['transfer_variance']);$this->balanced($r);
    }
    public function test_interhub_cash_transit_and_short_receipt_remain_separate(): void
    {
        $this->remit();$to=LogisticsHub::create(['company_name'=>'Alpha','municipality'=>'Other','province'=>'Laguna','is_operational'=>true]);$receiver=$this->person('hub_staff','Alpha',$to->id);
        $h=$this->desk->authorizeTransfer($this->admin,['key'=>(string)Str::uuid(),'hub_id'=>$this->hub->id,'destination_hub_id'=>$to->id,'amount'=>'100','notes'=>'Transfer cash']);
        $this->desk->transfer($this->staff,$h->id,'dispatch',['counted'=>'100','cod_amount'=>'100','notes'=>'Actual release']);$r=$this->report();$this->assertSame(10000,$r['metrics']['in_transit']);$this->balanced($r);
        $this->desk->transfer($receiver,$h->id,'receive',['counted'=>'90','cod_amount'=>'90','notes'=>'Actual receipt shortage']);$r=$this->report();$this->assertSame(109000,$r['metrics']['hub_cash']);$this->assertSame(-1000,$r['metrics']['transfer_variance']);$this->assertSame(0,$r['metrics']['in_transit']);$this->balanced($r);
    }
    protected function gcashShipping(string $funder,string $method='GCash'): object
    {
        $shipping=app(LogisticsShipping::class);$shipping->configure($this->admin,['revision'=>DB::table('logistics_shipping_accounts')->value('revision')??0,'account_name'=>'Alpha GCash','account_number'=>'09123456789']);
        $o=Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'ready_for_pickup','payment_method'=>$method,'subtotal'=>'1000','shipping_amount'=>'100','discount_amount'=>'20','total'=>'1080',
            'items'=>[['name'=>'Parcel','qty'=>1,'price'=>1000,'weight_grams'=>1000]],'shipping_address'=>['region'=>'Test Region','municipality'=>'Santa Cruz','province'=>'Laguna'],
            'commerce_snapshot'=>['vouchers'=>[['id'=>(string)Str::uuid(),'code'=>'VOUCHER-'.$funder,'kind'=>'shipping','funding_source'=>$funder,'amount'=>'20']]]]);
        $s=Shipment::create(['order_id'=>$o->id,'logistics_company'=>'Alpha','shipping_status'=>'ready_for_pickup','origin_hub'=>'Santa Cruz','origin_province'=>'Laguna','destination_hub'=>'Santa Cruz','destination_province'=>'Laguna']);
        return DB::table('logistics_shipping_obligations')->where('order_id',$o->id)->first();
    }
    public function test_gcash_vouchers_keep_gross_receipts_claims_and_treasury_separate(): void
    {
        foreach(['seller','platform'] as $funder){$ob=$this->gcashShipping($funder);$f=app(LogisticsShipping::class);$ref='SHIPPING-'.$funder;
            $f->submit($this->seller,$ob->id,['key'=>(string)Str::uuid(),'reference'=>$ref,'amount'=>'100','revision'=>1],$this->proof($ref));
            $before=$this->report();$this->assertGreaterThanOrEqual(10000,$before['metrics']['gcash_pending_proofs']);
            $payment=DB::table('logistics_shipping_payments')->where('obligation_id',$ob->id)->first();
            $f->review($this->admin,$ob->id,['payment_id'=>$payment->id,'revision'=>2,'accept'=>'1','amount'=>'100','reference'=>$ref,'notes'=>'Actual company GCash receipt verified']);
        }
        $r=$this->report();$this->assertSame(20000,$r['metrics']['gcash_receipts']);$this->assertSame(0,$r['metrics']['gcash_pending_proofs']);$this->assertSame(2000,$r['metrics']['seller_vouchers']);$this->assertSame(2000,$r['metrics']['platform_claims']);
        $this->assertSame(0,$r['buckets']['cod']);$this->assertSame(0,$r['buckets']['rider_payroll']);$this->balanced($r);
    }
    public function test_returns_disputes_and_holds_remain_exposure_not_paid_or_forgiven(): void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());
        $this->flow->action($this->admin,$this->payout()->id,['revision'=>1,'action'=>'hold','notes'=>'Documented settlement hold']);
        DB::table('complaints')->insert(['id'=>(string)Str::uuid(),'order_id'=>$this->order->id,'complainant_id'=>$this->buyer->id,'respondent_id'=>$this->seller->id,'complaint_type'=>'order','subject'=>'Order dispute','description'=>'Investigate','status'=>'open','created_at'=>now()]);
        $r=$this->report();$this->assertSame(100000,$r['metrics']['held_seller']);$this->assertSame(100000,$r['metrics']['seller_product_unpaid']);$this->assertSame(0,$r['metrics']['seller_eligible_unpaid']);$this->assertSame(100000,$r['reserved']['cod']);$this->balanced($r);
        $this->assertContains('seller_eligibility',array_column($r['issues'],'code'));
    }
    public function test_missing_ledger_and_movement_evidence_is_flagged_without_repairing_records(): void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());$this->verify();$this->pay();
        DB::table('logistics_financial_transactions')->where('seller_payout_id',$this->payout()->id)->delete();$r=$this->report();$this->assertContains('payout_mirror',array_column($r['issues'],'code'));$this->assertSame('paid',$this->payout()->status);
        DB::table('logistics_cash_movements')->where('kind','cash_received')->update(['delta_cents'=>100]);$r=$this->report();$this->assertContains('remittance_movement',array_column($r['issues'],'code'));$this->assertNotSame(0,$r['conservation']['residual_cents']);
    }
    public function test_legacy_and_cancelled_shipments_do_not_create_collection_or_refund_history(): void
    {
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_finance_version'=>0]);DB::table('logistics_financial_transactions')->where('kind','cod_collection')->delete();
        $before=DB::table('logistics_financial_events')->count();$r=$this->report();$this->assertSame(1,$r['metrics']['legacy_delivered_cod']);$this->assertContains('legacy_cod',array_column($r['issues'],'code'));$this->assertDatabaseCount('logistics_financial_transactions',0);$this->assertSame($before,DB::table('logistics_financial_events')->count());
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'cancelled']);$r=$this->report();$this->assertContains('cancelled_financial_review',array_column($r['issues'],'code'));
    }
    public function test_current_balances_ignore_activity_dates_and_company_scopes_cannot_be_overridden(): void
    {
        $this->fund();$r=app(\App\Services\LogisticsReconciliation::class)->snapshot($this->admin,'2000-01-01','2000-01-02');$this->assertSame(110000,$r['buckets']['cod']);$this->assertSame([],$r['rows']['transactions']);
        $other=$this->person('admin','Beta');$r=app(\App\Services\LogisticsReconciliation::class)->snapshot($other,now()->subDay()->toDateString(),now()->addDay()->toDateString());$this->assertSame(0,$r['metrics']['cod_collected']);$this->assertSame([],$r['rows']['collections']);
        $this->actingAs($other)->get(route('logistics.finance.reconciliation',['company_name'=>'Alpha']))->assertOk()->assertDontSee($this->collection->id);
        $this->actingAs($this->staff)->get(route('logistics.finance.reconciliation'))->assertForbidden();$this->get(route('logistics.finance.reconciliation.export'))->assertForbidden();
        $this->actingAs($this->rider)->get(route('logistics.finance.reconciliation'))->assertRedirect();
    }
    public function test_export_is_private_formula_safe_and_audited_without_account_or_proof_disclosure(): void
    {
        $this->payroll();$this->riderFlow()->prepare($this->admin,$this->riderPrepareData());
        $this->riderFlow()->action($this->admin,$this->riderPayout()->id,['revision'=>1,'action'=>'hold','notes'=>'=SUM(1,2)']);
        $response=$this->actingAs($this->admin)->get(route('logistics.finance.reconciliation.export',['dataset'=>'rider_payouts']));$response->assertOk()->assertHeader('Cache-Control','no-store, private');$csv=$response->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)",$csv);$this->assertStringNotContainsString('554433221',$csv);$this->assertStringNotContainsString('logistics-finance-proofs/',$csv);
        $this->assertSame(1,DB::table('logistics_financial_events')->where('event','reconciliation_exported')->count());$this->assertSame(10000,$this->treasury->balance($this->account->id,'rider_payroll'));
        $this->get(route('logistics.finance.reconciliation',['dataset'=>'invalid']))->assertSessionHasErrors('dataset');
        $this->get(route('logistics.finance.reconciliation',['from'=>'2026-10-29','to'=>'2026-10-28']))->assertStatus(422);
    }
    public function test_reports_render_labelled_controls_and_never_change_financial_states(): void
    {
        $before=DB::table('logistics_financial_events')->count();$this->actingAs($this->admin)->get(route('logistics.finance.reconciliation'))->assertOk()->assertSee('id="recon-from"',false)->assertSee('Counted-cash equation')->assertSee('Reconciliation &amp; Reports',false)->assertDontSee('@include');
        $this->assertSame($before,DB::table('logistics_financial_events')->count());$this->assertSame('pending',$this->collection->verification_state);
    }
    public function test_complete_buyer_gcash_to_delivery_preserves_direct_seller_payment_and_company_receipt(): void
    {
        $this->seller->update(['shipping_fee'=>100]);$this->buyer->update(['postal_code'=>'4009','barangay'=>'B','street'=>'Main Street','contact_no'=>'09123456789']);
        $this->rider->update(['vehicle_type'=>'two_wheels','vehicle_ownership'=>'own']);
        $shipping=app(LogisticsShipping::class);$shipping->configure($this->admin,['revision'=>0,'account_name'=>'Alpha GCash','account_number'=>'09123456789']);
        $product=\App\Models\Product::create(['seller_id'=>$this->seller->id,'name'=>'End to end parcel','status'=>'active','stock'=>5,'price'=>1000,'weight_grams'=>1000]);
        $cart=\App\Models\CartItem::create(['buyer_id'=>$this->buyer->id,'product_id'=>$product->id,'seller_slug'=>$this->seller->username,'name'=>$product->name,'price'=>1000,'qty'=>1]);
        $method=\App\Models\PaymentMethod::create(['name'=>'GCash','type'=>'ewallet','is_active'=>true]);
        DB::table('commerce_vouchers')->insert(['code'=>'RECONPLAT','kind'=>'shipping','seller_id'=>null,'discount_type'=>'fixed','amount'=>20,'starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'usage_limit'=>10,'per_buyer_limit'=>1]);
        $quote=DB::transaction(fn()=>app(\App\Services\BuyerCommerce::class)->quote($this->buyer,[$cart->id],null,['RECONPLAT']));
        $flow=app(\App\Services\GcashWorkflow::class);$p=$flow->create($this->buyer,[$cart->id],$method->id,(string)Str::uuid(),null,null,null,$quote)[0]->fresh();
        $p=$flow->instructions($this->seller,$p->id,$p->revision,'Seller Shop','09123456789');$file=$this->proof('Buyer actual GCash payment');
        $p=DB::transaction(function()use($flow,$p,$file){$path=app(\App\Services\MediaLifecycle::class)->store($file,'gcash-proofs','local');return $flow->proof($this->buyer,$p->id,(string)Str::uuid(),$path,'application/pdf',$file->getSize(),hash_file('sha256',$file->getRealPath()));});
        $p=$flow->decide($this->seller,$p->id,$p->revision,true,'1080.00',null,'RECONBUYER001');$o=$flow->place($this->buyer,$p->id,$p->revision);$o->update(['status'=>'preparing']);
        $this->actingAs($this->seller)->patch('/seller/orders/'.$o->id.'/ready',['logistics_company'=>'Alpha'])->assertRedirect()->assertSessionHasNoErrors();
        $ob=DB::table('logistics_shipping_obligations')->where('order_id',$o->id)->first();
        $this->actingAs($this->admin)->patch('/logistics/requests/'.$ob->shipment_id.'/approve')->assertSessionHasErrors('shipping');
        $shipping->submit($this->seller,$ob->id,['key'=>(string)Str::uuid(),'reference'=>'RECONSHIP001','amount'=>'100','revision'=>1],$this->proof('Seller full shipping advance'));
        $payment=DB::table('logistics_shipping_payments')->where('obligation_id',$ob->id)->first();
        $shipping->review($this->admin,$ob->id,['payment_id'=>$payment->id,'revision'=>2,'accept'=>'1','amount'=>'100','reference'=>'RECONSHIP001','notes'=>'Actual full shipping receipt']);
        // Clear the earlier expected validation message before the successful operational requests.
        session()->forget('errors');
        $this->actingAs($this->admin)->patch('/logistics/requests/'.$ob->shipment_id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->patch('/logistics/requests/'.$ob->shipment_id.'/assign-pickup',['courier_id'=>$this->rider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->rider)->patch('/rider/my-pickups/'.$ob->shipment_id.'/confirm')->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->seller)->patch(route('seller.orders.confirm-pickup',$o))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->patchJson('/logistics/scan/'.$ob->shipment_id.'/receive')->assertOk();
        $this->patch('/logistics/assignments/'.$ob->shipment_id.'/assign',['courier_id'=>$this->rider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->rider)->patch('/rider/deliveries/'.$ob->shipment_id.'/advance',['from'=>'assigned_to_rider'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/rider/deliveries/'.$ob->shipment_id.'/advance',['from'=>'out_for_delivery'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->buyer)->patch(route('buyer.orders.confirm-receipt',$o))->assertRedirect();
        $r=$this->report();$this->assertSame(10000,$r['metrics']['gcash_receipts']);$this->assertSame(2000,$r['metrics']['platform_claims']);$this->assertSame(110000,$r['metrics']['cod_collected']);
        $this->assertSame(10500,$r['metrics']['rider_earned']);$this->assertSame(0,$r['metrics']['seller_paid']);$this->assertSame(0,$r['buckets']['cod']);$this->balanced($r);
        $this->assertSame('completed',$o->fresh()->status);$this->assertSame('1080.00',number_format($o->fresh()->total,2,'.',''));
    }
    public function test_returns_and_refunds_after_payout_are_flagged_without_clawback(): void
    {
        $this->fund();$this->flow->prepare($this->admin,$this->prepareData());$this->verify();$this->pay();
        $id=(string)Str::uuid();DB::table('commerce_returns')->insert(['id'=>$id,'order_id'=>$this->order->id,'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'item_keys'=>'[0]','reason'=>'Damaged item','description'=>'Review returned goods','photos'=>'[]','amount'=>1000,'refund_destination'=>'Bank','status'=>'pending']);
        foreach(['pending','approved','escalated','received','refunded'] as $status){DB::table('commerce_returns')->where('id',$id)->update(['status'=>$status]);$r=$this->report();$this->assertContains('active_return',array_column($r['issues'],'code'));$this->assertSame(100000,$r['metrics']['seller_paid']);$this->assertSame(10000,$r['buckets']['cod']);$this->balanced($r);}
        DB::table('commerce_returns')->where('id',$id)->update(['status'=>'rejected','decided_at'=>now()->subDays(4)]);$this->assertNotContains('active_return',array_column($this->report()['issues'],'code'));
    }
    public function test_missing_phase6_schema_returns_service_unavailable_without_backfill(): void
    {
        \Illuminate\Support\Facades\Schema::drop('logistics_rider_payouts');$before=DB::table('logistics_financial_events')->count();
        $this->actingAs($this->admin)->get(route('logistics.finance.reconciliation'))->assertStatus(503);$this->assertSame($before,DB::table('logistics_financial_events')->count());
    }

    public function test_cod_seller_and_platform_vouchers_preserve_shipping_and_cash_only_seller_proceeds(): void
    {
        foreach(['seller','platform'] as $funder){$ob=$this->gcashShipping($funder,'Cash on Delivery');
            DB::table('shipments')->where('id',$ob->shipment_id)->update(['shipping_status'=>'out_for_delivery','courier_id'=>$this->rider->id]);DB::table('orders')->where('id',$ob->order_id)->update(['status'=>'out_for_delivery']);
            $a=DeliveryAssignment::create(['shipment_id'=>$ob->shipment_id,'courier_id'=>$this->rider->id,'leg'=>'delivery','status'=>'assigned_to_rider']);DB::table('delivery_assignments')->where('id',$a->id)->update(['status'=>'out_for_delivery']);
            $this->actingAs($this->rider)->patch('/rider/deliveries/'.$ob->shipment_id.'/advance',['from'=>'out_for_delivery','cod_amount'=>'1080','cod_confirmed'=>'1','collection_key'=>(string)Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
            $this->actingAs($this->buyer)->patch(route('buyer.orders.confirm-receipt',$ob->order_id))->assertRedirect();
            $c=DB::table('logistics_financial_transactions')->where('collection_order_id',$ob->order_id)->first();$h=$this->desk->submit($this->rider,['key'=>(string)Str::uuid(),'allocations'=>[$c->id=>'1080']]);
            $this->desk->count($this->staff,$h->id,['counted'=>'1080','allocations'=>[$c->id=>'1080'],'notes'=>'Actual full COD handover']);
            $key=(string)Str::uuid();$this->treasury->transfer($this->admin,['key'=>$key,'hub_id'=>$this->hub->id,'account_id'=>$this->account->id,'amount'=>'1080','notes'=>'Company bank deposit']);$transfer=DB::table('logistics_cash_handovers')->where('idempotency_key',$key)->first();
            $this->treasury->release($this->staff,$transfer->id,'1080');$this->treasury->receipt($this->admin,$transfer->id,$this->receiptData('1080','CODVOUCH-'.$funder),$this->proof('COD bank '.$funder));
            $this->flow->prepare($this->admin,['order_id'=>$ob->order_id,'account_id'=>$this->account->id,'destination_id'=>$this->destination->id,'adjustment'=>'0']);$p=DB::table('logistics_seller_payouts')->where('order_id',$ob->order_id)->first();
            $this->assertSame(98000,(int)$p->product_cents);$this->assertSame(10000,(int)$p->shipping_cents);
            $this->flow->action($this->admin,$p->id,['revision'=>1,'action'=>'verify','notes'=>'Full verified funds and buyer confirmation']);
            $data=['revision'=>2,'amount'=>'980','reference'=>'CODVOUCHPAY-'.$funder,'sent'=>'1','notes'=>'Actual cash-only product proceeds'];
            $this->flow->pay($this->admin,$p->id,$data,$this->proof('Actual voucher seller payout '.$funder));
            $this->actingAs($this->admin)->post(route('logistics.finance.payouts.pay',$p->id),$data+['proof'=>$this->proof('Retry '.$funder)])->assertStatus(409);
        }
        $r=$this->report();$this->assertSame(196000,$r['metrics']['seller_paid']);$this->assertSame(20000,$r['metrics']['cod_shipping_retained']);$this->assertSame(20000,$r['buckets']['cod']);$this->assertSame(2000,$r['metrics']['platform_claims']);$this->assertSame(2000,$r['metrics']['seller_vouchers']);$this->balanced($r);
    }

    public function test_cross_provider_seller_payout_cannot_reuse_a_buyer_gcash_reference(): void
    {
        $this->fund();$this->treasury->destination($this->seller,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Seller Test','account_number'=>'09123456789']);
        $data=$this->prepareData();$data['destination_id']=DB::table('logistics_payout_destinations')->where('channel','gcash')->value('id');$this->flow->prepare($this->admin,$data);$this->verify();
        DB::transaction(fn()=>app(LogisticsShipping::class)->reserveReference('PAYOUT001','buyer_gcash',(string)Str::uuid()));
        $this->invalid(fn()=>$this->pay());$this->assertSame('verified',$this->payout()->status);$this->assertSame(110000,$this->treasury->balance($this->account->id,'cod'));$this->balanced($this->report());
    }

    public function test_multiple_accounts_keep_overages_and_reservations_in_their_own_buckets(): void
    {
        $this->remit();$h=$this->authorize();$this->treasury->release($this->staff,$h->id,'1100');$this->treasury->receipt($this->admin,$h->id,$this->receiptData('1150'),$this->proof('Actual overage receipt'));
        $this->treasury->createAccount($this->admin,['channel'=>'gcash','institution'=>'GCash','account_name'=>'Second Treasury','account_number'=>'09122334455']);
        $r=$this->report();$this->assertSame(5000,$r['buckets']['unallocated']);$this->assertSame(0,$r['reserved']['unallocated']);$this->assertNotContains('treasury_balance',array_column($r['issues'],'code'));$this->balanced($r);
        $this->assertSame('-1000',\App\Services\LogisticsReconciliation::csvCell(-1000));$this->assertSame("'-1+SUM(1,2)",\App\Services\LogisticsReconciliation::csvCell('-1+SUM(1,2)'));
    }

    public function test_export_rechecks_company_after_building_its_snapshot(): void
    {
        $report=$this->report();$this->mock(\App\Services\LogisticsReconciliation::class,function($mock)use($report){
            $mock->shouldReceive('snapshot')->once()->andReturnUsing(function()use($report){DB::table('users')->where('id',$this->admin->id)->update(['business_name'=>'Beta']);return $report;});
        });
        $this->actingAs($this->admin)->get(route('logistics.finance.reconciliation.export'))->assertForbidden();
        $this->assertSame(0,DB::table('logistics_financial_events')->where('event','reconciliation_exported')->count());
    }

}
