@if(auth()->user()->isLogisticsAdmin())
<section class="finance-guidance" aria-label="Financial workflow guidance">
  @if(($financeStep ?? '') === 'cash')
  <div class="fin-guidance-icon" aria-hidden="true">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>
  </div>
  <div class="fin-guidance-body">
    <strong>Count at the hub, then fund the company account</strong>
    <p>Staff counts Rider remittances here. For Seller payments, authorize a treasury handover in Seller Settlements, record Staff release here, then verify actual company-account receipt there.</p>
    <a href="{{ route('logistics.finance.payouts') }}#treasury">Continue to company treasury →</a>
  </div>
  @elseif(($financeStep ?? '') === 'seller')
  <div class="fin-guidance-icon" aria-hidden="true">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
  </div>
  <div class="fin-guidance-body">
    <strong>Eligibility and available funds are separate checks</strong>
    <p>Staff-verified COD is still hub cash until released and received into treasury. Prepare reserves funds; Verified approves an instruction; Paid records an actual external GCash payment.</p>
    <a href="{{ route('logistics.cash-desk') }}#cash-history">Check Staff release and cash handovers →</a>
  </div>
  @elseif(($financeStep ?? '') === 'staff')
  <div class="fin-guidance-body">
    <strong>Fixed salaries use separate Staff payroll funds</strong>
    <p>Generate an obligation, verify additional Staff funding, prepare and review the instruction, then record a salary manually sent through GCash. Staff acknowledges actual receipt in My Salary.</p>
    <a href="#funding">Review Staff payroll funding</a>
  </div>
  @elseif(($financeStep ?? '') === 'rider')
  <div class="fin-guidance-icon" aria-hidden="true">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
  </div>
  <div class="fin-guidance-body">
    <strong>Earned work needs separately funded payroll</strong>
    <p>A pending rate snapshot is not earned pay. Prepared reserves payroll funds; Verified approves the instruction; Paid records actual GCash payment. Recipient acknowledgement does not pay again.</p>
    <a href="#funding">Review payroll funding →</a>
  </div>
  @elseif(($financeStep ?? '') === 'reconciliation')
  <div class="fin-guidance-icon" aria-hidden="true">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
  </div>
  <div class="fin-guidance-body">
    <strong>Review the records without moving money</strong>
    <p>This snapshot checks custody, obligations and payments. Use Cash Desk for physical cash, Seller Settlements for COD proceeds, and Rider Payouts for separately funded payroll.</p>
  </div>
  @else
  <div class="fin-guidance-icon" aria-hidden="true">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
  </div>
  <div class="fin-guidance-body">
    <strong>Choose the workspace for the next action</strong>
    <p>Cash Desk for physical cash · Seller Settlements for COD proceeds · Rider Payouts for payroll · Reconciliation to review records. GCash transfers happen outside PocketFinds.</p>
  </div>
  @endif
</section>
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-finance-navigation.css') }}?v={{ filemtime(public_path('css/logistics-finance-navigation.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/logistics-finance-feedback.js') }}" defer></script>@endpush
@endif
