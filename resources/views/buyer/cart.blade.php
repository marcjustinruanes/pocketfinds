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
          <div class="cart-column-head" aria-hidden="true">
            <span></span><span></span><span>Product</span><span>Subtotal</span><span>Actions</span>
          </div>
          <div class="cart-items-scroll">
          @foreach($groups as $shopItems)
          <section class="cart-shop">
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
                  @if($item['color'] || $item['size'])
                    <div class="cart-item-variant">{{ collect([$item['color'], $item['size']])->filter()->join(' · ') }}</div>
                  @endif
                  <div class="cart-item-quantity">&#8369;{{ number_format($item['price'], 2) }} each</div>
                </div>
                <div class="cart-item-total">₱{{ number_format($item['price'] * $item['qty'], 2) }}</div>
                <div class="cart-item-actions">
                  <button type="button" class="cart-edit" aria-label="Edit item options" title="Edit item options"
                    data-edit-key="{{ $item['key'] }}" data-edit-qty="{{ $item['qty'] }}" data-edit-color="{{ $item['color'] }}" data-edit-size="{{ $item['size'] }}" data-edit-options="{{ json_encode($item['variation_options']) }}">
                    @include('buyer.partials.icon', ['name' => 'edit', 'size' => 14])
                  </button>
                  <form method="POST" action="{{ route('buyer.cart.remove', ['key' => $item['key']]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cart-remove" aria-label="Remove item" title="Remove item">@include('buyer.partials.icon', ['name' => 'trash', 'size' => 14])</button>
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
      <div class="card-head"><h2>Order Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div class="cart-summary-row"><span>Subtotal</span><span class="mono" id="cartSubtotal">₱{{ number_format($subtotal, 2) }}</span></div>
        <div class="cart-summary-row"><span>Shipping</span><span class="mono">₱0.00</span></div>
        <div class="cart-summary-row" style="color:var(--success)"><span>Discount</span><span class="mono">- ₱0.00</span></div>
        <hr style="border:0;border-top:1px solid var(--border)">
        <div class="cart-summary-total"><span id="cartSelectedCount">{{ $items->count() }} item{{ $items->count() === 1 ? '' : 's' }}</span><span class="mono" id="cartTotal">₱{{ number_format($subtotal, 2) }}</span></div>

        <div class="cart-benefits">
          <div class="cart-benefit-head"><span>Vouchers</span><button type="button" class="cart-view-vouchers" data-voucher-modal-open>View all</button></div>
          @forelse($voucherData['applicable'] as $voucher)
            <button type="button" class="cart-offer" data-voucher-id="{{ $voucher['id'] }}" data-voucher-group="{{ $voucher['type'] }}" data-discount="{{ $voucher['discount'] }}" data-minimum="{{ $voucher['minimum'] }}" data-shop="{{ $voucher['shop_slug'] }}">
              <span><b>{{ $voucher['code'] }}</b><small>{{ $voucher['shop'] }}: PHP {{ number_format($voucher['discount']) }} off</small></span><em>Use</em>
            </button>
          @empty
            <p class="cart-no-voucher">No vouchers apply to the selected cart yet.</p>
          @endforelse
          <div class="cart-benefit-head"><span>Shipping options</span></div>
          @foreach($shippingOptions as $option)
            <label class="cart-shipping-option"><input type="radio" name="shipping" value="{{ $option['amount'] }}" {{ $loop->first ? 'checked' : '' }}><span>{{ $option['label'] }} <small>{{ $option['detail'] }}</small></span><b>PHP {{ number_format($option['amount']) }}</b></label>
          @endforeach
        </div>

        <div class="form-row" style="margin:0">
          <label>Voucher Code</label>
          <div style="display:flex;gap:8px">
            <input type="text" placeholder="Enter code…" style="flex:1;border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px">
            <button class="btn btn-outline">Apply</button>
          </div>
        </div>

        <div class="form-row" style="margin:0">
          <label>Payment Method</label>
          <select class="select" style="width:100%" name="payment_method">
            @forelse($paymentMethods as $paymentMethod)
              <option value="{{ $paymentMethod->id }}">{{ $paymentMethod->name }}</option>
            @empty
              <option value="" disabled selected>No payment methods available</option>
            @endforelse
          </select>
        </div>

        <form method="POST" action="{{ route('buyer.checkout') }}" id="checkoutForm">
          @csrf
          <input type="hidden" name="shipping_amount" id="checkoutShipping">
          <input type="hidden" name="payment_method" id="checkoutPayment">
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
<div class="cart-voucher-modal" id="voucherModal" aria-hidden="true">
  <div class="cart-voucher-dialog" role="dialog" aria-modal="true" aria-labelledby="voucherModalTitle">
    <div class="cart-voucher-modal-head">
      <div><h3 id="voucherModalTitle">Available vouchers</h3><p>Offers are checked against your current cart.</p></div>
      <button type="button" class="modal-close" data-voucher-modal-close aria-label="Close vouchers">&times;</button>
    </div>
    <div class="cart-voucher-modal-body">
      @foreach($voucherData['all'] as $voucher)
        <article class="voucher-modal-item {{ $voucher['applicable'] ? 'is-available' : '' }}">
          <div class="voucher-modal-value">PHP {{ number_format($voucher['discount']) }} OFF</div>
          <div class="voucher-modal-copy">
            <strong>{{ $voucher['code'] }}</strong>
            <span>{{ $voucher['shop'] }} &middot; Minimum spend PHP {{ number_format($voucher['minimum']) }}</span>
            @if($voucher['applicable'])
              <span class="voucher-available">Applicable to your selected items</span>
            @else
              <span class="voucher-nearest">Add PHP {{ number_format($voucher['shortfall']) }} more from <a href="{{ route('buyer.shop', $voucher['shop_slug']) }}">{{ $voucher['shop'] }}</a> to unlock this voucher.</span>
            @endif
          </div>
          @if($voucher['applicable'])
            <button type="button" class="voucher-modal-use" data-voucher-id="{{ $voucher['id'] }}" data-voucher-group="{{ $voucher['type'] }}" data-discount="{{ $voucher['discount'] }}">Use</button>
          @else
            <a href="{{ route('buyer.shop', $voucher['shop_slug']) }}" class="voucher-modal-shop">Add items</a>
          @endif
        </article>
      @endforeach
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
        <div class="form-row" id="editColorRow" hidden><label for="editColor">Color</label><select name="color" id="editColor"></select></div>
        <div class="form-row" id="editSizeRow" hidden><label for="editSize">Size</label><select name="size" id="editSize"></select></div>
        <div class="form-row"><label for="editQty">Quantity</label><input id="editQty" name="qty" type="number" min="1" max="99" required></div>
        <button type="submit" class="btn btn-primary btn-block">Save item</button>
      </div>
    </form>
  </div>
