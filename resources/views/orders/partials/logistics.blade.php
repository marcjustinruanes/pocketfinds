<section class="order-quantity-summary order-record-section" aria-label="Logistics financial record">
  <h3>Logistics funds and rider pay</h3>
  <div class="order-cost-row"><span>Shipping entitlement</span><strong>{{ $detail['shipping'] ? 'PHP '.number_format($detail['shipping']->amount_cents/100, 2) : 'Not recorded' }}</strong></div>
  @if($detail['shipping'])<p>Shipping settlement status: {{ str($detail['shipping']->status)->replace('_', ' ')->title() }}. Method: {{ strtoupper($detail['shipping']->collection_mode) }}.</p>@endif
  <div class="order-cost-row"><span>COD collected</span><strong>PHP {{ number_format($detail['cash_collected_cents']/100, 2) }}</strong></div>
  <div class="order-cost-row"><span>Hub-verified cash remittance</span><strong>PHP {{ number_format($detail['cash_verified_cents']/100, 2) }}</strong></div>
  @if($detail['commission'])<div class="order-cost-row"><span>Platform commission withheld</span><strong>PHP {{ number_format($detail['commission']->withheld_cents/100, 2) }}</strong></div>@endif
  <div class="order-cost-row"><span>Platform-verified commission receipts</span><strong>PHP {{ number_format($detail['commission_received_cents']/100, 2) }}</strong></div>
  @if($earning = $detail['rider_earning'])<div class="order-cost-row"><span>Separate rider compensation — {{ $earning->status }}</span><strong>PHP {{ number_format($earning->amount_cents/100, 2) }}</strong></div>@else<p>No rider earning snapshot recorded.</p>@endif
  @if($pay = $detail['rider_payout'])<p>Rider payout: {{ str($pay->status)->title() }} — PHP {{ number_format($pay->amount_cents/100, 2) }}@if($pay->paid_at), paid {{ \Illuminate\Support\Carbon::parse($pay->paid_at)->format('M j, Y g:i A') }}@endif.</p>@endif
  <p>Seller proceeds, platform commission, shipping funds and rider payroll are separate. Buyer payment and parcel status do not prove treasury remittance or payout.</p>
</section>
