<?php

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
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
if ($action === 'prepare') {
    foreach (['seller' => 'Cart Audit Crafts', 'buyer' => null] as $role => $business) {
        User::firstOrCreate(['username' => 'cart_browser_'.$role], ['email' => 'cart.browser.'.$role.'@example.com', 'password' => 'CartAudit@2026',
            'given_names' => 'Cart Audit', 'last_name' => ucfirst($role), 'account_type' => $role, 'status' => 'approved', 'business_name' => $business,
            'contact_no' => '09181234991', 'province' => 'Metro Manila', 'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'street' => 'Cart Audit Street', 'category_id' => 1]);
    }
}
$seller = User::where('username', 'cart_browser_seller')->firstOrFail();
$buyer = User::where('username', 'cart_browser_buyer')->firstOrFail();
if ($action === 'prepare') {
    CartItem::where('buyer_id', $buyer->id)->delete();
    foreach (['Rattan Fruit Basket' => 450, 'Cart Audit Deleted Basket' => 100, 'Cart Audit Available Lamp' => 200] as $name => $price) {
        $product = Product::updateOrCreate(['seller_id' => $seller->id, 'name' => $name], ['category_id' => 1, 'price' => $price, 'discount_price' => null, 'stock' => 10, 'status' => 'active',
            'description' => 'Fictional cart verification product with sufficient detail.', 'weight_grams' => 600]);
        CartItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'seller_slug' => $seller->username, 'seller' => $seller->business_name,
            'name' => $name, 'price' => $price, 'qty' => 1, 'variation_group' => '', 'variation_value' => '']);
    }
}
if ($action === 'restore-cart') {
    $product = Product::where('seller_id', $seller->id)->where('name', 'Rattan Fruit Basket')->firstOrFail();
    if ($product->stock < 1) {
        throw new RuntimeException('Restore cart before stock changes.');
    }
    CartItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'seller_slug' => $seller->username, 'seller' => $seller->business_name, 'name' => $product->name, 'price' => $product->price, 'qty' => 1]);
}
if ($action === 'quantity-five') {
    CartItem::where('buyer_id', $buyer->id)->where('product_id', Product::where('seller_id', $seller->id)->where('name', 'Rattan Fruit Basket')->value('id'))->update(['qty' => 5]);
}
echo json_encode(['products' => Product::where('seller_id', $seller->id)->get()->toArray(), 'cart' => CartItem::where('buyer_id', $buyer->id)->get()->toArray(),
    'orders' => Order::where('buyer_id', $buyer->id)->get()->toArray()]);
