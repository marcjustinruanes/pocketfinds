<?php $__env->startSection('title', 'My Account'); ?>
<?php $__env->startSection('page-title', 'My Account'); ?>
<?php $__env->startSection('page-sub', 'Manage your admin profile'); ?>

<?php $__env->startPush('head'); ?>
<style>
  .card-head h2, .card-head h3 { font-family: var(--font-body); font-weight: 600; font-size: 14px; }
  .form-row label { font-family: var(--font-body); font-size: 13px; }
  .form-row input { font-family: var(--font-body); font-size: 13px; }
  .stamp { font-family: var(--font-body); font-size: 11px; letter-spacing: 0; text-transform: none; }
  .profile-upload { border:1px dashed var(--line); border-radius:8px; padding:12px; background:var(--panel-soft); }
  .profile-upload input[type=file] { width:100%; border:0; padding:0; background:transparent; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php ($admin = auth()->user()); ?>
<?php if(session('success')): ?>
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  <?php echo e(session('success')); ?>

</div>
<?php endif; ?>
<?php if($errors->any()): ?>
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  Please check the highlighted account details and try again.
</div>
<?php endif; ?>

<div class="account-grid">
  <div class="card">
    <div class="card-head"><h2>Account Information</h2></div>
    <div class="card-pad">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
        <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => $admin,'size' => '72']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($admin),'size' => '72']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $attributes = $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $component = $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
        <div>
          <div style="font-weight:700;font-size:15px"><?php echo e($admin->first_name); ?> <?php echo e($admin->last_name); ?></div>
          <div style="font-size:12px;color:var(--muted)"><?php echo e($admin->email); ?></div>
          <span class="stamp stamp-active" style="margin-top:4px"><?php echo e(ucfirst($admin->account_type)); ?></span>
        </div>
      </div>
      <form method="POST" action="<?php echo e(route('admin.account.update')); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="form-row">
          <label>Profile Picture <span class="hint">JPG, PNG or WEBP · max 2MB</span></label>
          <div class="profile-upload">
            <input type="file" name="profile_picture" accept="image/png,image/jpeg,image/webp">
          </div>
          <?php $__errorArgs = ['profile_picture'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="form-row">
          <label>First Name</label>
          <input type="text" name="first_name" value="<?php echo e(old('first_name', $admin->first_name)); ?>" required>
          <?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="form-row">
          <label>Last Name</label>
          <input type="text" name="last_name" value="<?php echo e(old('last_name', $admin->last_name)); ?>" required>
          <?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="form-row">
          <label>Email</label>
          <input type="email" name="email" value="<?php echo e(old('email', $admin->email)); ?>" required>
          <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="form-row">
          <label>Contact Number</label>
          <input type="text" name="contact_no" value="<?php echo e(old('contact_no', $admin->contact_no)); ?>" maxlength="11" required>
          <?php $__errorArgs = ['contact_no'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="form-grid-2">
          <div class="form-row">
            <label>Province</label>
            <input type="text" name="province" value="<?php echo e(old('province', $admin->province)); ?>" required>
            <?php $__errorArgs = ['province'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="form-row">
            <label>Municipality</label>
            <input type="text" name="municipality" value="<?php echo e(old('municipality', $admin->municipality)); ?>" required>
            <?php $__errorArgs = ['municipality'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
        </div>
        <div class="form-row">
          <label>Barangay</label>
          <input type="text" name="barangay" value="<?php echo e(old('barangay', $admin->barangay)); ?>" required>
          <?php $__errorArgs = ['barangay'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="form-grid-2">
          <div class="form-row">
            <label>House No. <span class="hint">Optional</span></label>
            <input type="text" name="house_no" value="<?php echo e(old('house_no', $admin->house_no)); ?>">
            <?php $__errorArgs = ['house_no'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="form-row">
            <label>Street <span class="hint">Optional</span></label>
            <input type="text" name="street" value="<?php echo e(old('street', $admin->street)); ?>">
            <?php $__errorArgs = ['street'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
        </div>
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
      <form method="POST" action="<?php echo e(route('admin.account.password')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-row">
          <label>Current Password</label>
          <input type="password" name="current_password" required>
        </div>
        <div class="form-row">
          <label>New Password</label>
          <input type="password" name="password" required>
          <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="hint" style="color:var(--danger);margin-top:5px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="form-row">
          <label>Confirm New Password</label>
          <input type="password" name="password_confirmation" required>
        </div>
        <button class="btn btn-primary" type="submit">Update Password</button>
      </form>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\account.blade.php ENDPATH**/ ?>