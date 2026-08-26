<?php $__env->startSection('title', 'Courier Assignments'); ?>
<?php $__env->startSection('page-title', 'Courier Assignments'); ?>
<?php $__env->startSection('page-sub', 'System auto-assigns deliveries to the first courier who accepts'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head">
    <h2>Delivery Assignments</h2>
    <div style="display:flex;gap:8px">
      <span class="stamp stamp-pending"><?php echo e($shipments->where('shipping_status','available')->count()); ?> awaiting courier</span>
      <span class="stamp stamp-active"><?php echo e($shipments->whereIn('shipping_status',['accepted','picked_up','out_for_delivery'])->count()); ?> in progress</span>
    </div>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Delivery Status</th>
          <th>Assigned Courier</th>
          <th>Assignment Status</th>
          <th>Assigned At</th>
        </tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $shipments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php $courier = optional(optional($s->assignment)->courier) ?>
        <tr>
          <td class="mono"><?php echo e($s->tracking_number ?? substr($s->id, 0, 8)); ?></td>
          <td><?php echo e(optional(optional($s->order)->buyer)->first_name); ?> <?php echo e(optional(optional($s->order)->buyer)->last_name); ?></td>
          <td><span class="stamp stamp-<?php echo e($s->shipping_status); ?>"><?php echo e(ucfirst(str_replace('_',' ',$s->shipping_status))); ?></span></td>
          <td>
            <?php if($courier->first_name): ?>
              <div class="cell-user">
                <div class="avatar-sm"><?php echo e(strtoupper(substr($courier->first_name,0,1))); ?></div>
                <div>
                  <strong><?php echo e($courier->first_name); ?> <?php echo e($courier->last_name); ?></strong>
                  <div style="font-size:11px;color:var(--muted)"><?php echo e($courier->email); ?></div>
                </div>
              </div>
            <?php else: ?>
              <span style="color:var(--muted);font-size:12.5px">Waiting for courier…</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if($s->assignment): ?>
              <span class="stamp stamp-<?php echo e($s->assignment->status); ?>"><?php echo e(ucfirst($s->assignment->status)); ?></span>
            <?php else: ?>
              <span class="stamp stamp-pending">Unassigned</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--muted)">
            <?php echo e($s->assignment?->created_at ? \Carbon\Carbon::parse($s->assignment->created_at)->format('M d, H:i') : '—'); ?>

          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="6"><div class="empty"><h3>No active assignments</h3><p>Approved deliveries will appear here once available.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\assign.blade.php ENDPATH**/ ?>