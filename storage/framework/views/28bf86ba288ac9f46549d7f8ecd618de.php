<?php $__env->startSection('title', 'Prepare Orders'); ?>
<?php $__env->startSection('page-title', 'Prepare Orders'); ?>
<?php $__env->startSection('page-sub', 'Pack items and print shipping labels'); ?>

<?php $__env->startSection('content'); ?>
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div><h2>Orders to Prepare</h2><p>Pack and label before handing to courier</p></div>
        <button class="btn btn-sm btn-outline"><?php echo $__env->make('seller.partials.icon', ['name' => 'printer', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Print All Labels</button>
      </div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <?php $orders = [
          ['#00001','Sample Customer','1 item — Sample Product','₱299.00','Cash on Delivery'],
          ['#00002','Another Customer','3 items — Various Products','₱850.00','GCash'],
        ]; ?>
        <?php $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$id,$customer,$items,$total,$payment]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="order-card">
          <div class="order-card-head">
            <span class="order-id"><?php echo e($id); ?></span>
            <span class="stamp stamp-pending">To Prepare</span>
          </div>
          <div class="order-items"><?php echo e($items); ?></div>
          <div class="order-meta">
            <span><?php echo $__env->make('seller.partials.icon', ['name' => 'user', 'size' => 12], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo e($customer); ?></span>
            <span class="mono"><?php echo e($total); ?></span>
            <span><?php echo e($payment); ?></span>
          </div>
          <div class="order-actions">
            <button class="btn btn-sm btn-outline"><?php echo $__env->make('seller.partials.icon', ['name' => 'printer', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Print Waybill</button>
            <button class="btn btn-sm btn-outline"><?php echo $__env->make('seller.partials.icon', ['name' => 'eye', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View Details</button>
            <a href="<?php echo e(route('seller.shipments')); ?>" class="btn btn-sm btn-primary" style="margin-left:auto"><?php echo $__env->make('seller.partials.icon', ['name' => 'truck', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Hand to Courier</a>
          </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Packing Checklist</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <?php $__currentLoopData = ['Verify items match order','Wrap items securely','Attach printed waybill','Seal the package','Label fragile items if needed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <label style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer">
          <input type="checkbox" style="width:16px;height:16px;accent-color:var(--pink)">
          <?php echo e($step); ?>

        </label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Courier Info</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px;font-size:13px">
        <div><div class="field-label">Assigned Courier</div><div class="field-value">J&T Express</div></div>
        <div><div class="field-label">Pickup Schedule</div><div class="field-value">Today, 2:00 PM – 5:00 PM</div></div>
        <a href="<?php echo e(route('seller.shipments')); ?>" class="btn btn-outline" style="margin-top:6px"><?php echo $__env->make('seller.partials.icon', ['name' => 'truck', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Manage Shipments</a>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\prepare.blade.php ENDPATH**/ ?>