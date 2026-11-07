@extends('buyer.layout')
@section('title', $product['name'])
@section('hide-page-heading', true)
@section('body-class', 'product-detail-page')
@section('content')
<link rel="stylesheet" href="{{ asset('css/product-detail.css') }}?v={{ filemtime(public_path('css/product-detail.css')) }}">
<link rel="stylesheet" href="{{ asset('css/product-options.css') }}?v={{ filemtime(public_path('css/product-options.css')) }}">
@php
  $combinationGroup = \App\Support\ProductVariationSet::combination($product['variations'] ?? []);
  $displayVariations = \App\Support\ProductVariationSet::displayGroups($product['variations'] ?? []);
  $avg     = $product['rating'];
  $reviews = $product['reviews'];
  $counts  = [1=>0,2=>0,3=>0,4=>0,5=>0];
  foreach($reviews as $r) $counts[$r['rating']]++;
  $total   = count($reviews) ?: 1;
@endphp

@include('partials.product-detail', ['buyerMode' => true])
<form id="pdWishlistForm" method="POST" action="{{ route('buyer.commerce.wishlist', $product['id']) }}" hidden>@csrf<input type="hidden" name="saved" value="{{ ($isSaved ?? false) ? 0 : 1 }}"></form>
<script>
document.getElementById('pdFollowShop')?.addEventListener('click', async function () {
 this.disabled = true;
 try {
  const response = await fetch('/buyer/shop/' + encodeURIComponent(this.dataset.slug) + '/follow', { method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content} });
  const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Unable to follow this shop.');
  this.setAttribute('aria-pressed', String(data.following)); this.title = data.following ? 'Unfollow shop' : 'Follow shop'; this.setAttribute('aria-label', this.title);
  showToast(data.following ? 'Following shop.' : 'Shop unfollowed.');
 } catch (error) { showToast(error.message, 'error'); } finally { this.disabled = false; }
});
</script>
{{-- Image lightbox --}}
<div id="imgViewer" style="display:none;position:fixed;inset:0;z-index:400;background:rgba(0,0,0,.85);align-items:center;justify-content:center;cursor:zoom-out" onclick="closeImgViewer()">
  <img id="imgViewerImg" src="" style="max-width:92vw;max-height:92vh;border-radius:10px;object-fit:contain;box-shadow:0 18px 60px rgba(0,0,0,.5);display:block">
  <button onclick="closeImgViewer()" style="position:absolute;top:18px;right:22px;width:38px;height:38px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:rgba(0,0,0,.4);color:#fff;font-size:22px;line-height:1;cursor:pointer">×</button>
</div>

<div class="modal-overlay" id="productReportModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-head">
      <div><h3>Report Listing</h3><p>Send this report to the admin team.</p></div>
    </div>
    <form id="productReportForm" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="chat-report-context">
          <div class="field-label">Reported listing</div>
          <div class="field-value">{{ $product['name'] }}</div>
        </div>
        <div class="form-row">
          <label for="prReason">Why are you reporting this listing?</label>
          <select id="prReason" name="reason" required>
            <option value="">Choose a reason</option>
            <option>Counterfeit or fake item</option>
            <option>Misleading description or photos</option>
            <option>Prohibited or restricted item</option>
            <option>Scam or fraud</option>
            <option>Other</option>
          </select>
        </div>
        <div class="form-row">
          <label for="prDescription">More details</label>
          <textarea id="prDescription" name="description" rows="4" maxlength="3000" placeholder="Tell admin what happened..."></textarea>
        </div>
        <div class="form-row">
          <label for="prEvidence">Image or video evidence (optional)</label>
          <input id="prEvidence" name="evidence" type="file" accept="image/*,video/*">
        </div>
        <div id="prError" style="display:none;color:var(--danger);font-size:12px;margin-top:8px"></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger" id="prSubmit">Send Report</button>
      </div>
    </form>
  </div>
</div>

