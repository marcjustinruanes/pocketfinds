<?php $__env->startSection('title', 'Notifications'); ?>
<?php $__env->startSection('page-title', 'Notifications'); ?>
<?php $__env->startSection('page-sub', 'Your alerts and account updates'); ?>

<?php $__env->startSection('content'); ?>
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div><h2>All Notifications</h2><p><?php echo e($notifications->count()); ?> total</p></div>
        <?php if($notifications->where('is_read', false)->count()): ?>
          <form method="POST" action="<?php echo e(route('seller.notifications.read')); ?>">
            <?php echo csrf_field(); ?>
            <button class="btn btn-sm btn-outline" type="submit" style="display:inline-flex;align-items:center;gap:6px">
              <?php echo $__env->make('seller.partials.icon',['name'=>'check','size'=>13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Mark all read
            </button>
          </form>
        <?php endif; ?>
      </div>
      <div class="card-pad">
        <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notif): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            $isDoc = in_array($notif->notification_type, ['doc_approved','doc_rejected']);
            $icon  = $notif->notification_type === 'doc_approved' ? 'check-circle'
                   : ($notif->notification_type === 'doc_rejected' ? 'x' : 'bell');
            $color = $notif->notification_type === 'doc_approved' ? 'var(--success)'
                   : ($notif->notification_type === 'doc_rejected' ? 'var(--danger)' : 'var(--pink)');
          ?>
          <div class="notif-row">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--paper);border:1px solid var(--border);display:grid;place-items:center;flex:none;color:<?php echo e($color); ?>">
              <?php echo $__env->make('seller.partials.icon',['name'=>$icon,'size'=>15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
            <div class="notif-body">
              <div class="notif-title" style="<?php echo e($notif->is_read ? 'font-weight:500' : ''); ?>"><?php echo e($notif->title); ?></div>
              <div class="notif-sub"><?php echo e($notif->message); ?></div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex:none">
              <div class="notif-time"><?php echo e(\Carbon\Carbon::parse($notif->created_at)->diffForHumans()); ?></div>
              <?php if(!$notif->is_read): ?>
                <div style="width:7px;height:7px;border-radius:50%;background:var(--pink)"></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="empty">
            <?php echo $__env->make('seller.partials.icon',['name'=>'bell','size'=>32,'class'=>'ic'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <h3>No notifications yet</h3>
            <p>You'll be notified here about orders and account updates.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <?php
          $unread  = $notifications->where('is_read', false)->count();
          $docAppr = $notifications->where('notification_type','doc_approved')->count();
          $docRej  = $notifications->where('notification_type','doc_rejected')->count();
        ?>
        <?php $__currentLoopData = [
          ['bell',        'Unread',           $unread,  'stamp-new'],
          ['check-circle','Doc Approved',      $docAppr, 'stamp-approved'],
          ['x',           'Doc Rejected',      $docRej,  'stamp-rejected'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon,$label,$count,$stamp]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:9px">
          <span style="color:var(--pink-dark)"><?php echo $__env->make('seller.partials.icon',['name'=>$icon,'size'=>18], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
          <span style="font-size:13px;font-weight:600;flex:1"><?php echo e($label); ?></span>
          <span class="stamp <?php echo e($stamp); ?>"><?php echo e($count); ?></span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="<?php echo e(route('seller.orders')); ?>" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:7px">
          <?php echo $__env->make('seller.partials.icon',['name'=>'orders','size'=>15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View All Orders
        </a>
        <a href="<?php echo e(route('seller.account')); ?>" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:7px">
          <?php echo $__env->make('seller.partials.icon',['name'=>'shield','size'=>15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> My Documents
        </a>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\notifications.blade.php ENDPATH**/ ?>