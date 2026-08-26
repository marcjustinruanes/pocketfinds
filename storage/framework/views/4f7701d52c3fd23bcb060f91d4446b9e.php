<?php $__env->startSection('title', 'Delivery History'); ?>
<?php $__env->startSection('page-title', 'Delivery History'); ?>
<?php $__env->startSection('page-sub', 'Completed delivery records'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><h2>Completed Deliveries</h2><span class="stamp stamp-approved"><?php echo e($shipments->count()); ?> records</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Courier</th>
          <th>Status</th>
          <th>Picked Up</th>
          <th>Out for Delivery</th>
          <th>Delivered At</th>
        </tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $shipments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="mono"><?php echo e($s->tracking_number ?? substr($s->id, 0, 8)); ?></td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm"><?php echo e(strtoupper(substr(optional(optional($s->order)->buyer)->first_name ?? '?', 0, 1))); ?></div>
              <div>
                <strong><?php echo e(optional(optional($s->order)->buyer)->first_name); ?> <?php echo e(optional(optional($s->order)->buyer)->last_name); ?></strong>
              </div>
            </div>
          </td>
          <td><?php echo e(optional($s->courier)->first_name ? optional($s->courier)->first_name . ' ' . optional($s->courier)->last_name : '—'); ?></td>
          <td><span class="stamp stamp-<?php echo e($s->shipping_status); ?>"><?php echo e(ucfirst(str_replace('_',' ',$s->shipping_status))); ?></span></td>
          <td class="mono"><?php echo e($s->picked_up_at ? \Carbon\Carbon::parse($s->picked_up_at)->format('M d, Y H:i') : '—'); ?></td>
          <td class="mono"><?php echo e($s->out_for_delivery_at ? \Carbon\Carbon::parse($s->out_for_delivery_at)->format('M d, Y H:i') : '—'); ?></td>
          <td class="mono"><?php echo e($s->delivered_at ? \Carbon\Carbon::parse($s->delivered_at)->format('M d, Y H:i') : '—'); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="7"><div class="empty"><h3>No completed deliveries yet</h3></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\history.blade.php ENDPATH**/ ?>