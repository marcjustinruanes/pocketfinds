<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>
    <?php echo $__env->make('seller.partials.icon', ['name' => 'menu', 'size' => 20], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </button>
  <div class="page-heading">
    <h1><?php echo $__env->yieldContent('page-title', 'Dashboard'); ?></h1>
    <p><?php echo $__env->yieldContent('page-sub', ''); ?></p>
  </div>
  <div class="topbar-search">
    <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'search', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
    <input type="text" placeholder="Search orders, products…">
  </div>
  <div class="topbar-actions">
    <a href="<?php echo e(route('seller.notifications')); ?>" class="icon-btn" title="Notifications">
      <?php echo $__env->make('seller.partials.icon', ['name' => 'bell', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <span class="dot"></span>
    </a>
    <?php ($hasUnreadMessages = \App\Models\Message::where('receiver_id', auth()->id())->where('read', false)->exists()); ?>
    <a href="<?php echo e(route('seller.messages')); ?>" class="icon-btn" title="Messages">
      <?php echo $__env->make('seller.partials.icon', ['name' => 'mail', 'size' => 16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <span style="<?php echo e($hasUnreadMessages ? '' : 'display:none;'); ?>position:absolute;top:7px;right:7px;width:7px;height:7px;border-radius:50%;background:var(--pink);border:1px solid #fff"></span>
    </a>
    <a href="<?php echo e(route('seller.account')); ?>" class="topbar-avatar">
      <?php echo e(strtoupper(substr(auth()->user()->given_names, 0, 1))); ?>

    </a>
  </div>
</header>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/seller/partials/topbar.blade.php ENDPATH**/ ?>