<?php $__env->startSection('title', 'My Account'); ?>
<?php $__env->startSection('page-title', 'My Account'); ?>
<?php $__env->startSection('page-sub', 'Manage your profile and settings'); ?>

<?php $__env->startSection('content'); ?>
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Profile Information</h2></div>
      <div class="card-pad">
        <?php if(session('success')): ?>
        <div class="auth-success" style="margin-bottom:16px"><?php echo e(session('success')); ?></div>
        <?php endif; ?>
        <form method="POST" action="#">
          <?php echo csrf_field(); ?>
          <div class="form-grid-2">
            <div class="form-row">
              <label>First Name</label>
              <input type="text" name="first_name" value="<?php echo e(auth()->user()->first_name); ?>">
            </div>
            <div class="form-row">
              <label>Last Name</label>
              <input type="text" name="last_name" value="<?php echo e(auth()->user()->last_name); ?>">
            </div>
            <div class="form-row">
              <label>Email</label>
              <input type="email" value="<?php echo e(auth()->user()->email); ?>" disabled style="background:var(--paper)">
            </div>
            <div class="form-row">
              <label>Contact No.</label>
              <input type="text" name="contact_no" value="<?php echo e(auth()->user()->contact_no); ?>">
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Change Password</h2></div>
      <div class="card-pad">
        <form method="POST" action="#">
          <?php echo csrf_field(); ?>
          <div class="form-row">
            <label>Current Password</label>
            <input type="password" name="current_password" placeholder="••••••••">
          </div>
          <div class="form-row">
            <label>New Password</label>
            <input type="password" name="password" placeholder="••••••••">
          </div>
          <div class="form-row">
            <label>Confirm New Password</label>
            <input type="password" name="password_confirmation" placeholder="••••••••">
          </div>
          <button class="btn btn-primary" type="submit">Update Password</button>
        </form>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Account Details</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:14px">
        <div>
          <div class="field-label">Account Type</div>
          <div class="field-value"><span class="stamp stamp-approved">Buyer</span></div>
        </div>
        <div>
          <div class="field-label">Status</div>
          <div class="field-value"><span class="stamp stamp-<?php echo e(auth()->user()->status); ?>"><?php echo e(ucfirst(auth()->user()->status)); ?></span></div>
        </div>
        <div>
          <div class="field-label">Member Since</div>
          <div class="field-value mono"><?php echo e(auth()->user()->created_at->format('M d, Y')); ?></div>
        </div>
        <div>
          <div class="field-label">Address</div>
          <div class="field-value" style="font-size:13px">
            <?php echo e(auth()->user()->barangay); ?>, <?php echo e(auth()->user()->municipality); ?>, <?php echo e(auth()->user()->province); ?>

          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Delivery Address</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div class="address-card active-address">
          <div style="font-size:13px;font-weight:600"><?php echo e(auth()->user()->first_name); ?> <?php echo e(auth()->user()->last_name); ?></div>
          <div style="font-size:12.5px;color:var(--muted);margin-top:4px">
            <?php echo e(auth()->user()->house_no ? auth()->user()->house_no . ', ' : ''); ?>

            <?php echo e(auth()->user()->street ? auth()->user()->street . ', ' : ''); ?>

            <?php echo e(auth()->user()->barangay); ?>, <?php echo e(auth()->user()->municipality); ?>, <?php echo e(auth()->user()->province); ?>

          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px"><?php echo e(auth()->user()->contact_no); ?></div>
          <span class="stamp stamp-approved" style="margin-top:8px;display:inline-flex">Default</span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/buyer/account.blade.php ENDPATH**/ ?>