</div>
<div class="cart-voucher-modal" id="paymentModal" aria-hidden="true">
  <div class="cart-voucher-dialog" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
    <div class="cart-voucher-modal-head">
      <div><h3 id="paymentModalTitle">Payment details</h3><p>Enter the account details needed for this payment method.</p></div>
      <button type="button" class="modal-close" data-payment-close aria-label="Close payment details">&times;</button>
    </div>
    <div class="cart-voucher-modal-body">
      <div class="form-row payment-detail-field" data-payment-detail="gcash"><label for="gcashNumber">GCash number</label><input id="gcashNumber" type="tel" inputmode="numeric" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX"></div>
      <div class="form-row payment-detail-field" data-payment-detail="gcash"><label for="gcashName">GCash account name</label><input id="gcashName" type="text" placeholder="Account name"></div>
      <div class="form-row payment-detail-field" data-payment-detail="bank"><label for="bankName">Bank name</label><input id="bankName" type="text" placeholder="Bank name"></div>
      <div class="form-row payment-detail-field" data-payment-detail="bank"><label for="bankAccount">Bank account number</label><input id="bankAccount" type="text" inputmode="numeric" placeholder="Account number"></div>
      <div class="form-row payment-detail-field" data-payment-detail="bank"><label for="bankOwner">Account holder name</label><input id="bankOwner" type="text" placeholder="Account holder name"></div>
      <button type="button" class="btn btn-outline payment-detail-field" id="sendPaymentCode" data-payment-detail="verification">Send verification code</button>
      <div class="form-row payment-detail-field verification-code-field" data-payment-detail="verification" hidden><label for="paymentCode">Verification code</label><input id="paymentCode" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code"></div>
      <p id="paymentNoDetails" class="cart-no-voucher">No extra payment details are required.</p>
      <button type="button" class="btn btn-primary btn-block" id="paymentVerify">Verify details and code</button>
      <p id="paymentStatus" class="cart-no-voucher" hidden></p>
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
  const voucherButtons = [...document.querySelectorAll('[data-voucher-id]')];
  const shippingOptions = [...document.querySelectorAll('input[name="shipping"]')];
  const voucherModal = document.getElementById('voucherModal');
  const itemEditModal = document.getElementById('itemEditModal');
  const itemEditForm = document.getElementById('itemEditForm');
  const paymentModal = document.getElementById('paymentModal');
  const orderConfirmModal = document.getElementById('orderConfirmModal');
  const orderCancelButton = document.getElementById('orderCancelButton');
  const orderConfirmButton = document.getElementById('orderConfirmButton');
  const paymentSelect = document.querySelector('[name="payment_method"]');
  let appliedVoucher = null;
  let submittingOrder = false;
  if (!selectAll || !checkoutButton) return;
  const money = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function refreshSummary() {
    itemChecks.forEach(input => {
      const row = input.closest('.cart-item');
      row.querySelector('.cart-item-total').textContent = '₱' + money.format(Number(input.dataset.price) * Number(input.dataset.qty));
    });
    const selected = itemChecks.filter(input => input.checked);
    const total = selected.reduce((sum, input) => sum + Number(input.dataset.price) * Number(input.dataset.qty), 0);
    document.getElementById('cartSubtotal').textContent = '₱' + money.format(total);
    document.getElementById('cartTotal').textContent = '₱' + money.format(total);
    const shopTotals = selected.reduce((totals, input) => {
      totals[input.dataset.shop] = (totals[input.dataset.shop] || 0) + Number(input.dataset.price) * Number(input.dataset.qty);
      return totals;
    }, {});
    document.querySelectorAll('.cart-offer').forEach(offer => {
      const voucherTotal = offer.dataset.voucherGroup === 'shop' ? (shopTotals[offer.dataset.shop] || 0) : total;
      offer.hidden = voucherTotal < Number(offer.dataset.minimum);
    });
    const appliedVoucherTotal = appliedVoucher
      ? (appliedVoucher.dataset.voucherGroup === 'shop' ? (shopTotals[appliedVoucher.dataset.shop] || 0) : total)
      : 0;
    if (appliedVoucher && appliedVoucherTotal < Number(appliedVoucher.dataset.minimum)) {
      document.querySelectorAll(`[data-voucher-id="${appliedVoucher.dataset.voucherId}"]`).forEach(offer => offer.classList.remove('selected'));
      appliedVoucher = null;
    }
    const discount = appliedVoucher ? Number(appliedVoucher.dataset.discount) : 0;
    const shipping = selected.length ? Number(shippingOptions.find(input => input.checked)?.value || 0) : 0;
    const summaryRows = document.querySelectorAll('.cart-summary-row');
    summaryRows[1].querySelector('.mono').textContent = shipping ? 'PHP ' + money.format(shipping) : 'FREE';
    summaryRows[2].querySelector('.mono').textContent = '- PHP ' + money.format(Math.min(discount, total));
    document.getElementById('cartTotal').textContent = 'PHP ' + money.format(Math.max(0, total + shipping - discount));
    document.getElementById('cartSelectedCount').textContent = `${selected.length} item${selected.length === 1 ? '' : 's'}`;
    checkoutButton.disabled = selected.length === 0;

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

  itemChecks.forEach(input => input.addEventListener('change', () => {
    refreshSummary();
  }));
  shopChecks.forEach(input => input.addEventListener('change', () => {
    input.closest('.cart-shop').querySelectorAll('.cart-select').forEach(check => check.checked = input.checked);
    refreshSummary();
  }));
  selectAll.addEventListener('change', () => {
    itemChecks.forEach(check => check.checked = selectAll.checked);
    refreshSummary();
  });
  voucherButtons.forEach(button => button.addEventListener('click', () => {
    const voucherId = button.dataset.voucherId;
    document.querySelectorAll(`[data-voucher-group="${button.dataset.voucherGroup}"]`).forEach(offer => offer.classList.remove('selected'));
    document.querySelectorAll(`[data-voucher-id="${voucherId}"]`).forEach(offer => offer.classList.add('selected'));
    appliedVoucher = button;
    voucherModal.classList.remove('open');
    voucherModal.setAttribute('aria-hidden', 'true');
    refreshSummary();
  }));
  shippingOptions.forEach(input => input.addEventListener('change', refreshSummary));
  document.querySelectorAll('.cart-edit').forEach(button => button.addEventListener('click', () => {
    const options = JSON.parse(button.dataset.editOptions || '{}');
    const setOptions = (field, rowId, values, selected) => {
      const row = document.getElementById(rowId);
      const select = row.querySelector('select');
      select.innerHTML = '<option value="">No ' + field + ' selected</option>';
      values.forEach(value => {
        const option = new Option(value, value, false, value === selected);
        select.add(option);
      });
      row.hidden = values.length === 0;
    };
    setOptions('color', 'editColorRow', options.color || [], button.dataset.editColor);
    setOptions('size', 'editSizeRow', options.size || [], button.dataset.editSize);
    document.getElementById('editQty').value = button.dataset.editQty;
    itemEditForm.action = '{{ url('/buyer/cart') }}/' + encodeURIComponent(button.dataset.editKey) + '/edit';
    itemEditModal.classList.add('open');
    itemEditModal.setAttribute('aria-hidden', 'false');
  }));
  document.querySelector('[data-item-edit-close]').addEventListener('click', () => {
    itemEditModal.classList.remove('open');
    itemEditModal.setAttribute('aria-hidden', 'true');
  });
  paymentSelect?.addEventListener('change', () => {
    const name = paymentSelect.options[paymentSelect.selectedIndex]?.text.toLowerCase() || '';
    if (!name.includes('gcash') && !name.includes('bank')) return;
    document.querySelectorAll('.payment-detail-field[data-payment-detail="gcash"], .payment-detail-field[data-payment-detail="bank"]').forEach(field => {
      field.hidden = !(name.includes('gcash') && field.dataset.paymentDetail === 'gcash') && !(name.includes('bank') && field.dataset.paymentDetail === 'bank');
    });
    document.getElementById('sendPaymentCode').hidden = false;
    document.querySelector('.verification-code-field').hidden = true;
    document.getElementById('paymentCode').value = '';
    document.getElementById('paymentNoDetails').hidden = name.includes('gcash') || name.includes('bank');
    document.getElementById('paymentStatus').hidden = true;
    paymentModal.classList.add('open');
    paymentModal.setAttribute('aria-hidden', 'false');
  });
  document.querySelector('[data-payment-close]').addEventListener('click', () => {
    paymentModal.classList.remove('open');
    paymentModal.setAttribute('aria-hidden', 'true');
  });
  document.getElementById('paymentVerify').addEventListener('click', () => {
    const visibleFields = [...paymentModal.querySelectorAll('.payment-detail-field:not([hidden]) input')];
    const valid = visibleFields.every(input => input.value.trim() && input.checkValidity()) && paymentCode === document.getElementById('paymentCode').value.trim();
    const status = document.getElementById('paymentStatus');
    status.textContent = valid ? 'Details verified for this checkout.' : 'Please complete the required details.';
    status.hidden = false;
    status.style.color = valid ? 'var(--success)' : 'var(--danger)';
  });
  let paymentCode = '';
  document.getElementById('sendPaymentCode').addEventListener('click', () => {
    paymentCode = String(Math.floor(100000 + Math.random() * 900000));
    document.querySelector('.verification-code-field').hidden = false;
    document.getElementById('sendPaymentCode').textContent = 'Code sent';
  });
  document.querySelectorAll('[data-voucher-modal-open]').forEach(button => button.addEventListener('click', () => {
    voucherModal.classList.add('open');
    voucherModal.setAttribute('aria-hidden', 'false');
  }));
  document.querySelectorAll('[data-voucher-modal-close]').forEach(button => button.addEventListener('click', () => {
    voucherModal.classList.remove('open');
    voucherModal.setAttribute('aria-hidden', 'true');
  }));
  voucherModal.addEventListener('click', event => {
    if (event.target === voucherModal) event.currentTarget.querySelector('[data-voucher-modal-close]').click();
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
      const key = input.closest('.cart-item').querySelector('.cart-edit')?.dataset.editKey;
      if (!key) return;
      const hidden = document.createElement('input');
      hidden.type = 'hidden'; hidden.name = 'items[]'; hidden.value = key;
      checkoutForm.appendChild(hidden);
    });
    document.getElementById('checkoutShipping').value = shippingOptions.find(input => input.checked)?.value || 0;
    document.getElementById('checkoutPayment').value = paymentSelect?.value || '';
    submittingOrder = true;
    closeOrderConfirm();
    checkoutForm.submit();
  });

  refreshSummary();
});

</script>
@endpush
