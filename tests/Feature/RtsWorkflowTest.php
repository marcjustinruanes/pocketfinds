<?php
namespace Tests\Feature;
use App\Models\{DeliveryAssignment,Order,Shipment,Product};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RtsWorkflowTest extends RemainingLogisticsAuditTest
{
    protected function failed(): array
    {
        $product=Product::create(['seller_id'=>$this->seller->id,'name'=>'RTS fixture','price'=>1000,'stock'=>4,'status'=>'active','weight_grams'=>1000]);
        $o=Order::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'preparing','payment_method'=>'Cash on Delivery','subtotal'=>1000,'shipping_amount'=>100,'discount_amount'=>0,'total'=>1100,'commerce_snapshot'=>['vouchers'=>[]],'items'=>[['product_id'=>$product->id,'name'=>'RTS fixture','qty'=>1,'price'=>1000,'weight_grams'=>1000]],'shipping_address'=>['region'=>'Test Region','municipality'=>'Santa Cruz','province'=>'Laguna']]);
        $s=Shipment::create(['order_id'=>$o->id,'logistics_company'=>'Alpha','shipping_status'=>'ready_for_pickup','origin_hub'=>'Santa Cruz','origin_province'=>'Laguna','destination_hub'=>'Santa Cruz','destination_province'=>'Laguna']);
        $a=DeliveryAssignment::create(['shipment_id'=>$s->id,'courier_id'=>$this->rider->id,'leg'=>'delivery','status'=>'assigned_to_rider']);
        DB::table('delivery_assignments')->where('id',$a->id)->update(['status'=>'out_for_delivery']);
        DB::table('shipments')->where('id',$s->id)->update(['shipping_status'=>'delivery_failed','out_for_delivery_at'=>now()->subHour(),'delivery_failed_at'=>now(),'courier_id'=>$this->rider->id]);DB::table('orders')->where('id',$o->id)->update(['status'=>'delivery_failed']);return [$s,$product];
    }
    private function dataFor(string $action,int $revision): array {return ['action'=>$action,'revision'=>$revision,'key'=>(string)Str::uuid(),'reason'=>'Actual physical custody confirmed','confirmed'=>1];}
    public function test_rts_staff_custody_and_negative_authority(): void
    {
        [$s,$p]=$this->failed();$flow=app(\App\Services\LogisticsRts::class);
        $flow->act($this->admin,$s->id,$this->dataFor('request',0));
        $this->actingAs($this->seller)->postJson(route('seller.orders.rts',$s->id),$this->dataFor('seller_received',1))->assertForbidden();
        $flow->act($this->rider,$s->id,$this->dataFor('to_hub',1));
        $this->actingAs($this->admin)->postJson(route('logistics.rts',$s->id),$this->dataFor('hub_received',2))->assertForbidden();
        $flow->act($this->staff,$s->id,$this->dataFor('hub_received',2));$this->assertSame(4,(int)$p->fresh()->stock);
        $flow->act($this->staff,$s->id,$this->dataFor('hub_released',3));$flow->act($this->rider,$s->id,$this->dataFor('to_seller',4));
        $other=$this->person('seller');$this->actingAs($other)->postJson(route('seller.orders.rts',$s->id),$this->dataFor('seller_received',5))->assertForbidden();
        $flow->act($this->seller,$s->id,$this->dataFor('seller_received',5));$this->assertSame(5,(int)$p->fresh()->stock);$this->assertSame('returned',$s->fresh()->shipping_status);
        $this->assertSame(0,DB::table('logistics_financial_transactions')->where('collection_shipment_id',$s->id)->count());
        $this->assertSame(0,DB::table('logistics_compensations')->where('source_id',DeliveryAssignment::where('shipment_id',$s->id)->value('id'))->where('status','earned')->count());
    }
    public function test_rts_audit_failure_rolls_back_custody_request(): void
    {
        [$s,$p]=$this->failed();DB::unprepared("CREATE TRIGGER rts_audit_failure BEFORE INSERT ON logistics_financial_events BEGIN SELECT RAISE(ABORT, 'rts audit failure'); END");
        try{app(\App\Services\LogisticsRts::class)->act($this->admin,$s->id,$this->dataFor('request',0));$this->fail('Expected audit failure');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('rts audit failure',$e->getMessage());}
        $this->assertSame(0,DB::table('logistics_issues')->where('rts_shipment_id',$s->id)->count());$this->assertSame(4,(int)$p->fresh()->stock);
    }
    public function test_rts_cross_hub_return_is_held_and_suspended_rider_cannot_attest(): void
    {
        [$s,$p]=$this->failed();DB::table('shipments')->where('id',$s->id)->update(['origin_hub'=>'Other Origin']);
        $f=app(\App\Services\LogisticsRts::class);$f->act($this->admin,$s->id,$this->dataFor('request',0));
        $this->actingAs($this->rider)->postJson(route('rider.rts',$s->id),$this->dataFor('to_seller',1))->assertUnprocessable();
        $f->act($this->rider,$s->id,$this->dataFor('to_hub',1));$f->act($this->staff,$s->id,$this->dataFor('hub_received',2));
        $this->actingAs($this->staff)->postJson(route('logistics.rts',$s->id),$this->dataFor('hub_released',3))->assertForbidden();$this->assertSame(4,(int)$p->fresh()->stock);
        [$local,$unused]=$this->failed();$f->act($this->admin,$local->id,$this->dataFor('request',0));DB::table('users')->where('id',$this->rider->id)->update(['status'=>'suspended']);
        try{$f->act($this->rider,$local->id,$this->dataFor('to_seller',1));$this->fail('Suspended rider attested custody.');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$this->assertSame(403,$e->getStatusCode());}
    }
}
