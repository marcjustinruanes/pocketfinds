@extends('logistics.layout')
@section('title', 'Finance Overview & Settings')
@section('page-title', 'Finance Overview & Settings')
@section('page-sub', 'Shipping payments, compensation rates and financial workspace links')
@section('content')
<div class="finance-overview">

{{-- Workflow guidance banner --}}
@if(auth()->user()->isLogisticsAdmin())
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-finance-navigation.css') }}?v={{ filemtime(public_path('css/logistics-finance-navigation.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/logistics-finance-feedback.js') }}" defer></script>@endpush
@endif

{{-- Financial workspace navigation cards --}}
<section>
  <p class="fin-section-label" style="margin-bottom:10px">Financial workspaces</p>
  <div class="fin-workspaces">
    <a href="{{ route('logistics.cash-desk') }}" class="fin-workspace-card">
      <span class="fin-workspace-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M6 12h.01M18 12h.01"/></svg>
      </span>
      <strong>Cash Desk</strong>
      <span>COD remittances, hub custody and transfers</span>
      <span class="fin-workspace-arrow" aria-hidden="true">→</span>
    </a>
    <a href="{{ route('logistics.finance.payouts') }}" class="fin-workspace-card">
      <span class="fin-workspace-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </span>
      <strong>Seller Settlements</strong>
      <span>COD proceeds, treasury qualification and payouts</span>
      <span class="fin-workspace-arrow" aria-hidden="true">→</span>
    </a>
    <a href="{{ route('logistics.finance.riders') }}" class="fin-workspace-card">
      <span class="fin-workspace-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      </span>
      <strong>Rider Earnings & Payouts</strong>
      <span>Earned compensation, payroll funding and payments</span>
      <span class="fin-workspace-arrow" aria-hidden="true">→</span>
    </a>
    <a href="{{ route('logistics.finance.reconciliation') }}" class="fin-workspace-card">
      <span class="fin-workspace-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </span>
      <strong>Reconciliation & Reports</strong>
      <span>Financial snapshot, obligations and export</span>
      <span class="fin-workspace-arrow" aria-hidden="true">→</span>
    </a>
  </div>
</section>

{{-- Shipping settlements & configuration --}}
@if($shipping ?? null)
@include('logistics.partials.shipping-finance')
@endif

{{-- Rider compensation rates --}}
<section class="fin-settings-card" aria-labelledby="fin-comp-heading">
  <div class="fin-settings-head">
    <div>
      <p class="fin-section-label">Compensation settings</p>
      <h2 id="fin-comp-heading">Assignment compensation rates</h2>
      <p class="fin-note">Rates apply to <strong>future assignments only.</strong> Each assignment keeps its agreed amount at the time it was created. Transfer compensation is recorded once per batch, regardless of parcel count.</p>
    </div>
    <span class="fin-badge fin-badge--editable" aria-label="This section is editable">Editable</span>
  </div>
  <form method="POST" action="{{ route('logistics.finance.settings') }}" class="fin-comp-form">
    @csrf
    <input type="hidden" name="revision" value="{{ $settings->revision }}">
    <div class="fin-comp-grid">
      @foreach(['pickup' => ['Pickup assignment', 'Paid per accepted pickup trip'], 'transfer' => ['Hub-transfer batch / trip', 'One payment per approved transfer batch'], 'delivery' => ['Final-delivery assignment', 'Paid per completed delivery trip']] as $leg => [$label, $hint])
      <label for="finance-{{ $leg }}" class="fin-comp-label">
        <span class="fin-comp-label-text">{{ $label }}</span>
        <span class="fin-comp-hint">{{ $hint }}</span>
        <span class="fin-currency-wrap">
          <span class="fin-currency-prefix" aria-hidden="true">₱</span>
          <input id="finance-{{ $leg }}" name="{{ $leg }}" type="number" min="0" step="0.01" required
            value="{{ old($leg, number_format($settings->{$leg.'_cents'} / 100, 2, '.', '')) }}"
            class="fin-currency-input" aria-label="{{ $label }} rate in Philippine Peso">
        </span>
      </label>
      @endforeach
    </div>
    <p class="fin-note fin-note--info">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:5px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
      Platform commissions, automatic failed-delivery charges, deductions and payout offsets are excluded. Seller confirmation timeout: 7 days.
    </p>
    <div class="fin-comp-footer">
      <button class="btn btn-primary fin-save-btn">Save compensation rates</button>
    </div>
  </form>
