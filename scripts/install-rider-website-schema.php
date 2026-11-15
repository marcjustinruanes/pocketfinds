<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/api/bootstrap.php';
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Database\Schema\Blueprint;
$root=dirname(__DIR__).'/api/storage/buyer-website/database/migrations/';
$features=[
 '2026_10_28_000001_add_logistics_financial_foundation'=>'logistics_finance_settings',
 '2026_10_28_000002_add_logistics_financial_transactions'=>'logistics_financial_transactions',
 '2026_10_29_000001_add_logistics_cash_desk'=>'logistics_cash_handovers',
 '2026_10_29_000003_add_cod_seller_payouts'=>'logistics_treasury_accounts',
 '2026_10_29_000004_add_rider_compensation_payouts'=>'logistics_rider_destinations',
];
$batch=(int)DB::table('migrations')->max('batch')+1;
foreach($features as $name=>$table){
 if(DB::table('migrations')->where('migration',$name)->exists())continue;
 if(Schema::hasTable($table))throw new RuntimeException("Inspect partially installed migration $name before proceeding.");
 $path=$root.$name.'.php';
 if($name==='2026_10_29_000003_add_cod_seller_payouts'){
   // The app already has this shared column. Preserve it and all existing dates.
   $source=file_get_contents($path);
   $line="Schema::table('orders', fn(Blueprint \$t) => \$t->timestamp('buyer_confirmed_at')->nullable());";
   if(substr_count($source,$line)!==1)throw new RuntimeException('Review upstream payout migration compatibility.');
   $source=str_replace($line,"if (!Schema::hasColumn('orders','buyer_confirmed_at')) ".$line,$source);
   $path=dirname(__DIR__).'/api/storage/rider-payout-migration-compat.php';
   file_put_contents($path,$source);
 }
 (require $path)->up();
 DB::table('migrations')->insert(['migration'=>$name,'batch'=>$batch]);
 echo "Installed $name\n";
}
if(!Schema::hasColumn('rider_payouts','acknowledged_at'))Schema::table('rider_payouts',fn(Blueprint $t)=>$t->timestamp('acknowledged_at')->nullable());
echo "Rider website dependencies and payout acknowledgment are installed.\n";

$locationMigration='2026_11_15_000001_add_delivery_location_evidence';
if(!DB::table('migrations')->where('migration',$locationMigration)->exists()){
 (require dirname(__DIR__).'/integration/laravel/database/migrations/'.$locationMigration.'.php')->up();
 DB::table('migrations')->insert(['migration'=>$locationMigration,'batch'=>$batch]);
 echo "Installed delivery location evidence.\n";
}
