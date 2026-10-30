<?php

namespace App\Services;

use App\Models\{Shipment, User};
use Illuminate\Support\Facades\{DB, Schema};

/** Read-only presentation of recorded evidence, never a physical custody mutation. */
class ParcelCustody
{
    public function describe(Shipment $s, User $actor): array
    {
        abort_unless($actor->account_type==='logistics' && $actor->status==='approved' && ($actor->isHubStaff() || $actor->isLogisticsAdmin()) && $actor->business_name===$s->logistics_company,403);
        $unknown=['label'=>'Custody needs review','responsible'=>'Not established','at'=>null,'next'=>'Review parcel history and Issues before any physical handover.','note'=>'Shipment status alone does not establish current physical possession.'];
        if(in_array($s->shipping_status,['delivered','completed','returned','cancelled','delivery_failed']))return $unknown;
        if(Schema::hasColumn('logistics_issues','recovery') && DB::table('logistics_issues')->whereJsonContains('recovery->shipment_ids',$s->id)->exists())return array_replace($unknown,['label'=>'Recovery hold','next'=>'Use the existing supervised recovery record in Issues.']);
        if(Schema::hasColumn('logistics_issues','rts_shipment_id') && DB::table('logistics_issues')->where('rts_shipment_id',$s->id)->exists())return array_replace($unknown,['label'=>'RTS custody review','next'=>'Use the authenticated RTS custody record in Issues.']);
        $s->loadMissing(['hubLegs.transferBatch.members','courier','pickupRider','deliveryAssignment']);
        $legs=$s->hubLegs->sortBy('sequence');
        $active=$legs->where('status','in_transit');
        if($active->count()>1)return $unknown;
        $leg=$active->first();
        $access=app(LogisticsAccess::class);
        if($leg && $actor->isHubStaff() && !$access->matches($actor->logisticsHub,$leg->from_hub,$leg->from_province) && !$access->matches($actor->logisticsHub,$leg->to_hub,$leg->to_province))return array_replace($unknown,['label'=>'Outside your active hub leg']);
        $hub=null;$receipt=null;$transferred=false;
        if($s->at_sorting_center_at && $s->picked_up_at && $s->rider_confirmed_pickup_at && $s->seller_confirmed_pickup_at && $s->at_sorting_center_at->gte($s->picked_up_at) && $s->picked_up_at->gte($s->rider_confirmed_pickup_at) && $s->picked_up_at->gte($s->seller_confirmed_pickup_at)){
            $hub=$s->origin_hub.', '.$s->origin_province;$receipt=$s->at_sorting_center_at;
        }
        foreach($legs as $completed){
            if($completed->status!=='completed')break;
            $member=$completed->transferBatch?->members->firstWhere('leg_id',$completed->id);
            if(!$completed->rider_confirmed_pickup_at || !$completed->completed_at || !$member?->received_at || !$member->received_by || !$completed->completed_at->gte($completed->rider_confirmed_pickup_at))return $unknown;
            $hub=$completed->to_hub.', '.$completed->to_province;$receipt=$member->received_at;$transferred=true;
        }
        if($leg){
            if(!$leg->rider_id || !$leg->transferBatch || !$leg->transferBatch->dispatched_at || (int)$leg->rider_id!==(int)$leg->transferBatch->rider_id)return $unknown;
            if($leg->rider_confirmed_pickup_at)return ['label'=>'Rider Pickup Confirmed','responsible'=>'Transfer Rider #'.$leg->rider_id,'at'=>$leg->rider_confirmed_pickup_at,'next'=>'Destination Staff at '.$leg->to_hub.', '.$leg->to_province.' must confirm actual arrival.','note'=>'Rider pickup is recorded; dispatch alone is not a receipt.'];
            if(!$receipt || $hub!==$leg->from_hub.', '.$leg->from_province)return $unknown;
            return ['label'=>'Awaiting Rider Pickup','responsible'=>$hub.' / assigned Rider #'.$leg->rider_id,'at'=>$receipt,'next'=>'Assigned transfer Rider must confirm actual pickup.','note'=>'Last confirmed hub receipt shown. Admin dispatch is authorization, not physical release.'];
        }
        if($s->shipping_status==='picked_up' && $s->picked_up_at && $s->rider_confirmed_pickup_at && $s->seller_confirmed_pickup_at)return ['label'=>'Rider Pickup Confirmed','responsible'=>$s->pickupRider?->username ?? 'Pickup Rider #'.$s->pickup_rider_id,'at'=>$s->picked_up_at,'next'=>'Origin Staff at '.$s->origin_hub.' must scan and confirm actual arrival.','note'=>'Seller/Rider handover recorded; hub receipt is still required.'];
        if($s->shipping_status==='out_for_delivery' && $s->out_for_delivery_at && $s->courier_id && $s->deliveryAssignment && (int)$s->deliveryAssignment->courier_id===(int)$s->courier_id && $s->deliveryAssignment->status==='out_for_delivery')return ['label'=>'Rider Pickup Confirmed','responsible'=>$s->courier?->username ?? 'Delivery Rider #'.$s->courier_id,'at'=>$s->out_for_delivery_at,'next'=>'Assigned Rider continues final delivery.','note'=>'Rider-recorded departure, not a separate Staff release attestation.'];
        if(!$receipt || !in_array($s->shipping_status,['at_sorting_center','hub_transfer','sorted','assigned_to_rider']))return $unknown;
        if(in_array($s->shipping_status,['sorted','assigned_to_rider']) && $legs->contains(fn($l)=>$l->status!=='completed'))return $unknown;
        if($s->shipping_status==='assigned_to_rider' && (!$s->courier_id || !$s->deliveryAssignment || (int)$s->deliveryAssignment->courier_id!==(int)$s->courier_id))return $unknown;
        $label=match($s->shipping_status){'assigned_to_rider'=>'Awaiting Rider Pickup','sorted'=>'Awaiting Final Delivery',default=>$transferred?'Received at Next Hub':'At Hub'};
        return ['label'=>$label,'responsible'=>$hub.($s->shipping_status==='assigned_to_rider'?' / assigned Rider #'.$s->courier_id:''),'at'=>$receipt,'next'=>match($s->shipping_status){'assigned_to_rider'=>'Assigned Rider must confirm departure from their own delivery page.','sorted'=>'Assign an eligible final-delivery Rider or allow an eligible claim.',default=>'Review the next transfer leg; request/approval/dispatch does not confirm pickup.'},'note'=>'Last confirmed receipt shown. Assignment and sorting do not record another physical handover.'];
    }
}
