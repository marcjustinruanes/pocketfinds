<?php $__env->startSection('title', 'My Account'); ?>
<?php $__env->startSection('page-title', 'My Account'); ?>
<?php $__env->startSection('page-sub', 'Manage your profile'); ?>

<?php $__env->startSection('content'); ?>
<?php ($user = auth()->user()); ?>
<?php if(session('success')): ?>
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">
  <div class="card">
    <div class="card-head"><h2>Profile</h2></div>
    <div class="card-pad">
      <form method="POST" action="<?php echo e(route('logistics.account.update')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-row"><label>First Name</label><input type="text" name="first_name" value="<?php echo e(old('first_name', $user->first_name)); ?>" required></div>
        <div class="form-row"><label>Last Name</label><input type="text" name="last_name" value="<?php echo e(old('last_name', $user->last_name)); ?>" required></div>
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Change Password</h2></div>
    <div class="card-pad">
      <?php $__errorArgs = ['current_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
      <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px"><?php echo e($message); ?></div>
      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      <form method="POST" action="<?php echo e(route('logistics.account.password')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-row"><label>Current Password</label><input type="password" name="current_password" required></div>
        <div class="form-row"><label>New Password</label><input type="password" name="password" required></div>
        <div class="form-row"><label>Confirm New Password</label><input type="password" name="password_confirmation" required></div>
        <button class="btn btn-primary" type="submit">Update Password</button>
      </form>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\account.blade.php ENDPATH**/ ?>