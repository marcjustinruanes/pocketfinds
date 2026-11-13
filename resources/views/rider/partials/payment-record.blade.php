@if($detail)
<section class="card" aria-label="Collection and rider earnings">
  <div class="card-head"><h2>Collection and rider earnings</h2></div>
  <div class="card-pad order-record-section">
    <div class="order-detail-info-grid">
      <div><span>Order reference</span><strong>{{ $order->order_number }}</strong></div>
      <div><span>Payment method</span><strong>{{ $order->payment_method ?: 'Not recorded' }}</strong></div>
      <div><span>{{ $codRequired ? 'COD amount to collect' : 'Cash to collect' }}</span><strong>PHP {{ number_format($codRequired && !$codCollection ? $order->total : 0, 2) }}</strong></div>
      <div><span>Cash collection recorded</span><strong>PHP {{ number_format($detail['cash_collected_cents']/100, 2) }}</strong></div>
      <div><span>Hub-verified cash remittance</span><strong>PHP {{ number_format($detail['cash_verified_cents']/100, 2) }}</strong></div>
      <div><span>Separate rider earning</span><strong>@if($earning = $detail['rider_earning'])PHP {{ number_format($earning->amount_cents/100, 2) }} — {{ ucfirst($earning->status) }}@else Not recorded @endif</strong></div>
      <div><span>Rider payout</span><strong>{{ $detail['rider_payout'] ? ucfirst($detail['rider_payout']->status) : 'No payout recorded' }}</strong></div>
      @if($detail['rider_payout']?->paid_at)<div><span>Payout sent</span><strong>{{ \Illuminate\Support\Carbon::parse($detail['rider_payout']->paid_at)->format('M j, Y g:i A') }}</strong></div>@endif
    </div>
    <p>Remit the full collected cash. Your earning is paid separately by logistics; never deduct it from the buyer's payment.</p>
    <p><a class="btn btn-outline btn-sm" href="{{ route('rider.profit') }}">View earnings and payouts</a></p>
  </div>
</section>
@endif