@include('partials.product-combination-data')
<script>
function selectVariant(btn) {
  if (window.productCombinations) return window.productCombinations.select(btn);
  if (btn.disabled) return;
  // Options carrying data-exclusive represent alternative, self-contained
  // SKUs (each with its own price/stock/photo) rather than combinable
  // attributes like Color+Size — only one such option may be active across
  // the whole tab at a time. Legacy Color/Size rows stay independent.
  const scope = btn.dataset.exclusive === 'true'
    ? document.getElementById('tab-options')
    : btn.closest('.pd-opt-row');
  scope.querySelectorAll('.pd-opt-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  updatePurchaseAvailability(parseInt(btn.dataset.stock || '0', 10));
  if (btn.dataset.image) {
    showProductImage(btn.dataset.image);
    syncThumbForImage(btn.dataset.image);
  }
  updatePriceDisplay();
}

function updatePurchaseAvailability(stock) {
  if (@json($product['on_vacation']??false)) { ['pdAddCartBtn'].forEach(id => { const button = document.getElementById(id); if (button) button.disabled = true; }); return; }
  const cartButton = document.getElementById('pdAddCartBtn');
  const unavailable = stock <= 0;
  if (cartButton) {
    cartButton.disabled = unavailable;
    cartButton.textContent = unavailable ? ' Out of Stock' : ' Add to Cart';
  }
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

/** Stock for whatever's currently selected — the one specific variation option, or the plain product. */
function currentAvailableStock() {
  const priceEl = document.getElementById('pdPrice');
  const active = selectedPurchaseOption();
  const stock = active ? active.dataset.stock : (document.getElementById('pdSelectedStock') ? 0 : priceEl?.dataset.baseStock);
  return Math.max(0, parseInt(stock ?? '0', 10) || 0);
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

function openChatWithSelectedVariant(link) {
  let url = link.getAttribute('href');
  const active = selectedPurchaseOption();
  if (active) {
    const params = new URLSearchParams({ variation_group: active.dataset.group, variation_value: active.dataset.value });
    url += (url.includes('?') ? '&' : '?') + params.toString();
  }
  if (typeof openMessagesModal === 'function') {
    openMessagesModal(url);
  } else {
    window.location = url;
  }
}

function directAddToCart(btn) {
  if (@json($product['on_vacation']??false)) return;
  const active = window.productCombinations ? selectedPurchaseOption() : document.querySelector('#tab-options .pd-opt-btn.active');
  if (document.getElementById('pdSelectedStock') && !active) {
    showToast('Select an available option first.', 'error');
    return;
  }
  if (active && active.disabled) {
    showToast('This option is out of stock.', 'error');
    return;
  }

  const available = currentAvailableStock();
  if (qty > available) {
    showToast(available > 0 ? `Only ${available} item(s) available.` : 'This item is out of stock.', 'error');
    return;
  }

  const payload = { product_id: btn.dataset.productId, qty };
  if (active && active.dataset.exclusive === 'true') {
    payload.variation_group = active.dataset.group;
    payload.variation_value = active.dataset.value;
  } else {
    const colorBtn = document.querySelector('#tab-options [data-group="Color"].pd-opt-btn.active');
    const sizeBtn  = document.querySelector('#tab-options [data-group="Size"].pd-opt-btn.active');
    if (colorBtn) payload.color = colorBtn.dataset.value;
    if (sizeBtn)  payload.size  = sizeBtn.dataset.value;
  }

  fetch('/buyer/cart/add', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
    },
    body: JSON.stringify(payload)
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) throw new Error(data.message || 'Unable to add this item to your cart.');
      return data;
    })
    .then(data => {
        const badge = document.getElementById('cartBadge');
        if (badge) { badge.textContent = data.count; badge.style.display = ''; }
        flyToCart(btn);
    })
    .catch(error => {
      showToast(error.message || 'Unable to add this item to your cart.', 'error');
    });
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

document.getElementById('productReportForm').addEventListener('submit', async event => {
  event.preventDefault();
  const button = document.getElementById('prSubmit');
  button.disabled = true;
  button.textContent = 'Sending...';
  const fd = new FormData(event.target);
  fd.append('product_id', '{{ $product['id'] }}');
  try {
    const response = await fetch('{{ route('buyer.product.report') }}', {
      method: 'POST',
      body: fd,
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' }
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Report could not be sent.');
    document.getElementById('productReportModal').classList.remove('open');
    event.target.reset();
    document.getElementById('prError').style.display = 'none';
    showToast('Report sent to admin.', 'error');
  } catch (error) {
    const errorBox = document.getElementById('prError');
    errorBox.textContent = error.message || 'Report could not be sent.';
    errorBox.style.display = 'block';
  } finally {
    button.disabled = false;
    button.textContent = 'Send Report';
  }
});
</script>
<script defer src="{{ asset('js/product-quantity.js') }}?v={{ filemtime(public_path('js/product-quantity.js')) }}"></script>
@endsection
