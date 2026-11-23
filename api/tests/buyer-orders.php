<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
foreach (['Support','Buyer','Commerce','Queries','Actions','Delivery'] as $file) require dirname(__DIR__)."/$file.php";
use App\Models\{User,Order,Product,BuyerAddress,PaymentMethod};
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
$checks=0;
function check(bool $ok,string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; echo "PASS $message\n"; }
function denied(Closure $fn,string $message): void { try { $fn(); } catch(ApiError|Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { check(true,$message); return; } throw new RuntimeException('Expected rejection: '.$message); }
function buyerFixture(): User {
    $tag=Str::lower(Str::random(12)); $u=new User(); $u->forceFill(['email'=>"buyer-$tag@example.invalid",'username'=>'buyer_'.$tag,'password'=>'Testing-password-123!',
        'given_names'=>'Buyer','last_name'=>'Fixture','sex'=>'female','birthday'=>'1995-01-01','age'=>31,'contact_no'=>'09123456789','region'=>'Region IV-A','province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','house_no'=>'1','street'=>'Test Street','account_type'=>'buyer','status'=>'approved','email_verified_at'=>now()]); $u->save(); return $u;
}
DB::beginTransaction();
try {
    $buyer=buyerFixture(); $other=buyerFixture(); $service=new MobileBuyer(new Identity($buyer)); $outsider=new MobileBuyer(new Identity($other));
    $source=Product::where('status','active')->whereHas('seller',fn($q)=>$q->where('status','approved'))->firstOrFail();
    $product=$source->replicate(); $product->id=(string)Str::uuid(); $product->name='Buyer workflow fixture'; $product->sku='AUD-'.Str::random(12); $product->creation_key=null;
    $product->variations=[]; $product->variant_axes=null; $product->variant_rows=null; $product->stock=10; $product->price=50; $product->discount_price=null; $product->save();
    $items=[['product_id'=>$product->id,'name'=>$product->name,'price'=>50,'qty'=>1,'variation_group'=>'','variation_value'=>'']];
    $makeOrder=fn($status,$customItems=null)=>Order::create(['buyer_id'=>$buyer->id,'seller_id'=>$product->seller_id,'order_number'=>'BUYER-'.Str::random(12),'payment_method'=>'COD','items'=>$customItems??$items,'subtotal'=>$customItems?5:50,'shipping_amount'=>0,'discount_amount'=>0,'total'=>$customItems?5:50,'status'=>$status,'inventory_reserved'=>false]);
    $order=$makeOrder('placed');
    check($service->handle('order',['order_id'=>$order->id])['can_cancel'],'placed order offers cancellation');
    denied(fn()=>$outsider->handle('order',['order_id'=>$order->id]),'buyer cannot read another buyer order actions');
    denied(fn()=>$service->handle('cancel',['order_id'=>$order->id,'cancellation_reason'=>'Invented']),'cancellation requires a website reason');
    $service->handle('cancel',['order_id'=>$order->id,'cancellation_reason'=>'Ordered by mistake']);
    $history=DB::table('order_status_history')->where('order_id',$order->id)->count();
    $service->handle('cancel',['order_id'=>$order->id,'cancellation_reason'=>'Ordered by mistake']);
    check($order->fresh()->status==='cancelled' && DB::table('order_status_history')->where('order_id',$order->id)->count()===$history,'cancellation retry does not duplicate stock/history actions');
    $sent=$makeOrder('out_for_delivery');
    denied(fn()=>$service->handle('cancel',['order_id'=>$sent->id,'cancellation_reason'=>'Changed my mind']),'buyer cannot cancel after pickup');
    $delivered=$makeOrder('delivered');
    denied(fn()=>$service->handle('review',['order_id'=>$delivered->id,'rating'=>5]),'delivered order cannot be rated before receipt confirmation');
    $complete=$makeOrder('completed');
    $service->handle('review',['order_id'=>$complete->id,'rating'=>4,'comment'=>'Native buyer review']);
    check($complete->review()->count()===1 && !$service->handle('order',['order_id'=>$complete->id])['can_review'],'completed order accepts one website-style review');
    denied(fn()=>$service->handle('review',['order_id'=>$complete->id,'rating'=>5]),'duplicate order review is rejected');
    $request=['order_id'=>$complete->id,'request_id'=>(string)Str::uuid()];
    $service->handle('buy-again',$request); $service->handle('buy-again',$request);
    $cart=DB::table('cart_items')->where('buyer_id',$buyer->id)->where('product_id',$product->id)->first();
    check($cart && $cart->qty===1 && (float)$cart->price===50.0,'Buy Again uses current price and retry does not duplicate quantity');
    $queries=new MobileQueries(new Identity($buyer));
    $completed=$queries->handle(['table'=>'orders','columns'=>'id,status','filters'=>[['column'=>'status','op'=>'=','value'=>'completed']]]);
    check(count($completed)===1 && $completed[0]['id']===$complete->id,'Completed tab excludes Delivered orders');
    $rows=$queries->handle(['table'=>'orders','columns'=>'id,status','filters'=>[['column'=>'status','op'=>'=','value'=>'delivered']]]);
    check(count($rows)===1 && $rows[0]['status']==='delivered','Delivered tab retains separate handover state');
    $stale=$makeOrder('placed');
    DB::table('shipments')->insert(['id'=>(string)Str::uuid(),'order_id'=>$stale->id,'shipping_status'=>'picked_up','picked_up_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
    check(!$service->handle('order',['order_id'=>$stale->id])['can_cancel'],'pickup blocks cancellation even when the order status is stale');
    denied(fn()=>$service->handle('cancel',['order_id'=>$stale->id,'cancellation_reason'=>'Changed my mind']),'server rejects cancellation when the parcel was already picked up');
    $optionProduct=$product->replicate();$optionProduct->id=(string)Str::uuid();$optionProduct->sku='OPTION-'.Str::random(12);
    $optionProduct->variations=[['name'=>'Options','options'=>[['value'=>'Red / M','price'=>80,'stock'=>2]]]];$optionProduct->stock=2;$optionProduct->save();
    $oldOrder=$makeOrder('completed',[['product_id'=>$optionProduct->id,'name'=>'Previous name','price'=>1,'qty'=>5,'variation_group'=>'Options','variation_value'=>'Red / M']]);
    $service->handle('buy-again',['order_id'=>$oldOrder->id,'request_id'=>(string)Str::uuid()]);
    $optionCart=DB::table('cart_items')->where('buyer_id',$buyer->id)->where('product_id',$optionProduct->id)->first();
    check($optionCart && (float)$optionCart->price===80.0 && $optionCart->qty===2,'Buy Again uses current variation prices and caps quantity to available inventory');
    $optionProduct->variations=[];$optionProduct->stock=0;$optionProduct->save();
    denied(fn()=>$service->handle('buy-again',['order_id'=>$oldOrder->id,'request_id'=>(string)Str::uuid()]),'Buy Again rejects removed or unavailable variations');
    echo "$checks buyer order checks passed.\n";
} finally { DB::rollBack(); echo "Order fixtures rolled back; backend rules unchanged.\n"; }
