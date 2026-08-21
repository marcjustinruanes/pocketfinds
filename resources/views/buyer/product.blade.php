@extends('buyer.layout')
@section('title', $product['name'])
@section('page-title', 'Product')
@section('page-sub', $product['cat'])

@section('content')
@php
  $avg     = $product['rating'];
  $reviews = $product['reviews'];
  $counts  = [1=>0,2=>0,3=>0,4=>0,5=>0];
  foreach($reviews as $r) $counts[$r['rating']]++;
  $total   = count($reviews) ?: 1;
@endphp

<a href="{{ url()->previous() }}" class="pd-back">
  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  Back
</a>

<div class="pd-main-grid">

  {{-- LEFT COL: thumb strip + main image --}}
  <div class="pd-img-col">
    <div class="pd-thumb-col">
      <button class="pd-arr" id="arrUp" onclick="scrollThumbs(-1)">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
      </button>
      <div class="pd-thumb-viewport" id="pdThumbViewport">
        <div class="pd-thumb-track" id="pdThumbTrack">
          @foreach(range(1,6) as $t)
          <button class="pd-thumb {{ $t===1?'active':'' }}" onclick="switchThumb(this)">
            @include('buyer.partials.icon', ['name' => $product['img'], 'size' => 18])
          </button>
          @endforeach
        </div>
      </div>
      <button class="pd-arr" id="arrDown" onclick="scrollThumbs(1)">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
    <div class="pd-main-img">
      @include('buyer.partials.icon', ['name' => $product['img'], 'size' => 110])
    </div>
  </div>

  {{-- RIGHT COL: title card + tabs card stacked --}}
  <div class="pd-right-col">

    {{-- Title / info / actions card --}}
    <div class="pd-info-card">
      <div class="pd-crumb-row">
        <span class="pd-crumb">{{ $product['cat'] }}</span>
        @if($product['badge'])<span class="pd-pill">{{ $product['badge'] }}</span>@endif
      </div>
      <div class="pd-title-price-row">
        <h1 class="pd-title">{{ $product['name'] }}</h1>
        <span class="pd-price">₱{{ number_format($product['price']) }}</span>
      </div>
      <div class="pd-meta-row">
        <span class="pd-stars-row">
          @for($i=1;$i<=5;$i++)
          <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i<=round($avg)?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          @endfor
        </span>
        <span class="pd-meta-val">{{ $avg }}</span>
        <span class="pd-meta-sep">·</span>
        <span class="pd-meta-muted">{{ number_format($product['sold']) }} sold</span>
        <span class="pd-meta-sep">·</span>
        <a class="pd-meta-link" href="#" onclick="event.preventDefault();switchTab(document.querySelector('[data-tab=reviews]'),'reviews')">{{ count($reviews) }} reviews</a>
        @if($product['old_price'])<span class="pd-old" style="margin-left:auto">₱{{ number_format($product['old_price']) }}</span>@endif
      </div>
      <div class="pd-actions">
        <button class="pd-btn-cart" onclick="openCart('{{ addslashes($product['name']) }}',{{ $product['price'] }},{{ json_encode($product['variants']['color'] ?? []) }},{{ json_encode($product['variants']['size'] ?? []) }},false,this,'{{ $product['id'] }}','{{ $product['img'] }}')">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
          Add to Cart
        </button>
        <button class="pd-btn-buy" onclick="openCart('{{ addslashes($product['name']) }}',{{ $product['price'] }},{{ json_encode($product['variants']['color'] ?? []) }},{{ json_encode($product['variants']['size'] ?? []) }},true,this,'{{ $product['id'] }}','{{ $product['img'] }}')">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          Buy Now
        </button>
      </div>
    </div>

    {{-- Description / tabs card --}}
    <div class="pd-tabs-wrap">
      <div class="pd-tab-bar">
        <button class="pd-tab active" data-tab="options" onclick="switchTab(this,'options')">Options</button>
        <button class="pd-tab"        data-tab="details" onclick="switchTab(this,'details')">Details</button>
        <button class="pd-tab"        data-tab="reviews" onclick="switchTab(this,'reviews')">Reviews <span class="pd-tab-count">{{ count($reviews) }}</span></button>
      </div>

      {{-- Options --}}
      <div class="pd-tab-pane active" id="tab-options">
        @if(!empty($product['variants']['color']))
        <div class="pd-opt-group">
          <div class="pd-opt-label">Color</div>
          <div class="pd-opt-row">
            @foreach($product['variants']['color'] as $c)
            <button class="pd-opt-btn {{ $loop->first?'active':'' }}" onclick="selectVariant(this)">{{ $c }}</button>
            @endforeach
          </div>
        </div>
        @endif
        @if(!empty($product['variants']['size']))
        <div class="pd-opt-group">
          <div class="pd-opt-label">{{ str_contains(implode(',',$product['variants']['size']),'Switch') ? 'Switch Type' : 'Size' }}</div>
          <div class="pd-opt-row">
            @foreach($product['variants']['size'] as $s)
            <button class="pd-opt-btn {{ $loop->first?'active':'' }}" onclick="selectVariant(this)">{{ $s }}</button>
            @endforeach
          </div>
        </div>
        @endif
        <div class="pd-opt-group">
          <div class="pd-opt-label">Quantity</div>
          <div class="pd-qty-row">
            <button class="pd-qty-btn" onclick="changeQty(-1)">−</button>
            <span class="pd-qty-num" id="pdQty">1</span>
            <button class="pd-qty-btn" onclick="changeQty(1)">+</button>
          </div>
        </div>
      </div>

      {{-- Details --}}
      <div class="pd-tab-pane" id="tab-details">
        <p class="pd-desc-text">{{ $product['desc'] }}</p>
        <div class="pd-details-divider"></div>
        <div class="pd-specs-grid">
          @foreach($product['specs'] as [$label, $val])
          <div class="pd-spec-row">
            <span class="pd-spec-key">{{ $label }}</span>
            <span class="pd-spec-val">{{ $val }}</span>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Reviews --}}
      <div class="pd-tab-pane" id="tab-reviews">
        {{-- Star filter chips + count on right --}}
        <div class="pd-rev-filter">
          <div class="pd-rev-chips">
            <button class="pd-rev-chip active" data-star="0" onclick="filterReviews(0)">All</button>
            @foreach([5,4,3,2,1] as $star)
            <button class="pd-rev-chip" onclick="filterReviews({{ $star }})" data-star="{{ $star }}">
              {{ $star }}<svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="margin-left:1px"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </button>
            @endforeach
          </div>
          <div class="pd-rev-count" id="pdRevCount">
            <span class="pd-rev-count-num" id="pdRevCountNum">{{ count($reviews) }}</span>
            <span class="pd-rev-count-lbl" id="pdRevCountLbl">reviews</span>
          </div>
        </div>
        <div class="pd-rev-list" id="pdRevList">
          @forelse($reviews as $idx => $r)
          <div class="pd-rev-item {{ $idx>=3?'rev-hidden':'' }}" data-rating="{{ $r['rating'] }}">
            <div class="pd-rev-av">{{ strtoupper(substr($r['name'],0,1)) }}</div>
            <div class="pd-rev-body">
              <div class="pd-rev-header">
                <span class="pd-rev-name">{{ $r['name'] }}</span>
                <div class="pd-rev-stars-sm">
                  @for($i=1;$i<=5;$i++)
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="{{ $i<=$r['rating']?'#f59e0b':'none' }}" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  @endfor
                </div>
                <span class="pd-rev-date">{{ $r['date'] }}</span>
              </div>
              <p class="pd-rev-text">{{ $r['text'] }}</p>
            </div>
          </div>
          @empty
          <div class="empty"><h3>No reviews yet</h3></div>
          @endforelse
          @if(count($reviews) > 3)
          <button class="pd-see-more" id="pdSeeMore" onclick="toggleReviews()">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" id="pdSeeIcon"><polyline points="6 9 12 15 18 9"/></svg>
            See all {{ count($reviews) }} reviews
          </button>
          @endif
        </div>
      </div>

    </div>{{-- end pd-tabs-wrap --}}

  </div>{{-- end pd-right-col --}}

