<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/Support.php';
require dirname(__DIR__).'/BuyerWeb.php';
require dirname(__DIR__).'/GoogleIdentity.php';
use App\Models\{User,Product,BuyerAddress,CartItem,PaymentMethod,Order};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
DB::beginTransaction();
try {
 $buyer=new User; $buyer->forceFill(['email'=>'latest-'.Str::random(12).'@example.invalid','username'=>'latest_'.Str::random(12),'given_names'=>'Latest','last_name'=>'Fixture','account_type'=>'buyer','status'=>'approved','password'=>'Testing-password-123!','province'=>'Laguna','region'=>'Region IV-A','municipality'=>'Famy','barangay'=>'Test','postal_code'=>'4021','contact_no'=>'09123456789','house_no'=>'1','street'=>'Test']);$buyer->save();
 $p=Product::where('status','active')->sellerApproved()->firstOrFail()->replicate(); $p->id=(string)Str::uuid();$p->forceFill(['name'=>'Temporary updated buyer test','sku'=>'TEST-'.Str::random(14),'variations'=>null,'variant_axes'=>null,'stock'=>20,'price'=>100,'discount_price'=>null,'weight_grams'=>100]);$p->save();
 $a=new BuyerAddress;$a->forceFill(['buyer_id'=>$buyer->id,'label'=>'Test','recipient_name'=>'Latest Fixture','contact_no'=>'09123456789','province'=>'Laguna','region'=>'Region IV-A','postal_code'=>'4021','municipality'=>'Famy','barangay'=>'Test','house_no'=>'1','street'=>'Test','is_default'=>true]);$a->save();
 $cart=new CartItem;$cart->forceFill(['id'=>(string)Str::uuid(),'buyer_id'=>$buyer->id,'product_id'=>$p->id,'seller'=>'Test store','seller_slug'=>$p->seller->username,'name'=>$p->name,'price'=>100,'qty'=>1,'img'=>'','variation_group'=>'','variation_value'=>'']);$cart->save();
 $market=new MobileMarket(new Identity($buyer));
 $selection=['cart_ids'=>[$cart->id],'delivery_address_id'=>$a->id,'website_checkout'=>true];
 $expected=app(App\Services\BuyerCommerce::class)->quote($buyer,[$cart->id],$a->id);
 $quote=$market->handle('quote',$selection);
 if($quote['total']!==$expected['total'])throw new RuntimeException('Website and mobile totals differ');
 echo "PASS native GCash quote matches latest shared website pricing\n";
 // Reuse the real Google verifier with a test signing key; never contact Google.
 config(['services.google.client_id'=>'buyer-web-test','cache.default'=>'array']);
 $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA,'config'=>__DIR__.'/openssl.cnf']);
 if($key===false)throw new RuntimeException('Unable to generate the temporary Google test key.');
 $parts=openssl_pkey_get_details($key);
 $b64=fn($value)=>rtrim(strtr(base64_encode($value),'+/','-_'),'=');
 Illuminate\Support\Facades\Cache::put('pocketfinds-mobile-google-keys',['keys'=>[['kty'=>'RSA','kid'=>'fixture','alg'=>'RS256','use'=>'sig','n'=>$b64($parts['rsa']['n']),'e'=>$b64($parts['rsa']['e'])]]],60);
 $email='buyer-'.Str::lower(Str::random(12)).'@gmail.com';
 $claims=['aud'=>'buyer-web-test','iss'=>'https://accounts.google.com','exp'=>time()+60,'sub'=>'fixture-google-user','email'=>$email,'email_verified'=>true];
 $token=Firebase\JWT\JWT::encode($claims,$key,'RS256','fixture');
 (new MobileBuyerWeb(new Identity($buyer)))->handle('google-email',['id_token'=>$token]);
 if($buyer->fresh()->email!==$email)throw new RuntimeException('Native Google email not saved');
 echo "PASS account Google email verification reuses the server identity verifier\n";
 foreach([['aud'=>'another-app'],['email_verified'=>false]] as $invalid) {
   $invalidToken=Firebase\JWT\JWT::encode(array_replace($claims,$invalid),$key,'RS256','fixture');
   try{(new MobileBuyerWeb(new Identity($buyer)))->handle('google-email',['id_token'=>$invalidToken]);throw new RuntimeException('Invalid Google identity accepted');}
   catch(ApiError $e){echo "PASS Google account update rejects invalid audience or unverified identity\n";}
 }
 $method=PaymentMethod::forBuyerCheckout()->get()->first(fn($m)=>$m->buyerCheckoutKind()==='gcash');
 $data=$selection+['checkout_id'=>(string)Str::uuid(),'quote_hash'=>$quote['quote_hash'],'payment_method_id'=>$method->id,'payment_flow'=>'platform'];
 $orders=$market->handle('checkout',$data);$o=Order::findOrFail($orders[0]['id']);
 if(!$o->platform_payment || $o->status!=='pending_payment' || !$o->commerce_snapshot)throw new RuntimeException('GCash pending order missing website snapshot');
 if($p->fresh()->stock!==19)throw new RuntimeException('Stock not reserved once');
 echo "PASS website checkout preserves admin-verified GCash and reserves stock\n";
 if($orders!==$market->handle('checkout',$data) || $p->fresh()->stock!==19)throw new RuntimeException('Duplicate checkout changed order or stock');
 echo "PASS duplicate checkout reuses order and reservation\n";
 $o->status='placed';try{$o->save();throw new RuntimeException('Unverified fulfillment allowed');}catch(Illuminate\Validation\ValidationException $e){echo "PASS unverified platform order cannot enter fulfillment\n";}
}finally{DB::rollBack();echo "All temporary orders, stock and buyers rolled back.\n";}



