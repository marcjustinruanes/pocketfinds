<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/bootstrap.php';
foreach (['Support','Delivery','Pickup','Actions','Queries','Commerce'] as $file) require dirname(__DIR__)."/$file.php";
use App\Models\{User,Shipment,Order,LogisticsHub};
use Illuminate\Support\Facades\{DB,Mail,Hash,Auth};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
MobileDelivery::registerGuard();
config(['cache.default'=>'array']);
function connectAudit(string $name): void {
    if (!preg_match('/^pocketfinds_rider_audit_[0-9a-f]{12}$/D',$name)) throw new RuntimeException('Invalid disposable test database.');
    config(['database.connections.mysql.database'=>$name,'database.connections.mysql.url'=>null]); DB::purge('mysql');
}
if (($argv[1]??'')==='--worker') {
    connectAudit($argv[2]);
    $user=User::findOrFail((int)$argv[3]); Auth::setUser($user);
    try {
        if (($argv[6]??'')==='hold') DB::beginTransaction();
        echo "ATTEMPT\n"; fflush(STDOUT);
        if ($argv[4]==='pickup-accept') (new MobilePickup(new Identity($user)))->handle('accept',['shipment_id'=>$argv[5],'request_id'=>$argv[7]]);
        elseif ($argv[4]==='claim') (new MobileActions(new Identity($user)))->handle('claim_shipment',['p_shipment_id'=>$argv[5]]);
        else (new MobileDelivery(new Identity($user)))->handle('pickup',['shipment_id'=>$argv[5],'parcel_code'=>$argv[7],'request_id'=>$argv[8]]);
        if (($argv[6]??'')==='hold') { echo "LOCKED\n"; fflush(STDOUT); usleep(2000000); DB::commit(); }
        echo "SUCCESS\n";
    } catch(ApiError $e) { echo "REJECTED\n"; }
    exit;
}
$checks=0;
function check(bool $ok,string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); echo 'PASS '.$message."\n"; $checks++; }
function denied(Closure $fn,string $message): void {
    try { $fn(); } catch(ApiError|Illuminate\Validation\ValidationException $e) { check(true,$message); return; }
    throw new RuntimeException('Expected rejection: '.$message);
}
$original=config('database.connections.mysql');
if ($original['host']!=='127.0.0.1' || $original['database']!=='pocketfinds' || config('app.env')!=='local') throw new RuntimeException('Local PocketFinds MySQL is required.');
$name='pocketfinds_rider_audit_'.bin2hex(random_bytes(6));
$server=DB::connection()->getPdo();
$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$sent=[];
try {
    foreach (DB::select('SHOW TABLES') as $row) {
        $table=array_values((array)$row)[0];
        // This suite preserves regression coverage for the original protocol.
        // The stricter installed protocol is covered by rider-requirements.php.
        if(str_starts_with($table,'mobile_rider_'))continue;
        if (!preg_match('/^[a-zA-Z0-9_]+$/D',$table)) throw new RuntimeException('Invalid table name.');
        $server->exec("CREATE TABLE `$name`.`$table` LIKE `pocketfinds`.`$table`");
    }
    connectAudit($name);
    config(['mail.default'=>'smtp']);
    Mail::shouldReceive('raw')->andReturnUsing(function($text,$callback) use (&$sent) { $sent[]=$text; });
    $hub=LogisticsHub::create(['company_name'=>'Rider audit','municipality'=>'Famy','province'=>'Laguna','is_operational'=>true]);
    $away=LogisticsHub::create(['company_name'=>'Rider audit','municipality'=>'Paete','province'=>'Laguna','is_operational'=>true]);
    $makeUser=function(string $role,$location=null) use($hub) {
        $location??=$hub; $tag=Str::lower(Str::random(12));
        $u=new User(); $u->forceFill(['email'=>"rider-$tag@example.invalid",'username'=>'audit_'.$tag,'password'=>'AuditPassword123!',
            'given_names'=>'Audit','last_name'=>'Fixture','sex'=>'female','birthday'=>'1995-01-01','age'=>31,'contact_no'=>'09123456789','province'=>$location->province,'municipality'=>$location->municipality,'barangay'=>'Test',
            'account_type'=>$role,'status'=>'approved','email_verified_at'=>now(),'business_name'=>'Rider audit','logistics_hub_id'=>$location->id,'vehicle_type'=>'two_wheels','vehicle_ownership'=>'own','is_logistics'=>$role==='logistics','logistics_role'=>$role==='logistics'?'hub_staff':null]); $u->save(); return $u;
    };
    $buyer=$makeUser('buyer'); $seller=$makeUser('seller'); $rider=$makeUser('rider'); $other=$makeUser('rider'); $wrongArea=$makeUser('rider',$away); $staff=$makeUser('logistics'); $wrongStaff=$makeUser('logistics',$away);
    $makeParcel=function() use($buyer,$seller,$hub) {
        $o=Order::create(['buyer_id'=>$buyer->id,'seller_id'=>$seller->id,'order_number'=>'AUD-'.Str::random(12),'payment_method'=>'Cash on delivery','items'=>[],'total'=>100,'status'=>'sorted']);
        return Shipment::create(['order_id'=>$o->id,'tracking_number'=>'PF-'.strtoupper(bin2hex(random_bytes(12))),'shipping_status'=>'sorted','logistics_company'=>'Rider audit','destination_hub'=>$hub->municipality,'destination_province'=>$hub->province]);
    };
    $service=new MobileDelivery(new Identity($rider)); $actions=new MobileActions(new Identity($rider)); Auth::setUser($rider);
    $call=function(string $action,Shipment $s,array $fields=[]) use($service) { return $service->handle($action,['shipment_id'=>$s->id,'request_id'=>(string)Str::uuid(),...$fields]); };
    $claim=fn($s)=>(new MobileActions(new Identity($rider)))->handle('claim_shipment',['p_shipment_id'=>$s->id]);
    $cancelled=$makeParcel(); $cancelled->order->update(['status'=>'cancelled']);
    denied(fn()=>$claim($cancelled),'stale sorted parcel cannot be claimed after order cancellation');
    $s=$makeParcel();
    denied(fn()=>(new MobileActions(new Identity($wrongArea)))->handle('claim_shipment',['p_shipment_id'=>$s->id]),'rider from another service area cannot accept');
    $claim($s); check($s->fresh()->courier_id===$rider->id,'eligible rider accepts sorted parcel');
    denied(fn()=>(new MobileActions(new Identity($other)))->handle('claim_shipment',['p_shipment_id'=>$s->id]),'another rider cannot accept an assigned task');
    denied(fn()=>(new MobileDelivery(new Identity($other)))->details($s->id),'another rider cannot access delivery details');
    denied(fn()=>$call('start',$s),'delivery cannot start before verified pickup');
    denied(fn()=>$call('pickup',$s,['parcel_code'=>'WRONG-PARCEL']),'wrong parcel blocks pickup');
    $label=(new MobileDelivery(new Identity($staff)))->label($s->id);
    $expired='PF1:'.Illuminate\Support\Facades\Crypt::encryptString(json_encode(['purpose'=>'delivery-pickup','shipment_id'=>$s->id,'expires'=>time()-1]));
    denied(fn()=>$call('pickup',$s,['parcel_code'=>$expired]),'expired QR cannot verify pickup');
    denied(fn()=>$call('pickup',$s,['parcel_code'=>'PF1:tampered']),'tampered QR cannot verify pickup');
    denied(fn()=>(new MobileDelivery(new Identity($wrongStaff)))->label($s->id),'staff at another center cannot issue label');
    $pickup=['shipment_id'=>$s->id,'request_id'=>(string)Str::uuid(),'parcel_code'=>$label['parcel_code']];
    $service->handle('pickup',$pickup); $service->handle('pickup',$pickup);
    Shipment::whereKey($s->id)->update(['courier_id'=>$other->id]);
    denied(fn()=>(new MobileDelivery(new Identity($other)))->handle('start',['shipment_id'=>$s->id,'request_id'=>(string)Str::uuid()]),'reassignment cannot reuse another rider pickup verification');
    Shipment::whereKey($s->id)->update(['courier_id'=>$rider->id]);
    check($s->fresh()->shipping_status==='picked_up' && DB::table('delivery_events')->where('shipment_id',$s->id)->where('action','pickup')->count()===1,'correct QR and duplicate request persist one pickup event');
    denied(fn()=>$call('pickup',$s,['parcel_code'=>$s->tracking_number]),'fresh duplicate pickup is rejected');
    denied(fn()=>$actions->handle('update_delivery_status',['p_shipment_id'=>$s->id,'p_status'=>'delivered']),'legacy API cannot bypass delivery verification');
    $call('start',$s);
    denied(function() use($s) { $row=$s->fresh(); $row->shipping_status='delivered'; $row->save(); },'shared model guard blocks unverified native delivery changes');
    $buyer->forceFill(['email_verified_at'=>null])->save();
    denied(fn()=>$call('request-otp',$s),'delivery codes require the buyer verified email');
    check(count($sent)===0,'unverified recipient triggers no mail');
    $buyer->forceFill(['email_verified_at'=>now()])->save();
    $call('request-otp',$s); preg_match('/ is (\d{6})\./',end($sent),$match); $code=$match[1];
    $v=DB::table('delivery_verifications')->where('shipment_id',$s->id)->first();
    check($v->otp_hash!==$code && Hash::check($code,$v->otp_hash),'generated OTP is hashed and delivered through mail interface');
    check(!str_contains(json_encode($service->details($s->id)),$code),'rider details never expose OTP');
    check($service->details($s->id)['can_verify_otp'] && $service->details($s->id)['otp_resend_after_seconds']>0,'rider receives verification eligibility and resend cooldown without code');
    denied(fn()=>$call('request-otp',$s),'OTP resend cooldown is enforced');
    $wrong=$code==='000000'?'111111':'000000';
    $invalid=['shipment_id'=>$s->id,'request_id'=>(string)Str::uuid(),'code'=>$wrong];
    denied(fn()=>$service->handle('verify-otp',$invalid),'invalid OTP is rejected');
    denied(fn()=>$service->handle('verify-otp',$invalid),'duplicate invalid verification remains an error');
    check(DB::table('delivery_verifications')->where('shipment_id',$s->id)->value('otp_attempts')===1,'duplicate invalid verification does not consume another attempt');
    DB::table('delivery_verifications')->where('shipment_id',$s->id)->update(['otp_expires_at'=>now()->subMinute()]);
    denied(fn()=>$call('verify-otp',$s,['code'=>$code]),'expired OTP is rejected');
    DB::table('delivery_verifications')->where('shipment_id',$s->id)->update(['otp_expires_at'=>now()->addMinutes(10),'otp_attempts'=>5]);
    denied(fn()=>$call('verify-otp',$s,['code'=>$code]),'OTP attempt limit is enforced');
    DB::table('delivery_verifications')->where('shipment_id',$s->id)->update(['otp_attempts'=>0]);
    $delivery=['shipment_id'=>$s->id,'request_id'=>(string)Str::uuid(),'code'=>$code];
    $service->handle('verify-otp',$delivery); $service->handle('verify-otp',$delivery);
    check($s->fresh()->shipping_status==='delivered' && $s->order->fresh()->status==='delivered','valid OTP synchronizes delivered order and shipment');
    check(DB::table('delivery_events')->where('shipment_id',$s->id)->where('action','verify-otp')->whereNull('result_error')->count()===1,'duplicate delivery does not duplicate successful event');
    check(DB::table('reviews')->count()===0,'rider delivery never creates buyer reviews');
    denied(fn()=>(new MobileActions(new Identity($buyer)))->handle('submit_review',['p_order_id'=>$s->order_id,'p_product_id'=>(string)Str::uuid(),'p_rating'=>5]),'reviews stay blocked until buyer confirms receipt');
    $rows=(new MobileQueries(new Identity($buyer)))->handle(['table'=>'shipments','columns'=>'id,shipping_status','filters'=>[['column'=>'id','op'=>'=','value'=>$s->id]]]);
    check($rows[0]['shipping_status']==='delivered','buyer sees delivered shipment');
    (new MobileActions(new Identity($buyer)))->handle('confirm_order_received',['p_shipment_id'=>$s->id]);
    check($s->fresh()->shipping_status==='delivered' && $s->order->fresh()->status==='completed','buyer confirmation completes order while delivery stays delivered');
    (new MobileActions(new Identity($buyer)))->handle('confirm_order_received',['p_shipment_id'=>$s->id]);
    check($service->details($s->id)['order_status']==='completed','repeated buyer confirmation is safe and rider sees separate order completion');
    denied(fn()=>$call('fail',$s,['reason'=>'buyer_unavailable']),'completed delivery cannot be reopened by rider');

    $failed=$makeParcel(); $claim($failed); $call('pickup',$failed,['parcel_code'=>$failed->tracking_number]); $call('start',$failed);
    denied(fn()=>$call('fail',$failed,['reason'=>'invented']),'failure requires predefined reason');
    denied(fn()=>$call('fail',$failed,['reason'=>'other']),'Other requires explanation');
    $call('fail',$failed,['reason'=>'buyer_unavailable','notes'=>'No answer at the delivery address.']);
    check(DB::table('delivery_attempts')->where('shipment_id',$failed->id)->count()===1,'failed attempt persists reason rider and timestamp');
    denied(fn()=>$call('start',$failed),'failed delivery cannot bypass rescheduling');
    $call('reschedule',$failed,['scheduled_at'=>now()->addMinutes(2)->toIso8601String()]);
    denied(fn()=>$call('start',$failed),'future scheduled attempt cannot start early');
    DB::table('delivery_attempts')->where('shipment_id',$failed->id)->update(['scheduled_at'=>now()->subSecond()]);
    $call('start',$failed); check($failed->fresh()->shipping_status==='out_for_delivery','rescheduled delivery can resume');
    $history=$service->handle('history',[]);
    $row=collect($history)->firstWhere('id',$failed->id);
    check($row && (int)$row['attempt_count']===1,'history retains failed attempt while the next delivery is active');
    check(!collect((new MobileDelivery(new Identity($other)))->handle('history',[]))->contains('id',$failed->id),'history cannot expose another rider parcels');
    $call('fail',$failed,['reason'=>'buyer_unreachable']);
    $call('reschedule',$failed,['scheduled_at'=>now()->addMinute()->toIso8601String()]);
    DB::table('delivery_attempts')->where('shipment_id',$failed->id)->update(['scheduled_at'=>now()->subSecond()]);
    $call('start',$failed); $call('fail',$failed,['reason'=>'refused']);
    denied(fn()=>$call('reschedule',$failed,['scheduled_at'=>now()->addMinute()->toIso8601String()]),'retry limit is enforced');
    $call('request-return',$failed);
    check($failed->fresh()->shipping_status==='return_in_transit','rider return request does not mark returned');
    denied(fn()=>$call('receive-return',$failed,['tracking_number'=>$failed->tracking_number]),'rider cannot confirm sorting-center receipt');
    $staffService=new MobileDelivery(new Identity($staff));
    denied(fn()=>(new MobileDelivery(new Identity($wrongStaff)))->handle('receive-return',['shipment_id'=>$failed->id,'request_id'=>(string)Str::uuid(),'tracking_number'=>$failed->tracking_number]),'wrong center cannot receive return');
    $staffService->handle('receive-return',['shipment_id'=>$failed->id,'request_id'=>(string)Str::uuid(),'tracking_number'=>$failed->tracking_number]);
    check($failed->fresh()->shipping_status==='return_in_transit','sorting-center receipt does not claim the parcel reached the seller');
    denied(fn()=>$call('confirm-seller-return',$failed,['tracking_number'=>$failed->tracking_number]),'rider cannot confirm seller return receipt');
    $sellerService=new MobileDelivery(new Identity($seller));
    $sellerReturn=['shipment_id'=>$failed->id,'request_id'=>(string)Str::uuid(),'tracking_number'=>$failed->tracking_number];
    $sellerService->handle('confirm-seller-return',$sellerReturn); $sellerService->handle('confirm-seller-return',$sellerReturn);
    check($failed->fresh()->shipping_status==='returned' && $failed->fresh()->returned_at,'seller physical receipt alone finalizes returned status');
    check(DB::table('notifications')->where('user_id',$buyer->id)->where('reference_id',$failed->order_id)->exists(),'buyer receives persistent delivery notification');

    $pickupService=new MobilePickup(new Identity($rider));
    $sellerParcel=$makeParcel(); $sellerParcel->update(['shipping_status'=>'ready_for_pickup','origin_hub'=>$hub->municipality,'origin_province'=>$hub->province,'pickup_approved_at'=>now()]);
    $sellerParcel->order->update(['status'=>'ready_for_pickup']);
    $accept=['shipment_id'=>$sellerParcel->id,'request_id'=>(string)Str::uuid()];
    check(collect($pickupService->handle('list',[])['available'])->contains('id',$sellerParcel->id),'eligible approved seller pickup appears in mobile available tasks');
    denied(fn()=>(new MobilePickup(new Identity($wrongArea)))->handle('accept',$accept),'pickup acceptance rejects another service area');
    $pickupService->handle('accept',$accept); $pickupService->handle('accept',$accept);
    check($sellerParcel->fresh()->pickup_rider_id===$rider->id && $sellerParcel->fresh()->courier_id===null,'seller pickup assignment is separate from final delivery assignment');
    check(!collect((new MobilePickup(new Identity($other)))->handle('list',[])['available'])->contains('id',$sellerParcel->id),'claimed seller pickup disappears for other riders');
    denied(fn()=>(new MobilePickup(new Identity($other)))->handle('confirm',['shipment_id'=>$sellerParcel->id,'request_id'=>(string)Str::uuid(),'parcel_code'=>$sellerParcel->tracking_number]),'another rider cannot confirm seller pickup');
    denied(fn()=>$pickupService->handle('confirm',['shipment_id'=>$sellerParcel->id,'request_id'=>(string)Str::uuid(),'parcel_code'=>'INCORRECT']),'wrong parcel tracking cannot confirm seller pickup');
    $receipt=['shipment_id'=>$sellerParcel->id,'request_id'=>(string)Str::uuid(),'parcel_code'=>$sellerParcel->tracking_number];
    $pickupService->handle('confirm',$receipt); $pickupService->handle('confirm',$receipt);
    check($sellerParcel->fresh()->rider_confirmed_pickup_at!==null && $sellerParcel->fresh()->shipping_status==='ready_for_pickup','rider receipt still waits for seller handover confirmation');
    check(DB::table('delivery_events')->where('shipment_id',$sellerParcel->id)->where('action','seller-pickup-confirm')->count()===1,'seller pickup retry records one event');
    $sellerParcel->refresh(); $sellerParcel->seller_confirmed_pickup_at=now(); $sellerParcel->save(); $sellerParcel->maybeAdvancePastPickupConfirmation(); $sellerParcel->order->update(['status'=>'picked_up']);
    check($sellerParcel->fresh()->shipping_status==='picked_up','native seller confirmation advances both-sided pickup');
    check(collect($pickupService->handle('list',[])['mine'])->contains('id',$sellerParcel->id),'carried pickup stays visible until sorting-center receipt');
    $sellerParcel->update(['shipping_status'=>'at_sorting_center','at_sorting_center_at'=>now()]);
    check(collect($pickupService->handle('list',[])['mine'])->firstWhere('id',$sellerParcel->id)['status']==='at_sorting_center','staff receipt remains visible in pickup history');

    // Independent processes contend on the same actual MySQL rows.
    $race=$makeParcel();
    $a=new Process([PHP_BINARY,__FILE__,'--worker',$name,(string)$rider->id,'claim',$race->id,'hold']); $a->setTimeout(180); $a->start();
    $a->waitUntil(fn($type,$out)=>str_contains($a->getOutput(),'LOCKED'));
    $b=new Process([PHP_BINARY,__FILE__,'--worker',$name,(string)$other->id,'claim',$race->id,'run']); $b->setTimeout(180); $b->start();
    $a->wait(); $b->wait();
    check($a->isSuccessful() && str_contains($a->getOutput(),'SUCCESS') && str_contains($b->getOutput(),'REJECTED') && $race->fresh()->courier_id===$rider->id,'concurrent acceptance assigns exactly one rider');
    $request=(string)Str::uuid();
    $a=new Process([PHP_BINARY,__FILE__,'--worker',$name,(string)$rider->id,'pickup',$race->id,'hold',$race->tracking_number,$request]); $a->setTimeout(180); $a->start();
    $a->waitUntil(fn($type,$out)=>str_contains($a->getOutput(),'LOCKED'));
    $b=new Process([PHP_BINARY,__FILE__,'--worker',$name,(string)$rider->id,'pickup',$race->id,'run',$race->tracking_number,$request]); $b->setTimeout(180); $b->start();
    $a->wait(); $b->wait();
    check(str_contains($a->getOutput(),'SUCCESS') && str_contains($b->getOutput(),'SUCCESS') && DB::table('delivery_events')->where('shipment_id',$race->id)->where('action','pickup')->count()===1,'concurrent retry persists exactly one pickup');
    $pickupRace=$makeParcel(); $pickupRace->update(['shipping_status'=>'ready_for_pickup','origin_hub'=>$hub->municipality,'origin_province'=>$hub->province,'pickup_approved_at'=>now()]); $pickupRace->order->update(['status'=>'ready_for_pickup']);
    $a=new Process([PHP_BINARY,__FILE__,'--worker',$name,(string)$rider->id,'pickup-accept',$pickupRace->id,'hold',(string)Str::uuid()]); $a->setTimeout(180); $a->start();
    $a->waitUntil(fn($type,$out)=>str_contains($a->getOutput(),'LOCKED'));
    $b=new Process([PHP_BINARY,__FILE__,'--worker',$name,(string)$other->id,'pickup-accept',$pickupRace->id,'run',(string)Str::uuid()]); $b->setTimeout(180); $b->start(); $a->wait(); $b->wait();
    check(str_contains($a->getOutput(),'SUCCESS') && str_contains($b->getOutput(),'REJECTED') && $pickupRace->fresh()->pickup_rider_id===$rider->id,'concurrent seller pickup acceptance assigns exactly one rider: '.trim($a->getOutput().$a->getErrorOutput()).' / '.trim($b->getOutput().$b->getErrorOutput()));
    echo "$checks rider checks passed. No real email sent.\n";
} finally {
    DB::disconnect('mysql');
    // Only the freshly generated and validated disposable database is removed.
    if (!preg_match('/^pocketfinds_rider_audit_[0-9a-f]{12}$/D',$name)) throw new RuntimeException('Unsafe cleanup target.');
    $server->exec("DROP DATABASE `$name`");
    echo "Disposable rider audit database removed; existing PocketFinds data unchanged.\n";
}
