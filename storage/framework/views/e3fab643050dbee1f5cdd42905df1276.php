<?php $__env->startSection('title', 'Live Monitor'); ?>
<?php $__env->startSection('page-title', 'Live Monitor'); ?>
<?php $__env->startSection('page-sub', 'Active deliveries in progress'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><h2>Active Shipments</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Update Status</th></tr></thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $shipments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="mono">#<?php echo e($s->id); ?></td>
          <td><?php echo e(optional(optional($s->order)->buyer)->first_name); ?> <?php echo e(optional(optional($s->order)->buyer)->last_name); ?></td>
          <td><?php echo e(optional($s->courier)->first_name); ?> <?php echo e(optional($s->courier)->last_name ?? '—'); ?></td>
          <td><span class="stamp stamp-<?php echo e($s->shipping_status); ?>"><?php echo e(ucfirst(str_replace('_',' ',$s->shipping_status))); ?></span></td>
          <td>
            <form method="POST" action="<?php echo e(route('logistics.status.update', $s->id)); ?>" style="display:flex;gap:8px">
              <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
              <select name="status" class="select">
                <?php $__currentLoopData = ['assigned','accepted','picked_up','out_for_delivery','delivered','cancelled','failed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($st); ?>" <?php echo e($s->shipping_status === $st ? 'selected' : ''); ?>><?php echo e(ucfirst(str_replace('_',' ',$st))); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
              <button class="btn btn-sm btn-primary">Update</button>
            </form>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="5"><div class="empty"><h3>No active deliveries</h3></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/logistics/monitor.blade.php ENDPATH**/ ?>