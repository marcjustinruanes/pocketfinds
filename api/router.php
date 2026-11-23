<?php
declare(strict_types=1);
if(is_file(__DIR__.'/storage/database-maintenance.flag')) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Retry-After: 30');
    echo json_encode(['message'=>'The database is being updated. Please try again shortly.']);
    exit;
}
require_once __DIR__.'/Support.php';
try {
    require_once __DIR__.'/bootstrap.php';
    require_once __DIR__.'/Auth.php';
    require_once __DIR__.'/GoogleIdentity.php';
    require_once __DIR__.'/Registration.php';
    require_once __DIR__.'/Delivery.php';
    require_once __DIR__.'/Rider.php';
    require_once __DIR__.'/Pickup.php';
    require_once __DIR__.'/Buyer.php';
    require_once __DIR__.'/WebsiteBuyer.php';
    require_once __DIR__.'/BuyerWeb.php';
    require_once __DIR__.'/Market.php';
    require_once __DIR__.'/Aftercare.php';
    require_once __DIR__.'/Cart.php';
    require_once __DIR__.'/Catalog.php';
    require_once __DIR__.'/Push.php';
    MobileDelivery::registerGuard();
    require_once __DIR__.'/Queries.php';
    require_once __DIR__.'/Commerce.php';
    require_once __DIR__.'/Actions.php';
    require_once __DIR__.'/Messages.php';
    require_once __DIR__.'/Media.php';
    require_once __DIR__.'/Gcash.php';
    require_once __DIR__.'/PlatformPayments.php';
    require_once __DIR__.'/AccountForm.php';

    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    if ($_SERVER['REQUEST_METHOD']==='OPTIONS') { http_response_code(204); exit; }
    $path=trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/'),'/');
    if (in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true) && MobileBuyerWeb::asset($path)) exit;
    // Native bearer requests must never enter the cookie/CSRF website kernel.
    $nativeBuyer = str_starts_with($path,'buyer-api/') || (str_starts_with($path,'buyer/') &&
        $_SERVER['REQUEST_METHOD']==='POST' && str_starts_with($_SERVER['HTTP_AUTHORIZATION']??'','Bearer '));
    if (!$nativeBuyer && MobileBuyerWeb::isWeb($path)) MobileBuyerWeb::serve();
    $authorization=$_SERVER['HTTP_AUTHORIZATION']??'';
    $bearer=str_starts_with($authorization,'Bearer ')?substr($authorization,7):'';
    $identity=Identity::fromBearer($bearer);
    if ($identity->user) Illuminate\Support\Facades\Auth::setUser($identity->user);
    if (isset($_GET['media_token']) && (str_starts_with($path,'media/') || str_starts_with($path,'storage/'))) $identity=MobileMedia::mediaIdentity($_GET['media_token']);
    if (in_array($path,['account/reset','account/email'],true)) accountForm($path);
    if ($path==='health') {
        Illuminate\Support\Facades\DB::select('SELECT 1');
        $result=['status'=>'ok','database'=>'pocketfinds','driver'=>'mysql'];
    } elseif (str_starts_with($path,'media/')) {
        $parts=explode('/',$path,3); ensure(count($parts)===3,'File not found.',404);
        (new MobileMedia($identity))->serve($parts[1],$parts[2]);
    } elseif (str_starts_with($path,'storage/')) {
        (new MobileMedia($identity))->serve('products',substr($path,8));
    } elseif (preg_match('#^categories/(\d+)/image$#',$path,$m)) {
        $category=Illuminate\Support\Facades\DB::table('categories')->where('id',$m[1])->first(['image_data','image_mime']);
        ensure($category && $category->image_data,'Image not found.',404);
        ensure(in_array($category->image_mime,['image/jpeg','image/png','image/webp'],true),'Unsupported image.',404);
        header('Content-Type: '.$category->image_mime); echo $category->image_data; exit;
    } else {
        ensure($_SERVER['REQUEST_METHOD']==='POST','Endpoint not found.',404);
        ensure((int)($_SERVER['CONTENT_LENGTH']??0)<=29000000,'Request is too large.',413);
        $body=file_get_contents('php://input');
        try { $data=json_decode($body?:'{}',true,32,JSON_THROW_ON_ERROR); } catch (JsonException) { throw new ApiError('Invalid JSON.',400); }
        ensure(is_array($data),'Expected a JSON object.',400);
        $result=match(true) {
            str_starts_with($path,'buyer-web/')=>(new MobileBuyerWeb($identity))->handle(substr($path,10),$data),
            str_starts_with($path,'payments/')=>(new MobilePlatformPayments($identity))->handle(substr($path,9),$data),
            $path==='rider/web-module'=>(new MobileRiderWebModule($identity))->handle($data),
            str_starts_with($path,'rider/')=>(new MobileRider($identity))->handle(substr($path,6),$data),
            $path==='push/device'=>MobilePush::device($identity,$data),
            str_starts_with($path,'catalog/')=>MobileCatalog::handle(substr($path,8),$data),
            $path==='cart/add'=>(new MobileCart($identity))->handle($data),
            $path==='cart/edit'=>(new MobileCart($identity))->edit($data),
            str_starts_with($path,'market/')=>(new MobileMarket($identity))->handle(substr($path,7),$data),
            str_starts_with($path,'aftercare/')=>(new MobileAftercare($identity))->handle(substr($path,10),$data),
            str_starts_with($path,'buyer-api/')=>(new MobileBuyer($identity))->handle(substr($path,10),$data),
            str_starts_with($path,'buyer/')=>(new MobileBuyer($identity))->handle(substr($path,6),$data),
            str_starts_with($path,'website-buyer/')=>(new WebsiteBuyer($identity))->handle(substr($path,14),$data),
            str_starts_with($path,'delivery/')=>(new MobileDelivery($identity))->handle(substr($path,9),$data),
            str_starts_with($path,'pickup/')=>(new MobilePickup($identity))->handle(substr($path,7),$data),
            $path==='query'=>(new MobileQueries($identity))->handle($data),
            $path==='checkout'=>(new MobileCommerce($identity))->checkout($data),
            $path==='checkout/quote'=>(new MobileCommerce($identity))->quote($data),
            $path==='upload'=>(new MobileMedia($identity))->upload($data),
            str_starts_with($path,'gcash/')=>(new MobileGcash($identity))->handle(substr($path,6),$data),
            str_starts_with($path,'auth/')=>(new MobileAuth($identity,$bearer))->handle(substr($path,5),$data),
            str_starts_with($path,'rpc/')=>(new MobileActions($identity))->handle(substr($path,4),$data),
            default=>throw new ApiError('Endpoint not found.',404),
        };
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    $status=$e instanceof ApiError?$e->status:($e instanceof Illuminate\Validation\ValidationException?422:
        ($e instanceof Symfony\Component\HttpKernel\Exception\HttpExceptionInterface?$e->getStatusCode():(($e instanceof Illuminate\Database\Eloquent\ModelNotFoundException || $e instanceof Illuminate\Database\RecordNotFoundException)?404:500)));
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if ($status===500) error_log((string)$e);
    $message=$e instanceof Illuminate\Validation\ValidationException?$e->validator->errors()->first():$e->getMessage();
    echo json_encode(['message'=>$status===500?'The local API could not complete this request. Check the API console.':$message]);
}
