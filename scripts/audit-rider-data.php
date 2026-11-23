<?php
// Read-only integration audit. Prints schema and aggregate counts, never contact data.
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/api/Support.php';
require dirname(__DIR__).'/api/bootstrap.php';
require_once dirname(__DIR__).'/api/RiderWebModule.php';
use Illuminate\Support\Facades\{DB,Schema};
foreach (['users','orders','shipments','delivery_assignments','delivery_verifications',
    'delivery_events','delivery_attempts','shipment_hub_legs','logistics_hubs',
    'logistics_compensations','logistics_rider_payouts','logistics_cash_allocations'] as $table) {
    if (!Schema::hasTable($table)) throw new RuntimeException('Missing rider workflow table: '.$table);
    echo $table.': '.DB::table($table)->count()." rows\n";
}
$rider=App\Models\User::where('account_type','rider')->where('status','approved')->firstOrFail();
$overview=(new MobileRiderWebModule(new Identity($rider)))->handle(['action'=>'overview']);
foreach (['jobs','daily','earnings','cash','collections'] as $key) {
    if (!is_array($overview[$key]??null)) throw new RuntimeException('Invalid earnings response: '.$key);
}
echo "PASS Shared rider earnings, cash, and compensation response is available.\n";
require_once dirname(__DIR__).'/api/Actions.php';
$profile=(new MobileActions(new Identity($rider)))->handle('get_my_rider_profile',[]);
if ((int)$profile['user_id']!==(int)$rider->id || !array_key_exists('hub_municipality',$profile)) {
    throw new RuntimeException('The rider profile is missing its assigned hub.');
}
echo "PASS Rider profile includes the authenticated rider and assigned hub fields.\n";
