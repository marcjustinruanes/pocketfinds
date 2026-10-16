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
if (in_array($action, ['migrate', 'prepare'], true)) { if (Artisan::call('migrate', ['--force' => true]) !== 0) throw new RuntimeException(Artisan::output()); }
if ($action === 'migrate') { echo json_encode(['migrated' => true]); exit; }
if ($action === 'prepare') {
    foreach (['seller1','seller2','buyer','other'] as $role) User::firstOrCreate(['username' => 'matrix_browser_'.$role], [
        'email' => 'matrix.browser.'.$role.'@example.com', 'password' => 'MatrixBrowser@2026', 'account_type' => str_starts_with($role,'seller') ? 'seller' : 'buyer',
        'given_names' => 'Matrix Browser', 'last_name' => $role, 'status' => 'approved', 'business_name' => str_starts_with($role,'seller') ? 'Matrix Browser Crafts '.$role : null,
        'contact_no' => '09187770004', 'province' => 'Metro Manila', 'municipality' => 'City of Makati', 'barangay' => 'Bel-Air', 'street' => 'Matrix Browser Street', 'category_id' => 1]);
    foreach (['natural' => [206,164,115], 'black' => [20,20,20]] as $name => $rgb) {
        $image = imagecreatetruecolor(40,40); imagefill($image,0,0,imagecolorallocate($image,...$rgb)); ob_start(); imagepng($image); $bytes=ob_get_clean();
        \Illuminate\Support\Facades\Storage::disk('public')->put('audit_matrix/'.$name.'.png',$bytes);
    }
    $seller = User::where('username','matrix_browser_seller1')->firstOrFail();
    $existing = Product::where('seller_id',$seller->id)->where('name','Abaca Woven Tote Bag')->first();
    foreach (Product::where('seller_id',$seller->id)->where('name','Buri Coin Purse')->get() as $purse) { if (!$purse->hasUnfinishedOrders()) { CartItem::where('product_id',$purse->id)->delete(); $purse->delete(); } }
    $oldAxes = $existing?->variant_axes ?? []; $oldRows = $existing?->variant_rows ?? []; $axes = [];
    foreach (['Color' => ['Natural','Black'], 'Size' => ['S','M','L']] as $i => $values) {
        $index = count($axes); $axis = ['id' => $oldAxes[$index]['id'] ?? (string)\Illuminate\Support\Str::uuid(), 'name' => $i, 'options' => []];
        foreach ($values as $j => $value) $axis['options'][] = ['id' => $oldAxes[$index]['options'][$j]['id'] ?? (string)\Illuminate\Support\Str::uuid(), 'value' => $value,
            'image' => $index === 0 ? 'audit_matrix/'.strtolower($value).'.png' : null];
        $axes[] = $axis;
    }
    $rows = [];
    foreach ($axes[0]['options'] as $ci => $color) foreach ($axes[1]['options'] as $si => $size) {
        $ids = [$color['id'],$size['id']]; $previous = collect($oldRows)->first(fn ($row) => $row['option_ids'] === $ids);
        $rows[] = ['id' => $previous['id'] ?? (string)\Illuminate\Support\Str::uuid(), 'option_ids' => $ids, 'label' => $color['value'].'-'.$size['value'],
            'price' => [199,249,299][$si], 'stock' => [[10,8,5],[10,0,2]][$ci][$si], 'sku' => 'MCH-TOTE-'.($ci === 0 ? 'NAT' : 'BLK').'-'.$size['value']];
    }
    Product::updateOrCreate(['seller_id'=>$seller->id,'name'=>'Abaca Woven Tote Bag'], ['category_id'=>1,'description'=>'Handwoven abaca tote bags with independent color and size combinations.',
        'price'=>199,'stock'=>35,'status'=>'active','weight_grams'=>500,'variant_axes'=>$axes,'variant_rows'=>$rows,'variations'=>null,'images'=>['audit_matrix/natural.png','audit_matrix/black.png'],'image'=>'audit_matrix/natural.png']);
    Product::updateOrCreate(['seller_id'=>$seller->id,'name'=>'Matrix Rattan Fruit Basket'], ['category_id'=>1,'description'=>'Fictional basket for selective checkout.','price'=>450,'stock'=>20,'status'=>'active','weight_grams'=>500]);
    $otherSeller = User::where('username','matrix_browser_seller2')->firstOrFail();
    Product::updateOrCreate(['seller_id'=>$otherSeller->id,'name'=>'Matrix Graphic T-shirt'], ['category_id'=>1,'description'=>'Fictional shirt for selective checkout.','price'=>399,'stock'=>20,'status'=>'active','weight_grams'=>500]);
    CartItem::whereIn('buyer_id',User::whereIn('username',['matrix_browser_buyer','matrix_browser_other'])->pluck('id'))->delete();
}
$sellers = User::whereIn('username',['matrix_browser_seller1','matrix_browser_seller2'])->pluck('id');
$buyers = User::whereIn('username',['matrix_browser_buyer','matrix_browser_other'])->pluck('id');
echo json_encode(['products'=>Product::whereIn('seller_id',$sellers)->get()->toArray(),'cart'=>CartItem::whereIn('buyer_id',$buyers)->get()->toArray(),
    'orders'=>\App\Models\Order::whereIn('buyer_id',$buyers)->get()->toArray()]);
