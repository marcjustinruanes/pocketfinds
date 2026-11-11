@php($returnRequest=$returnRequests->get($order->id))
@if($returnRequest)
<div class="order-actions"><strong>Return / Refund: {{ ucfirst($returnRequest->status) }}</strong><span>₱{{ number_format($returnRequest->amount,2) }}</span></div>
<details class="order-tracking"><summary>View return / refund details</summary><div class="card-pad">
<p><strong>{{ $returnRequest->reason }}</strong></p><p>{{ $returnRequest->description }}</p>
@if($returnRequest->decision_reason)<p>{{ $returnRequest->decision_reason }}</p>@endif
@if($returnRequest->refund_reference)<p>Refund reference: {{ $returnRequest->refund_reference }}</p>@endif
@if($returnRequest->refund_destination)<p>Refund destination: {{ $returnRequest->refund_destination }}</p>@endif
<div class="order-actions">@foreach(json_decode($returnRequest->photos,true) as $index=>$photo)<a class="btn btn-outline" target="_blank" rel="noopener" href="{{ route('commerce.return.photo',[$returnRequest->id,$index]) }}">Photo {{ $index+1 }}</a>@endforeach</div>
@if($returnRequest->status==='rejected'&&!$returnRequest->escalated_at&&\Illuminate\Support\Carbon::parse($returnRequest->decided_at)->gte(now()->subDays(3)))
<form method="POST" action="{{ route('commerce.return.action',$returnRequest->id) }}">@csrf<input type="hidden" name="action" value="escalate"><button class="btn btn-outline">Ask Admin to Review</button></form>
@endif
</div></details>
@elseif(in_array($order->status,['delivered','completed'],true))
@if(\App\Services\BuyerCommerce::returnWindowOpen($order))
@if($order->status !== 'completed')
<div class="order-actions"><button type="button" class="btn btn-outline order-refund-open" data-order-details="refundOrder{{ $order->id }}">Request Return / Refund</button></div>
@endif
@include('buyer.partials.order-refund-modal')
@elseif($order->status !== 'completed')
<p class="review-closed-note order-return-expired">Return / Refund unavailable: the seven-day window after delivery has ended.</p>
@endif
@endif
