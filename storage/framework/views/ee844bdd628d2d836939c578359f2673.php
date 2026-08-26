<?php $__env->startSection('title', 'Reports'); ?>
<?php $__env->startSection('page-title', 'Reports'); ?>
<?php $__env->startSection('page-sub', 'Delivery performance and statistics'); ?>

<?php $__env->startSection('content'); ?>
<div class="kpi-grid">
  <div class="kpi"><div class="label">Total Shipments</div><div class="value"><?php echo e($total); ?></div></div>
  <div class="kpi"><div class="label">Completed</div><div class="value"><?php echo e($completed); ?></div><div class="delta up"><?php echo e($total > 0 ? round($completed/$total*100,1) : 0); ?>% success rate</div></div>
  <div class="kpi"><div class="label">Cancelled</div><div class="value"><?php echo e($cancelled); ?></div><div class="delta <?php echo e($cancelled > 0 ? 'down' : 'up'); ?>"></div></div>
  <div class="kpi"><div class="label">Failed</div><div class="value"><?php echo e($failed); ?></div><div class="delta <?php echo e($failed > 0 ? 'down' : 'up'); ?>"></div></div>
</div>

<div class="dash-grid">
  <div class="card">
    <div class="card-head"><h2>Courier Performance</h2><span style="font-size:12px;color:var(--muted)">Top 10 by deliveries</span></div>
    <div class="table-wrap">
      <table class="dtable">
        <thead><tr><th>#</th><th>Courier</th><th>Deliveries</th></tr></thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $courierStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td class="mono"><?php echo e($loop->iteration); ?></td>
            <td>
              <div class="cell-user">
                <div class="avatar-sm"><?php echo e(strtoupper(substr($c->first_name,0,1))); ?></div>
                <div><strong><?php echo e($c->first_name); ?> <?php echo e($c->last_name); ?></strong></div>
              </div>
            </td>
            <td class="mono"><?php echo e($c->delivered_count); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="3"><div class="empty"><h3>No courier data yet</h3></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="stack">
    <div class="card card-pad">
      <div class="field-label">Active Couriers</div>
      <div class="value" style="font-family:var(--font-display);font-size:28px;font-weight:600;margin:6px 0"><?php echo e($couriers); ?></div>
    </div>
    <div class="card card-pad">
      <div class="field-label">Success Rate</div>
      <div class="value" style="font-family:var(--font-display);font-size:28px;font-weight:600;margin:6px 0"><?php echo e($total > 0 ? round($completed/$total*100,1) : 0); ?>%</div>
    </div>
    <div class="card card-pad">
      <div class="field-label">Issue Rate</div>
      <div class="value" style="font-family:var(--font-display);font-size:28px;font-weight:600;margin:6px 0"><?php echo e($total > 0 ? round(($cancelled+$failed)/$total*100,1) : 0); ?>%</div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\reports.blade.php ENDPATH**/ ?>