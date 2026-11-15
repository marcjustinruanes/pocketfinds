<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/Support.php';
use App\Models\{User,ShipmentHubLeg,LogisticsHub};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
$checks=0;
function checkRider(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function rejectRider(Closure $action,string $message):void{try{$action();}catch(ApiError|Illuminate\Validation\ValidationException|Illuminate\Database\Eloquent\ModelNotFoundException|Illuminate\Database\RecordNotFoundException|Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){checkRider(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
DB::beginTransaction();
try{
 $tag=Str::lower(Str::random(10));$company='Rider Fixture '.$tag;
 $hub=LogisticsHub::create(['company_name'=>$company,'municipality'=>'Famy','province'=>'Laguna','is_operational'=>true]);
 $make=function(string $role)use($tag,$company,$hub){$u=new User;$u->forceFill(['email'=>$role.'-'.$tag.'@example.invalid','username'=>$role.'_'.$tag,'given_names'=>'Rider','last_name'=>'Fixture','password'=>'Testing-password-123!','province'=>'Laguna','municipality'=>'Famy','barangay'=>'Test','account_type'=>$role,'status'=>'approved','business_name'=>$company,'logistics_hub_id'=>$hub->id,'is_admin'=>false,'is_logistics'=>false]);$u->save();return $u;};
 $rider=$make('rider');$other=$make('buyer');$outsider=$make('seller');$outsider->forceFill(['account_type'=>'rider'])->save();
 $api=new MobileRiderWebModule(new Identity($rider));
 rejectRider(fn()=>(new MobileRiderWebModule(new Identity(null)))->handle(['action'=>'transfers']),'guest cannot read rider module');
 rejectRider(fn()=>(new MobileRiderWebModule(new Identity($other)))->handle(['action'=>'payouts']),'buyer cannot access rider payouts');
 $dest=['action'=>'destination','request_id'=>(string)Str::uuid(),'channel'=>'gcash','institution'=>'GCash','account_name'=>'Rider Fixture','account_number'=>'09123456789'];
 $api->handle($dest);$api->handle($dest);
 checkRider(DB::table('logistics_rider_destinations')->where('rider_id',$rider->id)->count()===1,'receiving account retry creates one historical record');
 rejectRider(fn()=>$api->handle(array_replace($dest,['request_id'=>(string)Str::uuid(),'account_number'=>'123'])),'backend rejects invalid GCash receiving number');
 checkRider((new MobileRiderWebModule(new Identity($outsider)))->handle(['action'=>'payouts'])['destinations']===[],'receiving accounts are scoped to authenticated rider');
 $shipment=(array)DB::table('shipments')->first();if(!$shipment)throw new RuntimeException('A local shipment template is required.');
 $order=(array)DB::table('orders')->first();$order['id']=(string)Str::uuid();$order['order_number']='RIDER-ORDER-'.$tag;$order['buyer_id']=$other->id;$order['gcash_purchase_id']=null;$order['platform_payment']=false;$order['status']='hub_transfer';DB::table('orders')->insert($order);
 $sid=(string)Str::uuid();$shipment['id']=$sid;$shipment['tracking_number']='RIDER-TEST-'.$tag;$shipment['order_id']=$order['id'];$shipment['shipping_status']='hub_transfer';$shipment['logistics_company']=$company;
 DB::table('shipments')->insert($shipment);
 $batch=(string)Str::uuid();DB::table('hub_transfer_batches')->insert(['id'=>$batch,'company_name'=>$company,'rider_id'=>$rider->id,'status'=>'in_transit','dispatched_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
 $leg=ShipmentHubLeg::create(['shipment_id'=>$sid,'transfer_batch_id'=>$batch,'sequence'=>1,'leg_type'=>'direct','from_hub'=>'Origin hub','to_hub'=>'Destination hub','rider_id'=>$rider->id,'status'=>'in_transit','started_at'=>now()]);
 checkRider(count($api->handle(['action'=>'transfers'])['transfers'])===1,'assigned hub transfer is listed');
 $outsideApi=new MobileRiderWebModule(new Identity($outsider));
 checkRider($outsideApi->handle(['action'=>'transfers'])['transfers']===[],'unassigned rider cannot list transfer');
 $confirm=['action'=>'confirm-transfer','id'=>$leg->id,'request_id'=>(string)Str::uuid()];
 rejectRider(fn()=>$outsideApi->handle($confirm),'unassigned rider cannot confirm transfer pickup');
 $api->handle($confirm);$time=$leg->fresh()->rider_confirmed_pickup_at->toDateTimeString();$api->handle($confirm);
 checkRider($leg->fresh()->rider_confirmed_pickup_at->toDateTimeString()===$time,'transfer confirmation retries preserve server timestamp');
 checkRider(DB::table('hub_transfer_events')->where('batch_id',$batch)->where('action','pickup_confirmed')->count()===1,'pickup retry does not duplicate transfer audit');
 rejectRider(fn()=>$api->handle(array_replace($confirm,['request_id'=>(string)Str::uuid()])),'second transfer confirmation is rejected');
 checkRider(DB::table('shipments')->where('id',$sid)->value('shipping_status')==='hub_transfer','pickup confirmation does not mark destination arrival');
 $pid=(string)Str::uuid();DB::table('rider_payouts')->insert(['id'=>$pid,'shipment_id'=>$sid,'leg'=>'delivery','rider_id'=>$rider->id,'amount'=>25,'status'=>'approved','approved_by'=>$rider->id,'created_at'=>now()]);
 $ack=['action'=>'acknowledge','source'=>'app','id'=>$pid,'received'=>true];
 rejectRider(fn()=>$api->handle($ack),'unreleased app payout cannot be acknowledged');
 DB::table('rider_payouts')->where('id',$pid)->update(['status'=>'released','reference'=>'TEST-'.$tag,'released_at'=>now()]);
 rejectRider(fn()=>$outsideApi->handle($ack),'another rider cannot acknowledge payout');
 $api->handle($ack);$stamp=DB::table('rider_payouts')->where('id',$pid)->value('acknowledged_at');$api->handle($ack);
 checkRider($stamp && DB::table('rider_payouts')->where('id',$pid)->value('acknowledged_at')===$stamp,'app payout receipt acknowledgment is idempotent');
 checkRider(DB::table('rider_payouts')->where('id',$pid)->value('status')==='released','acknowledgment does not change payout state or release money');
 $account=(string)Str::uuid();DB::table('logistics_treasury_accounts')->insert(['id'=>$account,'company_name'=>$company,'channel'=>'gcash','institution'=>'GCash','account_name'=>'Test account','account_number'=>'09123456789','created_by'=>$rider->id,'created_at'=>now()]);
 $comp=(string)Str::uuid();DB::table('logistics_compensations')->insert(['id'=>$comp,'company_name'=>$company,'source_type'=>'transfer_batch','source_id'=>$batch,'rider_id'=>$rider->id,'leg'=>'transfer','amount_cents'=>2500,'settings_revision'=>1,'status'=>'earned','created_at'=>now(),'updated_at'=>now()]);
 $webid=(string)Str::uuid();DB::table('logistics_rider_payouts')->insert(['id'=>$webid,'compensation_id'=>$comp,'company_name'=>$company,'rider_id'=>$rider->id,'treasury_account_id'=>$account,'destination_id'=>DB::table('logistics_rider_destinations')->where('rider_id',$rider->id)->value('id'),'amount_cents'=>2500,'status'=>'prepared','prepared_by'=>$rider->id,'created_at'=>now(),'updated_at'=>now()]);
 $webAck=['action'=>'acknowledge','source'=>'web','id'=>$webid,'received'=>true];
 rejectRider(fn()=>$api->handle($webAck),'unpaid website payout cannot be acknowledged');
 DB::table('logistics_rider_payouts')->where('id',$webid)->update(['status'=>'paid','paid_at'=>now()]);
 rejectRider(fn()=>$outsideApi->handle($webAck),'website payout ownership is enforced by shared service');
 $api->handle($webAck);$api->handle($webAck);
 checkRider(DB::table('logistics_rider_payouts')->where('id',$webid)->value('acknowledged_at')!==null,'website payout acknowledgment reuses shared financial service');
 checkRider(count($api->handle(['action'=>'payouts'])['payouts'])===2,'payout history includes existing app and website records');
 checkRider($outsideApi->handle(['action'=>'payouts'])['payouts']===[],'other riders cannot view payout histories');
 echo "$checks rider integration checks passed.\n";
}finally{DB::rollBack();echo "All rider fixtures, transfers, accounts and payouts rolled back.\n";}

