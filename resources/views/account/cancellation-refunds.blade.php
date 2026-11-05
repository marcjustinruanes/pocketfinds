@extends($u->account_type.'.layout')
@section('title','Cancellation refunds')
@section('page-title','Cancellation refunds')
@section('content')
@include('commerce.messages')
@forelse($refunds as $refund)
<article class="card" style="margin-bottom:16px"><div class="card-pad">
 <h2>{{ $refund->order_number }}</h2><p><strong>₱{{ number_format($refund->amount,2) }}</strong> · {{ ucfirst($refund->status) }}</p>
 <p>Refund to the original GCash payment account. Payment reference: {{ $refund->payment_reference }}</p>
 @if($refund->status==='completed')<p>Repayment reference: {{ $refund->repayment_reference }} · {{ $refund->completed_at }}</p>
 @elseif($u->account_type==='seller')
  <form method="POST" action="{{ route('account.cancellation-refunds.complete',$refund->id) }}">
   @csrf <label>Repayment transaction reference<input name="repayment_reference" required minlength="6" maxlength="100"></label>
   <label><input type="checkbox" name="confirm_repaid" value="1" required> I have repaid the full amount to the original payer.</label>
   <button class="btn btn-primary">Record completed repayment</button>
  </form>
 @else<p>Your refund is pending repayment. The reference will appear here once recorded.</p>@endif
</div></article>
@empty<p>No cancellation refunds.</p>@endforelse
@endsection
