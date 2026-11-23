<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli')exit;
require __DIR__.'/Support.php';
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/Push.php';
require __DIR__.'/Commerce.php';
if (!in_array('--run',$argv,true)) {
    echo "Use --run to expire unpaid reservations, send due reminders, process timed returns/completion and dispatch configured push notifications.\n";
    exit;
}
app(App\Services\GcashWorkflow::class)->sweep();
app(App\Services\PlatformPayments::class)->expireUnsubmitted();
(new MobileBuyerPricing)->sweep();
MobileAftercare::sweep();
if (MobileRider::installed()) {
    MobileRider::sweep();
    foreach(App\Models\User::where('account_type','rider')->where('status','approved')->where('is_available',true)->get()->unique('business_name') as $rider) (new MobileRider(new Identity($rider)))->dispatch();
}
MobilePush::scanChats();$queued=MobilePush::queue();$result=MobilePush::dispatch();
echo json_encode(['queued'=>$queued,'push'=>$result],JSON_THROW_ON_ERROR).PHP_EOL;
