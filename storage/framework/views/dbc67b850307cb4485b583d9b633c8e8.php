<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle>☰</button>
  <div class="page-heading">
    <h1><?php echo $__env->yieldContent('page-title', 'Dashboard'); ?></h1>
    <p><?php echo $__env->yieldContent('page-sub', ''); ?></p>
  </div>
  <div class="topbar-search">
    <span class="ic">🔍</span>
    <input type="text" placeholder="Search…">
  </div>
  <div class="topbar-actions">
    <?php
      $notifItems = [];
      if (($pendingRegistrations ?? 0) > 0)
          $notifItems[] = ['text' => $pendingRegistrations . ' registration' . ($pendingRegistrations > 1 ? 's' : '') . ' pending review.', 'link' => route('admin.registrations'), 'read' => false];
      if (($openDisputes ?? 0) > 0)
          $notifItems[] = ['text' => $openDisputes . ' open dispute' . ($openDisputes > 1 ? 's' : '') . ' need attention.', 'link' => route('admin.complaints'), 'read' => false];
      if (empty($notifItems))
          $notifItems[] = ['text' => 'No new notifications.', 'link' => '#', 'read' => true];
      $unreadCount = collect($notifItems)->where('read', false)->count();
    ?>

    <div class="dropdown">
      <button class="icon-btn" data-dropdown-toggle="notifPanel">
        🔔<?php if($unreadCount > 0): ?><span class="dot-badge"></span><?php endif; ?>
      </button>
      <div class="dropdown-panel" id="notifPanel">
        <div class="dropdown-head">
          <h3>Notifications</h3>
          <?php if($unreadCount > 0): ?><span style="font-size:11px;color:var(--pink-dark);font-weight:700"><?php echo e($unreadCount); ?> new</span><?php endif; ?>
        </div>
        <div class="notif-list">
          <?php $__currentLoopData = $notifItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notif): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e($notif['link']); ?>" class="notif-item <?php echo e($notif['read'] ? 'read' : ''); ?>" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p><?php echo e($notif['text']); ?></p></div>
          </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>

    <a href="<?php echo e(route('admin.account')); ?>" class="topbar-avatar">
      <?php echo e(strtoupper(substr(auth()->user()->first_name, 0, 1))); ?>

    </a>
  </div>
</header>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\partials\topbar.blade.php ENDPATH**/ ?>