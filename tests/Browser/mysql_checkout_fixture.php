<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$metadata = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/teacher-test-audit/mysql-audit-database.json'), true);
if (! preg_match('/\Apocketfinds_teacher_audit_[0-9_]+\z/', $metadata['database'])) {
    throw new RuntimeException('A separate audit database is required.');
}
config(['database.connections.mysql.database' => $metadata['database'], 'database.default' => 'mysql']);
\Illuminate\Support\Facades\DB::purge('mysql');
$path = dirname(__DIR__, 2).'/storage/app/private/teacher-audit/checkout-concurrency.json';
if (($argv[1] ?? '') === 'inspect') {
    $fixture = json_decode(file_get_contents($path), true);
    $result = ['remaining_stock' => \App\Models\Product::findOrFail($fixture['product'])->total_stock,
        'orders' => \App\Models\Order::whereIn('buyer_id', $fixture['buyers'])->count(),
        'remaining_cart_items' => \App\Models\CartItem::whereIn('id', $fixture['carts'])->count()];
    echo json_encode($result).PHP_EOL;
    exit;
}
$run = bin2hex(random_bytes(6));
$seller = \App\Models\User::where('username', 'audit_seller')->firstOrFail();
$product = \App\Models\Product::create(['seller_id' => $seller->id, 'category_id' => 1, 'name' => 'Audit Last Unit '.$run,
    'description' => 'A fictional basket used only in the teacher audit database.', 'price' => 100, 'stock' => 1,
    'status' => 'active', 'weight_grams' => 800, 'condition' => 'new']);
$fixture = ['database' => $metadata['database'], 'product' => $product->id, 'buyers' => [], 'carts' => [], 'barrier' => storage_path('app/private/teacher-audit/checkout-go-'.$run)];
for ($i = 0; $i < 2; $i++) {
    $buyer = \App\Models\User::create(['account_type' => 'buyer', 'status' => 'approved', 'given_names' => 'Audit', 'last_name' => 'Buyer',
        'email' => 'race'.$i.$run.'@example.com', 'username' => 'race'.$i.$run, 'contact_no' => '0917000000'.($i + 1),
        'province' => 'Metro Manila', 'municipality' => 'Makati City', 'barangay' => 'Bel-Air', 'street' => 'Audit Street']);
    $cart = \App\Models\CartItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'seller_slug' => $seller->username,
        'seller' => 'Audit Seller', 'name' => $product->name, 'price' => 100, 'qty' => 1, 'variation_group' => '', 'variation_value' => '']);
    $fixture['buyers'][] = $buyer->id;
    $fixture['carts'][] = $cart->id;
}
file_put_contents($path, json_encode($fixture));
echo json_encode($fixture).PHP_EOL;
