<?php $__env->startSection('title', 'Delivery Requests'); ?>
<?php $__env->startSection('page-title', 'Delivery Requests'); ?>
<?php $__env->startSection('page-sub', 'Incoming delivery requests awaiting review'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><h2>Pending Requests</h2><span class="stamp stamp-pending"><?php echo e($shipments->count()); ?> pending</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Items</th>
          <th>Order Amount</th>
          <th>Date</th>
          <th>Actions</th>
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
                <span><?php echo e(optional(optional($s->order)->buyer)->email); ?></span>
              </div>
            </div>
          </td>
          <td class="mono"><?php echo e(optional($s->order)->items?->count() ?? 0); ?> item(s)</td>
          <td class="mono">₱<?php echo e(number_format(optional($s->order)->total_amount ?? 0, 2)); ?></td>
          <td class="mono"><?php echo e($s->created_at?->format('M d, Y')); ?></td>
          <td>
            <div class="row-actions">
              <form method="POST" action="<?php echo e(route('logistics.requests.approve', $s->id)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                <button class="btn btn-sm btn-success">Approve</button>
              </form>
              <form method="POST" action="<?php echo e(route('logistics.requests.reject', $s->id)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                <button class="btn btn-sm btn-danger">Reject</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="6"><div class="empty"><h3>No pending requests</h3><p>All delivery requests have been processed.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/logistics/requests.blade.php ENDPATH**/ ?>