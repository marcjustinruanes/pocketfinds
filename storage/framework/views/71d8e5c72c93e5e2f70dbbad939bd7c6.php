<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
    </div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Buyer Portal</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Shop</div>
    <a href="<?php echo e(route('buyer.dashboard')); ?>" class="nav-item <?php echo e(request()->routeIs('buyer.dashboard') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'dashboard', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Dashboard
    </a>
    <a href="<?php echo e(route('buyer.browse')); ?>" class="nav-item <?php echo e(request()->routeIs('buyer.browse') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'bag', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Browse Products
    </a>
    <a href="<?php echo e(route('buyer.cart')); ?>" class="nav-item <?php echo e(request()->routeIs('buyer.cart') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'cart', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> My Cart
    </a>

    <div class="nav-label">Orders</div>
    <a href="<?php echo e(route('buyer.orders')); ?>?tab=to_ship" class="nav-item <?php echo e(request()->routeIs('buyer.orders') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'package', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> My Orders
    </a>

    <div class="nav-label">Account</div>
    <a href="<?php echo e(route('buyer.messages')); ?>" class="nav-item <?php echo e(request()->routeIs('buyer.messages') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'mail', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Messages
    </a>
    <a href="<?php echo e(route('buyer.account')); ?>" class="nav-item <?php echo e(request()->routeIs('buyer.account') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'user', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar"><?php echo e(strtoupper(substr(auth()->user()->first_name, 0, 1))); ?></div>
      <div class="who">
        <strong><?php echo e(auth()->user()->first_name); ?> <?php echo e(auth()->user()->last_name); ?></strong>
        <span>Buyer</span>
      </div>
    </div>
    <button class="logout-btn" data-logout>
      <?php echo $__env->make('buyer.partials.icon', ['name' => 'logout', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Sign out
    </button>
  </div>
</nav>
<?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/buyer/partials/sidebar.blade.php ENDPATH**/ ?>