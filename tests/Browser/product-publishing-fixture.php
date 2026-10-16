<?php

use App\Models\CartItem;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$metadata = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/teacher-test-audit/mysql-audit-database.json'), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/\Apocketfinds_teacher_audit_[0-9_]+\z/', $metadata['database'])) {
    throw new RuntimeException('Invalid audit database.');
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $metadata['database']]);
DB::purge('mysql');
if (($argv[1] ?? '') === 'prepare') {
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    DB::table('categories')->updateOrInsert(['id' => 88001], ['name' => 'Home & Living']);
    $seller = User::firstOrCreate(['username' => 'publishing_browser_audit'], ['email' => 'publishing.browser.audit@example.com',
        'given_names' => 'Maria Clara', 'last_name' => 'Santos', 'account_type' => 'seller', 'status' => 'approved',
        'business_name' => 'Publishing Audit Crafts', 'contact_no' => '09181234567', 'region' => 'Central Luzon', 'province' => 'Bulacan', 'municipality' => 'City of Malolos', 'barangay' => 'Anilao', 'street' => 'Artisan Street', 'password' => 'PublishingAudit@2026', 'category_id' => 88001]);
    $category = StoreCategory::firstOrCreate(['seller_id' => $seller->id, 'name_key' => 'kitchen & dining'], ['name' => 'Kitchen & Dining']);
    // Only fictional records owned by this fixture; never reset other audit accounts or the real database.
    CartItem::whereIn('product_id', Product::where('seller_id', $seller->id)->pluck('id'))->delete();
    foreach (Product::where('seller_id', $seller->id)->get() as $product) {
        if (! $product->hasUnfinishedOrders()) {
            $product->delete();
        }
    }
}
$seller = User::where('username', 'publishing_browser_audit')->firstOrFail();
if (($argv[1] ?? '') === 'cart') {
    $buyer = User::where('email', 'buyer@example.com')->firstOrFail();
    $product = Product::where('seller_id', $seller->id)->where('name', 'Buri Placemat Set')->firstOrFail();
    CartItem::updateOrCreate(['buyer_id' => $buyer->id, 'product_id' => $product->id], ['seller_slug' => $seller->username,
        'seller' => $seller->business_name, 'name' => $product->name, 'price' => $product->price, 'qty' => 1, 'variation_group' => '', 'variation_value' => '']);
}
echo json_encode(['seller_id' => $seller->id, 'category_id' => 88001,
    'store_category_id' => StoreCategory::where('seller_id', $seller->id)->value('id'),
    'products' => Product::where('seller_id', $seller->id)->with('storeCategories:id,name')->get()->toArray()]);
