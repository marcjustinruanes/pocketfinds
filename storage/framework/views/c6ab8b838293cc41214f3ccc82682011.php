<?php $__env->startSection('title', 'Delivery Issues'); ?>
<?php $__env->startSection('page-title', 'Delivery Issues'); ?>
<?php $__env->startSection('page-sub', 'Failed and cancelled deliveries requiring attention'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><h2>Problem Deliveries</h2><span class="stamp stamp-rejected"><?php echo e($shipments->count()); ?> issues</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Courier</th>
          <th>Issue</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $shipments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr class="rail-row rail-<?php echo e($s->shipping_status); ?>">
          <td class="mono"><?php echo e($s->tracking_number ?? substr($s->id, 0, 8)); ?></td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm"><?php echo e(strtoupper(substr(optional(optional($s->order)->buyer)->first_name ?? '?', 0, 1))); ?></div>
              <div>
                <strong><?php echo e(optional(optional($s->order)->buyer)->first_name); ?> <?php echo e(optional(optional($s->order)->buyer)->last_name); ?></strong>
                <span><?php echo e(optional(optional($s->order)->buyer)->email); ?></span>
              </div>
            </div>
          </td>
          <td><?php echo e(optional($s->courier)->first_name ? optional($s->courier)->first_name . ' ' . optional($s->courier)->last_name : '—'); ?></td>
          <td><span class="stamp stamp-<?php echo e($s->shipping_status); ?>"><?php echo e(ucfirst($s->shipping_status)); ?></span></td>
          <td class="mono"><?php echo e($s->created_at?->format('M d, Y')); ?></td>
          <td>
            <form method="POST" action="<?php echo e(route('logistics.status.update', $s->id)); ?>" style="display:flex;gap:8px">
              <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
              <input type="hidden" name="status" value="available">
              <button class="btn btn-sm btn-outline">Retry</button>
            </form>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="6"><div class="empty"><h3>No issues found</h3><p>All deliveries are running smoothly.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\issues.blade.php ENDPATH**/ ?>