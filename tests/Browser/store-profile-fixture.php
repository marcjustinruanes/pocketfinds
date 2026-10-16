<?php

use App\Models\Product;
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
    foreach (['store_profile_audit' => 'Maria Clara Handicrafts', 'store_profile_other' => 'RV Gadget Hub'] as $username => $name) {
        $user = User::firstOrCreate(['username' => $username], ['email' => $username.'@example.com', 'given_names' => 'Maria Clara', 'last_name' => 'Santos',
            'account_type' => 'seller', 'status' => 'approved', 'password' => 'StoreProfile@2026', 'contact_no' => '09189876543',
            'region' => 'Central Luzon', 'province' => 'Bulacan', 'municipality' => 'City of Malolos', 'barangay' => 'Anilao', 'street' => 'Artisan Street', 'category_id' => 1]);
        $user->update(['business_name' => $name, 'store_description' => null, 'profile_picture' => null, 'store_banner' => null,
            'region' => 'Central Luzon', 'province' => 'Bulacan', 'municipality' => 'City of Malolos', 'barangay' => 'Anilao', 'street' => 'Artisan Street']);
    }
    $seller = User::where('username', 'store_profile_audit')->firstOrFail();
    Product::firstOrCreate(['seller_id' => $seller->id, 'name' => 'Store Profile Rattan Basket'], ['category_id' => 1,
        'description' => 'Handmade basket used only for storefront audit.', 'price' => 450, 'stock' => 20, 'status' => 'active']);
}
$seller = User::where('username', 'store_profile_audit')->firstOrFail();
$other = User::where('username', 'store_profile_other')->firstOrFail();
echo json_encode(['seller' => $seller->only(['id', 'business_name', 'store_description', 'contact_no', 'profile_picture', 'store_banner', 'region', 'province', 'municipality', 'barangay', 'street']),
    'other' => $other->only(['id', 'business_name', 'store_description', 'contact_no', 'profile_picture', 'store_banner', 'region', 'province', 'municipality', 'barangay', 'street']),
    'serve_command' => get_class(Artisan::all()['serve'])]);
