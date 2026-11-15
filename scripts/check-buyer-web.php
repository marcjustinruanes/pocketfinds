<?php
/** Local read-only page smoke test using a temporary approved buyer, removed at exit. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/api/bootstrap.php';
require dirname(__DIR__).'/api/Support.php';
use App\Models\{User,Product,BuyerAddress,CartItem,PaymentMethod,Order};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
$cookie=tempnam(sys_get_temp_dir(),'pf-web-');$user=null;$checks=0;$photo=null;$upload=null;
function webHttp(string $path,?array $data=null,?string $bearer=null): array {
    global $cookie;
    $c=curl_init('http://127.0.0.1:8081'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>40]);
    if($data!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,json_encode($data));curl_setopt($c,CURLOPT_HTTPHEADER,['Content-Type: application/json','Accept: application/json',...($bearer?['Authorization: Bearer '.$bearer]:[])]);}
    $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);$type=curl_getinfo($c,CURLINFO_CONTENT_TYPE);$error=curl_error($c);curl_close($c);
    if($body===false)throw new RuntimeException($error);return [$status,$body,$type];
}
try {
    $user=new User;$user->forceFill(['email'=>'page-check-'.Str::random(14).'@example.invalid','username'=>'page_check_'.Str::random(12),'given_names'=>'Page','last_name'=>'Check','account_type'=>'buyer','status'=>'approved','password'=>'Testing-password-123!','email_verified_at'=>now(),'province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','region'=>'Region IV-A','postal_code'=>'4021','contact_no'=>'09123456789','house_no'=>'1','street'=>'Test Street']);$user->save();
    $raw=(new Identity($user))->session()['access_token'];
    [$status,$body]=webHttp('/buyer-web/session',['path'=>'/buyer/dashboard'],$raw);$handoff=json_decode($body,true);
    if($status!==200 || empty($handoff['ticket']))throw new RuntimeException('Could not issue web handoff: HTTP '.$status.' '.$body);
    [$status,$body]=webHttp('/mobile-buyer/session?ticket='.$handoff['ticket']);
    if($status!==200){file_put_contents(dirname(__DIR__).'/.tmp/web-page-error.html',$body);throw new RuntimeException('Web sign-in failed: HTTP '.$status);}
    $paths=['/buyer/dashboard','/buyer/browse','/buyer/cart','/buyer/orders','/buyer/orders?tab=refunds','/buyer/orders?tab=completed','/buyer/wishlist','/buyer/following','/buyer/messages','/buyer/announcements','/buyer/faqs','/buyer/account','/account/cancellation-refunds'];
    $product=Product::where('status','active')->sellerApproved()->first();
    if($product){$paths[]='/buyer/product/'.$product->id;if($product->seller->username)$paths[]='/buyer/shop/'.$product->seller->username;}
    foreach($paths as $path){[$status,$body]=webHttp($path);if($status!==200 || !str_contains($body,'mobile-buyer/bridge.js') || str_contains($body,'class="pf-assistant"')){file_put_contents(dirname(__DIR__).'/.tmp/web-page-error.html',$body);throw new RuntimeException($path.' failed HTTP '.$status.' or missing bridge/assistant exclusion');}$checks++;echo 'PASS actual website page '.$path."\n";}
    foreach(['/css/buyer.css','/css/marketplace.css','/css/buyer-pages.css','/js/buyer.js','/mobile-buyer/bridge.js','/js/buyer-order-gcash.js','/css/gcash-order-modal.css','/js/pdf-paper-preview.mjs','/vendor/pdfjs/pdf.min.mjs','/vendor/pdfjs/pdf.worker.min.mjs'] as $path){[$status,$body]=webHttp($path);if($status!==200 || strlen($body)<20)throw new RuntimeException('Missing website asset '.$path);$checks++;echo 'PASS website asset '.$path."\n";}
    [$status,$body]=webHttp('/buyer/cart');preg_match('/name="csrf-token" content="([^"]+)"/',$body,$csrf);$csrf=$csrf[1]??'';
    if(!$csrf)throw new RuntimeException('Missing real Laravel CSRF token');
    // Exercise a valid image above PHP's former 2 MB default using only this fixture.
    $photo=tempnam(sys_get_temp_dir(),'pf-photo-');$jpeg=file_get_contents(dirname(__DIR__).'/assets/brand/storefront.jpg');
    file_put_contents($photo,$jpeg.str_repeat("\0",max(0,3*1024*1024-strlen($jpeg))));
    $c=curl_init('http://127.0.0.1:8081/buyer/account/photo');
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>40,
        CURLOPT_REFERER=>'http://127.0.0.1:8081/buyer/account',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['_token'=>$csrf,'given_names'=>'Page','last_name'=>'Check','profile_picture'=>new CURLFile($photo,'image/jpeg','profile-test.jpg')]]);
    $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$upload=$user->fresh()->profile_picture;
    if($status!==200 || !$upload || !Storage::disk('profile_images')->exists($upload))throw new RuntimeException('Website multipart profile upload failed: HTTP '.$status);
    $checks++;echo "PASS actual website profile form accepts a valid 3 MB image\n";
    $gcash=PaymentMethod::forBuyerCheckout()->get()->first(fn($method)=>$method->buyerCheckoutKind()==='gcash');
    if($gcash && $product) {
        $stock=$product->stock;
        $cart=CartItem::create(['buyer_id'=>$user->id,'product_id'=>$product->id,'qty'=>1,'price'=>$product->price,'variation_group'=>'','variation_value'=>'','name'=>$product->name,'img'=>$product->image??'','seller'=>$product->seller->business_name?:$product->seller->given_names,'seller_slug'=>$product->seller->username]);
        [$status,$body]=webHttp('/buyer/cart');
        if($status!==200 || !str_contains($body,'id="checkoutForm"') || !str_contains($body,'data-quote-url=') || str_contains($body,'id="mobile-checkout"'))throw new RuntimeException('Missing actual website checkout form');
        $checks++;echo "PASS cart uses the actual website checkout UI\n";
        $payload=['_token'=>$csrf,'items'=>[$cart->id],'preview'=>true];
        [$status,$body]=webHttp('/buyer/commerce/quote',$payload);
        if($status!==200 || !isset(json_decode($body,true)['quote']))throw new RuntimeException('Website checkout quote failed HTTP '.$status);
        $checks++;echo "PASS website checkout quote endpoint works in embedded session\n";
        if(Order::where('buyer_id',$user->id)->exists() || $product->fresh()->stock!=$stock)throw new RuntimeException('Read-only quote changed orders or stock');
        $checks++;echo "PASS quote creates no order or stock reservation\n";
        [$status]=webHttp('/buyer/commerce/quote',array_replace($payload,['items'=>[(string)Str::uuid()]]));
        if(!in_array($status,[404,422],true))throw new RuntimeException('Foreign or missing cart was not rejected: HTTP '.$status);
        $checks++;echo "PASS quote rejects unowned or missing cart rows\n";
        unset($payload['_token']);[$status]=webHttp('/buyer/commerce/quote',$payload);
        if($status!==419)throw new RuntimeException('Embedded checkout did not enforce CSRF: HTTP '.$status);
        $checks++;echo "PASS embedded web forms retain Laravel CSRF enforcement\n";
    }
    [$status]=webHttp('/mobile-buyer/session?ticket='.$handoff['ticket']);
    if($status!==401)throw new RuntimeException('Used web handoff was accepted again');$checks++;echo "PASS HTTP handoff replay is rejected\n";
    [$status]=webHttp('/buyer/assistant/conversations');if($status!==404)throw new RuntimeException('Assistant endpoint remains enabled');$checks++;echo "PASS embedded assistant endpoint disabled\n";
    DB::table('personal_access_tokens')->where('tokenable_id',$user->id)->where('tokenable_type',User::class)->delete();
    [$status]=webHttp('/buyer/account');if($status!==401)throw new RuntimeException('Web cookie outlived revoked native session');$checks++;echo "PASS native session revocation blocks embedded pages\n";
    echo "$checks live buyer-page checks passed. No orders placed or real messages sent.\n";
} finally {
    if($upload)Storage::disk('profile_images')->delete($upload);
    if($photo && is_file($photo))unlink($photo);
    if($user){DB::table('personal_access_tokens')->where('tokenable_id',$user->id)->where('tokenable_type',User::class)->delete();CartItem::where('buyer_id',$user->id)->delete();BuyerAddress::where('buyer_id',$user->id)->delete();$user->delete();}
    if(is_file($cookie))unlink($cookie);
}
