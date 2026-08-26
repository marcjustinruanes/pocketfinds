<?php $__env->startSection('title', 'Issues'); ?>
<?php $__env->startSection('page-title', 'Issues'); ?>
<?php $__env->startSection('page-sub', 'Failed and cancelled shipments'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><h2>Problem Shipments</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $shipments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="mono">#<?php echo e($s->id); ?></td>
          <td><?php echo e(optional(optional($s->order)->buyer)->first_name); ?> <?php echo e(optional(optional($s->order)->buyer)->last_name); ?></td>
          <td><?php echo e(optional($s->courier)->first_name); ?> <?php echo e(optional($s->courier)->last_name ?? '—'); ?></td>
          <td><span class="stamp stamp-<?php echo e($s->shipping_status); ?>"><?php echo e(ucfirst($s->shipping_status)); ?></span></td>
          <td class="mono"><?php echo e($s->created_at?->format('Y-m-d')); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="5"><div class="empty"><h3>No issues found</h3></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/logistics/issues.blade.php ENDPATH**/ ?>