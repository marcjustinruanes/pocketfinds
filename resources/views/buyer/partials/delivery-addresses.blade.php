{{-- Shared between the cart page (radios, to pick one per order) and the account
     page (plain list, just managing them) — pass ['addresses' => ..., 'selectable' => bool]. --}}
@php($selectable = $selectable ?? false)
@php($selectedDeliveryAddress=$addresses->firstWhere('id',session('buyer_checkout_address'))?->id??$addresses->firstWhere('is_default',true)?->id)
@if($selectable)
<div class="modal-overlay address-modal cart-address-modal" id="cartAddressModal" aria-hidden="true"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="cartAddressModalTitle"><div class="modal-head"><div><h3 id="cartAddressModalTitle">Choose delivery address</h3><p>Select the address for this order.</p></div><button class="modal-close" type="button" data-modal-close aria-label="Close address selection">&times;</button></div><div class="modal-body">
@endif
<div id="addressList" style="display:flex;flex-direction:column;gap:8px">
  @forelse($addresses as $addr)
    <label class="address-option" style="display:flex;gap:9px;align-items:flex-start;border:1px solid var(--border);border-radius:9px;padding:9px 11px;{{ $selectable ? 'cursor:pointer' : '' }}">
      @if($selectable)
        <input type="radio" name="delivery_address_radio" value="{{ $addr->id }}" @checked($addr->id===$selectedDeliveryAddress) style="margin-top:3px;flex:none">
      @endif
      <span style="flex:1;font-size:12.5px;line-height:1.5">
        <strong>{{ $addr->label ?: 'Address' }}</strong>@if($addr->is_default) <span class="address-default-badge" data-default-address>Default</span>@endif<br>
        {{ $addr->recipient_name }}{{ $addr->contact_no ? ' · ' . $addr->contact_no : '' }}<br>
        <span class="saved-address-location" style="color:var(--muted)">{{ $addr->full_address }}</span>
      </span>
      <button type="button" class="icon-btn address-action-icon" data-edit-delivery-address="{{ $addr->id }}" aria-label="Edit address" title="Edit address" style="width:24px;height:24px;flex:none">@include('buyer.partials.icon',['name'=>'edit','size'=>12])</button>
      @unless($addr->is_default)
        <button type="button" class="icon-btn address-action-icon" data-delete-address="{{ $addr->id }}" aria-label="Remove {{ $addr->label ?: 'saved' }} address" title="Remove address" style="width:24px;height:24px;flex:none">
          @include('buyer.partials.icon', ['name' => 'trash', 'size' => 12])
        </button>
      @endunless
    </label>
    <div id="editDeliveryAddress{{ $addr->id }}" class="modal-overlay address-modal" aria-hidden="true">
      <section class="modal" role="dialog" aria-modal="true" aria-labelledby="editAddressTitle{{ $addr->id }}">
      <div class="modal-head"><div><h3 id="editAddressTitle{{ $addr->id }}">Edit {{ $addr->is_default && !$selectable ? 'registered' : 'delivery' }} address</h3>@if($addr->is_default && !$selectable)<p>Changes to your registered address require admin approval.</p>@endif</div></div>
      <form class="delivery-address-editor" method="POST" action="{{ $addr->is_default && !$selectable ? route('buyer.account.address') : route('buyer.addresses.update',$addr) }}" @if($addr->is_default && !$selectable) data-admin-address @endif>
        @csrf @unless($addr->is_default && !$selectable) @method('PATCH') @endunless
        <div class="modal-body">
        <div class="address-form-grid">
        @foreach(['label'=>'Label','recipient_name'=>'Recipient name','contact_no'=>'Mobile number','region'=>'Region','province'=>'Province','municipality'=>'City / municipality','barangay'=>'Barangay','postal_code'=>'Postal code','house_no'=>'House / unit','street'=>'Street'] as $field=>$label)
        @continue($addr->is_default && !$selectable && in_array($field,['label','recipient_name','contact_no']))
         <label class="address-field-{{ $field }}">{{ $label }}<input name="{{ $field }}" value="{{ $field==='contact_no' ? substr(\App\Support\PhilippineMobile::normalize((string)$addr->contact_no),1) : $addr->$field }}" @required(!in_array($field,['label','house_no','street','postal_code'])) @if($field==='region') data-address-edit-region @endif @if($field==='postal_code') inputmode="numeric" pattern="[0-9]{4}" maxlength="4" @endif @if($field==='contact_no') type="tel" data-national-mobile inputmode="numeric" minlength="10" maxlength="10" pattern="9[0-9]{9}" @endif></label>
        @endforeach
        </div></div>
        <div class="modal-foot">@unless($addr->is_default)<button class="btn btn-outline address-make-default" type="submit" form="defaultAddressForm{{ $addr->id }}">Make default</button>@endunless<button class="btn btn-outline" type="button" data-close-address-edit>Cancel</button><button type="submit" class="btn btn-primary">{{ $addr->is_default && !$selectable ? 'Submit address for review' : 'Save delivery address' }}</button></div>
      </form>
      @unless($addr->is_default)<form id="defaultAddressForm{{ $addr->id }}" method="POST" action="{{ route('buyer.addresses.default',$addr) }}">@csrf</form>@endunless
      </section></div>
  @empty
    <p id="noAddressMessage" style="font-size:12px;color:var(--muted);margin:0">No saved addresses yet — add one below.</p>
  @endforelse
