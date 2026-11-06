@extends($u->account_type.'.layout')
@section('title','Cancellation refunds')
@section('page-title','Cancellation refunds')
@section('body-class','refunds-page-layout')
@section('content')
@include('commerce.messages')
<section class="refunds-page">
@forelse($refunds as $refund)
<article class="card refund-card"><div class="card-pad">
 <header class="refund-card-header">
  <div><span class="refund-eyebrow">Order</span><h2>{{ $refund->order_number }}</h2></div>
  <span class="stamp stamp-{{ \Illuminate\Support\Str::slug($refund->status) }}">{{ ucfirst($refund->status) }}</span>
 </header>
 <div class="refund-amount"><span>Refund amount</span><strong>₱{{ number_format($refund->amount,2) }}</strong></div>
 <div class="refund-info"><span>Refund destination</span><strong>Original GCash payment account</strong><span>Payment reference</span><strong class="refund-reference">{{ $refund->payment_reference }}</strong></div>
 @if($refund->status==='completed')
 <div class="refund-completion"><span>Repayment reference</span><strong>{{ $refund->repayment_reference }}</strong><span>Completed</span><strong>{{ $refund->completed_at }}</strong></div>
 @elseif($u->account_type==='seller')
  <form class="refund-repayment-form" method="POST" action="{{ route('account.cancellation-refunds.complete',$refund->id) }}">
   @csrf <label>Repayment transaction reference<input name="repayment_reference" required minlength="6" maxlength="100"></label>
   <label><input type="checkbox" name="confirm_repaid" value="1" required> I have repaid the full amount to the original payer.</label>
   <button class="btn btn-primary">Record completed repayment</button>
  </form>
 @else<p class="refund-pending-note">Your refund is pending repayment. The reference will appear here once recorded.</p>@endif
</div></article>
@empty<div class="card refunds-empty"><div class="card-pad"><strong>No cancellation refunds.</strong><p>There are no cancellation refunds to show right now.</p></div></div>@endforelse
</section>
@endsection
