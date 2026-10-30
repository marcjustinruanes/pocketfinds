@php($returnRequest=$returnRequests->get($order->id))
@if($returnRequest)
<div class="order-actions"><strong>Return / Refund: {{ ucfirst($returnRequest->status) }}</strong><span>₱{{ number_format($returnRequest->amount,2) }}</span></div>
<details class="order-tracking"><summary>View return / refund details</summary><div class="card-pad">
<p><strong>{{ $returnRequest->reason }}</strong></p><p>{{ $returnRequest->description }}</p>
@if($returnRequest->decision_reason)<p>{{ $returnRequest->decision_reason }}</p>@endif
@if($returnRequest->refund_reference)<p>Refund reference: {{ $returnRequest->refund_reference }}</p>@endif
<div class="order-actions">@foreach(json_decode($returnRequest->photos,true) as $index=>$photo)<a class="btn btn-outline" target="_blank" rel="noopener" href="{{ route('commerce.return.photo',[$returnRequest->id,$index]) }}">Photo {{ $index+1 }}</a>@endforeach</div>
@if($returnRequest->status==='rejected'&&!$returnRequest->escalated_at&&\Illuminate\Support\Carbon::parse($returnRequest->decided_at)->gte(now()->subDays(3)))
<form method="POST" action="{{ route('commerce.return.action',$returnRequest->id) }}">@csrf<input type="hidden" name="action" value="escalate"><button class="btn btn-outline">Ask Admin to Review</button></form>
@endif
</div></details>
@elseif($order->status==='delivered')
@php($deliveredAt=$order->shipment?->delivered_at??\Illuminate\Support\Facades\DB::table('order_status_history')->where('order_id',$order->id)->where('status','delivered')->max('created_at')??$order->updated_at)
@if(\Illuminate\Support\Carbon::parse($deliveredAt)->gte(now()->subDays(7)))
<details class="order-tracking"><summary>Request Return / Refund</summary><div class="card-pad">
<form method="POST" enctype="multipart/form-data" action="{{ route('buyer.commerce.return',$order) }}">@csrf
<fieldset class="form-row"><legend>Select items to return</legend>@foreach($order->items as $key=>$item)<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="item_keys[]" value="{{ $key }}">{{ $item['name'] }} · Qty {{ $item['qty'] }}</label>@endforeach</fieldset>
<div class="form-row"><label>Reason<select class="select" name="reason" required><option value="">Select a reason</option>@foreach(['Damaged item','Wrong item','Missing item','Not as described'] as $reason)<option>{{ $reason }}</option>@endforeach</select></label></div>
<div class="form-row"><label>Description<textarea name="description" rows="3" minlength="10" maxlength="500" required placeholder="Describe the problem (10–500 characters)"></textarea></label></div>
<div class="form-row"><label>Photo evidence<input type="file" name="photos[]" accept="image/jpeg,image/png" multiple required></label><small>Attach 1–5 JPG/PNG photos, up to 5 MB each.</small></div>
@if($order->gcash_purchase_id)<input type="hidden" name="refund_destination" value="Original GCash payment"><p>Refunds go to the original GCash payment.</p>
@else<div class="form-row"><label>GCash refund account name<input type="text" name="refund_account_name" minlength="2" maxlength="150" required></label><label>GCash mobile number<input type="tel" name="refund_gcash_number" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required placeholder="09XXXXXXXXX"></label></div>@endif
<p>Submit within seven days of delivery. Order completion pauses while the request is reviewed.</p><div class="order-modal-actions"><button class="btn btn-primary">Submit Return / Refund Request</button></div>
</form></div></details>
@endif
@endif
