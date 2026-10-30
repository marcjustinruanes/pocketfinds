<?php

namespace Tests\Feature;

use App\Models\{Product, Order};
use App\Services\LogisticsShipping;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/** Reproduction tests: assertions describe observed behavior, not approval of that behavior. */
class RemainingLogisticsAuditTest extends LogisticsReconciliationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_29_000005_add_logistics_operational_recovery.php'))->up();
        (require database_path('migrations/2026_10_29_000007_add_issue_rts_custody.php'))->up();
        DB::table('shipments')->where('id',$this->shipment->id)->update(['tracking_number'=>'AUDIT-SYNTHETIC-001']);
        $this->shipment->refresh();
    }

    private function observe(string $case, array $data): void
    {
        file_put_contents(storage_path('app/private/logistics-ui-review/remaining-observations.jsonl'), json_encode(['case'=>$case]+$data)."\n", FILE_APPEND);
    }

    public function test_audit_returned_shortcut_is_blocked_and_seller_receipt_restocks_once(): void
    {
        $product=Product::create(['seller_id'=>$this->seller->id,'name'=>'Synthetic RTS','price'=>1000,'stock'=>4,'status'=>'active','weight_grams'=>1000]);
        $this->order=Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'preparing','payment_method'=>'Cash on Delivery','subtotal'=>1000,'shipping_amount'=>100,'discount_amount'=>0,'total'=>1100,'commerce_snapshot'=>['vouchers'=>[]],'items'=>[['product_id'=>$product->id,'name'=>'Synthetic RTS','qty'=>1,'price'=>1000,'weight_grams'=>1000]],'shipping_address'=>['region'=>'Test Region','municipality'=>'Santa Cruz','province'=>'Laguna']]);
        $this->shipment=\App\Models\Shipment::create(['order_id'=>$this->order->id,'logistics_company'=>'Alpha','shipping_status'=>'ready_for_pickup','origin_hub'=>'Santa Cruz','origin_province'=>'Laguna','destination_hub'=>'Santa Cruz','destination_province'=>'Laguna','tracking_number'=>'RTS-AUDIT']);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['shipping_status'=>'out_for_delivery','courier_id'=>$this->rider->id]);
        DB::table('orders')->where('id',$this->order->id)->update(['status'=>'out_for_delivery']);
        DB::table('shipments')->where('id',$this->shipment->id)->update(['out_for_delivery_at'=>now()]);
        $a=\App\Models\DeliveryAssignment::create(['shipment_id'=>$this->shipment->id,'courier_id'=>$this->rider->id,'leg'=>'delivery','status'=>'assigned_to_rider']);
        DB::table('delivery_assignments')->where('id',$a->id)->update(['status'=>'out_for_delivery']);
        $this->actingAs($this->admin)->patchJson('/logistics/status/'.$this->shipment->id,['status'=>'delivery_failed','reason'=>'Recipient unavailable'])->assertRedirect();
        $this->patchJson('/logistics/status/'.$this->shipment->id,['status'=>'returned'])->assertStatus(422);
        $this->assertSame(4,(int)$product->fresh()->stock);
        $this->finishRts($this->shipment->id);
        $this->assertSame(5,(int)$product->fresh()->stock);
        $this->assertSame('returned',$this->order->fresh()->status);
        $before=DB::table('order_status_history')->where('order_id',$this->order->id)->count();
        $duplicate=$this->actingAs($this->admin)->patchJson('/logistics/status/'.$this->shipment->id,['status'=>'returned']);
        $this->assertSame(422,$duplicate->getStatusCode());
        $this->assertSame(5,(int)$product->fresh()->stock);
        $this->assertSame($before,DB::table('order_status_history')->where('order_id',$this->order->id)->count());
        $eligibility=$this->flow->eligibility($this->order->fresh(),'Alpha');
        $this->assertFalse($eligibility['eligible']);
        $this->assertDatabaseHas('logistics_financial_transactions',['id'=>$this->collection->id,'amount_cents'=>110000]);
        $this->assertSame(0,DB::table('logistics_financial_transactions')->where('collection_order_id',$this->order->id)->count());
        $this->observe('A04',['stock_before'=>4,'stock_after'=>5,'seller_receipt_action'=>true,'duplicate_http'=>422,'settlement'=>$eligibility,'other_collection_preserved'=>true,'failed_order_collection_count'=>0,'shipping_obligation_status'=>DB::table('logistics_shipping_obligations')->where('order_id',$this->order->id)->value('status'),'activity'=>DB::table('logistics_activity_logs')->where('record_id',$this->shipment->id)->get()->toArray(),'history'=>DB::table('order_status_history')->where('order_id',$this->order->id)->get()->toArray()]);
    }

    public function test_audit_issue_scope_resolution_history_and_lost_kind(): void
    {
        $notifications=DB::table('notifications')->count();
        $this->actingAs($this->admin)->post('/logistics/issues',['tracking_number'=>$this->shipment->tracking_number,'kind'=>'damaged','description'=>'Synthetic damaged packaging','hub_id'=>$this->hub->id,'proof'=>$this->proof('Synthetic damage evidence')])->assertRedirect()->assertSessionHasNoErrors();
        $issue=DB::table('logistics_issues')->first();$this->assertNotNull($issue);
        $this->patchJson('/logistics/issues/'.$issue->id,['status'=>'resolved'])->assertUnprocessable();
        $this->patch('/logistics/issues/'.$issue->id,['status'=>'in_progress','revision'=>0])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/logistics/issues/'.$issue->id,['status'=>'resolved','revision'=>1,'outcome'=>'damage_documented','resolution'=>'Inspected synthetic parcel; documented condition'])->assertRedirect();
        $events=DB::table('logistics_issue_events')->where('issue_id',$issue->id)->get();$this->assertCount(3,$events);
        $this->postJson('/logistics/issues',['tracking_number'=>$this->shipment->tracking_number,'kind'=>'lost','description'=>'Cannot locate parcel','hub_id'=>$this->hub->id])->assertRedirect();
        $other=$this->person('admin','Foreign');
        $this->actingAs($other)->patch('/logistics/issues/'.$issue->id,['status'=>'resolved','resolution'=>'Foreign actor'])->assertNotFound();
        $this->observe('A10',['events'=>$events->toArray(),'lost_kind_supported'=>true,'foreign_update_http'=>404,'shipment_status'=>$this->shipment->fresh()->shipping_status,'customer_notification_delta'=>DB::table('notifications')->count()-$notifications,'issue_columns'=>array_keys((array)$issue)]);
    }

    public function test_audit_operational_export_headers_scope_and_audit(): void
    {
        $this->actingAs($this->admin);$before=DB::table('logistics_activity_logs')->count();
        $response=$this->get('/logistics/reports/export');$response->assertOk();
        $csv=$response->streamedContent();$this->assertStringContainsString($this->shipment->tracking_number,$csv);
        $this->assertSame($before+1,DB::table('logistics_activity_logs')->count());
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->observe('A12_export',['headers'=>array_diff_key($response->headers->all(),['set-cookie'=>true]),'activity_delta'=>DB::table('logistics_activity_logs')->count()-$before,'contains_own_tracking'=>true]);
    }

    public function test_audit_operational_audit_failure_rolls_back_issue(): void
    {
        DB::unprepared("CREATE TRIGGER audit_failure BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'audit failure fixture'); END");
        $this->withoutExceptionHandling();
        try { $this->actingAs($this->admin)->post('/logistics/issues',['tracking_number'=>$this->shipment->tracking_number,'kind'=>'damaged','description'=>'Rollback fixture','hub_id'=>$this->hub->id]); $this->fail('Expected audit failure'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('audit failure fixture',$e->getMessage()); }
        $this->assertSame(0,DB::table('logistics_issues')->count());$this->assertSame(0,DB::table('logistics_issue_events')->count());
        $this->observe('A12_rollback',['issue_rows'=>0,'event_rows'=>0]);
    }

    public function test_audit_dashboard_current_metrics_and_links(): void
    {
        $this->actingAs($this->admin);$response=$this->get('/logistics/dashboard');$response->assertOk();
        $response->assertViewHas('total',1);$response->assertViewHas('completed',1);$response->assertViewHas('active',0);
        $this->person('admin','Foreign');
        $this->get('/logistics/monitor')->assertOk();$this->get('/logistics/requests')->assertOk();
        file_put_contents(storage_path('app/private/logistics-ui-review/remaining-dashboard.html'),$response->getContent());
        $this->observe('A14',['total'=>1,'completed'=>1,'active'=>0,'dashboard_http'=>200,'monitor_http'=>200,'requests_http'=>200]);
    }
    public function test_audit_verified_gcash_rts_preserves_payment_and_requires_manual_resolution(): void
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
        $this->actingAs($this->admin)->patch('/logistics/status/'.$ob->shipment_id,['status'=>'delivery_failed','reason'=>'No recipient'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson('/logistics/status/'.$ob->shipment_id,['status'=>'returned'])->assertStatus(422);
        $this->assertSame(4,(int)$product->fresh()->stock);
        $this->finishRts($ob->shipment_id);
        $p->refresh();$product->refresh();$ob=DB::table('logistics_shipping_obligations')->where('id',$ob->id)->first();
        $this->assertSame(5,(int)$product->stock);
        $this->assertSame('manual_resolution',$p->payment_state);
        $this->assertSame('released',$p->reservation_state);
        $this->assertSame(1,DB::table('logistics_shipping_payments')->where('obligation_id',$ob->id)->where('status','verified')->count());
        $this->observe('A04_gcash',['stock'=>5,'payment_state'=>$p->payment_state,'reservation_state'=>$p->reservation_state,'shipping_status'=>$ob->status,'shipping_receipt_count'=>1,'refund_inferred'=>false]);
    }
    public function test_audit_dashboard_approved_pickup_card_reaches_matching_queue(): void
    {
        $own=$this->shipment->getAttributes();unset($own['id']);$own['id']=(string)Str::uuid();$own['tracking_number']='AUDIT-APPROVED-PICKUP';$own['shipping_status']='ready_for_pickup';$own['pickup_approved_at']=now();$own['pickup_rider_id']=null;DB::table('shipments')->insert($own);
        $foreign=$own;$foreign['id']=(string)Str::uuid();$foreign['tracking_number']='AUDIT-FOREIGN';$foreign['logistics_company']='Foreign';DB::table('shipments')->insert($foreign);
        $this->actingAs($this->admin)->get('/logistics/dashboard')->assertOk()->assertViewHas('total',2)->assertViewHas('forVerify',1)->assertDontSee('AUDIT-FOREIGN');
        $requests=$this->get('/logistics/requests?queue=assignment');$requests->assertOk()->assertSee('AUDIT-APPROVED-PICKUP')->assertSee('Assign pickup Rider');
        $this->get('/logistics/requests')->assertOk()->assertDontSee('AUDIT-APPROVED-PICKUP');
        $this->get('/logistics/reports/export')->assertOk();$csv=$this->get('/logistics/reports/export')->streamedContent();$this->assertStringNotContainsString('AUDIT-FOREIGN',$csv);
        $this->observe('A14_pickup_link',['card_count'=>1,'destination_requests_contains_approved_pickup'=>true,'foreign_tracking_excluded'=>true]);
    }
    public function test_audit_dashboard_current_state_matrix_is_company_scoped(): void
    {
        $states=['ready_for_pickup','ready_for_pickup','picked_up','at_sorting_center','hub_transfer','sorted','assigned_to_rider','out_for_delivery','delivery_failed','returned','cancelled','completed'];
        foreach($states as $index=>$state){$row=$this->shipment->getAttributes();$row['id']=(string)Str::uuid();$row['tracking_number']='MATRIX-'.$index;$row['shipping_status']=$state;$row['pickup_approved_at']=$index===1?now():null;$row['pickup_rider_id']=null;DB::table('shipments')->insert($row);}
        $this->actingAs($this->admin)->get('/logistics/dashboard')->assertOk()->assertViewHas('total',13)->assertViewHas('active',6)->assertViewHas('completed',2)->assertViewHas('failed',1)->assertViewHas('pending',1)->assertViewHas('forVerify',1)->assertViewHas('available',1)->assertViewHas('inTransit',3)->assertViewHas('inProgress',2);
        $this->observe('A14_matrix',['total'=>13,'active'=>6,'completed'=>2,'failed'=>1,'pending'=>1,'forVerify'=>1,'sorted'=>1,'inTransit'=>3,'inProgress'=>2]);
    }
    protected function finishRts(string $shipment): void
    {
        $f=app(\App\Services\LogisticsRts::class);$base=['reason'=>'Actual local custody confirmed','confirmed'=>1];
        $f->act($this->admin,$shipment,$base+['action'=>'request','key'=>(string)Str::uuid(),'revision'=>0]);
        $f->act($this->rider,$shipment,$base+['action'=>'to_seller','key'=>(string)Str::uuid(),'revision'=>1]);
        $data=$base+['action'=>'seller_received','key'=>(string)Str::uuid(),'revision'=>2];
        $this->actingAs($this->seller)->get(route('seller.orders'))->assertOk()->assertSee('Confirm full sellable parcel received');
        $this->actingAs($this->seller)->post(route('seller.orders.rts',$shipment),$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('seller.orders.rts',$shipment),$data)->assertRedirect()->assertSessionHasNoErrors();
    }
}
