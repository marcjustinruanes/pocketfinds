@extends('buyer.layout')
@section('title', 'My Cart')
@section('page-title', 'My Cart')
@section('page-sub', 'Review your selected items')

@section('content')
@php($subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['qty']))
<div class="cart-layout">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Cart Items</h2></div>
      <div class="card-pad">
        @if(count($items))
          <label class="cart-select-all">
            <input id="cartSelectAll" type="checkbox" checked>
            <span>Select all items ({{ $items->count() }})</span>
          </label>
          <div class="cart-column-head" aria-hidden="true">
            <span></span><span></span><span>Product / unit price</span><span>Subtotal</span><span>Quantity</span>
          </div>
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
                  <div class="cart-item-quantity">Unit price: &#8369;{{ number_format($item['price'], 2) }}</div>
                </div>
                <div class="cart-item-total">₱{{ number_format($item['price'] * $item['qty'], 2) }}</div>
                <div class="cart-item-actions">
                  <form method="POST" action="{{ route('buyer.cart.update', ['key' => $item['key']]) }}" class="cart-qty-form">
                    @csrf
                    @method('PATCH')
                    <label class="sr-only" for="qty-{{ md5($item['key']) }}">Quantity</label>
                    <button type="button" aria-label="Decrease quantity" onclick="changeCartQuantity(this, -1)">&minus;</button>
                    <input id="qty-{{ md5($item['key']) }}" name="qty" type="number" min="1" max="99" value="{{ $item['qty'] }}" onchange="this.form.submit()">
                    <button type="button" aria-label="Increase quantity" onclick="changeCartQuantity(this, 1)">+</button>
                  </form>
                  <form method="POST" action="{{ route('buyer.cart.remove', ['key' => $item['key']]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cart-remove">Remove</button>
                  </form>
                </div>
              </div>
            @endforeach
            </div>
          </section>
          @endforeach
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
    <div class="card">
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
            <p class="cart-no-voucher">No vouchers apply to the selected cart yet. <button type="button" data-voucher-modal-open>View vouchers</button></p>
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
          <select class="select" style="width:100%">
            <option>Cash on Delivery</option>
            <option>GCash</option>
            <option>Bank Transfer</option>
          </select>
        </div>

        <button class="btn btn-primary btn-block" id="checkoutButton" type="button" style="margin-top:4px" {{ $items->isEmpty() ? 'disabled' : '' }}>Proceed to Checkout</button>
      </div>
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
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const itemChecks = [...document.querySelectorAll('.cart-select')];
  const shopChecks = [...document.querySelectorAll('.cart-shop-select')];
  const selectAll = document.getElementById('cartSelectAll');
  const checkoutButton = document.getElementById('checkoutButton');
  const voucherButtons = [...document.querySelectorAll('[data-voucher-id]')];
  const shippingOptions = [...document.querySelectorAll('input[name="shipping"]')];
  const voucherModal = document.getElementById('voucherModal');
  let appliedVoucher = null;
  if (!selectAll || !checkoutButton) return;
  const money = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function refreshSummary() {
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

  checkoutButton.addEventListener('click', () => {
    const selected = itemChecks.filter(input => input.checked).length;
    if (selected) showToast(`Checkout for ${selected} selected item${selected === 1 ? '' : 's'} will be available next.`, 'buy');
  });

  refreshSummary();
});

function changeCartQuantity(button, change) {
  const form = button.closest('form');
  const input = form.querySelector('input[name="qty"]');
  input.value = Math.min(99, Math.max(1, Number(input.value) + change));
  form.submit();
}
</script>
@endpush
