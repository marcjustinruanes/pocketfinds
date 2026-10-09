<?php

// Exercise the migrated application inside a transaction. No retained records
// are modified, and exception output never includes application records.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\ShopFollow;

if (config('database.default') !== 'mysql') {
    config(['database.default'=>'mysql','database.connections.mysql.host'=>'127.0.0.1','database.connections.mysql.port'=>3307,'database.connections.mysql.database'=>'pocketfinds','database.connections.mysql.username'=>'root','database.connections.mysql.password'=>env('MYSQL_MIGRATION_PASSWORD',env('DB_PASSWORD')),'database.connections.mysql.url'=>null]);
}
DB::purge('mysql');
$checks = [];
$failures = [];
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
function checkPage(string $path, ?User $user=null, int $expected=200): void {
    global $kernel,$checks,$failures;
    Auth::forgetGuards();
    if ($user) Auth::guard()->setUser($user);
    $request=Request::create('http://localhost:8000'.$path,'GET');
    $response=$kernel->handle($request);
    $status=$response->getStatusCode();
    if ($status!==$expected) $failures[]=$path.' returned '.$status.' instead of '.$expected;
    else $checks[]=$path;
    echo $path.': '.$status.PHP_EOL;
}
try {
    DB::beginTransaction();
    $home=$app->make(App\Http\Controllers\GuestController::class)->home(Request::create('/'));
    if ($home->getData()['dbError']) throw new RuntimeException('Guest product queries failed');
    if (count($home->getData()['products'])===0 && Product::where('status','active')->sellerApproved()->exists()) throw new RuntimeException('Guest products missing');
    echo 'Guest queries include migrated products; no fallback error.'.PHP_EOL;
    checkPage('/');
    checkPage('/login');
    checkPage('/register/buyer');
    checkPage('/register/seller');
    checkPage('/register/rider');
    checkPage('/register/logistics');
    $product=Product::where('status','active')->sellerApproved()->firstOrFail();
    checkPage('/product/'.$product->id);
    checkPage('/shop/'.($product->seller->username ?: 'shop-'.$product->seller_id));
    foreach (['buyer'=>['dashboard','browse','cart','orders','messages','account'], 'seller'=>['dashboard','orders','inventory','messages','account'], 'admin'=>['dashboard','registrations','products','messages','settings'], 'logistics'=>['dashboard','monitor','assignments','messages','account'], 'rider'=>['dashboard','deliveries','messages','account']] as $role=>$pages) {
        $user=$role==='admin' ? User::where('is_admin',true)->first() : User::where('account_type',$role)->where('status','approved')->first();
        if (!$user) { echo 'No approved '.$role.' account; role pages not tested.'.PHP_EOL; continue; }
        foreach ($pages as $page) checkPage('/'.$role.'/'.$page,$user);
    }
    $message=App\Models\Message::whereNotNull('attachment_path')->get()->first(fn ($m)=>Storage::disk('messages')->exists($m->attachment_path));
    if ($message) {
        checkPage('/message-media/'.$message->attachment_path,$message->sender);
        $outsider=User::whereNotIn('id',[$message->sender_id,$message->receiver_id])->first();
        if ($outsider) checkPage('/message-media/'.$message->attachment_path,$outsider,404);
    }
    $clone=User::firstOrFail()->replicate();
    $clone->email='mysql-migration-check-'.bin2hex(random_bytes(6)).'@example.invalid';
    $clone->username='migration-check-'.bin2hex(random_bytes(6));
    $clone->google_id=null;
    $clone->save();
    if (!$clone->id) throw new RuntimeException('User auto-increment failed');
    $newProduct=$product->replicate();
    $newProduct->id=null;
    $newProduct->sku=null;
    $newProduct->length_cm=1.5;
    $newProduct->images=['migration-check.png'];
    $newProduct->save();
    $newProduct->refresh();
    if ((float)$newProduct->length_cm!==1.5 || $newProduct->images!==['migration-check.png']) throw new RuntimeException('Decimal or JSON persistence failed');
    $follow=ShopFollow::create(['buyer_id'=>$clone->id,'seller_id'=>$product->seller_id]);
    if (!Illuminate\Support\Str::isUuid($follow->id)) throw new RuntimeException('ShopFollow UUID failed');
    $count=DB::selectOne("SELECT COUNT(*) AS n FROM orders JOIN JSON_TABLE(COALESCE(items, JSON_ARRAY()), '$[*]' COLUMNS (product_id VARCHAR(36) PATH '$.product_id', qty INT PATH '$.qty')) AS item ON TRUE");
    $checks[]='Eloquent writes, auto-increment, UUID, JSON, decimal, JSON_TABLE';
    $temporary='migration-check/'.bin2hex(random_bytes(8)).'.txt';
    foreach (['public','messages','profile_images'] as $disk) {
        Storage::disk($disk)->put($temporary,'mysql storage verification');
        if (Storage::disk($disk)->get($temporary)!=='mysql storage verification') throw new RuntimeException('Local upload readback failed');
        Storage::disk($disk)->delete($temporary);
    }
    $checks[]='Local upload/read/delete on three disks';
    DB::table('jobs')->insert(['queue'=>'migration-verification','payload'=>'{}','attempts'=>0,'available_at'=>time(),'created_at'=>time()]);
    $checks[]='Database queue write';
    DB::rollBack();
    if ($failures) throw new RuntimeException(implode('; ',$failures));
    file_put_contents(storage_path('app/private/mysql-migration/app-verified.json'),json_encode(['verified_at'=>gmdate('c'),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo 'Application checks passed; all test database writes rolled back.'.PHP_EOL;
} catch (Throwable $e) {
    if (DB::transactionLevel()>0) DB::rollBack();
    fwrite(STDERR,get_class($e)===RuntimeException::class ? $e->getMessage().PHP_EOL : 'App verification failed: '.get_class($e).' code '.$e->getCode().PHP_EOL);
    exit(1);
}
