@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}?v={{ filemtime(public_path('css/logistics-cash-desk.css')) }}">@endpush
<div class="cash-desk finance-shipping-section">
  @if($errors->any())<div class="cash-alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  {{-- Shipping obligations --}}
  <section class="cash-card" aria-labelledby="fin-obligations-heading">
    <div class="fin-card-head">
      <div>
        <p class="fin-section-label">Shipping settlements</p>
        <h2 id="fin-obligations-heading">Shipping obligations</h2>
        <p class="fin-note">Gross shipping is the carrier entitlement. Sellers advance the full GCash shipping fee; platform voucher claims remain separate and unverified.</p>
      </div>
    </div>

    @if($shipping['obligations']->isEmpty())
      <div class="fin-empty fin-empty--card">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <strong>No shipping obligations</strong>
        <p>New obligations appear here when sellers hand off parcels.</p>
      </div>
    @else
      <div class="cash-table fin-obligations-table">
        <table>
          <thead><tr>
            <th scope="col">Order</th>
            <th scope="col">Shipping fee</th>
            <th scope="col">Collection</th>
            <th scope="col">Status</th>
            <th scope="col">Platform claim</th>
            <th scope="col">Action</th>
          </tr></thead>
          <tbody>
          @foreach($shipping['obligations'] as $ob)
            <tr>
              <td class="cash-reference">{{ $ob->order_id }}</td>
              <td class="fin-amount">₱{{ number_format($ob->amount_cents/100,2) }}</td>
              <td><span class="fin-mode-badge fin-mode-badge--{{ strtolower($ob->collection_mode) }}">{{ strtoupper($ob->collection_mode) }}</span></td>
              <td><span class="fin-status-pill fin-status-pill--{{ str_replace('_','-',$ob->status) }}">{{ ucwords(str_replace('_',' ',$ob->status)) }}</span></td>
              <td>
                <span class="fin-amount-sm">₱{{ number_format($ob->platform_claim_cents/100,2) }}</span>
                <span class="fin-claim-status fin-claim-status--{{ $ob->claim_status }}">{{ ucfirst($ob->claim_status) }}</span>
              </td>
              <td><a class="btn btn-sm" href="{{ route('logistics.finance.shipping.show',$ob->id) }}">View</a></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="fin-obligations-pagination">{{ $shipping['obligations']->links() }}</div>
    @endif
  </section>

  {{-- Shipping configuration collapsible --}}
  <details class="fin-reference-details fin-shipping-config" @if($errors->any()) open @endif>
    <summary>
      <span class="fin-config-summary-inner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
        <span>Shipping configuration</span>
        <span class="fin-config-subtitle">GCash account and regional rates</span>
      </span>
      <span class="fin-badge fin-badge--editable">Editable</span>
    </summary>

    <div class="fin-reference-body">
      <div class="fin-config-grid">

        {{-- GCash receiving account --}}
        <section class="fin-config-panel" aria-labelledby="fin-gcash-heading">
          <p class="fin-section-label">Receiving account</p>
          <h3 id="fin-gcash-heading">Company GCash account</h3>
          <p class="fin-note">Only publish an account authorized to receive company shipping payments. Existing shipments keep their original receiving instructions.</p>
          @if($shipping['account'])
          <div class="fin-current-account" role="status" aria-label="Current receiving account">
            <span class="fin-account-label">Current account</span>
            <strong>{{ $shipping['account']->account_name }}</strong>
            <span class="fin-account-number">{{ $shipping['account']->account_number }}</span>
          </div>
          @endif
          <form method="POST" action="{{ route('logistics.finance.shipping.account') }}" class="fin-config-form">
            @csrf
            <input type="hidden" name="revision" value="{{ $shipping['account']?->revision ?? 0 }}">
            <label for="shipping-account-name">
              Account name
              <span class="fin-field-hint">Full name as registered with GCash</span>
            </label>
            <input id="shipping-account-name" name="account_name" minlength="2" maxlength="150" required
              value="{{ $shipping['account']?->account_name }}" placeholder="e.g. Juan dela Cruz" autocomplete="off">
            <label for="shipping-account-number">
              GCash mobile number
              <span class="fin-field-hint">11-digit number starting with 09</span>
            </label>
            <input id="shipping-account-number" name="account_number" type="tel" inputmode="numeric"
              pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" required
              value="{{ $shipping['account']?->account_number }}" autocomplete="off">
            <button class="btn btn-primary fin-config-btn">{{ $shipping['account'] ? 'Update receiving account' : 'Save receiving account' }}</button>
          </form>
        </section>

        {{-- New regional rate --}}
        <section class="fin-config-panel" aria-labelledby="fin-newrate-heading">
          <p class="fin-section-label">Add rate</p>
          <h3 id="fin-newrate-heading">Approve a new regional rate</h3>
          <p class="fin-note">First kilogram plus each additional started kilogram. Match the destination region shown in the delivery address. Zero rates must be intentionally approved.</p>
          @include('logistics.partials.shipping-rate-form', ['rate' => null, 'formId' => 'new'])
        </section>

      </div>

      {{-- Approved rates list --}}
      <section class="fin-config-rates" aria-labelledby="fin-rates-heading">
        <div class="fin-config-rates-head">
          <div>
            <p class="fin-section-label">Approved rates</p>
            <h3 id="fin-rates-heading">Regional shipping rates</h3>
            <p class="fin-note">Publishing a revision changes future shipment validation. Existing obligations keep their snapshotted rate.</p>
          </div>
        </div>
        @if($shipping['rates']->isEmpty())
          <div class="fin-empty fin-empty--inline">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
            <span>No company rates approved yet. New shipment handoffs will require rate approval before proceeding.</span>
          </div>
        @else
          <div class="fin-rates-grid">
            @foreach($shipping['rates'] as $rate)
            <div class="fin-rate-card">
              <div class="fin-rate-header">
                <strong>{{ $rate->region }}</strong>
                <span class="fin-badge fin-badge--readonly">Rev. {{ $rate->revision }}</span>
              </div>
              <dl class="fin-rate-amounts">
                <div><dt>First kg</dt><dd>₱{{ number_format($rate->first_kg_cents/100,2) }}</dd></div>
                <div><dt>Each additional kg</dt><dd>₱{{ number_format($rate->additional_kg_cents/100,2) }}</dd></div>
              </dl>
              @include('logistics.partials.shipping-rate-form', ['rate' => $rate, 'formId' => $rate->id])
            </div>
            @endforeach
          </div>
        @endif
      </section>
    </div>
  </details>

</div>
