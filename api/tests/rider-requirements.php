<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/bootstrap.php';
foreach(['Support','Delivery','Pickup','Actions','Queries','Commerce','Buyer','Registration','Media','Auth'] as $file)require dirname(__DIR__)."/$file.php";
use App\Models\{User,Shipment,Order,LogisticsHub};
use Illuminate\Support\Facades\{DB,Mail,Auth,Storage};
use Illuminate\Support\Str;
config(['cache.default'=>'array','mail.default'=>'smtp']);
$checks=0;$files=[];$sent=[];$failure=null;
function verifyR(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";$checks++;}
function rejectR(Closure $fn,string $label):void{try{$fn();}catch(ApiError|Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){verifyR(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
DB::beginTransaction();
try{
 Mail::shouldReceive('raw')->andReturnUsing(function($text,$cb)use(&$sent){$sent[]=$text;});
 $company='Rider requirements '.Str::random(9);
 $hub=LogisticsHub::create(['company_name'=>$company,'municipality'=>'Famy','province'=>'Laguna','is_operational'=>true]);
 $makeUser=function($role)use($company,$hub){$tag=Str::lower(Str::random(12));$u=new User;$u->forceFill(['email'=>'rr-'.$tag.'@example.com','username'=>'rr_'.$tag,'password'=>'AuditPassword123!',
  'given_names'=>'Rider','last_name'=>'Fixture','sex'=>'female','birthday'=>'1995-01-01','age'=>31,'contact_no'=>'09'.random_int(100000000,999999999),'province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test',
  'account_type'=>$role,'status'=>'approved','email_verified_at'=>now(),'business_name'=>$company,'logistics_hub_id'=>$hub->id,'vehicle_type'=>'two_wheels','vehicle_ownership'=>'own','license_expiry'=>now()->addYear()->toDateString(),'is_logistics'=>$role==='logistics','logistics_role'=>$role==='logistics'?'admin':null]);$u->save();return $u;};
 $r=$makeUser('rider');$other=$makeUser('rider');$buyer=$makeUser('buyer');$seller=$makeUser('seller');$staff=$makeUser('logistics');
 $api=new MobileRider(new Identity($r));$otherApi=new MobileRider(new Identity($other));$staffApi=new MobileRider(new Identity($staff));
 rejectR(fn()=>MobileRegistration::register(['account_type'=>'rider']),'public rider applications are refused');
 rejectR(fn()=>$api->handle('presence',['online'=>true]),'going Online requires current location');
 $api->handle('presence',['online'=>true,'latitude'=>14.45,'longitude'=>121.45]);$r->refresh();
 verifyR(MobileRider::eligible($r),'Online rider with heartbeat is eligible');
 $staffApi->handle('dispatch-point',['hub_id'=>$hub->id,'latitude'=>14.45,'longitude'=>121.45]);
 $makeParcel=function($state='sorted')use($buyer,$seller,$hub,$company){$o=Order::create(['buyer_id'=>$buyer->id,'seller_id'=>$seller->id,'order_number'=>'RR-'.Str::random(12),'payment_method'=>'Cash on delivery','items'=>[],'total'=>500,'status'=>$state]);return Shipment::create(['order_id'=>$o->id,'tracking_number'=>'RR-'.Str::random(16),'shipping_status'=>$state,'logistics_company'=>$company,'origin_hub'=>$hub->municipality,'origin_province'=>$hub->province,'destination_hub'=>$hub->municipality,'destination_province'=>$hub->province]);};
 $s=$makeParcel();$offers=$api->handle('offers',[]);$offer=collect($offers)->firstWhere('shipment_id',$s->id);
 verifyR($offer && strtotime($offer['expires_at'])<=time()+60,'dispatch creates a sixty-second offer');
 rejectR(fn()=>(new MobileActions(new Identity($other)))->handle('claim_shipment',['p_shipment_id'=>$s->id]),'another rider cannot accept this offer');
 DB::table('mobile_rider_offers')->where('id',$offer['id'])->update(['expires_at'=>now()->subSecond()]);
 rejectR(fn()=>(new MobileActions(new Identity($r)))->handle('claim_shipment',['p_shipment_id'=>$s->id]),'expired offer cannot be accepted');
 DB::table('mobile_rider_offers')->where('id',$offer['id'])->update(['expires_at'=>now()->addMinute()]);
 Auth::setUser($r);(new MobileActions(new Identity($r)))->handle('claim_shipment',['p_shipment_id'=>$s->id]);
 verifyR($s->fresh()->courier_id===$r->id,'offer acceptance assigns the native shipment');
 $delivery=new MobileDelivery(new Identity($r));$call=fn($action,$fields=[])=>$delivery->handle($action,['shipment_id'=>$s->id,'request_id'=>(string)Str::uuid()]+$fields);
 $call('pickup',['parcel_code'=>$s->tracking_number]);$call('start');$call('request-otp');
 preg_match('/is ([0-9]{6})\./',end($sent),$m);$code=$m[1];
 $snapshot=(new MobileBuyer(new Identity($buyer)))->handle('order',['order_id'=>$s->order_id]);
 verifyR($snapshot['delivery_code']===$code,'only the owned buyer order exposes its delivery code');
 rejectR(fn()=>(new MobileBuyer(new Identity($other)))->handle('order',['order_id'=>$s->order_id]),'another user cannot read the buyer delivery code');
 rejectR(fn()=>$call('verify-otp',['code'=>$code]),'delivery cannot complete without photo COD and GPS');
 $capture=$delivery->handle('capture',['shipment_id'=>$s->id]);$photo=$r->id.'/'.$capture['token'].'.jpg';$files[]='delivery-proofs/'.$photo;Storage::disk('local')->put(end($files),'fixture photo');
 $payload=['code'=>$code,'photo'=>$photo,'latitude'=>14.45,'longitude'=>121.45,'cod_amount'=>500];
 rejectR(fn()=>$call('verify-otp',array_replace($payload,['cod_amount'=>499.99])),'short COD cannot complete delivery');
 rejectR(fn()=>$call('verify-otp',array_replace($payload,['cod_amount'=>500.01])),'excess COD cannot complete delivery');
 for($i=0;$i<5;$i++)rejectR(fn()=>$call('verify-otp',array_replace($payload,['code'=>$code==='000000'?'111111':'000000'])),'wrong OTP attempt '.($i+1).' rejected');
 rejectR(fn()=>$call('verify-otp',$payload),'correct OTP cannot bypass fifteen-minute lock');
 rejectR(fn()=>$call('request-otp'),'resending cannot bypass OTP lock');
 DB::table('mobile_rider_otp')->where('shipment_id',$s->id)->update(['locked_until'=>now()->subSecond()]);
 $request=['shipment_id'=>$s->id,'request_id'=>(string)Str::uuid()]+$payload;$delivery->handle('verify-otp',$request);$delivery->handle('verify-otp',$request);
 verifyR($s->fresh()->shipping_status==='delivered' && DB::table('mobile_rider_pay')->where('shipment_id',$s->id)->count()===1,'verified delivery and retry credit pay once');
 verifyR(DB::table('mobile_rider_proofs')->where('shipment_id',$s->id)->exists(),'delivery stores photo location COD and time');
 $ledger=$api->handle('earnings',[]);verifyR($ledger['outstanding']===500.0 && (float)$ledger['jobs'][0]['amount']===50.0,'successful delivery earns fifty and records full COD separately');
 $remit=['request_id'=>(string)Str::uuid(),'amount'=>450,'reference'=>'FIXTURE-REMIT'];$first=$api->handle('remit',$remit);$api->handle('remit',$remit);
 verifyR($api->handle('earnings',[])['outstanding']===500.0,'pending remittance does not reduce outstanding COD');
 rejectR(fn()=>$api->handle('confirm-remittance',['id'=>$first['id']]),'rider cannot confirm own remittance');
 $staffApi->handle('confirm-remittance',['id'=>$first['id']]);$staffApi->handle('confirm-remittance',['id'=>$first['id']]);
 verifyR($api->handle('earnings',[])['outstanding']===50.0,'confirmed short remittance leaves fifty outstanding without duplicate confirmation');
 $decline=$makeParcel();$o=collect($api->handle('offers',[]))->firstWhere('shipment_id',$decline->id);$api->handle('decline',['id'=>$o['id']]);
 verifyR(DB::table('mobile_rider_offers')->where('id',$o['id'])->value('state')==='declined','decline is persisted on the server');
 $api->handle('presence',['online'=>false]);verifyR(!MobileRider::eligible($r->fresh()),'Offline pauses new work without removing completed records');
 $api->handle('presence',['online'=>true,'latitude'=>14.45,'longitude'=>121.45]);DB::table('mobile_rider_presence')->where('rider_id',$r->id)->update(['heartbeat_at'=>now()->subMinutes(6)]);MobileRider::sweep();
 verifyR(!$r->fresh()->is_online,'stale heartbeat makes rider Offline');
 $api->handle('presence',['online'=>true,'latitude'=>14.45,'longitude'=>121.45]);$r->refresh();
 $otherApi->handle('presence',['online'=>true,'latitude'=>14.55,'longitude'=>121.55]);$other->refresh();
 $near=$makeParcel();$nearest=collect($api->handle('offers',[]))->firstWhere('shipment_id',$near->id);
 verifyR($nearest!==null,'dispatch selects the nearest eligible rider');
 $api->handle('decline',['id'=>$nearest['id']]);
 verifyR(DB::table('mobile_rider_offers')->where('shipment_id',$near->id)->where('rider_id',$other->id)->where('state','offered')->exists(),'declined offer moves to the next eligible rider');
 $next=DB::table('mobile_rider_offers')->where('shipment_id',$near->id)->where('rider_id',$other->id)->where('state','offered')->first();$otherApi->handle('decline',['id'=>$next->id]);
 $third=$makeUser('rider');$thirdApi=new MobileRider(new Identity($third));$thirdApi->handle('presence',['online'=>true,'latitude'=>14.65,'longitude'=>121.65]);
 $thirdOffer=collect($thirdApi->handle('offers',[]))->firstWhere('shipment_id',$near->id);$thirdApi->handle('decline',['id'=>$thirdOffer['id']]);
 verifyR(!DB::table('mobile_rider_offers')->where('shipment_id',$near->id)->where('state','offered')->exists(),'three declines leave the job unassigned');
 $active=[];
 for($i=0;$i<4;$i++){
   $job=$makeParcel();$active[]=$job;
   DB::table('mobile_rider_offers')->insert(['id'=>(string)Str::uuid(),'shipment_id'=>$job->id,'leg'=>'delivery','rider_id'=>$r->id,'state'=>'offered','expires_at'=>now()->addMinute(),'created_at'=>now()]);
   if($i<3)(new MobileActions(new Identity($r)))->handle('claim_shipment',['p_shipment_id'=>$job->id]);
   else rejectR(fn()=>(new MobileActions(new Identity($r)))->handle('claim_shipment',['p_shipment_id'=>$job->id]),'fourth active job is rejected');
 }
 $refused=$active[0];$act=fn($action,$fields=[])=>$delivery->handle($action,['shipment_id'=>$refused->id,'request_id'=>(string)Str::uuid()]+$fields);
 $act('pickup',['parcel_code'=>$refused->tracking_number]);$act('start');$act('fail',['reason'=>'refused']);
 verifyR($refused->fresh()->shipping_status==='return_in_transit' && !DB::table('mobile_rider_pay')->where('shipment_id',$refused->id)->exists(),'refusal starts return immediately without delivery earnings');
 $last=$active[1];$actLast=fn($action,$fields=[])=>$delivery->handle($action,['shipment_id'=>$last->id,'request_id'=>(string)Str::uuid()]+$fields);
 $actLast('pickup',['parcel_code'=>$last->tracking_number]);$actLast('start');
 for($i=1;$i<=3;$i++){ $actLast('fail',['reason'=>'buyer_unavailable']);if($i<3){$actLast('reschedule',['scheduled_at'=>now()->addMinute()->toIso8601String()]);DB::table('delivery_attempts')->where('shipment_id',$last->id)->update(['scheduled_at'=>now()->subMinute()]);$actLast('start');} }
 verifyR($last->fresh()->shipping_status==='return_in_transit','third failed attempt starts return automatically');
 rejectR(fn()=>$actLast('reschedule',['scheduled_at'=>now()->addHour()->toIso8601String()]),'fourth delivery attempt cannot be scheduled');
 $path=$r->id.'/'.Str::uuid().'.jpg';$files[]='rider-documents/'.$path;
 (new MobileMedia(new Identity($r)))->upload(['bucket'=>'rider-documents','path'=>$path,'content'=>base64_encode(file_get_contents(dirname(__DIR__,2).'/assets/brand/storefront.jpg'))]);
 $vehicle=['request_id'=>(string)Str::uuid(),'vehicle_type'=>'two_wheels','vehicle_brand'=>'Fixture','vehicle_model'=>'Updated','plate_number'=>'RR'.strtoupper(Str::random(8)),'license_number'=>'N01-23-'.random_int(100000,999999),'license_expiry'=>now()->addYear()->toDateString(),'license_file'=>$path,'or_cr_file'=>$path];
 $oldPlate=$r->plate_number;$api->handle('vehicle',$vehicle);$api->handle('vehicle',$vehicle);
 verifyR(DB::table('mobile_rider_vehicle_requests')->where('id',$vehicle['request_id'])->count()===1 && $r->fresh()->plate_number===$oldPlate,'vehicle change retries create one pending verification without bypassing approval');
 rejectR(fn()=>$api->handle('vehicle-decision',['id'=>$vehicle['request_id'],'approve'=>true,'reason'=>'Fixture approval completed']),'rider cannot approve vehicle changes');
 $staffApi->handle('vehicle-decision',['id'=>$vehicle['request_id'],'approve'=>true,'reason'=>'Fixture approval completed']);
 verifyR($r->fresh()->plate_number===$vehicle['plate_number'],'authorized logistics approval applies verified vehicle details');
 $suspended=$active[2];Auth::setUser($staff);$r->update(['status'=>'suspended']);MobileRider::sweep();
 verifyR($suspended->fresh()->courier_id===null && $refused->fresh()->courier_id===$r->id,'suspension releases only parcels not yet picked up');
 echo "$checks rider-requirement checks passed. No real mail sent.\n";
}catch(Throwable $e){$failure=$e;}finally{DB::rollBack();foreach($files as $path)Storage::disk('local')->delete($path);echo "Rider fixtures rolled back.\n";}
if($failure){fwrite(STDERR,(string)$failure.PHP_EOL);exit(1);}
