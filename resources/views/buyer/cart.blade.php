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
          @foreach($groups as $shopItems)
          <section class="cart-shop">
            <div class="cart-shop-head">
              <label class="cart-check-wrap">
                <input class="cart-shop-select" type="checkbox" checked>
                <span>{{ $shopItems->first()['seller'] }}</span>
              </label>
              <span>{{ $shopItems->count() }} item{{ $shopItems->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="cart-items">
            @foreach($shopItems as $item)
              <div class="cart-item">
                <input class="cart-select" type="checkbox" checked data-price="{{ $item['price'] }}" data-qty="{{ $item['qty'] }}">
                <div class="cart-item-image">
                  @include('buyer.partials.icon', ['name' => $item['img'], 'size' => 26])
                </div>
                <div style="min-width:0">
                  <div class="cart-item-name">{{ $item['name'] }}</div>
                  @if($item['color'] || $item['size'])
                    <div class="cart-item-variant">{{ collect([$item['color'], $item['size']])->filter()->join(' · ') }}</div>
                  @endif
                  <div class="cart-item-quantity">Quantity: {{ $item['qty'] }}</div>
                </div>
                <div class="cart-item-total">₱{{ number_format($item['price'] * $item['qty'], 2) }}</div>
                <div class="cart-item-actions">
                  <form method="POST" action="{{ route('buyer.cart.update', ['key' => $item['key']]) }}" class="cart-qty-form">
                    @csrf
                    @method('PATCH')
                    <label class="sr-only" for="qty-{{ md5($item['key']) }}">Quantity</label>
                    <input id="qty-{{ md5($item['key']) }}" name="qty" type="number" min="1" max="99" value="{{ $item['qty'] }}" onchange="this.form.submit()">
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

        <button class="btn btn-primary btn-block" id="checkoutButton" style="margin-top:4px" {{ $items->isEmpty() ? 'disabled' : '' }}>Checkout</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const itemChecks = [...document.querySelectorAll('.cart-select')];
  const shopChecks = [...document.querySelectorAll('.cart-shop-select')];
  const money = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function refreshSummary() {
    const selected = itemChecks.filter(input => input.checked);
    const total = selected.reduce((sum, input) => sum + Number(input.dataset.price) * Number(input.dataset.qty), 0);
    document.getElementById('cartSubtotal').textContent = '₱' + money.format(total);
    document.getElementById('cartTotal').textContent = '₱' + money.format(total);
    document.getElementById('cartSelectedCount').textContent = `${selected.length} item${selected.length === 1 ? '' : 's'}`;
    document.getElementById('checkoutButton').disabled = selected.length === 0;
  }

  itemChecks.forEach(input => input.addEventListener('change', () => {
    const shop = input.closest('.cart-shop');
    shop.querySelector('.cart-shop-select').checked = [...shop.querySelectorAll('.cart-select')].every(check => check.checked);
    refreshSummary();
  }));
  shopChecks.forEach(input => input.addEventListener('change', () => {
    input.closest('.cart-shop').querySelectorAll('.cart-select').forEach(check => check.checked = input.checked);
    refreshSummary();
  }));
});
</script>
@endpush
