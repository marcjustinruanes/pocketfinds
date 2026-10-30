<?php

namespace App\Services;

use App\Models\{DeliveryAssignment, LogisticsIssue, Shipment, User};
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Facades\Validator;

class LogisticsRecovery
{
    public function receive(User $user,array $input,?UploadedFile $proof=null): LogisticsIssue
    {
        abort_unless(Schema::hasColumn('logistics_issues','recovery'),503,'Install the operational recovery migration.');
        $d=Validator::make($input,['key'=>'required|uuid','rider_id'=>'required|integer','leg'=>'required|in:pickup,delivery,hub_transfer','tracking_numbers'=>'required|string|max:10000',
            'condition'=>'required|string|min:3|max:1000','reason'=>'required|string|min:3|max:1000','reference'=>'required|string|min:3|max:255','observed_at'=>'required|date','confirmed'=>'required|accepted'])->validate();
        $observed=CarbonImmutable::parse($d['observed_at'],'Asia/Manila')->utc();
        abort_if($observed->isFuture(),422,'Observation time cannot be in the future.');
        $tracking=preg_split('/[\s,]+/',trim($d['tracking_numbers']),-1,PREG_SPLIT_NO_EMPTY);
        abort_unless(count($tracking)>0 && count($tracking)<=100 && count(array_unique($tracking))===count($tracking),422,'Scan distinct parcels, up to 100.');sort($tracking);
        $hash=hash('sha256',json_encode([$d['rider_id'],$d['leg'],$tracking,$d['condition'],$d['reason'],$d['reference'],$observed->toIso8601String(),$proof?hash_file('sha256',$proof->getRealPath()):null]));
        return DB::transaction(function()use($user,$d,$tracking,$observed,$hash,$proof){
            $ids=Shipment::where('logistics_company',$user->business_name)->whereIn('tracking_number',$tracking)->pluck('id')->all();
            abort_unless(count($ids)===count($tracking),404);
            GcashLocks::operation($ids,[],[$user->id,(int)$d['rider_id']]);
            $actor=app(AssignmentReassignment::class)->actor($user);
            abort_unless($actor->isHubStaff(),403,'Only assigned Hub Staff may attest recovered custody.');
            $hub=app(LogisticsAccess::class)->hub($actor);
            $holder=User::whereKey($d['rider_id'])->lockForUpdate()->firstOrFail();
            abort_unless($holder->account_type==='rider' && ! $holder->is_admin && ! $holder->is_logistics && $holder->status==='suspended' && $holder->business_name===$actor->business_name,403);
            $existing=LogisticsIssue::where('recovery_key',$d['key'])->lockForUpdate()->first();
            if($existing){abort_unless($existing->company_name===$actor->business_name && $existing->reported_by===$actor->id && hash_equals($existing->recovery['request_hash'],$hash),409);return $existing;}
            $parcels=Shipment::whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();$batches=[];
            foreach($parcels as $s){
                abort_unless($s->logistics_company===$actor->business_name && !in_array($s->shipping_status,['delivered','completed','returned','cancelled']),422,'Only incomplete work can enter physical recovery.');
                $this->assertNotRecovered($s->id);
                $a=DeliveryAssignment::where('shipment_id',$s->id)->where('leg',$d['leg'])->where('courier_id',$holder->id)->lockForUpdate()->firstOrFail();
                abort_if($a->accepted_at && $observed->lt($a->accepted_at),422,'Recovery observation cannot predate this assignment.');
                abort_if(in_array($a->status,['completed','delivered','cancelled','rejected']) || $a->reassignment,422,'Work is closed or a possession review is pending.');
                if($d['leg']==='hub_transfer'){
                    $leg=$s->hubLegs()->where('status','in_transit')->where('rider_id',$holder->id)->lockForUpdate()->firstOrFail();
                    abort_unless(app(LogisticsAccess::class)->matches($hub,$leg->from_hub,$leg->from_province)||app(LogisticsAccess::class)->matches($hub,$leg->to_hub,$leg->to_province),403,'Use an actual hub on the active leg.');
                    abort_if(app(LogisticsAccess::class)->matches($hub,$leg->to_hub,$leg->to_province)&&$leg->rider_confirmed_pickup_at,422,'Use the normal destination receipt for confirmed transfers.');
                    $batches[$s->id]=$leg->transfer_batch_id;
                }else{
                    $owner=$d['leg']==='pickup'?$s->pickup_rider_id:$s->courier_id;
                    abort_unless((int)$owner===$holder->id,409,'Custody owner changed.');
                    abort_unless(app(LogisticsAccess::class)->matches($hub,$d['leg']==='pickup'?$s->origin_hub:$s->destination_hub,$d['leg']==='pickup'?$s->origin_province:$s->destination_province),403,'Recovery belongs to another hub.');
                    if($d['leg']==='pickup'){
                        abort_unless($s->picked_up_at||$s->rider_confirmed_pickup_at||$s->seller_confirmed_pickup_at,422,'Uncollected work requires possession review, not recovery.');
                        abort_if($s->shipping_status==='picked_up'&&$s->picked_up_at&&$s->rider_confirmed_pickup_at&&$s->seller_confirmed_pickup_at,422,'Use the normal origin hub receipt.');
                    }else abort_unless(in_array($s->shipping_status,['assigned_to_rider','out_for_delivery','delivery_failed']),422,'Delivery work is not recoverable here.');
                }
            }
            $record=['shipment_ids'=>$parcels->pluck('id')->all(),'rider_id'=>$holder->id,'leg'=>$d['leg'],'batch_ids'=>$batches,'received_by'=>$actor->id,'hub_id'=>$hub->id,
                'condition'=>$d['condition'],'observed_at'=>$observed->toIso8601String(),'recorded_at'=>now()->toIso8601String(),'reference'=>$d['reference'],'request_hash'=>$hash];
            $issue=LogisticsIssue::create(['shipment_id'=>$parcels->first()->id,'company_name'=>$actor->business_name,'hub_id'=>$hub->id,'reported_by'=>$actor->id,'kind'=>'other','description'=>$d['reason'],'status'=>'open','recovery_key'=>$d['key'],'recovery'=>$record]);
            if($proof)app(LogisticsTreasury::class)->evidence($actor,'parcel_recovery',(string)$issue->id,$proof);
            DB::table('logistics_issue_events')->insert(['issue_id'=>$issue->id,'actor_id'=>$actor->id,'status'=>'open','note'=>'Physical recovery only. '.$d['reason'].' Reference: '.$d['reference'],'created_at'=>now()]);
            app(LogisticsFinance::class)->event($actor->business_name,'parcel_recovery_received','parcel_recovery',(string)$issue->id,$record+['reason'=>$d['reason']],$actor->id);
            return $issue;
        },3);
    }

    public function assertNotRecovered(string $shipment): void
    {
        if (! Schema::hasColumn('logistics_issues', 'recovery')) return;
        abort_if(DB::table('logistics_issues')->whereJsonContains('recovery->shipment_ids', $shipment)
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->exists(), 422,
            'Physical recovery is recorded. Redispatch requires a separately approved workflow.');
    }
}
