<?php $__env->startSection('title', 'Notifications'); ?>
<?php $__env->startSection('page-title', 'Order Notifications'); ?>
<?php $__env->startSection('page-sub', 'New orders and alerts requiring your attention'); ?>

<?php $__env->startSection('content'); ?>
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div><h2>New Orders</h2><p>Requires your action</p></div>
        <button class="btn btn-sm btn-outline">Mark all read</button>
      </div>
      <div class="card-pad">
        <?php $notifs = [
          ['New order received','Order #00001 — 1 item · ₱299.00','2 min ago', false],
          ['New order received','Order #00002 — 3 items · ₱850.00','15 min ago', false],
          ['Low stock alert','Sample Product has only 2 units left','1 hr ago', false],
          ['Order delivered','Order #99998 was confirmed delivered','Yesterday', true],
        ]; ?>
        <?php $__currentLoopData = $notifs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$title,$sub,$time,$read]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="notif-row">
          <div class="notif-dot <?php echo e($read ? 'read' : ''); ?>"></div>
          <div class="notif-body">
            <div class="notif-title"><?php echo e($title); ?></div>
            <div class="notif-sub"><?php echo e($sub); ?></div>
          </div>
          <div class="notif-time"><?php echo e($time); ?></div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <?php $__currentLoopData = [['bell','Unread Notifications',3,'stamp-new'],['orders','Pending Orders',2,'stamp-pending'],['truck','In Transit',0,'stamp-transit'],['check-circle','Delivered Today',1,'stamp-delivered']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon,$label,$count,$stamp]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:9px">
          <span style="color:var(--pink-dark)"><?php echo $__env->make('seller.partials.icon', ['name' => $icon, 'size' => 18], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
          <span style="font-size:13px;font-weight:600;flex:1"><?php echo e($label); ?></span>
          <span class="stamp <?php echo e($stamp); ?>"><?php echo e($count); ?></span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="<?php echo e(route('seller.orders')); ?>" class="btn btn-primary"><?php echo $__env->make('seller.partials.icon', ['name' => 'orders', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View All Orders</a>
        <a href="<?php echo e(route('seller.prepare')); ?>" class="btn btn-outline"><?php echo $__env->make('seller.partials.icon', ['name' => 'package', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Prepare Orders</a>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/seller/notifications.blade.php ENDPATH**/ ?>