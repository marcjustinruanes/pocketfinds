@extends('buyer.layout')
@section('title', $shop['name'])
@section('page-title', 'Shop')
@section('page-sub', $shop['name'])
@section('hide-page-heading', true)
@section('body-class', 'buyer-shop-page')
@push('head')
<link rel="stylesheet" href="{{ asset('css/guest-catalog.css') }}?v={{ filemtime(public_path('css/guest-catalog.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buyer-shop.css') }}?v={{ filemtime(public_path('css/buyer-shop.css')) }}">
@endpush

@section('content')
@php
  $shopCategories = app(\App\Services\ShopCatalog::class)->categories($seller->id)->filter(fn($category) => !isset($category->is_visible) || $category->is_visible);
  $selectedCategory = (string)request('shop_category', '');
@endphp

{{-- Shop hero --}}
<div class="sp-hero">
  <div class="sp-avatar">@if($shop['photo'] ?? null)<img src="{{ $shop['photo'] }}" alt="{{ $shop['name'] }}">@else{{ $shop['initial'] }}@endif</div>
  <div class="sp-info">
    <div class="sp-name-row">
      <h1 class="sp-name">{{ $shop['name'] }}</h1>
      <button type="button" id="shopFollowBtn" class="sp-follow-btn" data-slug="{{ $slug }}" aria-pressed="{{ $shop['is_following'] ? 'true' : 'false' }}" aria-label="{{ $shop['is_following'] ? 'Unfollow shop' : 'Follow shop' }}" title="{{ $shop['is_following'] ? 'Unfollow shop' : 'Follow shop' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10v11h11M2 10l2-7h14l2 7M2 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M7 21v-7h5v7m7-7v8m-4-4h8"/></svg>
      </button>
      <button type="button" class="sp-message-btn" data-messages-trigger data-messages-url="{{ route('buyer.messages',['seller'=>$slug]) }}" aria-label="Message {{ $shop['name'] }}" title="Message shop">@include('buyer.partials.icon',['name'=>'chat','size'=>18])</button>
    </div>
    <div class="sp-rating-row">
      @for($i=1;$i<=5;$i++)
      <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $i<=round($shop['rating'])?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      @endfor
      <span class="sp-rating-val">{{ $shop['rating'] }}</span>
      <span class="sp-followers-count">· <span id="shopFollowersCount">{{ number_format($shop['followers']) }}</span> <span id="shopFollowersLabel">{{ $shop['followers'] === 1 ? 'follower' : 'followers' }}</span></span>
    </div>
    <p class="sp-desc">{{ $shop['desc'] }}</p>
  </div>
  <div class="sp-stats-panel"><div class="sp-stats">
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['products'] }}</div>
      <div class="sp-stat-label">Products</div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['sales'] }}</div>
      <div class="sp-stat-label">Sold</div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-val">{{ $shop['joined'] }}</div>
      <div class="sp-stat-label">Joined</div>
    </div>
  </div><div class="shop-header-tools"><button type="button" class="shop-vouchers-button" id="openShopVouchers" aria-haspopup="dialog" aria-controls="shopVouchersModal">Shop vouchers</button>
    <form method="GET" action="{{ route('buyer.shop',$seller->username) }}" id="shopCategoryFilter">
      <select class="shop-category-select" name="shop_category" aria-label="Shop categories">
        <option value="" @selected($selectedCategory==='')>All categories</option>
        @foreach($shopCategories->whereNull('parent_id') as $parent)
          <optgroup label="{{ $parent->name }}"><option value="{{ $parent->id }}" @selected($selectedCategory===(string)$parent->id)>{{ $parent->name }}</option>
          @foreach($shopCategories->where('parent_id',$parent->id) as $child)<option value="{{ $child->id }}" @selected($selectedCategory===(string)$child->id)>{{ $child->name }}</option>@endforeach</optgroup>
        @endforeach
        @if($shopCategories->isNotEmpty())<option value="uncategorized" @selected($selectedCategory==='uncategorized')>Uncategorized</option>@endif
      </select>
    </form>
  </div></div>
</div>

{{-- Products --}}
<section class="shop-catalog-panel" id="products" aria-label="Shop products">
<div class="shop-catalog-tools">@include('buyer.partials.shop-tools')</div>
<div class="sp-products-head">
  <span>All Products</span>
  <span class="sp-products-count">{{ count($items) }} item{{ count($items)!==1?'s':'' }}</span>
</div>

<div class="pf-product-grid">
  @forelse($items as $p)
  @include('guest.partials.pf-product-card', ['p' => $p, 'productRoute'=>'buyer.product','catalogCard'=>true,'highlightFlash'=>true])
  @empty
  <div class="empty"><h3>No products yet</h3></div>
  @endforelse
</div>
</section>

<script>
document.querySelector('#shopCategoryFilter select').addEventListener('change', event => event.target.form.requestSubmit());
const shopVoucherDialog = document.getElementById('shopVouchersModal');
document.getElementById('openShopVouchers').addEventListener('click', () => shopVoucherDialog.showModal());
shopVoucherDialog.addEventListener('click', event => { if (event.target === shopVoucherDialog) { const bounds=shopVoucherDialog.getBoundingClientRect(); if(event.clientX<bounds.left||event.clientX>bounds.right||event.clientY<bounds.top||event.clientY>bounds.bottom)shopVoucherDialog.close(); } });
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
    .then(async r => { const data = await r.json(); if (!r.ok) throw new Error(data.message || 'Unable to update follow status.'); return data; })
    .then(data => {
      btn.setAttribute('aria-pressed', String(data.following));
      btn.title = data.following ? 'Unfollow shop' : 'Follow shop';
      btn.setAttribute('aria-label', btn.title);
      document.getElementById('shopFollowersCount').textContent = Number(data.followers).toLocaleString();
      document.getElementById('shopFollowersLabel').textContent = Number(data.followers) === 1 ? 'follower' : 'followers';
    })
    .catch(error => showToast(error.message, 'error'))
    .finally(() => { btn.disabled = false; });
});
</script>

@endsection
