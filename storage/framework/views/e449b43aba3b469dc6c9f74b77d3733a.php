<?php $__env->startSection('title', 'Delivery History'); ?>
<?php $__env->startSection('page-title', 'Delivery History'); ?>
<?php $__env->startSection('page-sub', 'Completed deliveries'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><h2>Delivered Shipments</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Courier</th><th>Delivered At</th></tr></thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $shipments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="mono">#<?php echo e($s->id); ?></td>
          <td><?php echo e(optional(optional($s->order)->buyer)->first_name); ?> <?php echo e(optional(optional($s->order)->buyer)->last_name); ?></td>
          <td><?php echo e(optional($s->courier)->first_name); ?> <?php echo e(optional($s->courier)->last_name ?? '—'); ?></td>
          <td class="mono"><?php echo e($s->delivered_at ? \Carbon\Carbon::parse($s->delivered_at)->format('Y-m-d') : '—'); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="4"><div class="empty"><h3>No deliveries yet</h3></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/logistics/history.blade.php ENDPATH**/ ?>