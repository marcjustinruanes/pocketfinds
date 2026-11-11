@php
 $cancelRefund = \Illuminate\Support\Facades\Schema::hasTable('cancellation_refunds') && $refundOrder?->status === 'cancelled'
  ? \Illuminate\Support\Facades\DB::table('cancellation_refunds')->where('order_id',$refundOrder->id)->first() : null;
 $refundEvidence = $cancelRefund ? \Illuminate\Support\Facades\DB::table('gcash_purchase_history')->where('purchase_id',$refundPurchase->id)->where('event','admin_cancellation_refund_proof')->orderByDesc('id')->first() : null;
 $cancelledBy = $cancelRefund ? \Illuminate\Support\Facades\DB::table('order_status_history')->where('order_id',$refundOrder->id)->where('status','cancelled')->orderByDesc('created_at')->value('changed_by') : null;
 $cancellationMessage = match (true) {
  $cancelledBy !== null && (string)$cancelledBy === (string)$refundOrder->buyer_id => 'You cancelled this order.',
  $cancelledBy !== null && (string)$cancelledBy === (string)$refundOrder->seller_id => 'The seller cancelled this order.',
  default => 'This order was cancelled.',
 };
@endphp
@if($cancelRefund)
 @once
 @push('head')
 <link rel="stylesheet" href="{{ asset('css/cancellation-refund.css') }}?v={{ filemtime(public_path('css/cancellation-refund.css')) }}">
 @endpush
 @endonce
 <section class="order-cancellation-payment-refund" aria-label="Cancellation refund">
  <div class="cancellation-refund-heading"><div class="cancellation-refund-title"><h3>Cancellation refund</h3><span class="cancellation-refund-reason">{{ preg_replace('/^Cancelled by (?:seller|buyer):\s*/i', '', $refundOrder->cancellation_reason ?? '') }}</span></div><span class="cancellation-refund-status {{ $cancelRefund->status === 'completed' ? 'is-completed' : '' }}">{{ $cancelRefund->status === 'completed' ? 'Completed' : 'Pending' }}</span></div>
  @if($refundOrder->cancellation_note)<div class="cancellation-refund-details"><p><strong>Reason:</strong> {{ $refundOrder->cancellation_note }}</p></div>@endif
  @if($cancelRefund->status !== 'completed' && $refundReviewer)
   <form class="gcash-form gcash-cancellation-refund-form" data-action="refund" data-confirm="Confirm that the full payment has been returned to the original GCash payer. The buyer will receive the refund proof and a notification." method="POST" enctype="multipart/form-data" action="{{ route('account.cancellation-refunds.complete',$cancelRefund->id) }}">
    @csrf
    <label class="gcash-admin-returned-checkbox"><input type="checkbox" name="confirm_repaid" value="1" data-cancellation-repaid required> Full payment has been returned to the original payer.</label>
    <div class="cancellation-refund-upload-row" data-cancellation-refund-proof hidden>
     @include('gcash.partials.admin-proof-upload',['inputId'=>'gcashCancellationRefundProof','inputName'=>'refund_proof','legend'=>'Proof of returned payment','required'=>true])
     <button type="submit" class="gcash-button cancellation-refund-submit" aria-label="Confirm refund" title="Confirm refund"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg></button>
    </div>
   </form>
  @elseif($cancelRefund->status !== 'completed')
   <p>{{ $cancellationMessage }} Admin will return your GCash payment and provide proof here.</p>
  @endif
  @if($refundEvidence)
   @php $refundMeta=json_decode($refundEvidence->before,true)??[]; @endphp
   @include('gcash.partials.proof-card',['proofLabel'=>'Admin proof of returned payment','proofUrl'=>route('gcash.adminEvidence',[$refundPurchase->id,$refundEvidence->id]),'proofMime'=>$refundMeta['mime']??'','proofDate'=>$refundEvidence->created_at,'buyerPreview'=>!$refundReviewer])
  @endif
 </section>
@endif
