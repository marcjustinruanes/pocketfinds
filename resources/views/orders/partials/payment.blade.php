<section class="order-quantity-summary order-record-section" aria-label="Payment record">
  <h3>{{ ($printView ?? false) ? 'Payment record' : $detail['title'] }}</h3>
  <div class="order-detail-info-grid">
    <div><span>Order status</span><strong>{{ str($order->status)->replace('_', ' ')->title() }}</strong></div>
    <div><span>Payment status</span><strong>{{ $detail['payment_status'] }}</strong></div>
    <div><span>Confirmed payment applied</span><strong>PHP {{ number_format($detail['applied_cents']/100, 2) }}</strong></div>
    @if($order->status !== 'cancelled')<div><span>Remaining to pay</span><strong>PHP {{ number_format($detail['remaining_cents']/100, 2) }}</strong></div>@endif
    @if($detail['received_cents'] !== $detail['applied_cents'])<div><span>Actual amount received</span><strong>PHP {{ number_format($detail['received_cents']/100, 2) }}</strong></div>@endif
    @if($detail['receiver'] && $detail['received_cents'] > 0)<div><span>Payment received by</span><strong>{{ $detail['receiver'] }}</strong></div>@endif
    @if($detail['received_at'])<div><span>Payment recorded</span><strong>{{ \Illuminate\Support\Carbon::parse($detail['received_at'])->format('M j, Y g:i A') }}</strong></div>@endif
    @if($detail['reference'])<div><span>{{ $detail['admin_payment'] || $order->gcash_purchase_id ? 'Payment reference' : 'Cash collection reference' }}</span><strong>{{ $detail['reference'] }}</strong></div>@endif
    @if($order->buyer_confirmed_at)<div><span>Buyer confirmed receipt</span><strong>{{ \Illuminate\Support\Carbon::parse($order->buyer_confirmed_at)->format('M j, Y g:i A') }}</strong></div>@endif
  </div>
  @if($order->status === 'cancelled')<p>Order cancelled. Do not send another payment. Any recorded payment and refund are shown separately.</p>@endif
  @if($detail['excess'])<p>Excess payment: PHP {{ number_format($detail['excess']['amount']/100, 2) }} — {{ $detail['excess']['status'] }}.</p>@endif
  @if(!($printView ?? false))<p><a class="btn btn-outline btn-sm" href="{{ route('orders.receipt', $order) }}" target="_blank" rel="noopener">Print / save PDF</a></p>@endif
</section>
