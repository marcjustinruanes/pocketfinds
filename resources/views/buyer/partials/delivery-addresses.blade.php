{{-- Shared between the cart page (radios, to pick one per order) and the account
     page (plain list, just managing them) — pass ['addresses' => ..., 'selectable' => bool]. --}}
@php($selectable = $selectable ?? false)
<div id="addressList" style="display:flex;flex-direction:column;gap:8px">
  @forelse($addresses as $addr)
    <label class="address-option" style="display:flex;gap:9px;align-items:flex-start;border:1px solid var(--border);border-radius:9px;padding:9px 11px;{{ $selectable ? 'cursor:pointer' : '' }}">
      @if($selectable)
        <input type="radio" name="delivery_address_radio" value="{{ $addr->id }}" {{ $addr->is_default ? 'checked' : '' }} style="margin-top:3px;flex:none">
      @endif
      <span style="flex:1;font-size:12.5px;line-height:1.5">
        <strong>{{ $addr->label ?: 'Address' }}</strong>{{ $addr->is_default ? ' · Default' : '' }}<br>
        {{ $addr->recipient_name }}{{ $addr->contact_no ? ' · ' . $addr->contact_no : '' }}<br>
        <span style="color:var(--muted)">{{ $addr->full_address }}</span>
        @if($addr->is_default)
          <br><span style="color:var(--muted);font-size:11px">Set from your account address — change it via an account update request</span>
        @endif
      </span>
      @unless($addr->is_default)
        <button type="button" class="icon-btn" data-delete-address="{{ $addr->id }}" title="Remove address" style="width:24px;height:24px;flex:none">
          @include('buyer.partials.icon', ['name' => 'trash', 'size' => 12])
        </button>
      @endunless
    </label>
  @empty
    <p id="noAddressMessage" style="font-size:12px;color:var(--muted);margin:0">No saved addresses yet — add one below.</p>
  @endforelse
</div>

<div class="modal-overlay" id="addAddressModal">
  <div class="modal" style="max-width:460px">
    <div class="modal-head">
      <div><h3>Add Delivery Address</h3><p>Save it once, pick it at checkout anytime</p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:12px">
      <div class="form-row"><label for="addrLabel">Label <span style="color:var(--muted);font-weight:400">(optional)</span></label><input class="auth-input" id="addrLabel" placeholder="e.g. Home, Work" maxlength="50"></div>
      <div class="form-row"><label for="addrRecipientName">Recipient Name</label><input class="auth-input" id="addrRecipientName" maxlength="150"></div>
      <div class="form-row"><label for="addrContactNo">Contact No.</label><input class="auth-input" id="addrContactNo" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric"></div>
      <div class="form-row"><label for="addrProvince">Province</label><select class="select" id="addrProvince"><option value="" disabled selected>Loading provinces…</option></select></div>
      <div class="form-row"><label for="addrMunicipality">City / Municipality</label><select class="select" id="addrMunicipality" disabled><option value="" disabled selected>Select province first</option></select></div>
      <div class="form-row"><label for="addrBarangay">Barangay</label><select class="select" id="addrBarangay" disabled><option value="" disabled selected>Select municipality first</option></select></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-row"><label for="addrHouseNo">House No. / Unit</label><input class="auth-input" id="addrHouseNo" maxlength="100"></div>
        <div class="form-row"><label for="addrStreet">Street</label><input class="auth-input" id="addrStreet" maxlength="150"></div>
      </div>
      <p id="addAddressError" style="display:none;color:var(--danger);font-size:12px;margin:0"></p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" id="saveAddressBtn">Save Address</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ---- Delivery Address ----
  const PSGC = 'https://psgc.gitlab.io/api';
  const addrProvince = document.getElementById('addrProvince');
  const addrMunicipality = document.getElementById('addrMunicipality');
  const addrBarangay = document.getElementById('addrBarangay');

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

  if (addrProvince) {
    fetchAddrJSON(`${PSGC}/provinces/`)
      .then(data => populateAddrSelect(addrProvince, data, 'Select province'))
      .catch(() => { addrProvince.innerHTML = '<option value="" disabled selected>Failed to load provinces</option>'; addrProvince.disabled = false; });

    addrProvince.addEventListener('change', function () {
      addrMunicipality.innerHTML = '<option value="" disabled selected>Loading…</option>';
      addrMunicipality.disabled = true;
      addrBarangay.innerHTML = '<option value="" disabled selected>Select municipality first</option>';
      addrBarangay.disabled = true;
      const code = this.options[this.selectedIndex]?.dataset.code ?? this.value;
      fetchAddrJSON(`${PSGC}/provinces/${code}/cities-municipalities/`)
        .then(data => populateAddrSelect(addrMunicipality, data, 'Select city / municipality'))
        .catch(() => { addrMunicipality.innerHTML = '<option value="" disabled selected>Failed to load</option>'; addrMunicipality.disabled = false; });
    });

    addrMunicipality.addEventListener('change', function () {
      addrBarangay.innerHTML = '<option value="" disabled selected>Loading…</option>';
      addrBarangay.disabled = true;
      const code = this.options[this.selectedIndex]?.dataset.code ?? this.value;
      fetchAddrJSON(`${PSGC}/cities-municipalities/${code}/barangays/`)
        .then(data => populateAddrSelect(addrBarangay, data, 'Select barangay'))
        .catch(() => { addrBarangay.innerHTML = '<option value="" disabled selected>Failed to load</option>'; addrBarangay.disabled = false; });
    });
  }

  document.getElementById('saveAddressBtn')?.addEventListener('click', () => {
    const errorEl = document.getElementById('addAddressError');
    errorEl.style.display = 'none';
    const payload = {
      label: document.getElementById('addrLabel').value.trim(),
      recipient_name: document.getElementById('addrRecipientName').value.trim(),
      contact_no: document.getElementById('addrContactNo').value.trim(),
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
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false; btn.textContent = 'Save Address';
      if (data.success) {
        window.location.reload();
      } else {
        errorEl.textContent = data.message || 'Could not save this address.';
        errorEl.style.display = 'block';
      }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Save Address'; errorEl.textContent = 'Network error. Try again.'; errorEl.style.display = 'block'; });
  });

  document.querySelectorAll('[data-delete-address]').forEach(btn => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      if (!confirm('Remove this delivery address?')) return;
      const id = btn.dataset.deleteAddress;
      fetch(`/buyer/addresses/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      }).then(() => window.location.reload());
    });
  });
});
</script>
