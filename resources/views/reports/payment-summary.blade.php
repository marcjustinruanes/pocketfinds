@once
@push('head')
<style>
.payment-report-layout,.payment-report-layout>.stack,.payment-report-summary{min-width:0;max-width:100%}
.payment-report-summary{overflow-wrap:anywhere}
.payment-report-summary table{width:100%;min-width:0;table-layout:fixed}
.payment-report-summary th,.payment-report-summary td{white-space:normal;overflow-wrap:anywhere}
.payment-report-summary th:last-child,.payment-report-summary td:last-child{width:125px;padding-left:10px;padding-right:10px;text-align:right;white-space:nowrap;overflow-wrap:normal}
</style>
@endpush
@endonce
<section class="card payment-report-summary">
  <div class="card-head"><div><h2>Completed sales basis</h2><p>{{ $report['from'] ? 'Selected dates, using order last update' : 'All completed orders' }}. Values describe sales, not payment or profit.</p></div></div>
  <div class="card-pad"><div class="table-wrap"><table class="{{ $tableClass ?? 'tbl' }}"><thead><tr><th>Record</th><th>PHP</th></tr></thead><tbody>
  @foreach(['item_sales'=>'Item subtotal before discounts','quoted_shipping'=>'Quoted gross shipping, included in order value','discounts'=>'Buyer discounts already applied','buyer_order_value'=>'Buyer order value after discounts, including shipping'] as $key=>$label)
    <tr><td>{{ $label }}</td><td class="mono">{{ number_format($report['metrics'][$key]/100,2) }}</td></tr>
  @endforeach
  </tbody></table></div></div>
</section>
<section class="card payment-report-summary">
  <div class="card-head"><div><h2>Recorded payment activity</h2><p>{{ $report['from'] ? 'Selected dates' : 'All recorded history' }}; each amount uses its own receipt, count or payout date.</p></div></div>
  <div class="card-pad">
    @unless($report['available'])<p>Financial records are unavailable until the existing payment schemas are installed. No balances are inferred.</p>@endunless
    <div class="table-wrap"><table class="{{ $tableClass ?? 'tbl' }}"><thead><tr><th>Record</th><th>PHP</th></tr></thead><tbody>
    @foreach([
      'buyer_gcash_verified'=>'Buyer GCash verified directly by Seller',
      'cod_collected'=>'COD recorded by Riders (not Seller payment)',
      'cod_verified'=>'COD physically verified by Hub Staff',
      'seller_cod_paid'=>'Actual recorded COD payouts to Sellers',
      'shipping_gcash_paid'=>'GCash shipping paid to Logistics',
      'cod_shipping_retained'=>'Shipping retained in actual COD settlements'
    ] as $key=>$label)
      <tr><td>{{ $label }}</td><td class="mono">{{ $report['available'] ? number_format($report['metrics'][$key]/100,2) : 'Unavailable' }}</td></tr>
    @endforeach
    </tbody></table></div>
    <p>Direct GCash receipts and COD payouts are separate Seller payment paths. COD collection/count and payout ledger mirrors are not additional Seller receipts. Shipping is a logistics charge, not Seller profit. These are gross recorded events; later returns or refunds require separate review.</p>
  </div>
</section>
<section class="card payment-report-summary">
  <div class="card-head"><div><h2>Current pending instructions</h2><p>All history, as of {{ $report['snapshot_at']->format('Y-m-d H:i:s T') }}; independent of the sales date range.</p></div></div>
  <div class="card-pad"><div class="table-wrap"><table class="{{ $tableClass ?? 'tbl' }}"><thead><tr><th>Record</th><th>PHP</th></tr></thead><tbody>
    @foreach(['pending_cod_payouts'=>'Prepared / verified COD payouts, not yet paid','held_cod_payouts'=>'Held portion of pending payouts (already included above)','unverified_platform_claims'=>'Unverified platform shipping claims, not received'] as $key=>$label)
      <tr><td>{{ $label }}</td><td class="mono">{{ $report['available'] ? number_format($report['metrics'][$key]/100,2) : 'Unavailable' }}</td></tr>
    @endforeach
  </tbody></table></div><p>Orders without prepared payout instructions are not included in pending payout amounts. Eligibility, disputes and financial review remain in COD Payouts / Seller Settlements. No commission is deducted.</p></div>
</section>
