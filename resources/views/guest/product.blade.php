<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{{ $product['name'] }} — PocketFinds</title>
<link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Allura&family=Inter:wght@400;500;600;700;800;900&family=Manrope:wght@400&display=swap" rel="stylesheet">
{{-- Reuses the buyer app's own product-page styles/icons so a guest sees
     exactly the same page a signed-in buyer would, right up until they try
     to actually do something that needs an account. --}}
<link rel="stylesheet" href="{{ asset('css/buyer.css') }}?v={{ filemtime(public_path('css/buyer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/marketplace.css') }}?v={{ filemtime(public_path('css/marketplace.css')) }}">
{{-- Loaded last so its header/search styles win over marketplace.css's, matching the guest homepage. --}}
<link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
<link rel="stylesheet" href="{{ asset('css/guest-responsive.css') }}?v={{ filemtime(public_path('css/guest-responsive.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buyer-storefront.css') }}?v={{ filemtime(public_path('css/buyer-storefront.css')) }}">
<link rel="stylesheet" href="{{ asset('css/navigation.css') }}?v={{ filemtime(public_path('css/navigation.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buyer-pages.css') }}?v={{ filemtime(public_path('css/buyer-pages.css')) }}">
<link rel="stylesheet" href="{{ asset('css/product-detail.css') }}?v={{ filemtime(public_path('css/product-detail.css')) }}">
<link rel="stylesheet" href="{{ asset('css/product-options.css') }}?v={{ filemtime(public_path('css/product-options.css')) }}">
</head>
<body class="buyer marketplace buyer-storefront buyer-pages product-detail-page">


<div class="shell" id="appShell"><div style="display:flex;flex-direction:column;min-width:0">
@include('guest.partials.header')

<main class="content">

@php
  $combinationGroup = \App\Support\ProductVariationSet::combination($product['variations'] ?? []);
  $displayVariations = \App\Support\ProductVariationSet::displayGroups($product['variations'] ?? []);
  $avg     = $product['rating'];
  $reviews = $product['reviews'];
  $counts  = [1=>0,2=>0,3=>0,4=>0,5=>0];
  foreach($reviews as $r) $counts[$r['rating']]++;
  $total   = count($reviews) ?: 1;
@endphp

@include('partials.product-detail', ['buyerMode' => false])

</main>

@include('components.marketplace-footer', ['landing' => true])
</div></div>

@include('guest.auth-modal')

{{-- Image lightbox --}}
<div id="imgViewer" style="display:none;position:fixed;inset:0;z-index:400;background:rgba(0,0,0,.85);align-items:center;justify-content:center;cursor:zoom-out" onclick="closeImgViewer()">
  <img id="imgViewerImg" src="" style="max-width:92vw;max-height:92vh;border-radius:10px;object-fit:contain;box-shadow:0 18px 60px rgba(0,0,0,.5);display:block">
  <button onclick="closeImgViewer()" style="position:absolute;top:18px;right:22px;width:38px;height:38px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:rgba(0,0,0,.4);color:#fff;font-size:22px;line-height:1;cursor:pointer">×</button>
</div>

@include('partials.product-combination-data')
<script>
function selectVariant(btn) {
  if (window.productCombinations) return window.productCombinations.select(btn);
  if (btn.disabled) return;
  const scope = btn.dataset.exclusive === 'true'
    ? document.getElementById('tab-options')
    : btn.closest('.pd-opt-row');
  scope.querySelectorAll('.pd-opt-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (btn.dataset.image) {
    showProductImage(btn.dataset.image);
    syncThumbForImage(btn.dataset.image);
  }
  updatePriceDisplay();
}

// Keeps the mini-picture rail and the Options tab pointed at the same
// photo — selecting a variation highlights its matching thumbnail, and
// clicking a thumbnail that belongs to a variation selects that variation.
function syncThumbForImage(src) {
  document.querySelectorAll('#pdThumbTrack .pd-thumb').forEach(t => {
    const img = t.querySelector('img');
    if (img && img.getAttribute('src') === src) setActiveThumb(t);
  });
}

function syncVariantForImage(src) {
  if (window.productCombinations) return window.productCombinations.selectImage(src);
  const match = [...document.querySelectorAll('#tab-options .pd-opt-btn[data-image]')]
    .find(b => b.dataset.image === src);
  if (match && !match.disabled) {
    document.getElementById('tab-options').querySelectorAll('.pd-opt-btn').forEach(b => b.classList.remove('active'));
    match.classList.add('active');
    updatePriceDisplay();
  }
}

function currentUnitPrice() {
  const flashPrice = @json(!empty($product['flash_sale']) ? $product['price'] : null);
  if (flashPrice !== null) return Number(flashPrice);
  const priceEl = document.getElementById('pdPrice');
  const active  = selectedPurchaseOption();
  return active ? parseFloat(active.dataset.price) : parseFloat(priceEl.dataset.basePrice);
}

function selectedPurchaseOption() {
  return window.productCombinations ? window.productCombinations.selected()
    : document.querySelector('#tab-options .pd-opt-btn.active[data-exclusive="true"]');
}

function updateSelectedStock() {
  const label = document.getElementById('pdSelectedStock');
  if (!label) return;
  const selected = selectedPurchaseOption();
  label.hidden = !selected;
  label.textContent = selected ? (Number(selected.dataset.stock) > 0 ? Number(selected.dataset.stock) + ' available' : 'Out of stock') : '';
}

function updatePriceDisplay() {
  updateSelectedStock();
  if (window.productQuantity) qty = window.productQuantity.sync(qty);
  const priceEl = document.getElementById('pdPrice');
  if (!priceEl) return;
  priceEl.textContent = '₱' + currentUnitPrice().toLocaleString();
  updateQtyTotal();
}

function updateQtyTotal() {
  const totalEl = document.getElementById('pdQtyTotal');
  if (!totalEl) return;
  if (qty >= 2) {
    totalEl.textContent = 'Total: ₱' + (currentUnitPrice() * qty).toLocaleString();
    totalEl.style.display = '';
  } else {
    totalEl.style.display = 'none';
  }
}

function showProductImage(src, thumb) {
  const img = document.getElementById('pdMainImg');
  const video = document.getElementById('pdMainVideo');
  if (video) video.style.display = 'none';
  if (img) { img.src = src; img.style.display = 'block'; }
  const mainBox = img && img.closest('.pd-main-img');
  if (mainBox) mainBox.style.cursor = 'zoom-in';
  if (thumb) {
    setActiveThumb(thumb);
    syncVariantForImage(src);
  }
}
function openImgViewer() {
  const img = document.getElementById('pdMainImg');
  if (!img || img.style.display === 'none') return;
  document.getElementById('imgViewerImg').src = img.src;
  const v = document.getElementById('imgViewer');
  v.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeImgViewer() {
  document.getElementById('imgViewer').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeImgViewer(); });

function showProductVideo(thumb) {
  const img = document.getElementById('pdMainImg');
  const video = document.getElementById('pdMainVideo');
  if (!video) return;
  if (img) img.style.display = 'none';
  video.style.display = 'block';
  if (thumb) setActiveThumb(thumb);
}

function setActiveThumb(thumb) {
  const track = thumb.closest('.pd-thumb-track');
  if (!track) return;
  track.querySelectorAll('.pd-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}

(function () {
  const track = document.getElementById('pdThumbTrack');
  const up = document.getElementById('pdThumbUp');
  const down = document.getElementById('pdThumbDown');
  if (!track || !up || !down) return;
  const viewport = track.parentElement;
  const step = 64;
  function updateArrowVisibility() {
    const maximum = Math.max(0, viewport.scrollHeight - viewport.clientHeight);
    const needsScroll = maximum > 1;
    up.style.display = needsScroll ? '' : 'none';
    down.style.display = needsScroll ? '' : 'none';
    up.disabled = viewport.scrollTop <= 1;
    down.disabled = viewport.scrollTop >= maximum - 1;
  }
  updateArrowVisibility();
  viewport.addEventListener('scroll', updateArrowVisibility, { passive: true });
  window.addEventListener('resize', updateArrowVisibility);
  if (typeof ResizeObserver !== 'undefined') {
    const observer = new ResizeObserver(updateArrowVisibility);
    observer.observe(viewport);
    observer.observe(track);
  }
  const scroll = direction => viewport.scrollBy({ top: direction * step, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  up.addEventListener('click', () => scroll(-1));
  down.addEventListener('click', () => scroll(1));
})();
let qty = 1;
function changeQty(d) {
  if (!window.productQuantity) return;
  qty = window.productQuantity.sync(qty, d);
  updateQtyTotal();
}
function switchTab(btn, id) {
  document.querySelectorAll('.pd-tab').forEach(t => {
    t.classList.remove('active');
    t.setAttribute('aria-selected', 'false');
    t.tabIndex = -1;
  });
  document.querySelectorAll('.pd-product-information .pd-tab-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  btn.setAttribute('aria-selected', 'true');
  btn.tabIndex = 0;
  document.getElementById('tab-' + id).classList.add('active');
}
const informationTabs = [...document.querySelectorAll('.pd-product-information .pd-tab')];
informationTabs.forEach((tab, index) => {
  tab.tabIndex = tab.classList.contains('active') ? 0 : -1;
  tab.addEventListener('keydown', event => {
    let next;
    if (event.key === 'ArrowRight') next = (index + 1) % informationTabs.length;
    else if (event.key === 'ArrowLeft') next = (index + informationTabs.length - 1) % informationTabs.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = informationTabs.length - 1;
    else return;
    event.preventDefault();
    informationTabs[next].focus();
    switchTab(informationTabs[next], informationTabs[next].dataset.tab);
  });
});
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
</script>
<script src="{{ asset('js/marketplace.js') }}?v={{ @filemtime(public_path('js/marketplace.js')) ?: 1 }}"></script>
@include('buyer.partials.assistant', ['assistantGuest' => true])
<script defer src="{{ asset('js/product-quantity.js') }}?v={{ filemtime(public_path('js/product-quantity.js')) }}"></script>
</body>
</html>
