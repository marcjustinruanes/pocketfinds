@include('commerce.messages')
@php
 $excessRefunds = \Illuminate\Support\Facades\DB::table('gcash_purchase_history as h')
   ->where('h.event', 'overpayment_pending')
   ->whereNotExists(fn($q) => $q->selectRaw('1')->from('gcash_purchase_history as done')->whereColumn('done.purchase_id', 'h.purchase_id')->where('done.event', 'overpayment_refunded'))
   ->get();
@endphp
@foreach($excessRefunds as $excessRefund)
<section class="gcash-card">
 <h3>Excess payment refund</h3>
 <p>{{ $excessRefund->reason }}</p>
 <a href="{{ route('gcash.show', $excessRefund->purchase_id) }}">View payment and buyer details</a>
 @php $refundDetailsEvent = \Illuminate\Support\Facades\DB::table('gcash_purchase_history')->where('purchase_id',$excessRefund->purchase_id)->where('event','refund_details_submitted')->latest('id')->first(); @endphp
 @if($refundDetailsEvent)
 @php $refundDetails = json_decode($refundDetailsEvent->before,true); @endphp
 <dl><dt>Refund GCash account name</dt><dd>{{ $refundDetails['name'] }}</dd><dt>Refund GCash number</dt><dd>{{ $refundDetails['number'] }}</dd></dl>
 <form method="POST" enctype="multipart/form-data" action="{{ route('gcash.refundOverpayment', $excessRefund->purchase_id) }}" class="gcash-form">@csrf
  <p><label>Repayment reference<input name="repayment_reference" minlength="6" maxlength="100" required></label></p>
  <p><label>Proof of returned excess payment<input type="file" name="refund_proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label></p>
  <p><label><input type="checkbox" name="confirm_repaid" value="1" required> Excess payment has been refunded to the original payer.</label></p>
  <button class="gcash-button" type="submit">Record excess refund</button>
 </form>
 @else<p>Waiting for the buyer to submit their refund GCash account details. Verify the destination against the original payment before sending the refund.</p>@endif
</section>
@endforeach
<nav class="gcash-nav" aria-label="Payment review filters">
 <a href="{{ route('gcash.index',['state'=>'proof_submitted']) }}">Pending review</a>
 <a href="{{ route('gcash.index',['state'=>'awaiting_payment']) }}">Awaiting payment</a>
 <a href="{{ route('gcash.index',['state'=>'order_placed']) }}">Approved</a>
 <a href="{{ route('gcash.index') }}">All payments</a>
</nav>
@foreach(\App\Models\GcashPurchase::where('review_flow','admin')->where('payment_state','underpaid')->get() as $shortPurchase)
<section class="gcash-card"><h3>Underpaid GCash payment</h3><a href="{{ route('gcash.show',$shortPurchase->id) }}">{{ $shortPurchase->productLabel() }}</a><p>Remaining balance: PHP {{ number_format(max(0,(float)$shortPurchase->amount_due-(float)$shortPurchase->verified_amount),2) }}</p><form method="POST" action="{{ route('gcash.remindUnderpayment',$shortPurchase->id) }}">@csrf<button class="gcash-button" type="submit">Resend balance notice to buyer</button></form></section>
@endforeach
@foreach($platformRefunds as $refund)
<section class="gcash-card"><h3>Platform refund — {{ $refund->order_number }}</h3><p>Repay ₱{{ number_format($refund->amount,2) }} to the original payer, then record the actual repayment.</p>
 <form method="POST" action="{{ route('account.cancellation-refunds.complete',$refund->id) }}" class="gcash-form">@csrf
  <p><label>Repayment reference<input name="repayment_reference" minlength="6" maxlength="100" required></label></p>
  <p><label><input type="checkbox" name="confirm_repaid" value="1" required> Full repayment has been sent to the original payer.</label></p>
  <button class="gcash-button" type="submit">Record repayment</button>
 </form>
</section>
@endforeach
