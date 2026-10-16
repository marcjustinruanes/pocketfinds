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
    foreach (['seller1', 'seller2', 'buyer', 'other'] as $role) {
        User::firstOrCreate(['username' => 'cart_basics_'.$role], ['email' => 'cart.basics.'.$role.'@example.com', 'password' => 'CartBasics@2026',
            'given_names' => 'Cart Basics', 'last_name' => $role, 'account_type' => str_starts_with($role, 'seller') ? 'seller' : 'buyer', 'status' => 'approved',
            'business_name' => str_starts_with($role, 'seller') ? 'Cart Basics Store '.$role : null, 'contact_no' => '09187770002',
            'province' => 'Metro Manila', 'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'street' => 'Cart Basics Street', 'category_id' => 1]);
    }
}
$buyer = User::where('username', 'cart_basics_buyer')->firstOrFail();
$other = User::where('username', 'cart_basics_other')->firstOrFail();
$owners = User::whereIn('username', ['cart_basics_seller1', 'cart_basics_seller2'])->pluck('id');
if ($action === 'prepare') {
    CartItem::whereIn('buyer_id', [$buyer->id, $other->id])->delete();
    foreach (['seller1' => ['Rattan Fruit Basket', 450], 'seller2' => ['Cart Basics Graphic T-shirt', 399]] as $role => [$name, $price]) {
        $seller = User::where('username', 'cart_basics_'.$role)->firstOrFail();
        $product = Product::updateOrCreate(['seller_id' => $seller->id, 'name' => $name], ['category_id' => 1, 'price' => $price, 'stock' => 20, 'status' => 'active',
            'description' => 'Fictional test product for cart basics verification.', 'weight_grams' => 500]);
        CartItem::create(['buyer_id' => $other->id, 'product_id' => $product->id, 'seller_slug' => $seller->username, 'seller' => $seller->business_name,
            'name' => $product->name, 'price' => $price, 'qty' => 2]);
    }
    $secondSeller = User::where('username', 'cart_basics_seller2')->firstOrFail();
    Product::updateOrCreate(['seller_id' => $secondSeller->id, 'name' => 'Cart Basics Last Unit'], ['category_id' => 1, 'price' => 699, 'stock' => 1, 'status' => 'active']);
    $deleted = Product::firstOrCreate(['id' => 'abcdef09-1234-4234-8234-123456789012'], ['seller_id' => $owners[0], 'name' => 'Cart Basics Deleted Basket', 'price' => 100, 'stock' => 1, 'status' => 'active']);
    $deleted->delete();
    Product::updateOrCreate(['seller_id' => $owners[0], 'name' => 'Cart Basics Inactive Basket'], ['price' => 100, 'stock' => 1, 'status' => 'archived']);
}
echo json_encode(['deleted_id' => 'abcdef09-1234-4234-8234-123456789012', 'low_stock_alerts' => DB::table('notifications')->where('user_id', User::where('username', 'cart_basics_seller1')->value('id'))->where('notification_type', 'low_stock')->count(), 'products' => Product::whereIn('seller_id', $owners)->get()->toArray(), 'cart' => CartItem::where('buyer_id', $buyer->id)->get()->toArray(),
    'other_cart' => CartItem::where('buyer_id', $other->id)->get()->toArray(), 'orders' => Order::where('buyer_id', $buyer->id)->get()->toArray()]);
