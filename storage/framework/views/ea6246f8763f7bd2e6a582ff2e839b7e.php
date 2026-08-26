<?php $__env->startSection('title', 'Notifications'); ?>
<?php $__env->startSection('page-title', 'Notifications'); ?>
<?php $__env->startSection('page-sub', 'System alerts and updates'); ?>

<?php $__env->startSection('content'); ?>
<div class="card card-pad">
  <?php if($notifications->isEmpty()): ?>
  <div class="empty">
    <div class="ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </div>
    <h3>No notifications</h3>
    <p>You're all caught up.</p>
  </div>
  <?php else: ?>
  <div class="notif-list" style="max-height:none">
    <?php $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="notif-item <?php echo e($n->is_read ? 'read' : ''); ?>">
      <div class="dot"></div>
      <div>
        <p style="font-weight:600"><?php echo e($n->title); ?></p>
        <p><?php echo e($n->message); ?></p>
        <time><?php echo e(\Carbon\Carbon::parse($n->created_at)->format('M d, Y H:i')); ?></time>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
  <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\notifications.blade.php ENDPATH**/ ?>