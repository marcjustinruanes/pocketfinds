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
          <br><span style="color:var(--muted);font-size:11px">Selected automatically at checkout</span>
        @endif
      </span>
      <button type="button" class="btn btn-outline" data-edit-address="{{ $addr->id }}" style="font-size:11px;padding:4px 8px">Edit</button>
      @unless($addr->is_default)
        <form method="POST" action="{{ route('buyer.addresses.default', $addr) }}">
          @csrf
          @method('PATCH')
          <button type="submit" class="btn btn-outline" style="font-size:11px;padding:4px 8px">Set default</button>
        </form>
        <button type="button" class="icon-btn" data-delete-address="{{ $addr->id }}" aria-label="Remove {{ $addr->label ?: 'saved' }} address" title="Remove address" style="width:24px;height:24px;flex:none">
          @include('buyer.partials.icon', ['name' => 'trash', 'size' => 12])
        </button>
      @endunless
    </label>
  @empty
    <p id="noAddressMessage" style="font-size:12px;color:var(--muted);margin:0">No saved addresses yet — add one below.</p>
  @endforelse
</div>

<style>
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
      <div><h3 id="addAddressTitle">Add delivery address</h3><p id="addAddressDescription">Save an address to use at checkout. Fields marked * are required.</p></div>
      <button class="modal-close" type="button" data-modal-close aria-label="Close address form">✕</button>
    </div>
    <form id="addAddressForm" data-store-url="{{ route('buyer.addresses.store') }}">
    <div class="modal-body"><div class="address-form-grid">
      <div class="form-row"><label for="addrLabel">Label <span style="color:var(--muted);font-weight:400">(optional)</span></label><input class="auth-input" id="addrLabel" placeholder="e.g. Home, Work" maxlength="50"></div>
      <div class="form-row"><label for="addrRecipientName">Recipient name *</label><input class="auth-input" id="addrRecipientName" maxlength="150" autocomplete="name" required></div>
      <div class="form-row address-form-wide"><label for="addrContactNo">Mobile number *</label><input class="auth-input" id="addrContactNo" type="tel" placeholder="09XXXXXXXXX or +639XXXXXXXXX" maxlength="13" pattern="(09[0-9]{9}|\+639[0-9]{9})" autocomplete="tel" required aria-describedby="addrContactHint"><p class="address-hint" id="addrContactHint">Use 09XXXXXXXXX or +639XXXXXXXXX.</p></div>
      <div class="form-row address-form-wide"><label for="addrRegion">Region *</label><select class="select" id="addrRegion" required><option value="" disabled selected>Select region</option></select></div>
      <div class="form-row"><label for="addrProvince">Province *</label><select class="select" id="addrProvince" required><option value="" disabled selected>Loading provinces…</option></select></div>
      <div class="form-row"><label for="addrMunicipality">City / municipality *</label><select class="select" id="addrMunicipality" disabled required><option value="" disabled selected>Select province first</option></select></div>
      <div class="form-row address-form-wide"><label for="addrBarangay">Barangay *</label><select class="select" id="addrBarangay" disabled required><option value="" disabled selected>Select municipality first</option></select></div>
        <div class="form-row"><label for="addrHouseNo">House No. / Unit</label><input class="auth-input" id="addrHouseNo" maxlength="100"></div>
        <div class="form-row"><label for="addrStreet">Street *</label><input class="auth-input" id="addrStreet" maxlength="150" required></div>
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
    <div class="modal-head"><h3 id="removeAddressTitle">Remove saved address?</h3><button type="button" class="modal-close" data-modal-close aria-label="Close confirmation">&times;</button></div>
    <div class="modal-body"><p id="removeAddressDescription">Remove this address from your saved list? Delivery details on existing orders will stay unchanged.</p><p class="address-error" id="removeAddressError" role="alert" hidden></p></div>
    <div class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close id="keepAddressBtn">No, keep it</button><button type="button" class="btn btn-danger" id="confirmRemoveAddressBtn">Yes, remove</button></div>
  </div>
</div>

<script src="{{ asset('js/buyer-addresses.js') }}" defer></script>
