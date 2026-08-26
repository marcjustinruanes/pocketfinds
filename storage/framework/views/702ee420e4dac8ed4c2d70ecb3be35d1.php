<?php $__env->startSection('title', 'Reports'); ?>
<?php $__env->startSection('page-title', 'Reports'); ?>
<?php $__env->startSection('page-sub', 'Delivery performance overview'); ?>

<?php $__env->startSection('content'); ?>
<div class="kpi-grid">
  <div class="kpi"><div class="label">Total Shipments</div><div class="value"><?php echo e($total); ?></div></div>
  <div class="kpi"><div class="label">Delivered</div><div class="value"><?php echo e($completed); ?></div><div class="delta up">Completed</div></div>
  <div class="kpi"><div class="label">Cancelled</div><div class="value"><?php echo e($cancelled); ?></div><div class="delta down"></div></div>
  <div class="kpi"><div class="label">Failed</div><div class="value"><?php echo e($failed); ?></div><div class="delta down"></div></div>
</div>
<div class="card">
  <div class="card-head"><h2>Summary</h2></div>
  <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
    <div style="display:flex;justify-content:space-between;font-size:13px"><span>Active Couriers</span><span class="mono"><?php echo e($couriers); ?></span></div>
    <div style="display:flex;justify-content:space-between;font-size:13px"><span>Success Rate</span><span class="mono"><?php echo e($total > 0 ? round($completed / $total * 100, 1) : 0); ?>%</span></div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/logistics/reports.blade.php ENDPATH**/ ?>