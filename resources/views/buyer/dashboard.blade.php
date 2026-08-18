@extends('buyer.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->first_name . '!')

@section('content')
{{-- Quick stats --}}
<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Active Orders</div>
    <div class="value">0</div>
    <div class="delta">In progress</div>
  </div>
  <div class="kpi">
    <div class="label">Cart Items</div>
    <div class="value">0</div>
    <div class="delta">Ready to checkout</div>
  </div>
  <div class="kpi">
    <div class="label">Completed Orders</div>
    <div class="value">0</div>
    <div class="delta up">All time</div>
  </div>
  <div class="kpi">
    <div class="label">Unread Messages</div>
    <div class="value">0</div>
    <div class="delta">From sellers</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    {{-- Categories --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Browse by Category</h2><p>Find what you're looking for</p></div>
        <a href="{{ route('buyer.browse') }}" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="card-pad">
        <div class="category-grid">
          @foreach([['🍔','Food & Drinks'],['👗','Clothing'],['💄','Beauty'],['📱','Electronics'],['🏠','Home & Living'],['🎮','Hobbies']] as [$icon,$label])
          <a href="{{ route('buyer.browse') }}?category={{ urlencode($label) }}" class="category-chip">
            <span class="category-icon">{{ $icon }}</span>
            <span>{{ $label }}</span>
          </a>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Featured products placeholder --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Featured Products</h2><p>Handpicked for you</p></div>
        <a href="{{ route('buyer.browse') }}" class="btn btn-sm btn-outline">See more</a>
      </div>
      <div class="card-pad">
        <div class="product-grid">
          @foreach(range(1,4) as $i)
          <div class="product-card">
            <div class="product-img">🛍</div>
            <div class="product-info">
              <div class="product-name">Sample Product {{ $i }}</div>
              <div class="product-price">₱ —</div>
              <button class="btn btn-sm btn-primary" style="width:100%;margin-top:8px">Add to Cart</button>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    {{-- Order status --}}
    <div class="card">
      <div class="card-head"><h2>My Orders</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        @foreach([['📦','To Ship','to_ship'],['🚚','In Transit','in_transit'],['🏍','Out for Delivery','out_for_delivery'],['✅','Completed','completed']] as [$ic,$label,$tab])
        <a href="{{ route('buyer.orders') }}?tab={{ $tab }}" class="order-status-row">
          <span class="order-status-ic">{{ $ic }}</span>
          <span>{{ $label }}</span>
          <span class="order-status-count mono">0</span>
        </a>
        @endforeach
      </div>
    </div>

    {{-- Quick actions --}}
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('buyer.browse') }}" class="btn btn-outline">🛍 Browse Products</a>
        <a href="{{ route('buyer.cart') }}" class="btn btn-outline">🛒 View Cart</a>
        <a href="{{ route('buyer.orders') }}" class="btn btn-outline">📦 Track Orders</a>
        <a href="{{ route('buyer.messages') }}" class="btn btn-outline">✉ Messages</a>
      </div>
    </div>
  </div>
</div>
@endsection
