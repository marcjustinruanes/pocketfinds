@extends('buyer.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names . '!')

@section('content')
{{-- Welcome hero --}}
<div class="dash-hero">
  <div class="dash-hero-inner">
    <div>
      <div class="dash-hero-eyebrow">Good to see you</div>
      <h1>Hi {{ auth()->user()->given_names }}, ready to find something great?</h1>
      <p>Browse today's picks, track what's on the way, and keep in touch with your sellers — all from here.</p>
    </div>
    <a href="{{ route('buyer.browse') }}" class="btn btn-primary">
      @include('buyer.partials.icon', ['name' => 'bag', 'size' => 15]) Browse Products
    </a>
  </div>
</div>

{{-- Quick stats --}}
<div class="kpi-grid">
  <div class="kpi tone-info">
    <div class="kpi-icon">@include('buyer.partials.icon', ['name' => 'package', 'size' => 16])</div>
    <div class="label">Active Orders</div>
    <div class="value">{{ $activeOrders }}</div>
    <div class="delta">In progress</div>
  </div>
  <div class="kpi">
    <div class="kpi-icon">@include('buyer.partials.icon', ['name' => 'cart', 'size' => 16])</div>
    <div class="label">Cart Items</div>
    <div class="value">{{ $cartCount }}</div>
    <div class="delta">Ready to checkout</div>
  </div>
  <div class="kpi tone-success">
    <div class="kpi-icon">@include('buyer.partials.icon', ['name' => 'check', 'size' => 16])</div>
    <div class="label">Completed Orders</div>
    <div class="value">{{ $completedOrders }}</div>
    <div class="delta up">All time</div>
  </div>
  <div class="kpi tone-warning">
    <div class="kpi-icon">@include('buyer.partials.icon', ['name' => 'mail', 'size' => 16])</div>
    <div class="label">Unread Messages</div>
    <div class="value">{{ $unreadMessages }}</div>
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
      <div class="card-pad">
        <div class="order-status-grid">
          @php
          $orderStatuses = [
            ['package', 'To Ship',         'to_ship',          $statusCounts['to_ship']],
            ['truck',   'In Transit',      'in_transit',       $statusCounts['in_transit']],
            ['bike',    'Out for Delivery','out_for_delivery', $statusCounts['out_for_delivery']],
            ['check',   'Completed',       'completed',        $statusCounts['completed']],
          ];
          @endphp
          @foreach($orderStatuses as [$icon, $label, $tab, $count])
          <a href="{{ route('buyer.orders') }}?tab={{ $tab }}" class="order-status-tile">
            <span class="ic">@include('buyer.partials.icon', ['name' => $icon, 'size' => 15])</span>
            <span class="count mono">{{ $count }}</span>
            <span class="label">{{ $label }}</span>
          </a>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Recent activity --}}
    <div class="card">
      <div class="card-head"><div><h2>Recent Orders</h2><p>Your latest purchases</p></div></div>
      <div class="card-pad">
        @forelse($recentOrders as $order)
        <a href="{{ route('buyer.orders') }}?tab={{ $order->status }}" class="recent-order-row" style="text-decoration:none;color:inherit">
          <span class="ic">@include('buyer.partials.icon', ['name' => 'package', 'size' => 16])</span>
          <span class="info">
            <strong>{{ $order->seller?->business_name ?: ($order->seller?->given_names ?: 'Order ' . $order->order_number) }}</strong>
            <span>{{ str_replace('_', ' ', ucfirst($order->status)) }} · {{ $order->created_at?->format('M d, Y') ?? '—' }}</span>
          </span>
          <span class="amount">₱{{ number_format($order->total, 2) }}</span>
        </a>
        @empty
        <div class="empty" style="padding:16px 0">
          @include('buyer.partials.icon',['name'=>'package','size'=>26,'class'=>'ic'])
          <h3>No orders yet</h3>
          <p>Your recent purchases will show up here.</p>
        </div>
        @endforelse
      </div>
    </div>

    {{-- Quick actions --}}
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('buyer.browse') }}" class="action-tile">
          <span class="ic">@include('buyer.partials.icon', ['name' => 'bag', 'size' => 16])</span>
          <span class="copy"><strong>Browse Products</strong></span>
          <span class="chev">@include('buyer.partials.icon', ['name' => 'arrow-right', 'size' => 14])</span>
        </a>
        <a href="{{ route('buyer.cart') }}" class="action-tile">
          <span class="ic">@include('buyer.partials.icon', ['name' => 'cart', 'size' => 16])</span>
          <span class="copy"><strong>View Cart</strong></span>
          <span class="chev">@include('buyer.partials.icon', ['name' => 'arrow-right', 'size' => 14])</span>
        </a>
        <a href="{{ route('buyer.orders') }}" class="action-tile">
          <span class="ic">@include('buyer.partials.icon', ['name' => 'package', 'size' => 16])</span>
          <span class="copy"><strong>Track Orders</strong></span>
          <span class="chev">@include('buyer.partials.icon', ['name' => 'arrow-right', 'size' => 14])</span>
        </a>
        <a href="{{ route('buyer.messages') }}" class="action-tile">
          <span class="ic">@include('buyer.partials.icon', ['name' => 'mail', 'size' => 16])</span>
          <span class="copy"><strong>Messages</strong></span>
          <span class="chev">@include('buyer.partials.icon', ['name' => 'arrow-right', 'size' => 14])</span>
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
