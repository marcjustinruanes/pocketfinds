<?php

use App\Models\CartItem;
use App\Models\Order;
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
$action = $argv[1] ?? 'inspect';
if ($action === 'vacation-prepare') {
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    $buyer = User::firstOrCreate(['username' => 'vacation_audit_buyer'], [
        'email' => 'vacation.audit.buyer@example.com', 'password' => 'VacationAudit@2026', 'given_names' => 'Vacation Audit', 'last_name' => 'Buyer',
        'account_type' => 'buyer', 'status' => 'approved', 'contact_no' => '09170000201', 'province' => 'Metro Manila',
        'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'street' => 'Vacation Audit Street',
    ]);
    $seller = User::where('username', 'store_category_audit_1')->firstOrFail();
    $seller->update(['is_on_vacation' => false]);
    $product = Product::where('seller_id', $seller->id)->where('name', 'Rattan Fruit Basket')->firstOrFail();
    $product->update(['price' => 450, 'stock' => 20]);
    CartItem::where('buyer_id', $buyer->id)->delete();
    CartItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'seller_slug' => $seller->username,
        'seller' => $seller->business_name, 'name' => $product->name, 'price' => 450, 'qty' => 1]);
}
if ($action === 'prepare') {
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    foreach ([1, 2] as $number) {
        $seller = User::firstOrCreate(['username' => 'store_category_audit_'.$number], [
            'email' => "category.audit{$number}@example.com", 'given_names' => 'Category Audit', 'last_name' => "Seller {$number}",
            'account_type' => 'seller', 'status' => 'approved', 'password' => 'CategoryAudit@2026', 'business_name' => 'Category Audit Store '.$number,
            'province' => 'Metro Manila', 'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'category_id' => 1,
            'contact_no' => '0917000010'.$number,
        ]);
        StoreCategory::where('seller_id', $seller->id)->whereNotNull('parent_id')->delete();
        StoreCategory::where('seller_id', $seller->id)->delete();
    }
    $seller = User::where('username', 'store_category_audit_1')->firstOrFail();
    foreach (['Abaca Tote', 'Rattan Fruit Basket', 'Native Lamp', 'Buri Placemat Set'] as $name) {
        Product::firstOrCreate(['seller_id' => $seller->id, 'name' => $name], ['category_id' => 1,
            'description' => 'Fictional handmade product for store category verification.', 'price' => 249, 'stock' => 20, 'status' => 'active']);
    }
    $platformCategory = DB::table('categories')->where('name', "Women's Bags")->first();
    if (! $platformCategory) {
        $id = (int) DB::table('categories')->max('id') + 1;
        DB::table('categories')->insert(['id' => $id, 'name' => "Women's Bags"]);
        $platformCategory = (object) ['id' => $id];
    }
    Product::where('seller_id', $seller->id)->where('name', 'Abaca Tote')->update(['category_id' => $platformCategory->id]);
}
$seller = User::where('username', 'store_category_audit_1')->firstOrFail();
$vacationBuyer = User::where('username', 'vacation_audit_buyer')->first();
echo json_encode(['database' => $metadata['database'], 'seller_id' => $seller->id,
    'vacation' => $seller->is_on_vacation,
    'vacation_cart' => $vacationBuyer ? CartItem::where('buyer_id', $vacationBuyer->id)->get()->toArray() : [],
    'vacation_orders' => $vacationBuyer ? Order::where('buyer_id', $vacationBuyer->id)->count() : 0,
    'categories' => StoreCategory::where('seller_id', $seller->id)->with('products')->orderBy('position')->orderBy('id')->get()->toArray(),
    'products' => Product::where('seller_id', $seller->id)->get()->toArray()]);
