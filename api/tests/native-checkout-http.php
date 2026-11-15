<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/Support.php';
use App\Models\{User,Product,CartItem,BuyerAddress,PaymentMethod,Order};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
$base='http://127.0.0.1:8081';$buyer=null;$product=null;$checks=0;
function httpCheck(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);++$checks;echo "PASS $message\n";}
function postCheckoutHttp(string $path,array $body=[],?string $token=null):array {
 global $base;
 $context=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nAccept: application/json\r\n".($token?"Authorization: Bearer $token\r\n":''),'content'=>json_encode($body),'ignore_errors'=>true,'timeout'=>30]]);
 $raw=file_get_contents($base.'/'.$path,false,$context);
 $headers=$http_response_header??[];
 preg_match('/\s(\d{3})\s/',$headers[0]??'',$status);
 httpCheck((bool)array_filter($headers,fn($h)=>stripos($h,'Content-Type: application/json')===0),"$path returns JSON, not website HTML");
 return [(int)($status[1]??0),json_decode($raw,true,512,JSON_THROW_ON_ERROR)];
}
function cleanupCheckoutHttp(User $buyer, ?Product $product): void {
 DB::transaction(function()use($buyer,$product){
  $orders=Order::where('buyer_id',$buyer->id)->pluck('id');
  $obligations=DB::table('commission_obligations')->whereIn('order_id',$orders)->pluck('id');
  foreach(['commission_events','commission_lines'] as $table)DB::table($table)->whereIn('obligation_id',$obligations)->delete();
  DB::table('commission_obligations')->whereIn('id',$obligations)->delete();
  DB::table('notifications')->whereIn('reference_id',$orders)->delete();
  DB::table('commerce_checkouts')->where('buyer_id',$buyer->id)->delete();
  $addressIds=BuyerAddress::where('buyer_id',$buyer->id)->pluck('id');
  DB::table('mobile_address_details')->whereIn('address_id',$addressIds)->delete();
  DB::table('orders')->whereIn('id',$orders)->delete();
  CartItem::where('buyer_id',$buyer->id)->delete();BuyerAddress::where('buyer_id',$buyer->id)->delete();
  DB::table('personal_access_tokens')->where('tokenable_type',User::class)->where('tokenable_id',$buyer->id)->delete();
  if($product)DB::table('products')->where('id',$product->id)->delete();
  DB::table('users')->where('id',$buyer->id)->delete();
 });
}
if(($argv[1]??'')==='--cleanup-buyer'){
 $fixture=User::whereKey((int)($argv[2]??0))->where('given_names','HTTP')->where('last_name','Fixture')->where('email','like','http-%@example.invalid')->firstOrFail();
 $productIds=CartItem::where('buyer_id',$fixture->id)->pluck('product_id');
 $fixtureProduct=Product::whereIn('id',$productIds)->where('name','HTTP temporary checkout fixture')->first();
 cleanupCheckoutHttp($fixture,$fixtureProduct);echo "Interrupted HTTP fixture removed.\n";exit;
}
try {
 [$status]=postCheckoutHttp('buyer-api/addresses');httpCheck($status===401,'missing authentication returns an API 401');
 $tag=Str::lower(Str::random(12));$buyer=new User;$buyer->forceFill(['email'=>'http-'.$tag.'@example.invalid','username'=>'http_'.$tag,'given_names'=>'HTTP','last_name'=>'Fixture','account_type'=>'buyer','status'=>'approved','password'=>'Testing-12345!','region'=>'Region IV-A','province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','postal_code'=>'4021','house_no'=>'1','street'=>'Test','contact_no'=>'09123456789','email_verified_at'=>now()]);$buyer->save();
 $token=(new Identity($buyer))->session()['access_token'];
 [$status,$addresses]=postCheckoutHttp('buyer-api/addresses',[],$token);
 httpCheck($status===200 && count($addresses)===1 && $addresses[0]['region']==='Region IV-A','HTTP initialization loads the buyer saved/default address');
 [$legacyStatus,$legacyAddresses]=postCheckoutHttp('buyer/addresses',[],$token);
 httpCheck($legacyStatus===200 && $legacyAddresses[0]['id']===$addresses[0]['id'],'older native clients retain bearer-authenticated address access');
 [$status,$methods]=postCheckoutHttp('query',['table'=>'payment_methods','action'=>'select','columns'=>'id,name,description','filters'=>[],'orders'=>[]],$token);
 httpCheck($status===200 && count($methods)>0,'HTTP initialization loads allowed checkout payment methods');
 $product=Product::where('status','active')->sellerApproved()->firstOrFail()->replicate();$product->id=(string)Str::uuid();$product->forceFill(['name'=>'HTTP temporary checkout fixture','sku'=>'HTTP-'.Str::random(14),'variations'=>null,'variant_axes'=>null,'variant_rows'=>null,'stock'=>3,'price'=>100,'discount_price'=>null,'weight_grams'=>100]);$product->save();
 $cart=CartItem::create(['buyer_id'=>$buyer->id,'product_id'=>$product->id,'qty'=>1,'price'=>100,'variation_group'=>'','variation_value'=>'','name'=>$product->name,'img'=>'','seller'=>'Fixture','seller_slug'=>$product->seller->username]);
 $selection=['cart_ids'=>[$cart->id],'delivery_address_id'=>$addresses[0]['id'],'website_checkout'=>true,'website_payment'=>true,'voucher_codes'=>[]];
 [$status,$quote]=postCheckoutHttp('market/quote',$selection,$token);
 httpCheck($status===200 && ($quote['total']??0)>0 && strlen($quote['quote_hash']??'')===64,'HTTP checkout loads the authoritative website quote');
 $method=PaymentMethod::forBuyerCheckout()->firstOrFail();
 [$status]=postCheckoutHttp('market/checkout',$selection+['payment_method_id'=>$method->id,'checkout_id'=>(string)Str::uuid(),'quote_hash'=>str_repeat('0',64)],$token);
 httpCheck($status===422 && !DB::table('orders')->where('buyer_id',$buyer->id)->exists(),'HTTP checkout rejects stale totals without creating an order');
 $fixtureOrder=Order::create(['order_number'=>'HTTP-'.strtoupper(Str::random(12)),'buyer_id'=>$buyer->id,'seller_id'=>$product->seller_id,'status'=>'placed','items'=>[['product_id'=>$product->id,'name'=>$product->name,'qty'=>1,'price'=>100]],'subtotal'=>100,'shipping_amount'=>0,'discount_amount'=>0,'total'=>100,'shipping_address'=>['region'=>'Region IV-A'],'payment_method_id'=>$method->id,'payment_method'=>$method->name]);
 [$status,$detail]=postCheckoutHttp('buyer-api/order',['order_id'=>$fixtureOrder->id],$token);
 httpCheck($status===200 && $detail['status']==='placed' && $detail['can_review']===false,'HTTP order details and actions load without HTML and cannot review uncompleted purchases');
 [$status,$detail]=postCheckoutHttp('buyer/order',['order_id'=>$fixtureOrder->id],$token);
 httpCheck($status===200 && $detail['status']==='placed','older native clients also receive JSON order details');
 $cod=PaymentMethod::forBuyerCheckout()->get()->first(fn($m)=>$m->buyerCheckoutKind()==='cod');
 httpCheck($cod!==null,'COD is available for buyer checkout');
 $payload=$selection+['payment_method_id'=>$cod->id,'checkout_id'=>(string)Str::uuid(),'quote_hash'=>$quote['quote_hash']];
 [$status,$placed]=postCheckoutHttp('market/checkout',$payload,$token);
 httpCheck($status===200 && count($placed)===1 && !($placed[0]['payment_pending']??true),'buyer can place an actual COD order through the running API');
 $actual=Order::where('buyer_id',$buyer->id)->where('id','!=',$fixtureOrder->id)->firstOrFail();
 httpCheck($actual->status==='placed' && (float)$actual->total===(float)$quote['total'],'created order stores the quoted total and placed status');
 httpCheck((int)$product->fresh()->stock===2 && !CartItem::find($cart->id),'successful HTTP checkout reserves stock and removes purchased cart rows');
 [$status,$retried]=postCheckoutHttp('market/checkout',$payload,$token);
 httpCheck($status===200 && $retried===$placed && Order::where('buyer_id',$buyer->id)->count()===2 && (int)$product->fresh()->stock===2,'retry returns the same order without duplicate orders or stock deductions');
 [$status,$detail]=postCheckoutHttp('buyer-api/order',['order_id'=>$actual->id],$token);
 httpCheck($status===200 && $detail['status']==='placed','buyer can open the newly purchased order');
 echo "$checks HTTP checkout checks passed.\n";
} finally {
 if($buyer){
  cleanupCheckoutHttp($buyer,$product);
 }
 echo "Temporary HTTP buyer, address, cart, product and tokens removed.\n";
}
