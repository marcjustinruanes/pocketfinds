<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/Support.php';
require_once dirname(__DIR__).'/Actions.php';
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
$checks=0;
function notificationCheck(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
DB::beginTransaction();
try {
 $make=function(){ $tag=Str::lower(Str::random(12));$user=new User;$user->forceFill(['email'=>'notification-'.$tag.'@example.invalid','username'=>'notification_'.$tag,'given_names'=>'Notification','last_name'=>'Fixture','province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','region'=>'Region IV-A','postal_code'=>'4021','contact_no'=>'09123456789','house_no'=>'1','street'=>'Test','account_type'=>'buyer','status'=>'approved','password'=>'Testing-12345!','email_verified_at'=>now()]);$user->save();return $user;};
 $buyer=$make();$other=$make();$ownIds=[];$otherIds=[];
 foreach([$buyer,$other] as $user){
  foreach(['order_status','delivery_otp','gcash_purchase','flash_sale'] as $index=>$type){
   $id=(string)Str::uuid();DB::table('notifications')->insert(['id'=>$id,'user_id'=>$user->id,'title'=>'Temporary notification fixture','message'=>'Temporary saved notification details.','notification_type'=>$type,'reference_id'=>null,'is_read'=>false,'created_at'=>now()->subDays($index)]);
   if($user->id===$buyer->id)$ownIds[]=$id;else $otherIds[]=$id;
  }
 }
 $api=new MobileActions(new Identity($buyer));$foreignApi=new MobileActions(new Identity($other));
 $rows=$api->handle('fetch_my_notifications',[]);
 notificationCheck(count($rows)===4 && count(array_intersect(array_column($rows,'id'),$otherIds))===0,'feed exposes only authenticated buyer notifications');
 notificationCheck(array_column($rows,'id')===$ownIds,'feed is newest first using actual saved timestamps');
 notificationCheck($api->handle('unread_notification_count',[])===4,'unread count reflects persisted buyer records');
 $api->handle('mark_notification_read',['p_id'=>$otherIds[0]]);
 notificationCheck($foreignApi->handle('unread_notification_count',[])===4,'buyer cannot mark another buyer notification read');
 $api->handle('mark_notification_read',['p_id'=>$ownIds[0]]);
 notificationCheck((bool)DB::table('notifications')->where('id',$ownIds[0])->value('is_read'),'single read action persists to the database');
 notificationCheck($api->handle('unread_notification_count',[])===3,'single read action reduces the actual unread count');
 $api->handle('mark_notification_read',['p_id'=>$ownIds[0]]);
 notificationCheck($api->handle('unread_notification_count',[])===3,'repeating a read action does not decrement the count again');
 $api->handle('mark_all_notifications_read',[]);
 notificationCheck($api->handle('unread_notification_count',[])===0,'mark all persists all buyer notifications as read');
 notificationCheck($foreignApi->handle('unread_notification_count',[])===4,'mark all leaves other buyer notifications unchanged');
 $api->handle('mark_all_notifications_read',[]);
 notificationCheck(DB::table('notifications')->whereIn('user_id',[$buyer->id,$other->id])->count()===8,'read retries create no duplicate notifications');
 try{(new MobileActions(new Identity(null)))->handle('fetch_my_notifications',[]);throw new RuntimeException('Unauthenticated access was accepted.');}
 catch(ApiError $e){notificationCheck($e->status===401,'notification feed requires authentication');}
 echo "$checks notification database checks passed.\n";
} finally {DB::rollBack();echo "Temporary notification and buyer records rolled back.\n";}
