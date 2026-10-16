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

$action = $argv[1] ?? 'inspect';
if ($action === 'prepare') {
    DB::table('categories')->updateOrInsert(['id' => 88201], ['name' => 'Validation Audit Home & Living']);
    User::firstOrCreate(['username' => 'product_validation_audit'], ['email' => 'product.validation.audit@example.com', 'password' => 'ProductValidation@2026',
        'given_names' => 'Product Validation', 'last_name' => 'Seller', 'account_type' => 'seller', 'status' => 'approved', 'business_name' => 'Product Validation Crafts',
        'contact_no' => '09187770003', 'province' => 'Metro Manila', 'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'street' => 'Product Validation Street', 'category_id' => 88201]);
}
$seller = User::where('username', 'product_validation_audit')->firstOrFail();
echo json_encode(['products' => Product::where('seller_id', $seller->id)->get()->toArray()]);
