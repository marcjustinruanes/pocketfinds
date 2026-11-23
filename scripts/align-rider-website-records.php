<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/api/bootstrap.php';
require dirname(__DIR__).'/api/Support.php';
use App\Models\Shipment;
use Illuminate\Support\Facades\{DB,Storage,Hash,Crypt};
use Illuminate\Support\Str;

// Dry run by default. Never delete the legacy rows or overwrite web evidence.
$apply=in_array('--apply',$argv,true);
$counts=['availability'=>0,'offers'=>0,'preassigned_offers'=>0,'delivery_stage'=>0,'otp'=>0,'proof'=>0,'missing_proof_files'=>0];
$copied=[];
DB::beginTransaction();
try {
 $riders=DB::table('users')->where('account_type','rider')->whereNull('availability_updated_at')->where('is_online',true)->where('is_available',false)->lockForUpdate()->get();
 foreach($riders as $r){$counts['availability']++;if($apply)DB::table('users')->where('id',$r->id)->update(['is_available'=>true,'availability_updated_at'=>now()]);}
 // Old requested assignments reserved courier_id before acceptance. Website
 // offers reserve only delivery_assignments; the parcel stays sorted.
 foreach(DB::table('delivery_assignments')->where('leg','delivery')->where('status','requested')->whereNull('accepted_at')->lockForUpdate()->get() as $a){
  $s=DB::table('shipments')->where('id',$a->shipment_id)->lockForUpdate()->first();
  if(!$s || $s->shipping_status!=='assigned_to_rider' || (int)$s->courier_id!==(int)$a->courier_id || DB::table('delivery_verifications')->where('shipment_id',$s->id)->whereNotNull('pickup_verified_at')->exists())continue;
  $counts['preassigned_offers']++;
  if($apply){DB::table('delivery_assignments')->where('id',$a->id)->update(['status'=>'offered','requested_at'=>now(),'expires_at'=>now()->addSeconds(60)]);DB::table('shipments')->where('id',$s->id)->update(['courier_id'=>null,'shipping_status'=>'sorted','delivery_offer_expires_at'=>now()->addSeconds(60)]);DB::table('orders')->where('id',$s->order_id)->where('status','assigned_to_rider')->update(['status'=>'sorted']);}
 }
 foreach(DB::table('mobile_rider_offers')->where('state','offered')->where('expires_at','>',now())->lockForUpdate()->get() as $a){
  $s=DB::table('shipments')->where('id',$a->shipment_id)->lockForUpdate()->first();$field=$a->leg==='pickup'?'pickup_rider_id':'courier_id';
  if(!$s || $s->$field || !in_array($s->shipping_status,['sorted','ready_for_pickup'],true) || DB::table('delivery_assignments')->where('shipment_id',$s->id)->where('leg',$a->leg)->exists())continue;
  $counts['offers']++;
  if($apply){DB::table('delivery_assignments')->insert(['id'=>$a->id,'shipment_id'=>$s->id,'courier_id'=>$a->rider_id,'leg'=>$a->leg,'status'=>'offered','requested_at'=>$a->created_at,'expires_at'=>$a->expires_at]);DB::table('shipments')->where('id',$s->id)->update([$a->leg.'_offer_expires_at'=>$a->expires_at]);}
 }
 foreach(DB::table('delivery_verifications')->whereNotNull('pickup_verified_at')->lockForUpdate()->get() as $v){
  $s=DB::table('shipments')->where('id',$v->shipment_id)->lockForUpdate()->first();
  if(!$s || $s->shipping_status!=='picked_up' || !$s->courier_id || (int)$s->courier_id!==(int)$v->pickup_rider_id)continue;
  $counts['delivery_stage']++;
  if($apply){DB::table('shipments')->where('id',$s->id)->update(['shipping_status'=>'assigned_to_rider']);DB::table('orders')->where('id',$s->order_id)->where('status','picked_up')->update(['status'=>'assigned_to_rider']);DB::table('delivery_assignments')->where('shipment_id',$s->id)->where('leg','delivery')->where('courier_id',$s->courier_id)->update(['status'=>'assigned_to_rider']);}
 }
 foreach(DB::table('mobile_rider_otp')->whereNotNull('code_encrypted')->lockForUpdate()->get() as $m){
  $s=DB::table('shipments')->where('id',$m->shipment_id)->lockForUpdate()->first();$v=DB::table('delivery_verifications')->where('shipment_id',$m->shipment_id)->first();
  if(!$s || $s->shipping_status!=='out_for_delivery' || $s->delivery_otp_hash || !$v?->otp_hash || $v->otp_consumed_at || !$v->otp_expires_at || strtotime($v->otp_expires_at)<=time())continue;
  $code=Crypt::decryptString($m->code_encrypted);if(!Hash::check($code,$v->otp_hash))continue;
  $counts['otp']++;
  if($apply)DB::table('shipments')->where('id',$s->id)->update(['delivery_otp_hash'=>$v->otp_hash,'delivery_otp_ciphertext'=>$m->code_encrypted,'delivery_otp_attempts'=>$v->otp_attempts,'delivery_otp_locked_until'=>$m->locked_until]);
 }
 foreach(DB::table('mobile_rider_proofs')->lockForUpdate()->get() as $p){
  $s=DB::table('shipments')->where('id',$p->shipment_id)->lockForUpdate()->first();
  if(!$s || $s->delivery_proof_file || !in_array($s->shipping_status,['delivered','completed'],true))continue;
  $path=(string)$p->photo;if(!preg_match('#^\d+/[a-zA-Z0-9_-]+\.(jpg|jpeg|png)$#D',$path)){$counts['missing_proof_files']++;continue;}
  $source='delivery-proofs/'.$path;
  if(!Storage::disk('local')->exists($source)){$counts['missing_proof_files']++;continue;}
  $counts['proof']++;
  if($apply){$target='delivery_proofs/'.Str::uuid().'.'.pathinfo($path,PATHINFO_EXTENSION);$copied[]=$target;ensure(Storage::disk('sensitive_documents')->put($target,Storage::disk('local')->get($source)),'Unable to preserve delivery proof.',500);DB::table('shipments')->where('id',$s->id)->update(['delivery_proof_file'=>$target]);}
 }
 if($apply){MobileRider::audit(0,'website-rider-record-alignment','shared-rider-workflow',null,$counts);DB::commit();}
 else DB::rollBack();
 echo json_encode(['mode'=>$apply?'applied':'dry_run','counts'=>$counts,'legacy_records_preserved'=>true],JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){DB::rollBack();foreach($copied as $path)Storage::disk('sensitive_documents')->delete($path);throw $e;}
