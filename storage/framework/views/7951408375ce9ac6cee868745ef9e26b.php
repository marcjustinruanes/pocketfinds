<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">
      <?php echo $__env->make('seller.partials.icon', ['name' => 'bag', 'size' => 18], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Seller Portal</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Overview</div>
    <a href="<?php echo e(route('seller.dashboard')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.dashboard') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'dashboard', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Dashboard
    </a>

    <div class="nav-label">Orders</div>
    <a href="<?php echo e(route('seller.notifications')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.notifications') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'bell', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Notifications
      <span class="badge">3</span>
    </a>
    <a href="<?php echo e(route('seller.orders')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.orders') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'orders', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Order Management
    </a>
    <a href="<?php echo e(route('seller.prepare')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.prepare') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'package', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Prepare Orders
    </a>
    <a href="<?php echo e(route('seller.shipments')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.shipments') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'truck', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Shipments
    </a>
    <a href="<?php echo e(route('seller.deliveries')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.deliveries') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'check-circle', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Confirm Delivery
    </a>

    <div class="nav-label">Store</div>
    <a href="<?php echo e(route('seller.inventory')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.inventory') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'inventory', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Inventory
    </a>
    <a href="<?php echo e(route('seller.feedback')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.feedback') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'star', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Customer Feedback
    </a>
    <a href="<?php echo e(route('seller.reports')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.reports') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'chart', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Reports
    </a>

    <div class="nav-label">Account</div>
    <a href="<?php echo e(route('seller.messages')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.messages') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'mail', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> Messages
    </a>
    <a href="<?php echo e(route('seller.account')); ?>" class="nav-item <?php echo e(request()->routeIs('seller.account') ? 'active' : ''); ?>">
      <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'user', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar"><?php echo e(strtoupper(substr(auth()->user()->given_names, 0, 1))); ?></div>
      <div class="who">
        <strong><?php echo e(auth()->user()->given_names); ?> <?php echo e(auth()->user()->last_name); ?></strong>
        <span>Seller</span>
      </div>
    </div>
    <button class="logout-btn" data-logout>
      <?php echo $__env->make('seller.partials.icon', ['name' => 'logout', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Sign out
    </button>
  </div>
</nav>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\partials\sidebar.blade.php ENDPATH**/ ?>