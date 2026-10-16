<?php

use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Fictional data exclusively in the retained teacher audit database.
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
    $buyer = User::firstOrCreate(['username' => 'buyer_account_audit'], [
        'email' => 'buyer.account.audit@example.com', 'given_names' => 'Juan Miguel', 'last_name' => 'Dela Cruz',
        'status' => 'approved', 'account_type' => 'buyer', 'contact_no' => '09170000011', 'password' => 'Mamimili@2026',
        'sex' => 'male', 'birthday' => '1998-05-14', 'age' => 28,
        'province' => 'Metro Manila', 'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'street' => 'Original Home Street',
    ]);
    $buyer->update(['email' => 'buyer.account.audit@example.com', 'given_names' => 'Juan Miguel', 'last_name' => 'Dela Cruz',
        'contact_no' => '09170000011', 'password' => 'Mamimili@2026', 'profile_picture' => null]);
    // Earlier audit fixtures may already use the workbook's proposed spare phone.
    // Move only fictional audit occupants so this case starts with an unused number.
    foreach (User::where('contact_no', '09170000001')->whereKeyNot($buyer->id)->get() as $occupant) {
        $occupant->update(['contact_no' => '0918'.str_pad((string) $occupant->id, 7, '0', STR_PAD_LEFT)]);
    }
    BuyerAddress::where('buyer_id', $buyer->id)->delete();
    DB::table('account_verification_tokens')->where('user_id', $buyer->id)->delete();
    BuyerAddress::create(['buyer_id' => $buyer->id, 'label' => 'Original Home', 'recipient_name' => 'Juan Miguel Dela Cruz',
        'contact_no' => '09170000011', 'region' => 'National Capital Region (NCR)', 'province' => 'Metro Manila',
        'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'street' => 'Original Home Street', 'is_default' => true]);
}
$buyer = User::where('username', 'buyer_account_audit')->firstOrFail();
if ($action === 'cart') {
    $seller = User::where('username', 'audit_seller')->firstOrFail();
    $product = Product::firstOrCreate(['seller_id' => $seller->id, 'name' => 'Address Audit Basket'], [
        'category_id' => 1, 'description' => 'Fictional product for address selection audit.', 'price' => 450, 'stock' => 20, 'status' => 'active']);
    CartItem::firstOrCreate(['buyer_id' => $buyer->id, 'product_id' => $product->id], [
        'seller_slug' => $seller->username, 'seller' => $seller->business_name, 'name' => $product->name, 'qty' => 1, 'price' => 450]);
}
if ($action === 'fill-nine') {
    while (BuyerAddress::where('buyer_id', $buyer->id)->count() < 9) {
        BuyerAddress::create(['buyer_id' => $buyer->id, 'label' => 'Limit fixture', 'recipient_name' => 'Juan Miguel',
            'contact_no' => '09170000011', 'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'City of Makati',
            'barangay' => 'Bel-Air', 'street' => 'Limit Test Street', 'is_default' => false]);
    }
}
if ($action === 'expire-email') {
    DB::table('account_verification_tokens')->where('user_id', $buyer->id)->where('purpose', 'change_email')->update(['expires_at' => now()->subSecond()]);
}
if ($action === 'duplicates') {
    foreach (['buyer' => ['anamarie.reyes@example.com', '09173456789'], 'seller' => ['mariaclara.santos@example.com', '09170000022']] as $role => [$email, $phone]) {
        $other = User::firstOrCreate(['username' => 'account_duplicate_'.$role], ['email' => $email,
            'given_names' => 'Audit Duplicate', 'last_name' => ucfirst($role), 'contact_no' => $phone, 'account_type' => $role,
            'status' => 'approved', 'password' => 'Duplicate@2026', 'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug']);
        if ($role === 'buyer') {
            $foreign = BuyerAddress::firstOrCreate(['buyer_id' => $other->id, 'label' => 'Foreign Address'], ['recipient_name' => 'Other Buyer', 'contact_no' => $phone,
                'region' => 'Central Visayas', 'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug', 'street' => 'Private Foreign Street', 'is_default' => true]);
        }
    }
    echo json_encode(['foreign_address_id' => $foreign->id]);
    exit;
}
if ($action === 'snapshot') {
    $address = BuyerAddress::where('buyer_id', $buyer->id)->where('label', 'Makati office')->firstOrFail();
    $seller = User::where('username', 'audit_seller')->firstOrFail();
    $order = Order::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'ADDR-AUDIT-'.time(),
        'status' => 'placed', 'items' => [['name' => 'Address Audit Basket', 'qty' => 1, 'price' => 450]], 'subtotal' => 450,
        'shipping_amount' => 50, 'total' => 500, 'payment_method' => 'Cash on Delivery', 'shipping_address' => $address->toShippingArray()]);
    Shipment::create(['order_id' => $order->id, 'tracking_number' => 'ADDR'.time(), 'shipping_status' => 'pending']);
    echo json_encode(['order_id' => $order->id, 'snapshot' => $order->shipping_address]);
    exit;
}
$orders = Order::where('buyer_id', $buyer->id)->where('order_number', 'like', 'ADDR-AUDIT-%')->get();
echo json_encode(['database' => $metadata['database'], 'buyer' => $buyer->only(['id', 'given_names', 'email', 'contact_no', 'profile_picture']),
    'addresses' => BuyerAddress::where('buyer_id', $buyer->id)->get()->toArray(),
    'snapshots' => $orders->map(fn ($order) => ['id' => $order->id, 'address' => $order->shipping_address])->all()]);