</div>{{-- end pd-main-grid --}}

{{-- BOTTOM: More from shop + related --}}
@if(count($shopProducts))
<div class="pd-shop-more">
  <div class="pd-shop-more-head">
    <div class="pd-shop-more-left">
      <div class="pd-shop-more-av">{{ strtoupper(substr($product['seller'],0,1)) }}</div>
      <div>
        <div class="pd-shop-more-name">{{ $product['seller'] }}</div>
        <div class="pd-shop-more-sub">More from this shop</div>
      </div>
    </div>
    <a href="{{ route('buyer.shop', $product['seller_slug']) }}" class="pd-shop-more-link">View Shop →</a>
  </div>
  <div class="pd-shop-more-grid">
    @foreach($shopProducts as $sp)
    <div class="pd-mini-card" onclick="window.location='{{ route('buyer.product', $sp['id']) }}'">
      <div class="pd-mini-img">
        @include('buyer.partials.icon', ['name' => $sp['img'], 'size' => 28])
        @if($sp['badge'])<span class="pd-mini-badge">{{ $sp['badge'] }}</span>@endif
      </div>
      <div class="pd-mini-info">
        <div class="pd-mini-name">{{ $sp['name'] }}</div>
        <div class="pd-mini-price">₱{{ number_format($sp['price']) }}</div>
        <div class="pd-mini-rating">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          {{ $sp['rating'] }}
        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endif