</div>
@if($selectable)</div><div class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close>Done</button><button type="button" class="btn btn-primary" data-modal-open="addAddressModal" data-modal-close>Add address</button></div></section></div>@endif

@once<script src="{{ asset('js/buyer-address-edit.js') }}?v={{ filemtime(public_path('js/buyer-address-edit.js')) }}"></script>@endonce

<style>
.address-default-badge{display:inline-flex;align-items:center;justify-content:center;margin-left:5px;padding:2px 8px;border:1px solid #e6bcd3;border-radius:999px;background:#fcf1f7;color:#a52d6e;font:600 10px/1.5 Inter,system-ui,sans-serif;vertical-align:middle;white-space:nowrap}
.address-default-badge[hidden]{display:none!important}
.delivery-address-editor label{display:flex;flex-direction:column;gap:6px;min-width:0;font-size:12px}
.delivery-address-editor input,.delivery-address-editor select{box-sizing:border-box;width:100%;min-width:0;padding:10px;border:1px solid var(--border);border-radius:8px;font:inherit;background:#fff;color:var(--ink)}
.delivery-address-editor .address-form-grid{margin-bottom:14px}
.delivery-address-editor [role=alert]{color:var(--danger)}
.buyer-pages .address-modal { z-index: 240; padding: 16px; }
.buyer-pages .address-modal.open { display: flex; align-items: center; justify-content: center; }
.address-modal [hidden] { display: none !important; }
.buyer-pages .address-modal > .modal { display: flex; flex-direction: column; width: min(560px, 100%); max-width: 560px; max-height: calc(100dvh - 32px); margin: 0; border-radius: 14px; overflow: hidden; }
.address-modal .modal-head, .address-modal .modal-foot { flex: none; position: static; }
.address-modal .modal-head p { line-height: 1.5; }
.address-modal .modal-close { min-width: 40px; min-height: 40px; cursor: pointer; }
.address-modal form { display: flex; flex-direction: column; min-height: 0; }
.address-modal .modal-body { padding: 20px 22px; overflow-y: auto; min-height: 0; }
.address-form-grid { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 16px; }
.address-modal .form-row { margin: 0; min-width: 0; }
.address-modal .form-row label { font-size: 13px; line-height: 1.5; }
.address-modal .form-row input, .address-modal .form-row select { box-sizing: border-box; width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid #dcd2dc; border-radius: 8px; background: #fff; color: #302733; font: inherit; font-size: 14px; }
.address-modal .form-row select:disabled { background: #f8f5f7; color: #746776; }
.address-form-wide { grid-column: 1 / -1; }
.address-modal .address-hint { font-size: 12px; line-height: 1.5; color: #695e6c; margin: 6px 0 0; }
.address-modal .address-error { font-size: 13px; line-height: 1.5; color: #a72734; margin: 12px 0 0; }
.address-modal .modal-foot .btn { min-height: 44px; }
.address-modal :is(button,input,select):focus-visible { outline: 3px solid #1b6b91; outline-offset: 2px; }
.address-modal button:disabled { opacity: .6; cursor: wait; }
#removeAddressModal > .modal { max-width: 420px; }
#removeAddressModal .modal-body p { margin: 0; line-height: 1.6; overflow-wrap: anywhere; }
@media(max-width:560px) { .address-form-grid { grid-template-columns: minmax(0,1fr); gap: 12px; } .address-modal .form-row input, .address-modal .form-row select { font-size: 16px; } .address-modal .modal-head, .address-modal .modal-body, .address-modal .modal-foot { padding: 16px; } }
</style>
<div class="modal-overlay address-modal" id="addAddressModal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="addAddressTitle" aria-describedby="addAddressDescription">
    <div class="modal-head">
      <div><h3 id="addAddressTitle">Add delivery address</h3><p id="addAddressDescription">Save an address to use at checkout.</p></div>
    </div>
    <form id="addAddressForm" class="delivery-address-editor">
    <div class="modal-body"><div class="address-form-grid">
      <div class="form-row address-field-label"><label for="addrLabel">Label</label><input class="auth-input" id="addrLabel" name="label" placeholder="e.g. Home, Work" maxlength="50"></div>
      <div class="form-row address-field-recipient_name"><label for="addrRecipientName">Recipient name</label><input class="auth-input" id="addrRecipientName" name="recipient_name" maxlength="150" autocomplete="name" required></div>
      <div class="form-row address-field-contact_no"><label for="addrContactNo">Mobile number</label><input class="auth-input" id="addrContactNo" name="contact_no" data-national-mobile type="tel" maxlength="10" minlength="10" inputmode="numeric" pattern="9[0-9]{9}" autocomplete="tel-national" required></div>
      <div class="form-row address-field-region"><label for="addrRegion">Region</label><select id="addrRegion" name="region" required><option value="">Loading regions…</option></select></div>
      <div class="form-row address-field-province"><label for="addrProvince">Province</label><select class="select" id="addrProvince" name="province" required><option value="" disabled selected>Loading provinces…</option></select></div>
      <div class="form-row address-field-municipality"><label for="addrMunicipality">City / municipality</label><select class="select" id="addrMunicipality" name="municipality" disabled required><option value="" disabled selected>Select province first</option></select></div>
      <div class="form-row address-field-barangay"><label for="addrBarangay">Barangay</label><select class="select" id="addrBarangay" name="barangay" disabled required><option value="" disabled selected>Select municipality first</option></select></div>
      <div class="form-row address-field-postal_code"><label for="addrPostalCode">Postal code</label><input id="addrPostalCode" name="postal_code" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" readonly></div>
        <div class="form-row address-field-house_no"><label for="addrHouseNo">House / unit</label><input class="auth-input" id="addrHouseNo" name="house_no" maxlength="100"></div>
        <div class="form-row address-field-street"><label for="addrStreet">Street</label><input class="auth-input" id="addrStreet" name="street" maxlength="150"></div>
      </div>
      <button type="button" class="btn btn-outline" id="retryAddressLocations" hidden>Retry loading provinces</button>
      <p id="addAddressError" class="address-error" role="alert" style="display:none"></p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="submit" class="btn btn-primary" id="saveAddressBtn">Save address</button>
    </div>
    </form>
  </div>
</div>
<div class="modal-overlay address-modal" id="removeAddressModal" aria-hidden="true">
  <div class="modal pf-confirm" role="dialog" aria-modal="true" aria-labelledby="removeAddressTitle" aria-describedby="removeAddressDescription">
    <div class="modal-head"><h3 id="removeAddressTitle">Remove saved address?</h3></div>
    <div class="modal-body"><p id="removeAddressDescription">Remove this address from your saved list? Delivery details on existing orders will stay unchanged.</p><p class="address-error" id="removeAddressError" role="alert" hidden></p></div>
    <div class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close id="keepAddressBtn">No, keep it</button><button type="button" class="btn btn-danger" id="confirmRemoveAddressBtn">Yes, remove</button></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ---- Delivery Address ----
  const PSGC = 'https://psgc.gitlab.io/api';
  const addrProvince = document.getElementById('addrProvince');
  const addrMunicipality = document.getElementById('addrMunicipality');
  const addrBarangay = document.getElementById('addrBarangay');
  const addrContactNo = document.getElementById('addrContactNo');
  addrContactNo.addEventListener('input', () => {
    addrContactNo.value = addrContactNo.value.replace(/[^0-9]/g, '').slice(0, 10);
    const number = addrContactNo.value;
    const message = number && !/^9[0-9]{9}$/.test(number) ? 'Enter exactly 10 digits starting with 9.' : '';
    addrContactNo.setCustomValidity(message);
    addrContactNo.setAttribute('aria-invalid', message ? 'true' : 'false');
  });
  const addModal = document.getElementById('addAddressModal');
  const removeModal = document.getElementById('removeAddressModal');
  let returnFocus = null, pendingAddressButton = null;
  const closeAddressModal = modal => {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    returnFocus?.focus();
  };
  document.addEventListener('click', event => {
    const opener = event.target.closest('[data-modal-open="addAddressModal"]');
    if (opener) {
      returnFocus = opener;
      addModal.classList.add('open');
      addModal.setAttribute('aria-hidden', 'false');
      document.getElementById('addrLabel').focus();
      if (!addrProvince.dataset.loaded) loadProvinces();
    }
    const modal = event.target.closest('.address-modal');
    if (modal && (event.target === modal || event.target.closest('[data-modal-close]'))) closeAddressModal(modal);
  });
  [addModal, removeModal].forEach(modal => modal.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeAddressModal(modal); }
    if (event.key !== 'Tab') return;
    const controls = [...modal.querySelectorAll('button,input,select')].filter(el => !el.disabled && el.getClientRects().length);
    const first = controls[0], last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
  }));

  async function fetchAddrJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('Network error');
    return res.json();
  }
  function populateAddrSelect(sel, items, placeholder) {
    sel.innerHTML = `<option value="" disabled selected>${placeholder}</option>`;
    [...items].sort((a, b) => a.name.localeCompare(b.name)).forEach(item => {
      const o = document.createElement('option');
      o.value = item.name; o.dataset.code = item.code; o.textContent = item.name;
      sel.appendChild(o);
    });
    sel.disabled = false;
  }

  function loadProvinces() {
    if(document.getElementById('addrRegion')) return;
    if (addrProvince.dataset.loading) return;
    addrProvince.dataset.loading = 'true';
    const retry = document.getElementById('retryAddressLocations');
    retry.hidden = true;
    addrProvince.disabled = true;
    fetchAddrJSON(`${PSGC}/provinces/`)
      .then(data => { populateAddrSelect(addrProvince, data, 'Select province'); addrProvince.dataset.loaded = 'true'; })
      .catch(() => { addrProvince.innerHTML = '<option value="" disabled selected>Could not load provinces</option>'; retry.hidden = false; })
      .finally(() => { delete addrProvince.dataset.loading; });
  }
  document.getElementById('retryAddressLocations').addEventListener('click', loadProvinces);
  if (addrProvince) {

    addrProvince.addEventListener('change', function () {
      addrMunicipality.innerHTML = '<option value="" disabled selected>Loading…</option>';
      addrMunicipality.disabled = true;
      addrBarangay.innerHTML = '<option value="" disabled selected>Select municipality first</option>';
      addrBarangay.disabled = true;
      const code = this.options[this.selectedIndex]?.dataset.code ?? this.value;
      const parent=this.selectedOptions[0]?.dataset.isRegion==='1'?'regions':'provinces';
      const selectedRegion=document.getElementById('addrRegion')?.value;
      fetchAddrJSON(`${PSGC}/${parent}/${code}/cities-municipalities/`)
        .then(data => {if((addrProvince.selectedOptions[0]?.dataset.code??addrProvince.value)===code && document.getElementById('addrRegion')?.value===selectedRegion)populateAddrSelect(addrMunicipality, data, 'Select city / municipality');})
        .catch(() => { addrMunicipality.innerHTML = '<option value="" disabled selected>Failed to load</option>'; addrMunicipality.disabled = false; });
    });

    addrMunicipality.addEventListener('change', function () {
      addrBarangay.innerHTML = '<option value="" disabled selected>Loading…</option>';
      addrBarangay.disabled = true;
      const code = this.options[this.selectedIndex]?.dataset.code ?? this.value;
      const selectedProvince=addrProvince.value;
      const selectedRegion=document.getElementById('addrRegion')?.value;
      fetchAddrJSON(`${PSGC}/cities-municipalities/${code}/barangays/`)
        .then(data => {if((addrMunicipality.selectedOptions[0]?.dataset.code??addrMunicipality.value)===code && addrProvince.value===selectedProvince && document.getElementById('addrRegion')?.value===selectedRegion)populateAddrSelect(addrBarangay, data, 'Select barangay');})
        .catch(() => { addrBarangay.innerHTML = '<option value="" disabled selected>Failed to load</option>'; addrBarangay.disabled = false; });
    });
  }

  document.getElementById('addAddressForm')?.addEventListener('submit', event => {
    event.preventDefault();
    if (document.getElementById('saveAddressBtn').disabled) return;
    const errorEl = document.getElementById('addAddressError');
    errorEl.style.display = 'none';
    const payload = {
      label: document.getElementById('addrLabel').value.trim(),
      recipient_name: document.getElementById('addrRecipientName').value.trim(),
      contact_no: document.getElementById('addrContactNo').value.trim(),
      region: document.getElementById('addrRegion').value,
      postal_code: document.getElementById('addrPostalCode').value,
      province: addrProvince.value,
      municipality: addrMunicipality.value,
      barangay: addrBarangay.value,
      house_no: document.getElementById('addrHouseNo').value.trim(),
      street: document.getElementById('addrStreet').value.trim(),
    };
    if (!payload.recipient_name || !payload.province || !payload.municipality || !payload.barangay) {
      errorEl.textContent = 'Recipient name, province, municipality, and barangay are required.';
      errorEl.style.display = 'block';
      return;
    }
    const btn = document.getElementById('saveAddressBtn');
    btn.disabled = true; btn.textContent = 'Saving…';
    fetch('{{ route('buyer.addresses.store') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false; btn.textContent = 'Save address';
      if (data.success) {
        window.location.reload();
      } else {
        errorEl.textContent = Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save this address.';
        errorEl.style.display = 'block';
      }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Save address'; errorEl.textContent = 'Network error. Try again.'; errorEl.style.display = 'block'; });
  });

  document.querySelectorAll('[data-delete-address]').forEach(btn => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      if (document.getElementById('confirmRemoveAddressBtn').disabled) return;
      pendingAddressButton = btn;
      returnFocus = btn;
      document.getElementById('removeAddressError').hidden = true;
      removeModal.classList.add('open');
      removeModal.setAttribute('aria-hidden', 'false');
      document.getElementById('keepAddressBtn').focus();
    });
  });
  document.getElementById('confirmRemoveAddressBtn').addEventListener('click', async event => {
    const button = event.currentTarget, error = document.getElementById('removeAddressError');
    if (!pendingAddressButton || button.disabled) return;
    button.disabled = true;
    button.textContent = 'Removing…';
    error.hidden = true;
    try {
      const response = await fetch(`/buyer/addresses/${pendingAddressButton.dataset.deleteAddress}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Could not remove this address.');
      window.location.reload();
    } catch (failure) {
      error.textContent = failure.message || 'Could not remove this address. Please try again.';
      error.hidden = false;
      button.disabled = false;
      button.textContent = 'Yes, remove';
    }
  });
});
</script>
