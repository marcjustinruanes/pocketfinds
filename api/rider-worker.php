<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/Support.php';
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require __DIR__.'/bootstrap.php';
if(!in_array('--run',$argv,true)){echo "Use --run to maintain rider presence, offers and pickup earnings. Add --push only after Firebase is configured.\n";exit;}
MobileRider::sweep();
foreach(App\Models\User::where('account_type','rider')->where('status','approved')->where('is_available',true)->get()->unique('business_name') as $rider) (new MobileRider(new Identity($rider)))->dispatch();
if(in_array('--push',$argv,true)){MobilePush::scanChats();MobilePush::queue();echo json_encode(MobilePush::dispatch()).PHP_EOL;}
echo "Rider maintenance complete.\n";
