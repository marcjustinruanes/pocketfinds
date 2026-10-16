<?php

use App\Http\Controllers\Concerns\FetchesProducts;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
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
    foreach ([88101 => 'Home & Living', 88102 => 'Search Audit Fashion'] as $id => $name) {
        DB::table('categories')->updateOrInsert(['id' => $id], ['name' => $name]);
    }
    foreach (['manila' => 'Metro Manila', 'bulacan' => 'Bulacan'] as $area => $province) {
        User::firstOrCreate(['username' => 'search_browser_'.$area], ['email' => 'search.browser.'.$area.'@example.com', 'password' => 'SearchAudit@2026',
            'given_names' => 'Search Audit', 'last_name' => 'Seller', 'account_type' => 'seller', 'status' => 'approved', 'contact_no' => '09181234568',
            'province' => $province, 'municipality' => $area === 'manila' ? 'City of Makati' : 'City of Malolos', 'barangay' => 'Audit Barangay', 'category_id' => 88101]);
    }
    $manila = User::where('username', 'search_browser_manila')->firstOrFail();
    $bulacan = User::where('username', 'search_browser_bulacan')->firstOrFail();
    $buyer = User::where('email', 'buyer@example.com')->firstOrFail();
    $definitions = [
        ['Rattan Fruit Basket', 450, 88101, $manila, 'active', 5],
        ['Search Audit Low Rating', 350, 88101, $manila, 'active', 3],
        ['Search Audit Bulacan Decor', 500, 88101, $bulacan, 'active', 5],
        ['Search Audit Fashion', 700, 88102, $manila, 'active', 5],
        ['Search Audit Inactive', 400, 88101, $manila, 'archived', 0],
        ['Search Audit Draft', 400, 88101, $manila, 'draft', 0],
    ];
    foreach (range(1, 25) as $n) {
        $definitions[] = [sprintf('Search Audit Page %02d', $n), 100 + $n, 88101, $manila, 'active', 0];
    }
    foreach ($definitions as $i => [$name, $price, $category, $owner, $status, $rating]) {
        $product = Product::updateOrCreate(['seller_id' => $owner->id, 'name' => $name], ['description' => 'Fictional search audit product only.', 'price' => $price, 'discount_price' => null, 'stock' => 20,
            'category_id' => $category, 'status' => $status, 'created_at' => now()->subDays(10)->addMinutes($i)]);
        DB::table('products')->where('id', $product->id)->update(['created_at' => now()->subDays(10)->addMinutes($i)]);
        if ($rating) {
            $order = Order::firstOrCreate(['order_number' => 'SEARCH-AUDIT-'.$product->id], ['buyer_id' => $buyer->id, 'seller_id' => $owner->id,
                'status' => 'completed', 'items' => [['product_id' => $product->id, 'name' => $name, 'qty' => $rating, 'price' => $price]],
                'subtotal' => $price * $rating, 'total' => $price * $rating, 'payment_method' => 'Cash on Delivery']);
            Review::updateOrCreate(['product_id' => $product->id, 'buyer_id' => $buyer->id], ['seller_id' => $owner->id, 'order_id' => $order->id, 'rating' => $rating, 'comment' => 'Fictional search audit rating.']);
        }
    }
    $deleted = Product::firstOrCreate(['id' => 'abcdef01-1234-4234-8234-123456789012'], ['seller_id' => $manila->id, 'category_id' => 88101, 'name' => 'Search Audit Deleted', 'price' => 400, 'stock' => 1, 'status' => 'active']);
    $deleted->delete();
}
$owners = User::whereIn('username', ['search_browser_manila', 'search_browser_bulacan'])->pluck('id');
$suggester = new class
{
    use FetchesProducts;

    public function suggestion()
    {
        return $this->productSuggestion('Ratan baskit', null);
    }
};
echo json_encode(['suggestion' => $suggester->suggestion(), 'products' => Product::whereIn('seller_id', $owners)->get()->toArray()]);
