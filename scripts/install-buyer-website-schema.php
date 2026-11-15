<?php
/** Only reviewed buyer dependencies; no dumps, seeders, destructive or timezone migrations. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/api/bootstrap.php';
use Illuminate\Support\Facades\{DB, Schema};
$root=dirname(__DIR__).'/api/storage/buyer-website/database/migrations/';
$features=[
    '2026_10_17_000001_add_buyer_commerce_features'=>'commerce_checkouts',
    '2026_11_03_100000_add_buyer_module_tools'=>'shop_categories',
    '2026_10_31_100000_add_delivery_guarantees'=>'delivery_late_vouchers',
    '2026_10_31_110000_add_seller_regional_shipping_policy'=>null,
    '2026_10_31_120000_add_seller_late_delivery_voucher_policy'=>null,
    '2026_11_06_120000_add_seller_module_completion_tools'=>'commerce_sales',
    '2026_11_07_110000_expand_order_reviews'=>'order_service_reviews',
    '2026_11_06_130000_add_buyer_account_deletion_marker'=>null,
    '2026_11_10_220000_add_review_follow_ups'=>null,
];
$batch=(int)DB::table('migrations')->max('batch')+1;
foreach($features as $name=>$table){
    if(DB::table('migrations')->where('migration',$name)->exists())continue;
    if($table && Schema::hasTable($table))throw new RuntimeException("Inspect partially installed feature $name before proceeding.");
    $migration=require $root.$name.'.php';
    $migration->up();
    DB::table('migrations')->insert(['migration'=>$name,'batch'=>$batch]);
    echo "Installed $name\n";
}
$import='2026_11_13_000001_import_embedded_buyer_saved_items';
if (!DB::table('migrations')->where('migration',$import)->exists()) DB::transaction(function()use($import,$batch){
    foreach (['wishlist_entries','mobile_commerce_wishlist'] as $table) {
        if (!Schema::hasTable($table)) continue;
        foreach (DB::table($table)->cursor() as $item) {
            DB::table('commerce_wishlist')->insertOrIgnore(['buyer_id'=>$item->buyer_id,'product_id'=>$item->product_id,'created_at'=>$item->created_at??now()]);
        }
    }
    DB::table('migrations')->insert(['migration'=>$import,'batch'=>$batch]);
    echo "Imported existing saved products without deleting legacy records.\n";
});