</section>

{{-- Reference: assignment snapshots (collapsed by default) --}}
<details class="fin-reference-details" @if(request()->has('page')) open @endif>
  <summary>
    <span class="fin-reference-summary-inner">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
      <span>Reference: assignment rate snapshots</span>
    </span>
    <span class="fin-badge fin-badge--readonly">Read-only</span>
  </summary>
  <div class="fin-reference-body">
    <p class="fin-note">Agreed rates at the time each assignment was created. Earned compensation, payroll reservations and actual payments are tracked in <a href="{{ route('logistics.finance.riders') }}">Rider Earnings &amp; Payouts</a>.</p>
    <div class="table-wrap"><table class="dtable">
      <thead><tr>
        <th scope="col">Rider</th>
        <th scope="col">Assignment</th>
        <th scope="col">Amount</th>
        <th scope="col">Status</th>
        <th scope="col">Agreed</th>
      </tr></thead>
      <tbody>@forelse($snapshots as $row)
        <tr>
          <td>{{ $row->username }}</td>
          <td>{{ ucfirst($row->leg) }}<br><small class="fin-ref">{{ $row->source_id }}</small></td>
          <td class="fin-amount">₱{{ number_format($row->amount_cents / 100, 2) }}</td>
          <td><span class="fin-status-pill fin-status-pill--{{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
          <td><span class="fin-date">{{ \Carbon\CarbonImmutable::parse($row->created_at)->setTimezone('Asia/Manila')->format('M j, Y') }}</span></td>
        </tr>
      @empty
        <tr><td colspan="5">
          <div class="fin-empty">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            <strong>No assignment snapshots yet</strong>
            <p>Existing assignments are not backfilled. New assignments will appear here once created.</p>
          </div>
        </td></tr>
      @endforelse</tbody>
    </table></div>
    <div class="logistics-pagination">{{ $snapshots->links() }}</div>
  </div>
</details>

{{-- Reference: COD collections (collapsed by default) --}}
@if($collections !== null)
<details class="fin-reference-details" @if(request()->has('collection_page')) open @endif>
  <summary>
    <span class="fin-reference-summary-inner">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M6 12h.01M18 12h.01"/></svg>
      <span>Recorded COD collections</span>
    </span>
    <span class="fin-badge fin-badge--readonly">Read-only</span>
  </summary>
  <div class="fin-reference-body">
    <p class="fin-note">Cash recorded by delivery riders. Parcel delivery does not verify a cash remittance. Full cash custody and verification is managed in <a href="{{ route('logistics.cash-desk') }}">Cash Desk</a>.</p>
    <div class="table-wrap"><table class="dtable">
      <thead><tr>
        <th scope="col">Order</th>
        <th scope="col">Rider</th>
        <th scope="col">Assigned hub</th>
        <th scope="col">Cash recorded</th>
        <th scope="col">Hub verification</th>
      </tr></thead>
      <tbody>@forelse($collections as $collection)
        <tr>
          <td class="fin-ref">{{ $collection->order_id }}</td>
          <td>{{ $collection->username }}</td>
          <td>{{ $collection->hub_municipality }}</td>
          <td class="fin-amount">₱{{ number_format($collection->amount_cents / 100, 2) }}</td>
          <td><span class="fin-status-pill fin-status-pill--{{ $collection->verification_state }}">{{ ucfirst($collection->verification_state) }}</span></td>
        </tr>
      @empty
        <tr><td colspan="5">
          <div class="fin-empty">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>
            <strong>No COD collections recorded</strong>
            <p>Collections appear here once riders record delivery payment.</p>
          </div>
        </td></tr>
      @endforelse</tbody>
    </table></div>
    <div class="logistics-pagination">{{ $collections->links() }}</div>
  </div>
</details>
@endif

</div>
@endsection
