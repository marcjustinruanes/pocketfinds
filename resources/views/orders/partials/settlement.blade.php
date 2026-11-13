<section class="order-quantity-summary order-record-section" aria-label="Seller settlement">
  <h3>Seller earnings and settlement</h3>
  <div class="order-cost-row"><span>Gross product sales</span><strong>PHP {{ number_format($order->subtotal, 2) }}</strong></div>
  @if($detail['commission'])
  <div class="order-cost-row"><span>Product commission (recorded)</span><strong>PHP {{ number_format($detail['commission_cents']/100, 2) }}</strong></div>
  <div class="order-cost-row"><span>Product earnings after commission, before voucher/refund adjustments</span><strong>PHP {{ number_format(($order->subtotal*100 - $detail['commission_cents'])/100, 2) }}</strong></div>
  <p>Commission status: {{ $detail['commission']->cancelled_at ? 'Cancelled / reversed' : ($detail['commission']->earned_at ? 'Earned' : 'Provisional') }}. Delivery fees and rider pay are separate.</p>
  @else<p>No commission ledger entry is recorded for this order. Historical orders retain their original terms.</p>@endif
  @if($detail['payout'])
  <div class="order-cost-row"><span>{{ $detail['payout']->status === 'paid' ? 'Released seller payout' : 'Prepared seller payout' }}</span><strong>PHP {{ number_format($detail['payout_cents']/100, 2) }}</strong></div>
  <p>Payout status: {{ str($detail['payout']->status)->replace('_', ' ')->title() }}@if($detail['payout']->hold_reason) — On hold: {{ $detail['payout']->hold_reason }}@endif.</p>
  @if($detail['payout']->paid_at)<p>Released {{ \Illuminate\Support\Carbon::parse($detail['payout']->paid_at)->format('M j, Y g:i A') }}. Reference: {{ $detail['payout']->reference ?: 'Not recorded' }}.</p>@endif
  @else<p>No seller payout is recorded in the settlement ledger.</p>@endif
  @if($detail['admin_payment'])<p>The buyer pays the PocketFinds platform account. Buyer payment approval does not prove that seller funds have been released.</p>
  @elseif($order->gcash_purchase_id)<p>This purchase uses seller-received GCash. Direct receipt and platform commission remittance are separate from COD payouts.</p>@endif
</section>
