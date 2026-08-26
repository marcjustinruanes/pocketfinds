@extends('buyer.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names . '!')

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
          @php
          $categories = [
            ['food',   'Food & Drinks'],
            ['shirt',  'Clothing'],
            ['sparkle','Beauty'],
            ['phone',  'Electronics'],
            ['home',   'Home & Living'],
            ['puzzle', 'Hobbies'],
          ];
          @endphp
          @foreach($categories as [$icon, $label])
          <a href="{{ route('buyer.browse') }}?category={{ urlencode($label) }}" class="category-chip">
            <span class="category-icon">@include('buyer.partials.icon', ['name' => $icon, 'size' => 24])</span>
            <span>{{ $label }}</span>
          </a>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Featured products --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Featured Products</h2><p>Handpicked for you</p></div>
        <a href="{{ route('buyer.browse') }}" class="btn btn-sm btn-outline">See more</a>
      </div>
      <div class="card-pad">
        <div class="product-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
          @forelse($featured as $p)
          @include('buyer.partials.product-card', ['p' => $p])
          @empty
          <div class="empty" style="grid-column:1/-1;padding:30px 0">
            @include('buyer.partials.icon',['name'=>'bag','size'=>28,'class'=>'ic'])
            <h3>No products yet</h3>
            <p>Check back soon!</p>
          </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    {{-- Order status --}}
    <div class="card">
      <div class="card-head"><h2>My Orders</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        @php
        $orderStatuses = [
          ['package', 'To Ship',           'to_ship'],
          ['truck',   'In Transit',         'in_transit'],
          ['bike',    'Out for Delivery',   'out_for_delivery'],
          ['check',   'Completed',          'completed'],
        ];
        @endphp
        @foreach($orderStatuses as [$icon, $label, $tab])
        <a href="{{ route('buyer.orders') }}?tab={{ $tab }}" class="order-status-row">
          <span class="order-status-ic">@include('buyer.partials.icon', ['name' => $icon, 'size' => 18])</span>
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
        <a href="{{ route('buyer.browse') }}" class="btn btn-outline">
          @include('buyer.partials.icon', ['name' => 'bag', 'size' => 15]) Browse Products
        </a>
        <a href="{{ route('buyer.cart') }}" class="btn btn-outline">
          @include('buyer.partials.icon', ['name' => 'cart', 'size' => 15]) View Cart
        </a>
        <a href="{{ route('buyer.orders') }}" class="btn btn-outline">
          @include('buyer.partials.icon', ['name' => 'package', 'size' => 15]) Track Orders
        </a>
        <a href="{{ route('buyer.messages') }}" class="btn btn-outline">
          @include('buyer.partials.icon', ['name' => 'mail', 'size' => 15]) Messages
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
