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
 $returnState=$returnIssue?->rts['state']??null;
 $returnActions=[];$returnActor=auth()->user();
 if($returnActor->is_logistics && !$returnIssue) $returnActions=['request'=>'Request return custody review'];
 elseif($returnActor->isHubStaff() && $returnState==='to_hub') $returnActions=['hub_received'=>'Confirm actual hub receipt'];
 elseif($returnActor->isHubStaff() && $returnState==='at_hub' && ($returnIssue->rts['local']??false)) $returnActions=['hub_released'=>'Confirm release to assigned Rider'];
 elseif($returnActor->account_type==='rider' && $returnState==='requested') $returnActions=['to_hub'=>'Confirm return transport to hub','to_seller'=>'Confirm local return transport to Seller'];
 elseif($returnActor->account_type==='rider' && $returnState==='released_to_rider') $returnActions=['to_seller'=>'Confirm local return transport to Seller'];
 elseif($returnActor->account_type==='seller' && $returnState==='to_seller') $returnActions=['seller_received'=>'Confirm full sellable parcel received'];
 $returnRoute=$returnActor->account_type==='seller'?'seller.orders.rts':($returnActor->account_type==='rider'?'rider.rts':'logistics.rts');
@endphp
<details class="rts-custody"><summary>Return custody @if($returnState) — {{ ucfirst(str_replace('_',' ',$returnState)) }} @endif</summary>
<p>Failed delivery is not Seller receipt. Stock stays reserved until the Seller confirms the entire parcel is physically received in sellable condition. Partial/damaged and cross-hub returns require review. No automatic refund or return fee.</p>
@foreach($returnActions as $returnAction=>$returnLabel)
<form method="POST" action="{{ route($returnRoute,$shipment->id) }}">@csrf
<input type="hidden" name="action" value="{{ $returnAction }}"><input type="hidden" name="key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="revision" value="{{ $returnIssue?->rts['revision']??0 }}">
<label>Custody reason / reference<input name="reason" required minlength="3" maxlength="500" style="min-height:44px;max-width:100%"></label>
<label style="display:flex;align-items:center;gap:8px;min-height:44px"><input type="checkbox" name="confirmed" value="1" required> {{ $returnAction==='request'?'I request a documented return; this does not establish receipt.':($returnAction==='seller_received'?'I received the entire parcel in sellable condition.':'I attest this actual physical custody event.') }}</label>
<button class="btn btn-primary" style="min-height:44px">{{ $returnLabel }}</button></form>
@endforeach
</details>
@endif
