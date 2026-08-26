<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $__env->yieldContent('title', 'Admin'); ?> — Console</title>
<link rel="stylesheet" href="/css/admin.css">
<?php echo $__env->yieldPushContent('head'); ?>
</head>
<body class="admin">
<div class="shell" id="appShell">
  <?php echo $__env->make('admin.partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div style="display:flex;flex-direction:column;min-width:0">
    <?php echo $__env->make('admin.partials.topbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="content">
      <?php echo $__env->yieldContent('content'); ?>
    </main>
  </div>
</div>


<div class="modal-overlay" id="logoutOverlay">
  <div class="modal" style="max-width:380px">
    <div class="modal-head">
      <div><h3>Sign out?</h3><p>You will be returned to the login screen.</p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Cancel</button>
      <form method="POST" action="<?php echo e(route('admin.logout')); ?>">
        <?php echo csrf_field(); ?>
        <button class="btn btn-danger" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</div>

<div class="toast-stack" id="toastStack"></div>
<script src="/js/admin.js"></script>
</body>
</html>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\layout.blade.php ENDPATH**/ ?>