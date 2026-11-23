<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/bootstrap.php';
foreach (['Support','Auth','GoogleIdentity','Registration','Delivery','Queries','Commerce','Actions','Messages','Media','Gcash','Buyer'] as $file) require dirname(__DIR__)."/$file.php";
MobileDelivery::registerGuard();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\PaymentMethod;

$checks=0;
function check(bool $condition,string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++; echo "PASS $message\n";
}
function denied(Closure $operation,string $message): void {
    try { $operation(); } catch(ApiError $e) { check($e->status>=400,$message); return; }
    throw new RuntimeException('Expected rejection: '.$message);
}
function fixtureUser(string $role): User {
    $tag=Str::lower(Str::random(14));
    $id=DB::table('users')->insertGetId(['email'=>"mobile-test-$tag@example.invalid",'username'=>"mobile_test_$tag",'password'=>Hash::make('Testing-password-123!'),
        'given_names'=>'Integration','last_name'=>'Fixture','sex'=>'female','birthday'=>'1995-01-01','age'=>31,'contact_no'=>'09123456789',
        'province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','house_no'=>'1','street'=>'Test Street','account_type'=>$role,'status'=>'approved','email_verified_at'=>now()]);
    return User::findOrFail($id);
}

DB::beginTransaction();
try {
    $guest=new MobileQueries(new Identity(null));
    $products=$guest->handle(['table'=>'products','columns'=>'id,name,price','limit'=>5]);
    check(count($products)>0,'catalog reads existing MySQL products');
    denied(fn()=>$guest->handle(['table'=>'users','columns'=>'id,email,password']),'public seller query cannot expose credentials or email');
    denied(fn()=>$guest->handle(['table'=>'orders']),'guest cannot read orders');
    denied(fn()=>$guest->handle(['table'=>'users','action'=>'update','data'=>['is_admin'=>true]]),'generic client cannot change account privileges');
    $buyer=fixtureUser('buyer'); $other=fixtureUser('buyer'); $rider=fixtureUser('rider');
    $identity=new Identity($buyer); $queries=new MobileQueries($identity); $actions=new MobileActions($identity);
    Illuminate\Support\Facades\Auth::setUser($buyer);
    $session=(new MobileAuth(new Identity(null),''))->handle('login',['email'=>$buyer->email,'password'=>'Testing-password-123!']);
    check(Identity::fromBearer($session['access_token'])->id()===$buyer->id,'password login and persisted bearer use the existing users table');
    denied(fn()=>(new MobileAuth(new Identity(null),''))->handle('login',['email'=>$buyer->email,'password'=>'wrong']),'incorrect password is rejected');
    $product=Product::where('status','active')->sellerApproved()->where('stock','>',2)->get()->first(fn($p)=>empty($p->variations) && empty($p->variant_axes) && !$p->seller->is_on_vacation);
    check($product!==null,'checkout fixture uses an available existing product');
    $stock=$product->stock;
    $queries->handle(['table'=>'cart_items','action'=>'insert','data'=>['buyer_id'=>$buyer->id,'product_id'=>$product->id,'qty'=>1,'price'=>0.01,'variation_group'=>'','variation_value'=>'']]);
    $cart=$queries->handle(['table'=>'cart_items','columns'=>'id,price,qty']);
    check(count($cart)===1 && (float)$cart[0]['price']===(float)($product->discount_price??$product->price),'cart price is computed on the server');
    $foreign=(new MobileQueries(new Identity($other)))->handle(['table'=>'cart_items','filters'=>[['column'=>'buyer_id','op'=>'=','value'=>$buyer->id]]]);
    check($foreign===[],'buyer cannot read another buyer’s cart');
    denied(fn()=>$queries->handle(['table'=>'cart_items','action'=>'update','data'=>['qty'=>-2]]),'invalid cart quantity is rejected');
    $method=PaymentMethod::forBuyerCheckout()->get()->first(fn($p)=>$p->buyerCheckoutKind()==='cod');
    check($method!==null,'existing COD payment method is available');
    $request=['cart_ids'=>[$cart[0]['id']],'checkout_id'=>(string)Str::uuid(),'payment_method_id'=>$method->id,
        'shipping_address'=>['house_no'=>'1','street'=>'Test Street','barangay'=>'Test','municipality'=>'Famy','province'=>'Laguna']];
    $bad=$request+['voucher_code'=>'MOBILE-INVALID-VOUCHER'];
    denied(fn()=>(new MobileCommerce($identity))->checkout($bad),'invalid voucher rolls back the whole checkout');
    check((int)$product->fresh()->stock===(int)$stock && DB::table('cart_items')->where('id',$cart[0]['id'])->exists(),'failed checkout preserves stock and cart');
    $result=(new MobileCommerce($identity))->checkout($request);
    check(count($result)===1 && Order::find($result[0]['id'])->buyer_id===$buyer->id,'checkout creates a native website order');
    check((int)$product->fresh()->stock===(int)$stock-1,'checkout reserves actual product stock');
    check((new MobileCommerce($identity))->checkout($request)===$result,'checkout retry returns the same order');
    check(Order::where('buyer_id',$buyer->id)->count()===1,'retry does not create a duplicate order');
    check(!DB::table('cart_items')->where('id',$cart[0]['id'])->exists(),'successful checkout removes purchased cart rows');
    $gcash=PaymentMethod::forBuyerCheckout()->get()->first(fn($p)=>$p->buyerCheckoutKind()==='gcash');
    check($gcash!==null,'existing GCash payment method is available');
    $queries->handle(['table'=>'cart_items','action'=>'insert','data'=>['product_id'=>$product->id,'qty'=>1,'variation_group'=>'','variation_value'=>'']]);
    $gcashCart=$queries->handle(['table'=>'cart_items','columns'=>'id']);
    $gcashRequest=$request;
    $gcashRequest['cart_ids']=[$gcashCart[0]['id']]; $gcashRequest['checkout_id']=(string)Str::uuid(); $gcashRequest['payment_method_id']=$gcash->id;
    $pending=(new MobileCommerce($identity))->checkout($gcashRequest);
    check($pending[0]['payment_pending']===true && Order::where('buyer_id',$buyer->id)->count()===1,'GCash creates a reservation before an order');
    $purchases=(new MobileGcash($identity))->handle('list',[]);
    check(count($purchases)===1 && $purchases[0]['placement']['eligible']===false,'unverified GCash payment cannot place an order');
    $purchase=\App\Models\GcashPurchase::findOrFail($purchases[0]['id']);
    check(!$purchases[0]['can_upload_proof'],'receipt upload waits for actual seller instructions');
    app(\App\Services\GcashWorkflow::class)->instructions(User::findOrFail($purchase->seller_id),$purchase->id,$purchase->revision,'Fixture Seller','09123456789');
    $payment=(new MobileGcash($identity))->handle('list',[])[0];
    check($payment['instructions']['name']==='Fixture Seller' && $payment['can_upload_proof'],'saved seller instructions and receipt upload are available directly on the payment screen');
    check(DB::table('notifications')->where('user_id',$buyer->id)->where('notification_type','gcash_purchase')->where('reference_id',$purchase->id)->exists(),'persistent purchase notification can resume the same payment');
    DB::beginTransaction();
    try {
        $workflow=app(\App\Services\GcashWorkflow::class);
        // Fixture-only receipt metadata; no money is transferred or receipt file sent.
        $proof=(string)Str::uuid();
        $workflow->proof($buyer,$purchase->id,$proof,'gcash-proofs/'.$proof.'.png','image/png',100,str_repeat('a',64));
        $purchase->refresh(); $seller=User::findOrFail($purchase->seller_id);
        $workflow->decide($seller,$purchase->id,$purchase->revision,true,(string)$purchase->amount_due,null,strtoupper(Str::random(12)));
        $verified=(new MobileGcash($identity))->handle('list',[])[0];
        check($verified['placement']['eligible']===true,'verified purchase can resume order placement without another checkout');
        check(DB::table('notifications')->where('user_id',$buyer->id)->where('title','GCash payment verified')->where('reference_id',$purchase->id)->exists(),'verification creates a buyer notification');
        $paid=$workflow->place($buyer,$purchase->id,$verified['revision']);
        $buyerApi=new MobileBuyer($identity);
        check($buyerApi->handle('order',['order_id'=>$paid->id])['prepaid']===true,'prepaid order exposes reconciliation notice before cancellation');
        $buyerApi->handle('cancel',['order_id'=>$paid->id,'cancellation_reason'=>'Changed my mind']);
        check($paid->fresh()->status==='cancelled' && $purchase->fresh()->payment_state==='manual_resolution','prepaid cancellation requires manual reconciliation and does not claim a refund');
    } finally { DB::rollBack(); }
    check((new MobileGcash(new Identity($other)))->handle('list',[])===[],'another buyer cannot see a GCash reservation');
    $orders=$queries->handle(['table'=>'orders','columns'=>'id,status,app_buyer_id,items','filters'=>[['column'=>'status','op'=>'=','value'=>'to_ship']]]);
    check(count($orders)===1 && $orders[0]['status']==='placed' && is_array($orders[0]['items']),'native order status is preserved and JSON is decoded for Flutter');
    denied(fn()=>(new MobileActions(new Identity($other)))->handle('fetch_order_status_history',['p_order_id'=>$result[0]['id']]),'another buyer cannot read order history');
    $hub=App\Models\LogisticsHub::create(['company_name'=>'Integration rider','municipality'=>'Famy','province'=>'Laguna','is_operational'=>true]);
    $rider->forceFill(['business_name'=>'Integration rider','logistics_hub_id'=>$hub->id])->save();
    $shipment=new Shipment(); $shipment->forceFill(['order_id'=>$result[0]['id'],'courier_id'=>$rider->id,'tracking_number'=>'TEST-'.Str::uuid(),'logistics_company'=>'Integration rider','destination_hub'=>'Famy','destination_province'=>'Laguna','shipping_status'=>'assigned_to_rider']); $shipment->save();
    $riderActions=new MobileActions(new Identity($rider));
    denied(fn()=>$riderActions->handle('update_delivery_status',['p_shipment_id'=>$shipment->id,'p_status'=>'delivered']),'rider cannot skip delivery stages');
    $delivery=new MobileDelivery(new Identity($rider));
    $delivery->handle('pickup',['shipment_id'=>$shipment->id,'request_id'=>(string)Str::uuid(),'parcel_code'=>$shipment->tracking_number]);
    $delivery->handle('start',['shipment_id'=>$shipment->id,'request_id'=>(string)Str::uuid()]);
    // Test-only recipient challenge; mail generation/delivery is covered in rider.php.
    $code=sprintf('%06d',random_int(0,999999));
    DB::table('delivery_verifications')->where('shipment_id',$shipment->id)->update(['otp_hash'=>Hash::make($code),'otp_recipient_id'=>$buyer->id,'otp_expires_at'=>now()->addMinutes(10)]);
    $deliveryEvidence=[];
    if(MobileRider::installed()) {
        $capture=$delivery->handle('capture',['shipment_id'=>$shipment->id]);$proofPath=$rider->id.'/'.$capture['token'].'.jpg';
        Illuminate\Support\Facades\Storage::disk('local')->put('delivery-proofs/'.$proofPath,file_get_contents(dirname(__DIR__,2).'/assets/brand/storefront.jpg'));
        $deliveryEvidence=['photo'=>$proofPath,'latitude'=>14.4,'longitude'=>121.4,'cod_amount'=>(float)Order::find($result[0]['id'])->total];
    }
    $delivery->handle('verify-otp',['shipment_id'=>$shipment->id,'request_id'=>(string)Str::uuid(),'code'=>$code]+$deliveryEvidence);
    check($shipment->fresh()->shipping_status==='delivered','rider delivery advances through valid native stages');
    $actions->handle('confirm_order_received',['p_shipment_id'=>$shipment->id]);
    check(Order::find($result[0]['id'])->status==='completed','buyer confirmation completes the website order');
    $review=['p_order_id'=>$result[0]['id'],'p_product_id'=>$product->id,'p_rating'=>5,'p_comment'=>'Integration fixture','p_is_anonymous'=>true];
    $actions->handle('submit_review',$review);
    denied(fn()=>$actions->handle('submit_review',$review),'duplicate review cannot earn rewards twice');
    $reviews=$actions->handle('fetch_product_reviews',['p_product_id'=>$product->id]);
    $saved=collect($reviews)->firstWhere('comment','Integration fixture');
    check($saved['reviewer_name']==='Anonymous buyer' && !isset($saved['buyer_id']),'anonymous review does not expose buyer identity');
    $messages=new MobileMessages($identity);
    $message=$messages->handle('send_message',['p_receiver_id'=>$product->seller_id,'p_body'=>'Integration fixture']);
    check(isset($message['id']),'mobile messages use the website messages table');
    $messages->handle('block_user',['p_user_id'=>$product->seller_id]);
    denied(fn()=>$messages->handle('send_message',['p_receiver_id'=>$product->seller_id,'p_body'=>'Blocked']),'blocked conversation cannot send messages');
    denied(fn()=>(new MobileMedia($identity))->upload(['bucket'=>'profile-images','path'=>$other->id.'/avatar.png','content'=>'x']),'upload cannot target another account’s directory');
    $ticket=MobileMedia::mediaIdentity($session['media_token']);
    check($ticket->id()===$buyer->id,'private media ticket resolves its own account');
    (new MobileAuth(Identity::fromBearer($session['access_token']),$session['access_token']))->handle('logout',[]);
    denied(fn()=>MobileMedia::mediaIdentity($session['media_token']),'logout revokes private media access');
    echo "$checks integration checks passed. All fixture writes rolled back.\n";
} finally {
    DB::rollBack();
    if(isset($proofPath))Illuminate\Support\Facades\Storage::disk('local')->delete('delivery-proofs/'.$proofPath);
}
