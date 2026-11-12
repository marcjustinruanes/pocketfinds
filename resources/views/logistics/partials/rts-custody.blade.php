@once
@push('head')
<style>
.rts-custody{box-sizing:border-box;min-width:0;max-width:100%;margin:12px 0;padding:14px;border:1px solid var(--border);border-radius:12px;font-size:13px;line-height:1.5;overflow-wrap:anywhere}.rts-custody summary{cursor:pointer;min-height:44px;display:flex;align-items:center;font-weight:600}.rts-custody form{display:grid;gap:10px;min-width:0;margin:12px 0}.rts-custody label{min-width:0}.rts-custody input:not([type=hidden]):not([type=checkbox]){display:block;box-sizing:border-box;min-height:44px;width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);font:inherit}.rts-custody .btn{min-height:44px;white-space:normal}
</style>
@endpush
@endonce
@if($shipment && $shipment->shipping_status === 'delivery_failed' && Illuminate\Support\Facades\Schema::hasColumn('logistics_issues','rts'))
@php
 $returnIssue=App\Models\LogisticsIssue::where('rts_shipment_id',$shipment->id)->first();
 $returnState=is_string($returnIssue?->rts['state']??null)?$returnIssue->rts['state']:null;
 $returnEvidenceReady=!$returnIssue || app(App\Services\LogisticsRts::class)->witness($shipment,$returnIssue);
 $returnActions=[];$returnActor=auth()->user();
 $returnAccess=app(App\Services\LogisticsAccess::class);
 $returnOperator=$returnActor->status==='approved' && $returnActor->account_type==='logistics' && !$returnActor->is_admin && $returnActor->is_logistics && ($returnActor->isHubStaff() || $returnActor->isLogisticsAdmin()) && $returnActor->business_name===$shipment->logistics_company;
 $returnStaff=$returnOperator && $returnActor->isHubStaff() && $returnActor->logisticsHub?->is_operational && $returnAccess->matches($returnActor->logisticsHub,$shipment->destination_hub,$shipment->destination_province);
 $returnRider=$returnActor->status==='approved' && $returnActor->account_type==='rider' && !$returnActor->is_admin && !$returnActor->is_logistics && $returnActor->business_name===$shipment->logistics_company && (int)$shipment->courier_id===(int)$returnActor->id;
 $returnSeller=$returnActor->status==='approved' && $returnActor->account_type==='seller' && !$returnActor->is_admin && !$returnActor->is_logistics && (int)$shipment->order?->seller_id===(int)$returnActor->id;
 $returnLocal=$returnIssue?->rts['local']??false;
 $returnReleaseHeld=app(App\Services\LogisticsRecovery::class)->hasConditionHold($shipment->id);
 $returnRecovered=app(App\Services\LogisticsRecovery::class)->hasRecoveryHold($shipment->id);
 if(!$returnRecovered && $returnEvidenceReady){
     if(($returnStaff || ($returnOperator && $returnActor->isLogisticsAdmin())) && !$returnIssue) $returnActions=['request'=>'Request return custody review'];
     elseif($returnStaff && $returnState==='to_hub') $returnActions=['hub_received'=>'Confirm actual hub receipt'];
     elseif($returnStaff && $returnState==='at_hub' && $returnLocal && !$returnReleaseHeld) $returnActions=['hub_released'=>'Confirm release to assigned Rider'];
     elseif($returnRider && $returnState==='requested') {
         $returnActions=['to_hub'=>'Confirm return transport to hub'];
         if($returnLocal && !$returnReleaseHeld) $returnActions['to_seller']='Confirm local return transport to Seller';
     }
     elseif($returnRider && $returnState==='released_to_rider' && $returnLocal && !$returnReleaseHeld) $returnActions=['to_seller'=>'Confirm local return transport to Seller'];
     elseif($returnSeller && $returnState==='to_seller' && $returnLocal && !$returnReleaseHeld) $returnActions=['seller_received'=>'Confirm full sellable parcel received'];
 }
 $returnRoute=$returnActor->account_type==='seller'?'seller.orders.rts':($returnActor->account_type==='rider'?'rider.rts':'logistics.rts');
@endphp
<details class="rts-custody"><summary>Return custody @if($returnState) — {{ ucfirst(str_replace('_',' ',$returnState)) }} @endif</summary>
<p>Failed delivery is not Seller receipt. Stock stays reserved until the Seller confirms the entire parcel is physically received in sellable condition. Partial/damaged and cross-hub returns require review. No automatic refund or return fee.</p>
@if($returnReleaseHeld || $returnRecovered)<p>Parcel hold: physical disposition review is required. Investigation resolution alone does not authorize release or sellable Seller receipt.</p>@endif
@if(!$returnEvidenceReady)<p>Return custody needs evidence review. Historical possession cannot be inferred.</p>@endif
@foreach($returnActions as $returnAction=>$returnLabel)
<form method="POST" action="{{ route($returnRoute,$shipment->id) }}">@csrf
<input type="hidden" name="action" value="{{ $returnAction }}"><input type="hidden" name="key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="revision" value="{{ $returnIssue?->rts['revision']??0 }}">
<label>Custody reason / reference<input name="reason" required minlength="3" maxlength="500" style="min-height:44px;max-width:100%"></label>
<label style="display:flex;align-items:center;gap:8px;min-height:44px"><input type="checkbox" name="confirmed" value="1" required> {{ $returnAction==='request'?'I request a documented return; this does not establish receipt.':($returnAction==='seller_received'?'I received the entire parcel in sellable condition.':'I attest this actual physical custody event.') }}</label>
<button class="btn btn-primary" style="min-height:44px">{{ $returnLabel }}</button></form>
@endforeach
</details>
@endif
