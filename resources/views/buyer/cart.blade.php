@extends('buyer.layout')
@section('title', 'My Cart')
@section('page-title', 'My Cart')
@section('page-sub', 'Review your selected items')

@section('content')
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Cart Items</h2></div>
      <div class="card-pad">
        <div class="empty">
          <div class="ic">@include('buyer.partials.icon', ['name' => 'cart', 'size' => 28])</div>
          <h3>Your cart is empty</h3>
          <p>Browse products and add items to your cart.</p>
          <a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Browse Products</a>
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Order Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px"><span>Subtotal</span><span class="mono">₱ 0.00</span></div>
        <div style="display:flex;justify-content:space-between;font-size:13px"><span>Shipping</span><span class="mono">₱ 0.00</span></div>
        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--success)"><span>Discount</span><span class="mono">- ₱ 0.00</span></div>
        <hr style="border:0;border-top:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700"><span>Total</span><span class="mono">₱ 0.00</span></div>

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

        <button class="btn btn-primary btn-block" style="margin-top:4px">Place Order</button>
      </div>
    </div>
  </div>
</div>
@endsection
