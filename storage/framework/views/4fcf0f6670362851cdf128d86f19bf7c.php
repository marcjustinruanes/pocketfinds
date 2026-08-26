<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('page-title', 'Dashboard'); ?>
<?php $__env->startSection('page-sub', 'Welcome back, <?php echo e(auth()->user()->first_name); ?>'); ?>

<?php $__env->startSection('content'); ?>
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="kpi"><div class="label">Total Shipments</div><div class="value"><?php echo e($total); ?></div></div>
  <div class="kpi"><div class="label">Pending</div><div class="value"><?php echo e($pending); ?></div><div class="delta <?php echo e($pending > 0 ? 'down' : 'up'); ?>">Awaiting review</div></div>
  <div class="kpi"><div class="label">For Verification</div><div class="value"><?php echo e($forVerify); ?></div><div class="delta <?php echo e($forVerify > 0 ? 'down' : 'up'); ?>">Needs checking</div></div>
  <div class="kpi"><div class="label">Available</div><div class="value"><?php echo e($available); ?></div><div class="delta up">Ready for pickup</div></div>
</div>
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-top:0">
  <div class="kpi"><div class="label">Active</div><div class="value"><?php echo e($active); ?></div><div class="delta up">In transit</div></div>
  <div class="kpi"><div class="label">Completed</div><div class="value"><?php echo e($completed); ?></div><div class="delta up">Delivered</div></div>
  <div class="kpi"><div class="label">Cancelled / Failed</div><div class="value"><?php echo e($cancelled); ?></div><div class="delta <?php echo e($cancelled > 0 ? 'down' : 'up'); ?>"></div></div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Recent Shipments</h2>
    <a href="<?php echo e(route('logistics.monitor')); ?>" class="btn btn-sm btn-outline">View all</a>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Tracking #</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="mono"><?php echo e($s->tracking_number ?? substr($s->id, 0, 8)); ?></td>
          <td><?php echo e(optional(optional($s->order)->buyer)->first_name); ?> <?php echo e(optional(optional($s->order)->buyer)->last_name); ?></td>
          <td><?php echo e(optional($s->courier)->first_name ? optional($s->courier)->first_name . ' ' . optional($s->courier)->last_name : '—'); ?></td>
          <td><span class="stamp stamp-<?php echo e($s->shipping_status); ?>"><?php echo e(ucfirst(str_replace('_', ' ', $s->shipping_status))); ?></span></td>
          <td class="mono"><?php echo e($s->created_at?->format('M d, Y')); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="5"><div class="empty"><h3>No shipments yet</h3></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\dashboard.blade.php ENDPATH**/ ?>