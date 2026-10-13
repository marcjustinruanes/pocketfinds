<!DOCTYPE html>
<html lang="en">
<head>
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{{ $shop['name'] }} — PocketFinds</title>
<link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}?v={{ filemtime(public_path('css/marketplace.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buyer.css') }}?v={{ filemtime(public_path('css/buyer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/guest-product.css') }}">
<link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
<link rel="stylesheet" href="{{ asset('css/guest-responsive.css') }}?v={{ filemtime(public_path('css/guest-responsive.css')) }}">
<link rel="stylesheet" href="{{ asset('css/guest-shop.css') }}?v={{ filemtime(public_path('css/guest-shop.css')) }}">
</head>
<body class="marketplace guest-shop-page">

@include('guest.partials.header')

<main class="container" style="padding:20px 0 40px">

<a href="{{ url()->previous() === url()->current() ? url('/') : url()->previous() }}" class="gp-back">
  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg> Back
</a>

{{-- Shop hero --}}
<div class="gs-hero">
  <div class="gs-avatar">@if(!empty($shop['photo']))<img src="{{ $shop['photo'] }}" alt="{{ $shop['name'] }}" style="width:100%;height:100%;object-fit:contain;background:#fff;border-radius:inherit">@else{{ $shop['initial'] }}@endif</div>
  <div class="gs-info">
    <div class="gs-name-row">
      <h1 class="gs-name">{{ $shop['name'] }}</h1>
      <button type="button" class="gs-follow-btn" data-protected>
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Follow
      </button>
    </div>
    <div class="gs-rating-row">
      @for($i=1;$i<=5;$i++)
      <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $i<=round($shop['rating'])?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      @endfor
      <span class="gs-rating-val">{{ $shop['rating'] }}</span>
      <span class="gs-followers-count">· {{ number_format($shop['followers']) }} follower{{ $shop['followers'] === 1 ? '' : 's' }}</span>
    </div>
    @if(!empty($shop['desc']))<p class="gs-desc">{{ $shop['desc'] }}</p>@endif
    <button class="gs-chat-btn" type="button" data-protected>@include('buyer.partials.icon', ['name' => 'chat', 'size' => 15]) Chat with Seller</button>
  </div>
  <div class="gs-stats">
    <div class="gs-stat"><div class="gs-stat-val">{{ $shop['products'] }}</div><div class="gs-stat-label">Products</div></div>
    <div class="gs-stat"><div class="gs-stat-val">{{ $shop['sales'] }}</div><div class="gs-stat-label">Sales</div></div>
    <div class="gs-stat"><div class="gs-stat-val">{{ $shop['followers'] }}</div><div class="gs-stat-label">Followers</div></div>
    <div class="gs-stat"><div class="gs-stat-val">{{ $shop['joined'] }}</div><div class="gs-stat-label">Joined</div></div>
  </div>
</div>

<div class="gs-products-head">
  <h2>All Products</h2>
  <span class="gs-products-count">{{ count($items) }} item{{ count($items)!==1?'s':'' }}</span>
</div>


<div class="gs-product-grid">
  @forelse($items as $p)
    @include('guest.partials.pf-product-card', ['p' => $p])
  @empty
    <div class="gs-empty"><h2>No products yet</h2><p>Check back soon for new arrivals.</p></div>
  @endforelse
</div>

</main>

@include('components.marketplace-footer', ['landing' => true])

@include('guest.auth-modal')
<script src="{{ asset('js/marketplace.js') }}?v={{ @filemtime(public_path('js/marketplace.js')) ?: 1 }}"></script>
@include('buyer.partials.assistant', ['assistantGuest' => true])
</body>
</html>