@if(count($related))
<div class="pd-related">
  <div class="pd-related-head">
    <span>Similar Products</span>
    <a href="{{ route('buyer.browse') }}?category={{ urlencode($product['cat']) }}" class="pd-related-more">Browse all in {{ $product['cat'] }} →</a>
  </div>
  <div class="product-grid product-grid-lg">
    @foreach($related as $rp)
    <div class="product-card" onclick="window.location='{{ route('buyer.product', $rp['id']) }}'">
      <div class="product-img">
        @include('buyer.partials.icon', ['name' => $rp['img'], 'size' => 36])
        @if($rp['badge'])<span class="product-badge">{{ $rp['badge'] }}</span>@endif
      </div>
      <div class="product-info">
        <div class="product-name">{{ $rp['name'] }}</div>
        <a class="product-seller" href="{{ route('buyer.shop', $rp['seller_slug']) }}" onclick="event.stopPropagation()">by {{ $rp['seller'] }}</a>
        <div class="product-price">₱{{ number_format($rp['price']) }}</div>
        <div class="product-rating">
          <span class="star-ic">@include('buyer.partials.icon',['name'=>'star','size'=>11])</span>
          {{ $rp['rating'] }} · {{ number_format($rp['sold']) }} sold
        </div>
        <div class="pc-actions">
          <button class="pc-act pc-act-cart" onclick="event.stopPropagation();openCart('{{ addslashes($rp['name']) }}',{{ $rp['price'] }},{{ json_encode($rp['variants']['color']) }},{{ json_encode($rp['variants']['size']) }},false,this,'{{ $rp['id'] }}','{{ $rp['img'] }}')" title="Add to Cart">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
          </button>
          <button class="pc-act pc-act-buy" onclick="event.stopPropagation();openCart('{{ addslashes($rp['name']) }}',{{ $rp['price'] }},{{ json_encode($rp['variants']['color']) }},{{ json_encode($rp['variants']['size']) }},true,this,'{{ $rp['id'] }}','{{ $rp['img'] }}')" title="Buy Now">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          </button>
        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endif

<script>
const THUMB_STEP = 68;
let thumbPos = 0;
function scrollThumbs(dir) {
  const track = document.getElementById('pdThumbTrack');
  const vp    = document.getElementById('pdThumbViewport');
  const max   = Math.max(0, track.scrollHeight - vp.clientHeight);
  thumbPos = Math.max(0, Math.min(max, thumbPos + dir * THUMB_STEP));
  track.style.transform = `translateY(-${thumbPos}px)`;
  document.getElementById('arrUp').style.opacity   = thumbPos <= 0   ? '.3' : '1';
  document.getElementById('arrDown').style.opacity = thumbPos >= max ? '.3' : '1';
}
function switchThumb(btn) {
  btn.closest('.pd-thumb-track').querySelectorAll('.pd-thumb').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
function selectVariant(btn) {
  btn.closest('.pd-opt-row').querySelectorAll('.pd-opt-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
let qty = 1;
function changeQty(d) {
  qty = Math.max(1, qty + d);
  document.getElementById('pdQty').textContent = qty;
}
function switchTab(btn, id) {
  document.querySelectorAll('.pd-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.pd-tab-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tab-' + id).classList.add('active');
}
let expanded = false;
function toggleReviews() {
  expanded = !expanded;
  document.querySelectorAll('.rev-hidden').forEach(el => el.style.display = expanded ? '' : 'none');
  document.getElementById('pdSeeMore').innerHTML = expanded
    ? '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg> Show less'
    : '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg> See all {{ count($reviews) }} reviews';
}
const revCounts = @json($counts);
const revTotal  = {{ count($reviews) }};
let activeFilter = 0;
function filterReviews(star) {
  activeFilter = star;
  document.querySelectorAll('#pdRevList .pd-rev-item').forEach(el => {
    const match  = !activeFilter || parseInt(el.dataset.rating) === activeFilter;
    const hidden = el.classList.contains('rev-hidden') && !expanded;
    el.style.display = match && !hidden ? '' : 'none';
  });
  document.querySelectorAll('.pd-rev-chip').forEach(b => b.classList.toggle('active', parseInt(b.dataset.star) === activeFilter));
  const num = activeFilter ? (revCounts[activeFilter] || 0) : revTotal;
  const lbl = activeFilter ? 'rates' : 'reviews';
  document.getElementById('pdRevCountNum').textContent = num;
  document.getElementById('pdRevCountLbl').textContent = lbl;
}
document.querySelectorAll('.rev-hidden').forEach(el => el.style.display = 'none');
document.getElementById('arrUp').style.opacity = '.3';
function fitTabsWrap() {
  const imgCol  = document.querySelector('.pd-img-col');
  const infoCard = document.querySelector('.pd-info-card');
  const tabsWrap = document.querySelector('.pd-tabs-wrap');
  const rightCol = document.querySelector('.pd-right-col');
  const imgH    = imgCol.offsetHeight;
  const infoH   = infoCard.offsetHeight;
  const gap     = parseInt(getComputedStyle(rightCol).gap) || 12;
  tabsWrap.style.height = (imgH - infoH - gap) + 'px';
}
fitTabsWrap();
window.addEventListener('resize', fitTabsWrap);
</script>
@endsection
