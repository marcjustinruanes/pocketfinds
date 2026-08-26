<?php $__env->startSection('title', $product['name']); ?>
<?php $__env->startSection('page-title', 'Product'); ?>
<?php $__env->startSection('page-sub', $product['cat']); ?>

<?php $__env->startSection('content'); ?>
<?php
  $avg     = $product['rating'];
  $reviews = $product['reviews'];
  $counts  = [1=>0,2=>0,3=>0,4=>0,5=>0];
  foreach($reviews as $r) $counts[$r['rating']]++;
  $total   = count($reviews) ?: 1;
?>

<section class="pd-shop-card">
  <a href="<?php echo e(url()->previous()); ?>" class="pd-shop-back" aria-label="Back"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></a>
  <div class="pd-shop-more-av"><?php echo e(strtoupper(substr($product['seller'], 0, 1))); ?></div>
  <div class="pd-shop-card-info">
    <div class="pd-shop-more-name"><?php echo e($product['seller']); ?></div>
    <div class="pd-shop-card-location">
      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      <?php echo e($product['location'] ?: 'Location not provided'); ?>

    </div>
  </div>
  <div class="pd-shop-stats"><span><strong><?php echo e($avg > 0 ? number_format($avg, 1) : '—'); ?></strong> Rating</span><span><strong><?php echo e(count($shopProducts) + 1); ?></strong> Products</span><span><strong>—</strong> Followers</span></div>
  <div class="pd-shop-card-actions">
    <a href="<?php echo e(route('buyer.messages', ['seller' => $product['seller_slug']])); ?>" class="pd-shop-chat"><?php echo $__env->make('buyer.partials.icon', ['name' => 'chat', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Chat with Seller</a>
    <a href="<?php echo e(route('buyer.shop', $product['seller_slug'])); ?>" class="pd-shop-more-link pd-shop-view"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>View Shop</a>
  </div>
</section>

<div class="pd-main-grid">

  
  <?php $hasRealImg = !empty($product['img']); ?>
  <div class="pd-img-col">
    <div class="pd-main-img">
      <?php if($hasRealImg): ?>
        <img src="<?php echo e($product['img']); ?>" style="width:100%;height:100%;object-fit:contain;border-radius:12px">
      <?php else: ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" opacity=".25"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      <?php endif; ?>
    </div>
  </div>

  
  <div class="pd-right-col">

    
    <div class="pd-info-card">
      <div class="pd-crumb-row">
        <span class="pd-crumb"><?php echo e($product['cat']); ?></span>
        <?php if($product['badge']): ?><span class="pd-pill"><?php echo e($product['badge']); ?></span><?php endif; ?>
      </div>
      <div class="pd-title-price-row">
        <h1 class="pd-title"><?php echo e($product['name']); ?></h1>
        <span class="pd-price">₱<?php echo e(number_format($product['price'])); ?></span>
      </div>
      <div class="pd-meta-row">
        <span class="pd-stars-row">
          <?php for($i=1;$i<=5;$i++): ?>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="<?php echo e($i<=round($avg)?'#f59e0b':'none'); ?>" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <?php endfor; ?>
        </span>
        <span class="pd-meta-val"><?php echo e($avg); ?></span>
        <span class="pd-meta-sep">·</span>
        <span class="pd-meta-muted"><?php echo e(number_format($product['sold'])); ?> sold</span>
        <span class="pd-meta-sep">·</span>
        <a class="pd-meta-link" href="#" onclick="event.preventDefault();switchTab(document.querySelector('[data-tab=reviews]'),'reviews')"><?php echo e(count($reviews)); ?> reviews</a>
        <?php if($product['old_price']): ?><span class="pd-old" style="margin-left:auto">₱<?php echo e(number_format($product['old_price'])); ?></span><?php endif; ?>
      </div>
      <div class="pd-actions">
        <a class="pd-btn-chat" href="<?php echo e(route('buyer.messages', ['seller' => $product['seller_slug'], 'product' => $product['id']])); ?>"><?php echo $__env->make('buyer.partials.icon', ['name' => 'chat', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Chat</a>
        <button class="pd-btn-cart" onclick="openCart('<?php echo e(addslashes($product['name'])); ?>',<?php echo e($product['price']); ?>,<?php echo e(json_encode($product['variants']['color'] ?? [])); ?>,<?php echo e(json_encode($product['variants']['size'] ?? [])); ?>,false,this,'<?php echo e($product['id']); ?>','<?php echo e($product['img']); ?>')">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
          Add to Cart
        </button>
        <button class="pd-btn-buy" onclick="openCart('<?php echo e(addslashes($product['name'])); ?>',<?php echo e($product['price']); ?>,<?php echo e(json_encode($product['variants']['color'] ?? [])); ?>,<?php echo e(json_encode($product['variants']['size'] ?? [])); ?>,true,this,'<?php echo e($product['id']); ?>','<?php echo e($product['img']); ?>')">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          Buy Now
        </button>
      </div>
    </div>

    
    <div class="pd-tabs-wrap">
      <div class="pd-tab-bar">
        <button class="pd-tab active" data-tab="options" onclick="switchTab(this,'options')">Options</button>
        <button class="pd-tab"        data-tab="description" onclick="switchTab(this,'description')">Description</button>
        <button class="pd-tab"        data-tab="details" onclick="switchTab(this,'details')">Details</button>
        <button class="pd-tab"        data-tab="reviews" onclick="switchTab(this,'reviews')">Reviews</button>
      </div>

      
      <div class="pd-tab-pane active" id="tab-options">
        <?php if(!empty($product['variations'])): ?>
          <?php $__currentLoopData = $product['variations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="pd-opt-group">
            <div class="pd-opt-label"><?php echo e($variation['name']); ?></div>
            <div class="pd-opt-row">
              <?php $__currentLoopData = $variation['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <button class="pd-opt-btn <?php echo e($loop->first?'active':''); ?>"
                onclick="selectVariant(this)"
                data-stock="<?php echo e($opt['stock']); ?>"
                style="<?php echo e($opt['stock'] == 0 ? 'opacity:.4;cursor:not-allowed' : ''); ?>">
                <?php echo e($opt['value']); ?>

                <?php if($opt['stock'] > 0): ?>
                  <span style="font-size:10px;color:var(--muted);display:block"><?php echo e($opt['stock']); ?> left</span>
                <?php else: ?>
                  <span style="font-size:10px;color:var(--danger);display:block">Out of stock</span>
                <?php endif; ?>
              </button>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php elseif(!empty($product['variants']['color'])): ?>
          <div class="pd-opt-group">
            <div class="pd-opt-label">Color</div>
            <div class="pd-opt-row">
              <?php $__currentLoopData = $product['variants']['color']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <button class="pd-opt-btn <?php echo e($loop->first?'active':''); ?>" onclick="selectVariant(this)"><?php echo e($c); ?></button>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if(!empty($product['variants']['size'])): ?>
        <div class="pd-opt-group">
          <div class="pd-opt-label"><?php echo e(str_contains(implode(',',$product['variants']['size']),'Switch') ? 'Switch Type' : 'Size'); ?></div>
          <div class="pd-opt-row">
            <?php $__currentLoopData = $product['variants']['size']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button class="pd-opt-btn <?php echo e($loop->first?'active':''); ?>" onclick="selectVariant(this)"><?php echo e($s); ?></button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if(empty($product['variations']) && empty($product['variants']['color']) && empty($product['variants']['size'])): ?>
          <?php if(isset($product['stock'])): ?>
          <div style="font-size:13px;color:var(--muted);padding:8px 0">
            Stock: <strong style="color:<?php echo e($product['stock'] > 0 ? 'var(--success)' : 'var(--danger)'); ?>"><?php echo e($product['stock'] > 0 ? $product['stock'] . ' available' : 'Out of stock'); ?></strong>
          </div>
          <?php endif; ?>
        <?php endif; ?>
        <div class="pd-opt-group">
          <div class="pd-opt-label">Quantity</div>
          <div class="pd-qty-row">
            <button class="pd-qty-btn" onclick="changeQty(-1)">−</button>
            <span class="pd-qty-num" id="pdQty">1</span>
            <button class="pd-qty-btn" onclick="changeQty(1)">+</button>
          </div>
        </div>
      </div>

      
      <div class="pd-tab-pane" id="tab-description">
        <p class="pd-desc-text"><?php echo e($product['desc'] ?: 'No description provided.'); ?></p>
      </div>

      
      <div class="pd-tab-pane" id="tab-details">
        <?php if(!empty($product['specs'])): ?>
        <div class="pd-section-label">Specifications</div>
        <div class="pd-specs-grid">
          <?php $__currentLoopData = $product['specs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $spec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="pd-spec-row">
            <span class="pd-spec-key"><?php echo e($spec[0]); ?></span>
            <span class="pd-spec-val"><?php echo e($spec[1]); ?></span>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php else: ?>
        <p class="pd-desc-text">No specification provided.</p>
        <?php endif; ?>
      </div>

      
      <div class="pd-tab-pane" id="tab-reviews">
        
        <div class="pd-rev-filter">
          <div class="pd-rev-chips">
            <button class="pd-rev-chip active" data-star="0" onclick="filterReviews(0)">All</button>
            <?php $__currentLoopData = [5,4,3,2,1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $star): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button class="pd-rev-chip" onclick="filterReviews(<?php echo e($star); ?>)" data-star="<?php echo e($star); ?>">
              <?php echo e($star); ?><svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="margin-left:1px"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
          <div class="pd-rev-count" id="pdRevCount">
            <span class="pd-rev-count-num" id="pdRevCountNum"><?php echo e(count($reviews)); ?></span>
            <span class="pd-rev-count-lbl" id="pdRevCountLbl">reviews</span>
          </div>
        </div>
        <div class="pd-rev-list" id="pdRevList">
          <?php $__empty_1 = true; $__currentLoopData = $reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div class="pd-rev-item <?php echo e($idx>=3?'rev-hidden':''); ?>" data-rating="<?php echo e($r['rating']); ?>">
            <div class="pd-rev-av"><?php echo e(strtoupper(substr($r['name'],0,1))); ?></div>
            <div class="pd-rev-body">
              <div class="pd-rev-header">
                <span class="pd-rev-name"><?php echo e($r['name']); ?></span>
                <div class="pd-rev-stars-sm">
                  <?php for($i=1;$i<=5;$i++): ?>
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="<?php echo e($i<=$r['rating']?'#f59e0b':'none'); ?>" stroke="#f59e0b" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  <?php endfor; ?>
                </div>
                <span class="pd-rev-date"><?php echo e($r['date']); ?></span>
              </div>
              <p class="pd-rev-text"><?php echo e($r['text']); ?></p>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="empty"><h3>No reviews yet</h3></div>
          <?php endif; ?>
          <?php if(count($reviews) > 3): ?>
          <button class="pd-see-more" id="pdSeeMore" onclick="toggleReviews()">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" id="pdSeeIcon"><polyline points="6 9 12 15 18 9"/></svg>
            See all <?php echo e(count($reviews)); ?> reviews
          </button>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </div>

</div>


<?php if(count($shopProducts)): ?>
<div class="pd-shop-more">
  <div class="pd-shop-more-head">
    <div class="pd-shop-more-left">
      <div class="pd-shop-more-av"><?php echo e(strtoupper(substr($product['seller'],0,1))); ?></div>
      <div>
        <div class="pd-shop-more-name"><?php echo e($product['seller']); ?></div>
        <div class="pd-shop-more-sub">More from this shop</div>
      </div>
    </div>
    <a href="<?php echo e(route('buyer.messages', ['seller' => $product['seller_slug']])); ?>" class="pd-shop-chat"><?php echo $__env->make('buyer.partials.icon', ['name' => 'chat', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Chat with Seller</a>
    <a href="<?php echo e(route('buyer.shop', $product['seller_slug'])); ?>" class="pd-shop-more-link">View Shop →</a>
  </div>
  <?php if(count($shopProducts)): ?>
  <div class="pd-shop-more-grid">
    <?php $__currentLoopData = $shopProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="pd-mini-card" onclick="window.location='<?php echo e(route('buyer.product', $sp['id'])); ?>'">
      <div class="pd-mini-img">
        <?php if(is_string($sp['img']) && str_starts_with($sp['img'], '/')): ?>
          <img src="<?php echo e($sp['img']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
        <?php else: ?>
          <?php echo $__env->make('buyer.partials.icon', ['name' => $sp['img'], 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
        <?php if($sp['badge']): ?><span class="pd-mini-badge"><?php echo e($sp['badge']); ?></span><?php endif; ?>
      </div>
      <div class="pd-mini-info">
        <div class="pd-mini-name"><?php echo e($sp['name']); ?></div>
        <div class="pd-mini-price">₱<?php echo e(number_format($sp['price'])); ?></div>
        <div class="pd-mini-rating">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <?php echo e($sp['rating']); ?>

        </div>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if(count($related)): ?>
<div class="pd-related">
  <div class="pd-related-head">
    <span>Similar Products</span>
    <a href="<?php echo e(route('buyer.browse')); ?>?category=<?php echo e(urlencode($product['cat'])); ?>" class="pd-related-more">Browse all in <?php echo e($product['cat']); ?> →</a>
  </div>
  <div class="product-grid product-grid-lg">
    <?php $__currentLoopData = $related; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="product-card" onclick="window.location='<?php echo e(route('buyer.product', $rp['id'])); ?>'">
      <div class="product-img">
        <?php if(is_string($rp['img']) && str_starts_with($rp['img'], '/')): ?>
          <img src="<?php echo e($rp['img']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px">
        <?php else: ?>
          <?php echo $__env->make('buyer.partials.icon', ['name' => $rp['img'], 'size' => 36], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
        <?php if($rp['badge']): ?><span class="product-badge"><?php echo e($rp['badge']); ?></span><?php endif; ?>
      </div>
      <div class="product-info">
        <div class="product-name"><?php echo e($rp['name']); ?></div>
        <a class="product-seller" href="<?php echo e(route('buyer.shop', $rp['seller_slug'])); ?>" onclick="event.stopPropagation()">by <?php echo e($rp['seller']); ?></a>
        <div class="product-price">₱<?php echo e(number_format($rp['price'])); ?></div>
        <div class="product-rating">
          <span class="star-ic"><?php echo $__env->make('buyer.partials.icon',['name'=>'star','size'=>11], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
          <?php echo e($rp['rating']); ?> · <?php echo e(number_format($rp['sold'])); ?> sold
        </div>
        <div class="pc-actions">
          <button class="pc-act pc-act-cart" onclick="event.stopPropagation();openCart('<?php echo e(addslashes($rp['name'])); ?>',<?php echo e($rp['price']); ?>,<?php echo e(json_encode($rp['variants']['color'])); ?>,<?php echo e(json_encode($rp['variants']['size'])); ?>,false,this,'<?php echo e($rp['id']); ?>','<?php echo e($rp['img']); ?>')" title="Add to Cart">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
          </button>
          <button class="pc-act pc-act-buy" onclick="event.stopPropagation();openCart('<?php echo e(addslashes($rp['name'])); ?>',<?php echo e($rp['price']); ?>,<?php echo e(json_encode($rp['variants']['color'])); ?>,<?php echo e(json_encode($rp['variants']['size'])); ?>,true,this,'<?php echo e($rp['id']); ?>','<?php echo e($rp['img']); ?>')" title="Buy Now">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>
<?php endif; ?>

<script>
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
    : '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg> See all <?php echo e(count($reviews)); ?> reviews';
}
const revCounts = <?php echo json_encode($counts, 15, 512) ?>;
const revTotal  = <?php echo e(count($reviews)); ?>;
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\buyer\product.blade.php ENDPATH**/ ?>