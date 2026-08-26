<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Marketplace — PocketFinds</title>
<link rel="stylesheet" href="<?php echo e(asset('css/marketplace.css')); ?>">
</head>
<body class="marketplace">
<div class="market-top"><div class="container"><div>Welcome to PocketFinds Marketplace</div><div class="top-links"><span>Help Centre</span><span>Sell on PocketFinds</span><span>Download App</span></div></div></div>
<header class="market-header"><div class="container header-main">
<a class="logo" href="<?php echo e(url('/')); ?>"><span class="logo-mark"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span><span>PocketFinds</span></a>
<div class="search"><input data-search type="search" placeholder="Search for products, brands and categories"><button type="button"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button></div>
<div class="header-actions">
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg><span>Wishlist</span></button>
<button class="icon-action" type="button" data-protected><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg><span>Cart</span></button>
<a class="signin" href="<?php echo e(url('/login')); ?>">Sign In</a><a class="register" href="<?php echo e(url('/register/type')); ?>">Register</a>
</div>
</div></header>

<main class="container">
<section class="hero"><div class="hero-grid"><div class="hero-main"><div class="hero-copy"><div class="eyebrow">Guest shopping</div><h1>Discover products you'll love.</h1><p>Browse products, explore categories, compare deals, and find something worth adding to your cart.</p><a class="primary" href="#products">Explore products →</a></div></div><div class="hero-side"><div class="promo"><strong>New Arrivals</strong><span>Fresh products from local sellers.</span></div><div class="promo dark"><strong>Real Products</strong><span>Discover authentic items from verified sellers.</span></div></div></div></section>

<div class="guest-notice"><div><strong>You're browsing as a guest.</strong><span>Sign in to save items, checkout, and track orders.</span></div><a class="notice-link" href="<?php echo e(url('/login')); ?>">Sign in now →</a></div>

<section class="section"><div class="section-head"><h2 class="section-title">Browse Categories</h2><a class="see-all" href="#">See All →</a></div><div class="category-grid" id="browseCategories"><p class="cat-loading">Loading…</p></div></section>

<section class="section" id="products">
  <div class="section-head"><h2 class="section-title">All Products</h2></div>
  <div class="deal-strip"><div class="deal-grid">
    <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <article class="product" data-product="<?php echo e(strtolower($p['name'])); ?>" onclick="window.location='<?php echo e(route('guest.product', $p['id'])); ?>'">
      <div class="product-img">
        <?php if($p['img']): ?>
          <img src="<?php echo e($p['img']); ?>" alt="<?php echo e($p['name']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
        <?php else: ?>
          <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        <?php endif; ?>
      </div>
      <div class="product-body">
        <div class="product-name"><?php echo e($p['name']); ?></div>
        <?php if(!empty($p['location'])): ?>
        <div style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:#888;margin:3px 0">
          <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?php echo e($p['location']); ?>

        </div>
        <?php endif; ?>
        <div style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:#888;margin-bottom:4px">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="#f59e0b" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <?php echo e($p['rating'] > 0 ? number_format($p['rating'],1) : 'New'); ?>

        </div>
        <div class="price">₱<?php echo e(number_format($p['price'])); ?></div>
        <div class="product-actions">
          <button class="btn-cart" type="button" data-protected>
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg> Cart
          </button>
          <button class="btn-buy" type="button" data-protected>Buy Now</button>
        </div>
      </div>
    </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#888">
      <p style="font-size:14px">No products available yet. Check back soon!</p>
    </div>
    <?php endif; ?>
  </div></div>
</section>
</main>

<footer class="footer"><div class="container"><div class="footer-grid"><div><h3>PocketFinds Marketplace</h3><p>A simple marketplace experience for discovering products from local sellers.</p></div><div><h3>Customer Service</h3><a href="#">Help Centre</a><a href="#">Contact Us</a><a href="#">Returns</a></div><div><h3>About</h3><a href="#">About Us</a><a href="#">Careers</a><a href="#">Privacy</a></div><div><h3>Account</h3><a href="<?php echo e(url('/login')); ?>">Sign In</a><a href="<?php echo e(url('/register/type')); ?>">Register</a><a href="#">Seller Centre</a></div></div><div class="footer-bottom">© <?php echo e(date('Y')); ?> PocketFinds. All rights reserved.</div></div></footer>
<?php echo $__env->make('guest.auth-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script src="<?php echo e(asset('js/marketplace.js')); ?>"></script>
</body></html>
<?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/guest/home.blade.php ENDPATH**/ ?>