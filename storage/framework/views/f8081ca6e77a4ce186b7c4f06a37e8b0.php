<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>
    <?php echo $__env->make('buyer.partials.icon', ['name' => 'menu', 'size' => 20], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </button>
  <div class="page-heading">
    <h1><?php echo $__env->yieldContent('page-title', 'Dashboard'); ?></h1>
    <p><?php echo $__env->yieldContent('page-sub', ''); ?></p>
  </div>
  <div class="topbar-search">
    <span class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'search', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
    <input type="text" placeholder="Search products…">
  </div>
  <div class="topbar-actions">
    <a href="<?php echo e(route('buyer.cart')); ?>" class="icon-btn" id="cartIconBtn" title="Cart">
      <?php echo $__env->make('buyer.partials.icon', ['name' => 'cart', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php ($cartCount = array_sum(array_column(session('cart', []), 'qty'))); ?>
      <span id="cartBadge" style="<?php echo e($cartCount ? '' : 'display:none;'); ?>position:absolute;top:-5px;right:-5px;min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:var(--pink);color:#fff;font-size:10px;font-weight:700;line-height:16px;text-align:center"><?php echo e($cartCount ?: ''); ?></span>
    </a>
    <a href="<?php echo e(route('buyer.messages')); ?>" class="icon-btn" title="Messages">
      <?php echo $__env->make('buyer.partials.icon', ['name' => 'mail', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </a>
    <a href="<?php echo e(route('buyer.account')); ?>" class="topbar-avatar">
      <?php echo e(strtoupper(substr(auth()->user()->first_name, 0, 1))); ?>

    </a>
  </div>
</header>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\buyer\partials\topbar.blade.php ENDPATH**/ ?>