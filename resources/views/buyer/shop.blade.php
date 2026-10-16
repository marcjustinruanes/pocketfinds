@extends('buyer.layout')
@section('title', $shop['name'])
@section('page-title', 'Shop')
@section('page-sub', $shop['name'])

@section('content')

<a href="{{ url()->previous() }}" class="pd-back">
  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  Back
</a>

{{-- Shop hero --}}
@if($shop['banner'] ?? null)<img class="store-banner" src="{{ $shop['banner'] }}" alt="{{ $shop['name'] }} banner" style="display:block;width:100%;max-height:280px;object-fit:cover;margin-bottom:16px;border-radius:12px">@endif
<div class="sp-hero">
  <div class="sp-avatar">@if($shop['photo'] ?? null)<img src="{{ $shop['photo'] }}" alt="{{ $shop['name'] }}">@else{{ $shop['initial'] }}@endif</div>
  <div class="sp-info">
    <div class="sp-name-row">
      <h1 class="sp-name">{{ $shop['name'] }}</h1>
      <button type="button" id="shopFollowBtn" class="sp-follow-btn {{ $shop['is_following'] ? 'is-following' : '' }}" data-slug="{{ $slug }}">
        @if($shop['is_following'])
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span>Following</span>
        @else
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          <span>Follow</span>
        @endif
      </button>
    </div>
    <div class="sp-rating-row">
      @for($i=1;$i<=5;$i++)
      <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $i<=round($shop['rating'])?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      @endfor
      <span class="sp-rating-val">{{ $shop['rating'] }}</span>
      <span class="sp-followers-count">· <span id="shopFollowersCount">{{ number_format($shop['followers']) }}</span> follower{{ $shop['followers'] === 1 ? '' : 's' }}</span>
    </div>
    <p class="sp-desc">{{ $shop['desc'] }}</p>
  </div>
  <div class="sp-stats">
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['products'] }}</div>
      <div class="sp-stat-label">Products</div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['sales'] }}</div>
      <div class="sp-stat-label">Sales</div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['followers'] }}</div>
      <div class="sp-stat-label">Followers</div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['joined'] }}</div>
      <div class="sp-stat-label">Joined</div>
    </div>
  </div>
</div>

{{-- Products --}}
@include('components.store-category-navigation')
<div class="sp-products-head">
  <span>All Products</span>
  <span class="sp-products-count">{{ count($items) }} item{{ count($items)!==1?'s':'' }}</span>
</div>

<div class="product-grid product-grid-lg">
  @forelse($items as $p)
  @include('buyer.partials.product-card', ['p' => $p])
  @empty
  <div class="empty"><h3>No products yet</h3></div>
  @endforelse
</div>

<script>
document.getElementById('shopFollowBtn')?.addEventListener('click', function () {
  const btn = this;
  btn.disabled = true;
  fetch(`/buyer/shop/${btn.dataset.slug}/follow`, {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
    }
  })
    .then(r => r.json())
    .then(data => {
      btn.classList.toggle('is-following', data.following);
      btn.innerHTML = data.following
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Following</span>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span>Follow</span>';
      const count = document.getElementById('shopFollowersCount');
      if (count) count.textContent = data.followers.toLocaleString();
      document.querySelectorAll('.sp-stat-val')[2].textContent = data.followers;
    })
    .finally(() => { btn.disabled = false; });
});
</script>

@endsection
