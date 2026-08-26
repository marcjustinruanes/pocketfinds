<?php $__env->startSection('title', 'Monitor Deliveries'); ?>
<?php $__env->startSection('page-title', 'Monitor Deliveries'); ?>
<?php $__env->startSection('page-sub', 'Track and manage ongoing delivery statuses'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><h2>Active Deliveries</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Courier</th>
          <th>Current Status</th>
          <th>Last Update</th>
          <th>Update Status</th>
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
          <td class="mono" style="font-size:11.5px"><?php echo e($s->updated_at?->format('M d, Y H:i') ?? '—'); ?></td>
          <td>
            <form method="POST" action="<?php echo e(route('logistics.status.update', $s->id)); ?>" style="display:flex;gap:8px">
              <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
              <select name="status" class="select">
                <?php $__currentLoopData = ['pending','for_verification','verified','available','accepted','picked_up','out_for_delivery','delivered','completed','cancelled','failed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($st); ?>" <?php echo e($s->shipping_status === $st ? 'selected' : ''); ?>><?php echo e(ucfirst(str_replace('_',' ',$st))); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
              <button class="btn btn-sm btn-primary">Update</button>
            </form>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="6"><div class="empty"><h3>No active deliveries</h3><p>Deliveries in progress will appear here.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\monitor.blade.php ENDPATH**/ ?>