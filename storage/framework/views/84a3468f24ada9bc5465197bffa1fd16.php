<?php $__env->startSection('title', 'My Orders'); ?>
<?php $__env->startSection('page-title', 'My Orders'); ?>
<?php $__env->startSection('page-sub', 'Track and manage your orders'); ?>

<?php $__env->startSection('content'); ?>
<?php $tab = request('tab', 'to_ship'); ?>

<div class="tabs">
  <?php
  $orderTabs = [
    ['to_ship',          'package', 'To Ship'],
    ['in_transit',       'truck',   'In Transit'],
    ['out_for_delivery', 'bike',    'Out for Delivery'],
    ['completed',        'check',   'Completed'],
    ['cancelled',        'x',       'Cancelled'],
  ];
  ?>
  <?php $__currentLoopData = $orderTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$key, $icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <a href="<?php echo e(route('buyer.orders')); ?>?tab=<?php echo e($key); ?>" class="tab <?php echo e($tab === $key ? 'active' : ''); ?>">
    <span style="display:inline-flex;align-items:center;gap:5px">
      <?php echo $__env->make('buyer.partials.icon', ['name' => $icon, 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php echo e($label); ?>

    </span>
  </a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="card">
  <div class="card-pad">
    <div class="empty">
      <div class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'package', 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
      <h3>No orders yet</h3>
      <p>Orders with status "<?php echo e(str_replace('_', ' ', ucfirst($tab))); ?>" will appear here.</p>
      <?php if($tab === 'to_ship'): ?>
      <a href="<?php echo e(route('buyer.browse')); ?>" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\buyer\orders.blade.php ENDPATH**/ ?>