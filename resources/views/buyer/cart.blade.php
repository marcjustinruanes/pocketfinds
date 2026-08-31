@extends('buyer.layout')
@section('title', 'My Cart')
@section('page-title', 'My Cart')
@section('page-sub', 'Review your selected items')

@section('content')
@php($subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['qty']))
<div class="cart-layout">
  <div class="stack">
    <div class="card cart-items-card">
      <div class="card-head"><h2>Cart Items</h2></div>
      <div class="card-pad">
        @if(count($items))
          <label class="cart-select-all">
            <input id="cartSelectAll" type="checkbox" checked>
            <span>Select all items ({{ $items->count() }})</span>
          </label>
          <div class="cart-items-scroll">
          <div class="cart-column-head" aria-hidden="true">
            <span></span><span></span><span>Product</span><span style="text-align:right">Subtotal</span><span style="text-align:right">Actions</span>
          </div>
          @foreach($groups as $slug => $shopItems)
          @php($shopShipping = $shippingFees[$slug] ?? 0)
          <section class="cart-shop" data-shipping-fee="{{ $shopShipping }}" data-shop-slug="{{ $slug }}">
            <div class="cart-shop-head">
              <label class="cart-check-wrap">
                <input class="cart-shop-select" type="checkbox" checked>
                <a href="{{ route('buyer.shop', $shopItems->first()['seller_slug']) }}">{{ $shopItems->first()['seller'] }}</a>
              </label>
              <span>{{ $shopItems->count() }} item{{ $shopItems->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="cart-items">
            @foreach($shopItems as $item)
              <div class="cart-item">
                <input class="cart-select" type="checkbox" checked data-price="{{ $item['price'] }}" data-qty="{{ $item['qty'] }}" data-shop="{{ $item['seller_slug'] }}">
                <div class="cart-item-image">
                  @if(filled($item['img']))
                    <img src="{{ $item['img'] }}" alt="{{ $item['name'] }}">
                  @else
                    @include('buyer.partials.icon', ['name' => 'package', 'size' => 26])
                  @endif
                </div>
                <div style="min-width:0">
                  <a class="cart-item-name" href="{{ route('buyer.product', $item['product_id']) }}">{{ $item['name'] }}</a>
                  @if($item['variation_value'])
                    <div class="cart-item-variant">{{ $item['variation_value'] }}</div>
                  @endif
                  <div class="cart-item-quantity">&#8369;{{ number_format($item['price'], 2) }} each</div>
                </div>
                <div class="cart-item-total">₱{{ number_format($item['price'] * $item['qty'], 2) }}</div>
                <div class="cart-item-actions">
                  <button type="button" class="cart-action-btn" aria-label="Edit item options" title="Edit item options"
                    data-edit-key="{{ $item['key'] }}" data-edit-qty="{{ $item['qty'] }}" data-edit-value="{{ $item['variation_value'] }}"
                    data-edit-group="{{ $item['variation_group'] }}" data-edit-variations="{{ json_encode($item['product_variations']) }}"
                    data-edit-name="{{ $item['name'] }}" data-edit-image="{{ $item['img'] }}" data-edit-price="{{ number_format($item['price'], 2) }}">
                    @include('buyer.partials.icon', ['name' => 'edit', 'size' => 14])
                  </button>
                  <form method="POST" action="{{ route('buyer.cart.remove', ['key' => $item['key']]) }}" class="cart-remove-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cart-action-btn danger" data-remove-name="{{ $item['name'] }}" aria-label="Remove item" title="Remove item">@include('buyer.partials.icon', ['name' => 'trash', 'size' => 14])</button>
                  </form>
                </div>
              </div>
            @endforeach
            </div>
          </section>
          @endforeach
          </div>
        @else
        <div class="empty">
          <div class="ic">@include('buyer.partials.icon', ['name' => 'cart', 'size' => 28])</div>
          <h3>Your cart is empty</h3>
          <p>Browse products and add items to your cart.</p>
          <a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Browse Products</a>
        </div>
        @endif
      </div>
    </div>
  </div>

  <div class="stack cart-summary">
    <div class="card cart-summary-card">
      <div class="card-head">
        <h2>Order Summary</h2>
        <button type="button" class="icon-btn" id="addNoteBtn" data-modal-open="orderNoteModal" title="Add a note for the seller" style="width:30px;height:30px">
          @include('buyer.partials.icon', ['name' => 'edit', 'size' => 14])
        </button>
      </div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        @error('voucher_code')
          <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:9px 12px;border-radius:9px;font-size:12.5px">{{ $message }}</div>
        @enderror
        <div class="cart-summary-row"><span>Subtotal</span><span class="mono" id="cartSubtotal">₱{{ number_format($subtotal, 2) }}</span></div>
        <div class="cart-summary-row"><span>Shipping</span><span class="mono" id="cartShipping">Free</span></div>
        <div class="cart-summary-row" id="cartDiscountRow" style="display:none;color:var(--success)"><span id="cartDiscountLabel">Discount</span><span class="mono" id="cartDiscount">- ₱0.00</span></div>
        <hr style="border:0;border-top:1px solid var(--border)">
        <div class="cart-summary-total">
          <span id="cartSelectedCount">{{ $items->count() }} item{{ $items->count() === 1 ? '' : 's' }}</span>
          <span class="mono" id="cartTotal">₱{{ number_format($subtotal, 2) }}</span>
        </div>

        <div class="form-row" style="margin:0">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <label style="margin:0">Vouchers</label>
            <button type="button" class="cart-view-vouchers" id="viewAllVouchersBtn" data-modal-open="allVouchersModal" {{ $otherVouchers->isNotEmpty() ? '' : 'hidden' }}>
              View all
            </button>
          </div>
          <p id="noVoucherMessage" style="font-size:12px;color:var(--muted);margin:4px 0 0" {{ $usableVouchers->isEmpty() ? '' : 'hidden' }}>
            No vouchers available for your selected items.
          </p>
          <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px" id="usableVoucherList">
            @foreach($usableVouchers as $v)
            <button type="button" class="cart-offer" data-voucher-code="{{ $v['voucher']->code }}" data-shop="{{ $v['shop'] }}">
              <span>
                <b>{{ $v['voucher']->code }}</b>
                <small>{{ $v['voucher']->isFreeShipping() ? 'Free Shipping' : '₱' . number_format($v['voucher']->discount_amount, 2) . ' off' }} · {{ $v['shop_name'] }}</small>
              </span>
              <em>Apply</em>
            </button>
            @endforeach
          </div>
        </div>

        @php($typeMeta = ['ewallet' => ['wallet', 'E-Wallet'], 'bank' => ['bank', 'Bank'], 'cod' => ['cash', 'Cash on Delivery'], 'other' => ['tag', 'Other']])
        @php($firstAvailableType = collect($typeMeta)->keys()->first(fn ($type) => $paymentMethods->has($type)))
        <div class="form-row" style="margin:0">
          <label>Payment Method</label>
          @if($paymentMethods->isEmpty())
            <p style="font-size:12.5px;color:var(--muted);margin:4px 0 0">No payment methods available.</p>
          @else
            <div style="display:flex;gap:8px">
              <div class="payment-type-select" style="position:relative;width:44px;flex:none">
                <select id="paymentTypeSelect" aria-label="Payment type" style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;border:0">
                  @foreach($typeMeta as $type => [$icon, $label])
                    @if($paymentMethods->has($type))
                      <option value="{{ $type }}">{{ $label }}</option>
                    @endif
                  @endforeach
                </select>
                <div style="width:44px;height:38px;border:1px solid var(--border);border-radius:9px;display:grid;place-items:center;pointer-events:none;background:#fff;color:var(--pink-dark)">
                  @foreach($typeMeta as $type => [$icon, $label])
                    @if($paymentMethods->has($type))
                      <span class="payment-type-icon" data-type="{{ $type }}" {{ $type !== $firstAvailableType ? 'hidden' : '' }}>
                        @include('buyer.partials.icon', ['name' => $icon, 'size' => 18])
                      </span>
                    @endif
                  @endforeach
                </div>
              </div>
              <select class="select" id="paymentNameSelect" style="flex:1"></select>
            </div>
            <div id="paymentVerifyStatus" style="font-size:11.5px;margin-top:6px"></div>
          @endif
        </div>

        <form method="POST" action="{{ route('buyer.checkout') }}" id="checkoutForm">
          @csrf
          <input type="hidden" name="payment_method" id="checkoutPayment">
          <input type="hidden" name="voucher_code" id="checkoutVoucher">
          <input type="hidden" name="buyer_note" id="checkoutNote">
          <button class="btn btn-primary btn-block" id="checkoutButton" type="submit" style="margin-top:4px" {{ $items->isEmpty() ? 'disabled' : '' }}>Proceed to Checkout</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="cart-confirm-modal" id="orderConfirmModal" aria-hidden="true">
  <div class="cart-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="orderConfirmTitle">
    <h3 id="orderConfirmTitle">Place this order?</h3>
    <p id="orderConfirmMessage">Are you sure you want to place this order?</p>
    <div class="cart-confirm-actions">
      <button type="button" class="btn btn-outline" id="orderCancelButton">Cancel</button>
      <button type="button" class="btn btn-primary" id="orderConfirmButton">Place Order</button>
    </div>
  </div>
</div>
<div class="cart-confirm-modal" id="removeItemModal" aria-hidden="true">
  <div class="cart-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="removeItemTitle">
    <h3 id="removeItemTitle">Remove this item?</h3>
    <p id="removeItemMessage">Are you sure you want to remove this item from your cart?</p>
    <div class="cart-confirm-actions">
      <button type="button" class="btn btn-outline" id="removeItemCancelButton">Cancel</button>
      <button type="button" class="btn btn-danger" id="removeItemConfirmButton">Remove</button>
    </div>
  </div>
</div>
<div class="cart-voucher-modal" id="itemEditModal" aria-hidden="true">
  <div class="cart-voucher-dialog" role="dialog" aria-modal="true" aria-labelledby="itemEditTitle">
    <div class="cart-voucher-modal-head">
      <div><h3 id="itemEditTitle">Edit item</h3><p>Update the options and quantity for this cart item.</p></div>
      <button type="button" class="modal-close" data-item-edit-close aria-label="Close item editor">&times;</button>
    </div>
    <form method="POST" id="itemEditForm">
      @csrf
      @method('PATCH')
      <div class="cart-voucher-modal-body">
        <div class="cart-edit-context">
          <div class="cart-edit-context-img" id="editItemImgWrap">
            <img id="editItemImg" alt="">
          </div>
          <div>
            <div class="cart-edit-context-name" id="editItemName"></div>
            <div class="cart-edit-context-price" id="editItemPrice"></div>
          </div>
        </div>
        <input type="hidden" name="variation_value" id="editValue">
        <input type="hidden" name="variation_group" id="editVariationGroup">
        <div id="editVariationsContainer"></div>
        <div class="form-row"><label for="editQty">Quantity</label><input id="editQty" name="qty" type="number" min="1" max="99" required></div>
        <button type="submit" class="btn btn-primary btn-block">Save item</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="orderNoteModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-head">
      <div><h3>Add a Note</h3><p>Let the seller know something about your order (optional).</p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <textarea id="orderNoteTextarea" rows="4" maxlength="500" placeholder="e.g. Please gift wrap, deliver after 6PM…" style="width:100%;border:1px solid var(--border);border-radius:9px;padding:10px 12px;font-size:13px;resize:vertical"></textarea>
      <div style="text-align:right;font-size:11px;color:var(--muted);margin-top:4px" id="orderNoteCount">0/500</div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" id="saveNoteBtn" data-modal-close>Save Note</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="allVouchersModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-head">
      <div><h3>Shop Vouchers</h3><p>Vouchers from the shops in your cart.</p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:10px">
      @foreach($usableVouchers as $v)
      <div class="voucher-modal-item is-available" data-shop="{{ $v['shop'] }}">
        <div class="voucher-modal-value">{{ $v['voucher']->code }}</div>
        <div class="voucher-modal-copy">
          <strong>{{ $v['voucher']->isFreeShipping() ? 'Free Shipping' : '₱' . number_format($v['voucher']->discount_amount, 2) . ' off' }}</strong>
          <span>{{ $v['shop_name'] }} · Min. spend ₱{{ number_format($v['voucher']->minimum_spend, 2) }}</span>
          <span class="voucher-available">Available for your cart</span>
        </div>
        <button type="button" class="voucher-modal-use" data-voucher-code="{{ $v['voucher']->code }}" data-modal-close>Apply</button>
      </div>
      @endforeach
      @foreach($otherVouchers as $v)
      <div class="voucher-modal-item" data-shop="{{ $v['shop'] }}">
        <div class="voucher-modal-value">{{ $v['voucher']->code }}</div>
        <div class="voucher-modal-copy">
          <strong>{{ $v['voucher']->isFreeShipping() ? 'Free Shipping' : '₱' . number_format($v['voucher']->discount_amount, 2) . ' off' }}</strong>
          <span>{{ $v['shop_name'] }} · Min. spend ₱{{ number_format($v['voucher']->minimum_spend, 2) }}</span>
          <span class="voucher-nearest">{{ $v['reason'] }}</span>
        </div>
      </div>
      @endforeach
      <p id="allVouchersEmptyMessage" style="font-size:13px;color:var(--muted);margin:0" hidden>No vouchers for your currently selected items.</p>
      @if($usableVouchers->isEmpty() && $otherVouchers->isEmpty())
      <p style="font-size:13px;color:var(--muted);margin:0">No vouchers from these shops yet.</p>
      @endif
    </div>
  </div>
</div>

<div class="modal-overlay" id="cartVerifyModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-head">
      <div><h3 id="cartVerifyTitle">Verify Account</h3><p>We'll send a verification code to your registered email.</p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
      <input type="hidden" id="cartVerifyType">
      <input type="hidden" id="cartVerifyBankName">
      <div class="form-row">
        <label for="cartVerifyAccountName">Account holder name</label>
        <input class="auth-input" id="cartVerifyAccountName" type="text">
      </div>
      <div class="form-row">
        <label for="cartVerifyAccountNumber" id="cartVerifyAccountNumberLabel">Account number</label>
        <input class="auth-input" id="cartVerifyAccountNumber" type="text">
      </div>
      <div id="cartVerifyStep1">
        <button type="button" class="btn btn-primary btn-block" id="cartVerifySendBtn">Send verification code</button>
      </div>
      <div class="form-row" id="cartVerifyStep2" hidden>
        <label for="cartVerifyOtp">Verification code</label>
        <input class="auth-input" id="cartVerifyOtp" type="text" inputmode="numeric" maxlength="6" placeholder="6-digit code">
        <button type="button" class="btn btn-primary btn-block" style="margin-top:8px" id="cartVerifyConfirmBtn">Verify and Save</button>
      </div>
      <p id="cartVerifyMessage" style="font-size:12.5px;margin:0" hidden></p>
    </div>
  </div>
</div>
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const itemChecks = [...document.querySelectorAll('.cart-select')];
  const shopChecks = [...document.querySelectorAll('.cart-shop-select')];
  const selectAll = document.getElementById('cartSelectAll');
  const checkoutButton = document.getElementById('checkoutButton');
  const checkoutForm = document.getElementById('checkoutForm');
  const itemEditModal = document.getElementById('itemEditModal');
  const itemEditForm = document.getElementById('itemEditForm');
  const orderConfirmModal = document.getElementById('orderConfirmModal');
  const orderCancelButton = document.getElementById('orderCancelButton');
  const orderConfirmButton = document.getElementById('orderConfirmButton');
  const paymentTypeSelect = document.getElementById('paymentTypeSelect');
  const paymentNameSelect = document.getElementById('paymentNameSelect');
  const paymentMethodsByType = @json($paymentMethods->map(fn ($list) => $list->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values()));
  const verifiedLookup = @json($verifiedLookup);
  let submittingOrder = false;
  let orderNoteValue = '';
  if (!selectAll || !checkoutButton) return;
  const money = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function refreshSummary() {
    itemChecks.forEach(input => {
      const row = input.closest('.cart-item');
      row.querySelector('.cart-item-total').textContent = '₱' + money.format(Number(input.dataset.price) * Number(input.dataset.qty));
    });
    const selected = itemChecks.filter(input => input.checked);
    const subtotal = selected.reduce((sum, input) => sum + Number(input.dataset.price) * Number(input.dataset.qty), 0);

    // Shipping is charged once per shop that still has a selected item —
    // not per item — using each shop's own real shipping fee.
    let shipping = 0;
    const selectedShops = new Set();
    document.querySelectorAll('.cart-shop').forEach(shop => {
      const hasSelected = [...shop.querySelectorAll('.cart-select')].some(input => input.checked);
      if (hasSelected) {
        shipping += Number(shop.dataset.shippingFee || 0);
        selectedShops.add(shop.dataset.shopSlug);
      }
    });

    // A shop's voucher is only worth showing while at least one item from
    // that shop is actually selected — otherwise it looks applicable when
    // it isn't. (.cart-offer has its own `display:flex`, which beats the
    // native [hidden] attribute's display:none — toggle display directly.)
    let anyVoucherVisible = false;
    document.querySelectorAll('.cart-offer[data-shop]').forEach(chip => {
      const visible = selectedShops.has(chip.dataset.shop);
      chip.style.display = visible ? '' : 'none';
      if (visible) anyVoucherVisible = true;
    });
    const noVoucherMessage = document.getElementById('noVoucherMessage');
    if (noVoucherMessage) noVoucherMessage.hidden = anyVoucherVisible;

    // Same reactive filtering inside the "View all" modal — it must never
    // show a shop's voucher when nothing from that shop is selected.
    let anyModalVoucherVisible = false;
    let anyLockedForSelectedShop = false;
    document.querySelectorAll('#allVouchersModal .voucher-modal-item[data-shop]').forEach(row => {
      const visible = selectedShops.has(row.dataset.shop);
      row.style.display = visible ? '' : 'none';
      if (visible) {
        anyModalVoucherVisible = true;
        if (!row.classList.contains('is-available')) anyLockedForSelectedShop = true;
      }
    });
    const allVouchersEmptyMessage = document.getElementById('allVouchersEmptyMessage');
    if (allVouchersEmptyMessage) allVouchersEmptyMessage.hidden = anyModalVoucherVisible;

    // "View all" is only worth showing when it has something the inline
    // chip list doesn't already show — i.e. at least one locked/not-yet-
    // applicable voucher for a shop that's actually selected. No selection,
    // a selected shop with no vouchers, or a selected shop whose vouchers
    // are all already shown inline should all hide it.
    const viewAllBtn = document.getElementById('viewAllVouchersBtn');
    if (viewAllBtn) {
      viewAllBtn.hidden = !anyLockedForSelectedShop;
    }

    document.getElementById('cartSubtotal').textContent = '₱' + money.format(subtotal);
    document.getElementById('cartShipping').textContent = selected.length === 0 ? '—' : (shipping > 0 ? '₱' + money.format(shipping) : 'Free');
    document.getElementById('cartSelectedCount').textContent = `${selected.length} item${selected.length === 1 ? '' : 's'}`;
    checkoutButton.disabled = selected.length === 0;

    const discount = appliedVoucherFreeShipping ? shipping : appliedVoucherDiscount;
    document.getElementById('cartDiscountRow').style.display = discount > 0 ? '' : 'none';
    document.getElementById('cartDiscountLabel').textContent = appliedVoucherFreeShipping ? 'Free Shipping' : 'Discount';
    document.getElementById('cartDiscount').textContent = '- ₱' + money.format(discount);
    document.getElementById('cartTotal').textContent = '₱' + money.format(Math.max(0, subtotal + shipping - discount));

    shopChecks.forEach(check => {
      const shopItems = [...check.closest('.cart-shop').querySelectorAll('.cart-select')];
      const selectedCount = shopItems.filter(item => item.checked).length;
      check.checked = selectedCount === shopItems.length;
      check.indeterminate = selectedCount > 0 && selectedCount < shopItems.length;
    });
    selectAll.checked = selected.length === itemChecks.length;
    selectAll.indeterminate = selected.length > 0 && selected.length < itemChecks.length;
  }
  window.cartRefreshSummary = refreshSummary;

  let appliedVoucherDiscount = 0;
  let appliedVoucherFreeShipping = false;
  let appliedVoucherCode = null;

  // Vouchers are only ever applied by clicking "Apply" on a discoverable
  // chip (inline or in the "View all" modal) — never typed in, so there's
  // nothing here to keep in sync with a text field.
  function runVoucherPreview(code) {
    if (!code) {
      appliedVoucherDiscount = 0;
      appliedVoucherFreeShipping = false;
      appliedVoucherCode = null;
      document.querySelectorAll('.cart-offer.selected').forEach(chip => chip.classList.remove('selected'));
      refreshSummary();
      return;
    }
    const selectedKeys = itemChecks.filter(i => i.checked).map(i => i.closest('.cart-item').querySelector('[data-edit-key]')?.dataset.editKey).filter(Boolean);
    fetch('{{ route('buyer.cart.preview-voucher') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      body: JSON.stringify({ code, items: selectedKeys }),
    })
    .then(r => r.json())
    .then(data => {
      appliedVoucherFreeShipping = !!(data.applies && data.free_shipping);
      appliedVoucherDiscount = (data.applies && !data.free_shipping) ? data.discount : 0;
      appliedVoucherCode = data.applies ? code.toUpperCase() : null;
      document.querySelectorAll('.cart-offer').forEach(chip => chip.classList.toggle('selected', data.applies && chip.dataset.voucherCode === code.toUpperCase()));
      refreshSummary();
    })
    .catch(() => {});
  }

  function applyVoucherCode(code) {
    runVoucherPreview(code);
  }
  document.querySelectorAll('.cart-offer, .voucher-modal-use').forEach(el => {
    el.addEventListener('click', () => applyVoucherCode(el.dataset.voucherCode));
  });

  // Re-checks whatever voucher is currently applied against the
  // newly-selected set of items (e.g. its shop just got deselected).
  function revalidateAppliedVoucher() {
    if (appliedVoucherCode) runVoucherPreview(appliedVoucherCode);
  }

  itemChecks.forEach(input => input.addEventListener('change', () => { refreshSummary(); revalidateAppliedVoucher(); }));
  shopChecks.forEach(input => input.addEventListener('change', () => {
    input.closest('.cart-shop').querySelectorAll('.cart-select').forEach(check => check.checked = input.checked);
    refreshSummary();
    revalidateAppliedVoucher();
  }));
  selectAll.addEventListener('change', () => {
    itemChecks.forEach(check => check.checked = selectAll.checked);
    refreshSummary();
    revalidateAppliedVoucher();
  });
  // Renders the exact same options UI as the product page's Options tab
  // (.pd-opt-group / .pd-opt-row / .pd-opt-btn) — every variation group,
  // every option, with stock shown and out-of-stock ones disabled — so
  // editing a cart item can switch to ANY option the product offers, not
  // just the one dropdown this item happened to be added with.
  function renderEditVariations(variations, currentGroup, currentValue) {
    const container = document.getElementById('editVariationsContainer');
    container.innerHTML = '';
    if (!variations || !variations.length) return;

    let matched = false;
    variations.forEach(variation => {
      const group = document.createElement('div');
      group.className = 'pd-opt-group';
      const label = document.createElement('div');
      label.className = 'pd-opt-label';
      label.textContent = variation.name;
      const row = document.createElement('div');
      row.className = 'pd-opt-row';

      (variation.options || []).forEach(opt => {
        const inStock = (opt.stock ?? 0) > 0;
        const isActive = !matched && variation.name === currentGroup && opt.value === currentValue;
        if (isActive) matched = true;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pd-opt-btn' + (isActive ? ' active' : '');
        btn.dataset.group = variation.name;
        btn.dataset.value = opt.value;
        if (!inStock) { btn.disabled = true; btn.style.opacity = '.4'; btn.style.cursor = 'not-allowed'; }
        btn.innerHTML = opt.value + (inStock
          ? `<span style="font-size:10px;color:var(--muted);display:block">${opt.stock} left</span>`
          : `<span style="font-size:10px;color:var(--danger);display:block">Out of stock</span>`);
        btn.addEventListener('click', () => {
          container.querySelectorAll('.pd-opt-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          document.getElementById('editValue').value = opt.value;
          document.getElementById('editVariationGroup').value = variation.name;
        });
        row.appendChild(btn);
      });

      group.appendChild(label);
      group.appendChild(row);
      container.appendChild(group);
    });

    // If nothing matched the item's stored option (e.g. it went out of
    // stock), fall back to leaving the first available one selected —
    // same as how the product page picks a default.
    if (!matched) {
      const firstEnabled = container.querySelector('.pd-opt-btn:not(:disabled)');
      if (firstEnabled) {
        firstEnabled.classList.add('active');
        document.getElementById('editValue').value = firstEnabled.dataset.value;
        document.getElementById('editVariationGroup').value = firstEnabled.dataset.group;
      }
    } else {
      document.getElementById('editValue').value = currentValue;
      document.getElementById('editVariationGroup').value = currentGroup;
    }
  }

  document.querySelectorAll('[data-edit-key]').forEach(button => button.addEventListener('click', () => {
    const variations = JSON.parse(button.dataset.editVariations || '[]');
    renderEditVariations(variations, button.dataset.editGroup, button.dataset.editValue);
    document.getElementById('editQty').value = button.dataset.editQty;

    // Product context, so the modal is never just a bare set of dropdowns.
    const imgWrap = document.getElementById('editItemImgWrap');
    const img = document.getElementById('editItemImg');
    if (button.dataset.editImage) {
      img.src = button.dataset.editImage;
      imgWrap.hidden = false;
    } else {
      imgWrap.hidden = true;
    }
    document.getElementById('editItemName').textContent = button.dataset.editName || '';
    document.getElementById('editItemPrice').textContent = '₱' + (button.dataset.editPrice || '0.00') + ' each';

    itemEditForm.action = '{{ url('/buyer/cart') }}/' + encodeURIComponent(button.dataset.editKey) + '/edit';
    itemEditModal.classList.add('open');
    itemEditModal.setAttribute('aria-hidden', 'false');
  }));
  document.querySelector('[data-item-edit-close]').addEventListener('click', () => {
    itemEditModal.classList.remove('open');
    itemEditModal.setAttribute('aria-hidden', 'true');
  });

  // ---- Confirm before removing a cart item ----
  const removeItemModal = document.getElementById('removeItemModal');
  const removeItemCancelButton = document.getElementById('removeItemCancelButton');
  const removeItemConfirmButton = document.getElementById('removeItemConfirmButton');
  let pendingRemoveForm = null;

  function closeRemoveItemModal() {
    removeItemModal.classList.remove('open');
    removeItemModal.setAttribute('aria-hidden', 'true');
    pendingRemoveForm = null;
  }

  document.querySelectorAll('.cart-remove-form').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault();
    pendingRemoveForm = form;
    const name = form.querySelector('[data-remove-name]')?.dataset.removeName;
    document.getElementById('removeItemMessage').textContent = name
      ? `Remove "${name}" from your cart?`
      : 'Are you sure you want to remove this item from your cart?';
    removeItemModal.classList.add('open');
    removeItemModal.setAttribute('aria-hidden', 'false');
  }));
  removeItemCancelButton.addEventListener('click', closeRemoveItemModal);
  removeItemModal.addEventListener('click', event => {
    if (event.target === removeItemModal) closeRemoveItemModal();
  });
  removeItemConfirmButton.addEventListener('click', () => {
    if (pendingRemoveForm) pendingRemoveForm.submit();
  });

  checkoutForm.addEventListener('submit', event => {
    const selected = itemChecks.filter(input => input.checked);
    if (submittingOrder) return;
    event.preventDefault();
    if (!selected.length) {
      return;
    }
    document.getElementById('orderConfirmMessage').textContent = `Are you sure you want to place this order for ${selected.length} item${selected.length === 1 ? '' : 's'}?`;
    orderConfirmModal.classList.add('open');
    orderConfirmModal.setAttribute('aria-hidden', 'false');
  });

  function closeOrderConfirm() {
    orderConfirmModal.classList.remove('open');
    orderConfirmModal.setAttribute('aria-hidden', 'true');
  }

  orderCancelButton.addEventListener('click', closeOrderConfirm);
  orderConfirmModal.addEventListener('click', event => {
    if (event.target === orderConfirmModal) closeOrderConfirm();
  });
  orderConfirmButton.addEventListener('click', () => {
    const selected = itemChecks.filter(input => input.checked);
    if (!selected.length) {
      closeOrderConfirm();
      return;
    }
    selected.forEach(input => {
      const key = input.closest('.cart-item').querySelector('[data-edit-key]')?.dataset.editKey;
      if (!key) return;
      const hidden = document.createElement('input');
      hidden.type = 'hidden'; hidden.name = 'items[]'; hidden.value = key;
      checkoutForm.appendChild(hidden);
    });
    document.getElementById('checkoutPayment').value = paymentNameSelect?.value || '';
    document.getElementById('checkoutVoucher').value = appliedVoucherCode || '';
    document.getElementById('checkoutNote').value = orderNoteValue;
    submittingOrder = true;
    closeOrderConfirm();
    checkoutForm.submit();
  });

  // ---- Add Note modal ----
  const orderNoteTextarea = document.getElementById('orderNoteTextarea');
  const orderNoteCount = document.getElementById('orderNoteCount');
  const addNoteBtn = document.getElementById('addNoteBtn');

  document.querySelector('[data-modal-open="orderNoteModal"]')?.addEventListener('click', () => {
    orderNoteTextarea.value = orderNoteValue;
    orderNoteCount.textContent = orderNoteTextarea.value.length + '/500';
  });
  orderNoteTextarea?.addEventListener('input', () => {
    orderNoteCount.textContent = orderNoteTextarea.value.length + '/500';
  });
  document.getElementById('saveNoteBtn')?.addEventListener('click', () => {
    orderNoteValue = orderNoteTextarea.value.trim();
    addNoteBtn.style.color = orderNoteValue ? 'var(--pink-dark)' : '';
    addNoteBtn.style.borderColor = orderNoteValue ? 'var(--pink)' : '';
    addNoteBtn.title = orderNoteValue ? 'Edit your note' : 'Add a note for the seller';
  });

  // ---- Payment method: type + name dropdowns ----
  function renderPaymentTypeIcon(type) {
    document.querySelectorAll('.payment-type-icon').forEach(span => span.hidden = span.dataset.type !== type);
  }
  function populatePaymentNames(type) {
    const list = paymentMethodsByType[type] || [];
    paymentNameSelect.innerHTML = '';
    list.forEach(pm => paymentNameSelect.add(new Option(pm.name, pm.id)));
  }
  function verificationKeyFor(type, name) {
    if (type === 'ewallet') return name.toLowerCase();
    if (type === 'bank') return 'bank:' + name.toLowerCase();
    return null;
  }
  function updateVerificationStatus() {
    const type = paymentTypeSelect?.value;
    const statusEl = document.getElementById('paymentVerifyStatus');
    if (!statusEl) return;
    if (!type || type === 'cod' || type === 'other') { statusEl.innerHTML = ''; return; }
    const name = paymentNameSelect.options[paymentNameSelect.selectedIndex]?.text || '';
    const key = verificationKeyFor(type, name);
    if (verifiedLookup[key]) {
      statusEl.innerHTML = '<span style="color:var(--success);font-weight:600">✓ ' + name + ' verified</span>';
    } else {
      statusEl.innerHTML = '<span style="color:var(--muted)">' + name + ' isn\'t verified yet.</span> <button type="button" id="cartVerifyNowBtn" style="border:0;background:none;padding:0;color:var(--pink-dark);font-weight:700;cursor:pointer;text-decoration:underline">Verify now</button>';
      document.getElementById('cartVerifyNowBtn')?.addEventListener('click', () => openCartVerifyModal(type, name));
    }
  }
  if (paymentTypeSelect && paymentNameSelect) {
    populatePaymentNames(paymentTypeSelect.value);
    updateVerificationStatus();
    paymentTypeSelect.addEventListener('change', () => {
      renderPaymentTypeIcon(paymentTypeSelect.value);
      populatePaymentNames(paymentTypeSelect.value);
      updateVerificationStatus();
    });
    paymentNameSelect.addEventListener('change', updateVerificationStatus);
  }

  // ---- Verify e-wallet/bank account right from the cart ----
  function openCartVerifyModal(type, name) {
    const accountType = type === 'bank' ? 'bank' : name.toLowerCase();
    document.getElementById('cartVerifyType').value = accountType;
    document.getElementById('cartVerifyBankName').value = type === 'bank' ? name : '';
    document.getElementById('cartVerifyTitle').textContent = 'Verify ' + name;
    document.getElementById('cartVerifyAccountNumberLabel').textContent = type === 'bank' ? 'Account number' : name + ' number';
    document.getElementById('cartVerifyAccountName').value = '';
    document.getElementById('cartVerifyAccountNumber').value = '';
    document.getElementById('cartVerifyOtp').value = '';
    document.getElementById('cartVerifyStep1').hidden = false;
    document.getElementById('cartVerifyStep2').hidden = true;
    const msg = document.getElementById('cartVerifyMessage');
    msg.hidden = true;
    document.getElementById('cartVerifyModal').classList.add('open');
  }

  document.getElementById('cartVerifySendBtn')?.addEventListener('click', () => {
    const type = document.getElementById('cartVerifyType').value;
    const bankName = document.getElementById('cartVerifyBankName').value;
    const accountName = document.getElementById('cartVerifyAccountName').value.trim();
    const accountNumber = document.getElementById('cartVerifyAccountNumber').value.trim();
    const msg = document.getElementById('cartVerifyMessage');
    if (!accountName || !accountNumber) {
      msg.hidden = false; msg.style.color = 'var(--danger)'; msg.textContent = 'Please fill in all fields.';
      return;
    }
    const btn = document.getElementById('cartVerifySendBtn');
    btn.disabled = true; btn.textContent = 'Sending…';
    fetch('{{ route('buyer.payment-accounts.send-code') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      body: JSON.stringify({ type, account_name: accountName, account_number: accountNumber, bank_name: bankName }),
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false; btn.textContent = 'Send verification code';
      msg.hidden = false;
      msg.style.color = data.success ? 'var(--success)' : 'var(--danger)';
      msg.textContent = data.message;
      if (data.success) {
        document.getElementById('cartVerifyStep1').hidden = true;
        document.getElementById('cartVerifyStep2').hidden = false;
      }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Send verification code'; msg.hidden = false; msg.style.color = 'var(--danger)'; msg.textContent = 'Network error. Try again.'; });
  });

  document.getElementById('cartVerifyConfirmBtn')?.addEventListener('click', () => {
    const otp = document.getElementById('cartVerifyOtp').value.trim();
    const msg = document.getElementById('cartVerifyMessage');
    if (otp.length !== 6) {
      msg.hidden = false; msg.style.color = 'var(--danger)'; msg.textContent = 'Enter the full 6-digit code.';
      return;
    }
    fetch('{{ route('buyer.payment-accounts.verify') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      body: JSON.stringify({ otp }),
    })
    .then(r => r.json())
    .then(data => {
      msg.hidden = false;
      msg.style.color = data.success ? 'var(--success)' : 'var(--danger)';
      msg.textContent = data.success ? 'Verified!' : data.message;
      if (data.success) {
        const key = data.account.type === 'bank' ? 'bank:' + (data.account.bank_name || '').toLowerCase() : data.account.type;
        verifiedLookup[key] = true;
        updateVerificationStatus();
        setTimeout(() => document.getElementById('cartVerifyModal').classList.remove('open'), 800);
      }
    })
    .catch(() => { msg.hidden = false; msg.style.color = 'var(--danger)'; msg.textContent = 'Network error. Try again.'; });
  });

  refreshSummary();
});

</script>
@endpush
