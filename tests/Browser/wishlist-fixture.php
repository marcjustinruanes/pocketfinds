<?php

use App\Models\Product;
use App\Models\ShopFollow;
use App\Models\User;
use App\Models\WishlistEntry;
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
if ($action === 'prepare') {
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    foreach (['seller', 'seller_other', 'buyer', 'other'] as $role) {
        User::firstOrCreate(['username' => 'wishlist_audit_'.$role], ['email' => 'wishlist.audit.'.$role.'@example.com', 'password' => 'WishlistAudit@2026',
            'given_names' => 'Wishlist Audit', 'last_name' => ucfirst($role), 'account_type' => str_starts_with($role, 'seller') ? 'seller' : 'buyer', 'status' => 'approved',
            'business_name' => $role === 'seller' ? 'Wishlist Audit Crafts' : null, 'contact_no' => '09187770001', 'province' => 'Metro Manila', 'municipality' => 'City of Makati',
            'barangay' => 'Bel-Air', 'street' => 'Wishlist Audit Street', 'category_id' => 1]);
    }
    $seller = User::where('username', 'wishlist_audit_seller')->firstOrFail();
    $buyer = User::where('username', 'wishlist_audit_buyer')->firstOrFail();
    WishlistEntry::where('buyer_id', $buyer->id)->delete();
    ShopFollow::where('buyer_id', $buyer->id)->where('seller_id', $seller->id)->delete();
    foreach (['Rattan Fruit Basket', 'Wishlist Audit T-shirt'] as $name) {
        Product::updateOrCreate(['seller_id' => $name === 'Rattan Fruit Basket' ? $seller->id : User::where('username', 'wishlist_audit_seller_other')->value('id'), 'name' => $name], ['category_id' => 1, 'description' => 'Fictional wishlist audit product.', 'price' => 450, 'stock' => 20, 'status' => 'active']);
    }
}
$seller = User::where('username', 'wishlist_audit_seller')->firstOrFail();
$buyer = User::where('username', 'wishlist_audit_buyer')->firstOrFail();
echo json_encode(['seller_id' => $seller->id, 'products' => Product::whereIn('seller_id', User::whereIn('username', ['wishlist_audit_seller', 'wishlist_audit_seller_other'])->pluck('id'))->get()->toArray(),
    'wishlist' => WishlistEntry::where('buyer_id', $buyer->id)->get()->toArray(),
    'following' => ShopFollow::where('buyer_id', $buyer->id)->get()->toArray(), 'followers' => $seller->followers()->count()]);
