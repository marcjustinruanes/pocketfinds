@extends('buyer.layout')
@section('title', 'Dashboard')
@section('hide-page-heading', true)

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
          @forelse($categories as $cat)
          <a href="{{ route('buyer.browse', ['category' => $cat->id]) }}" class="category-chip">
            <span class="category-icon">@include('buyer.partials.category-icon', ['name' => $cat->name])</span>
            <span>{{ $cat->name }}</span>
          </a>
          @empty
          <p style="color:var(--muted);font-size:13px;grid-column:1/-1">No categories yet.</p>
          @endforelse
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
