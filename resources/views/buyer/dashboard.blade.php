@extends('buyer.layout')
@section('title', 'Dashboard')
@section('hide-page-heading', true)

@section('content')

{{-- Welcome hero — same design as the guest homepage hero --}}
@php
  $heroProducts = collect($featured)->filter(fn ($p) => !empty($p['img']))->take(6)->values();
@endphp
<div class="dash-hero"><div class="dash-hero-grid"><div class="dash-hero-main"><div class="dash-hero-copy">
  <div class="dash-hero-eyebrow">Hi, {{ auth()->user()->given_names }}!</div>
  <h1>Discover products <span class="accent">you'll love.</span></h1>
  <p>Browse today's picks, track what's on the way, and keep in touch with your sellers — all from here.</p>
  <a href="{{ route('buyer.browse') }}" class="btn btn-primary">
    @include('buyer.partials.icon', ['name' => 'bag', 'size' => 15]) Explore Products
  </a>
</div></div><div class="dash-hero-side">
  <div class="dash-hero-visual">
    <div class="dash-hero-photo-frame" id="dashHeroPhotoFrame">
      @forelse($heroProducts as $hp)
        <img class="dash-hero-slide {{ $loop->first ? 'active' : '' }}" src="{{ $hp['img'] }}" alt="{{ $hp['name'] }}">
      @empty
        <svg class="ph-icon" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      @endforelse
    </div>
    <span class="dash-hero-script">Good Finds<br>Brighter Days</span>
  </div>
</div></div></div>

{{-- My Orders — one compact horizontal row, not a tall 2x2 grid --}}
<div class="card">
  <div class="card-head"><h2>My Orders</h2></div>
  <div class="card-pad">
    <div class="order-status-grid order-status-row">
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
        <span class="order-status-copy">
          <span class="count mono">{{ $count }}</span>
          <span class="label">{{ $label }}</span>
        </span>
      </a>
      @endforeach
    </div>
  </div>
</div>

{{-- Categories — one horizontal line, scrolling if it ever overflows --}}
<div class="card">
  <div class="card-head">
    <div><h2>Browse by Category</h2><p>Find what you're looking for</p></div>
    <a href="{{ route('buyer.browse') }}" class="btn btn-sm btn-outline">View all</a>
  </div>
  <div class="card-pad">
    <div class="category-grid category-row">
      @forelse($categories as $cat)
      <a href="{{ route('buyer.browse', ['category' => $cat->id]) }}" class="category-chip">
        <span class="category-icon">@include('buyer.partials.category-icon', ['name' => $cat->name])</span>
        <span>{{ $cat->name }}</span>
      </a>
      @empty
      <p style="color:var(--muted);font-size:13px">No categories yet.</p>
      @endforelse
    </div>
  </div>
</div>

{{-- Featured products — full page width so it has room to grow --}}
<div class="card">
  <div class="card-head">
    <div><h2>Featured Products</h2><p>Handpicked for you</p></div>
    <a href="{{ route('buyer.browse') }}" class="btn btn-sm btn-outline">See more</a>
  </div>
  <div class="card-pad">
    <div class="product-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
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
@endsection
