<?php $__env->startSection('title', 'My Account'); ?>
<?php $__env->startSection('page-title', 'My Account'); ?>
<?php $__env->startSection('page-sub', 'Manage your seller profile and settings'); ?>

<?php $__env->startSection('content'); ?>
<div class="dash-grid">
  <div class="stack">
    
    <div class="card">
      <div class="card-head"><div><h2>Profile Information</h2><p>Update your personal details</p></div></div>
      <div class="card-pad">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px">
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:22px;flex:none">
            <?php echo e(strtoupper(substr(auth()->user()->given_names, 0, 1))); ?>

          </div>
          <div>
            <div style="font-size:16px;font-weight:700"><?php echo e(auth()->user()->given_names); ?> <?php echo e(auth()->user()->last_name); ?></div>
            <div style="font-size:12px;color:var(--muted)"><?php echo e(auth()->user()->email); ?></div>
            <span class="stamp stamp-active" style="margin-top:4px">Seller</span>
          </div>
        </div>
        <form>
          <div class="form-grid-2">
            <div class="form-row"><label>Given Names</label><input type="text" value="<?php echo e(auth()->user()->given_names); ?>" readonly></div>
            <div class="form-row"><label>Last Name</label><input type="text" value="<?php echo e(auth()->user()->last_name); ?>" readonly></div>
          </div>
          <div class="form-row"><label>Middle Name</label><input type="text" value="<?php echo e(auth()->user()->middle_name); ?>" readonly></div>
          <div class="form-row"><label>Email Address</label><input type="email" value="<?php echo e(auth()->user()->email); ?>" readonly></div>
          <div class="form-row"><label>Contact Number</label><input type="text" value="<?php echo e(auth()->user()->contact_no); ?>" readonly></div>
          <div class="form-row"><label>Username</label><input type="text" value="<?php echo e(auth()->user()->username); ?>" readonly></div>
        </form>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><div><h2>Shop Information</h2><p>Your store details visible to buyers</p></div></div>
      <div class="card-pad">
        <form>
          <div class="form-row"><label>Business Name</label><input type="text" value="<?php echo e(auth()->user()->business_name); ?>" readonly></div>
          <div class="form-row"><label>Shop Category</label><input type="text" value="<?php echo e(optional(\App\Models\Category::find(auth()->user()->category_id))->name ?? '—'); ?>" readonly></div>
          <div class="form-row"><label>Address</label><input type="text" value="<?php echo e(implode(', ', array_filter([auth()->user()->house_no, auth()->user()->street, auth()->user()->barangay, auth()->user()->municipality, auth()->user()->province]))); ?>" readonly></div>
        </form>
      </div>
    </div>
  </div>

  <div class="stack">
    
    <div class="card">
      <div class="card-head"><div><h2>Change Password</h2></div></div>
      <div class="card-pad">
        <form>
          <div class="form-row"><label>Current Password</label><input type="password" placeholder="••••••••"></div>
          <div class="form-row"><label>New Password</label><input type="password" placeholder="••••••••"></div>
          <div class="form-row"><label>Confirm New Password</label><input type="password" placeholder="••••••••"></div>
          <button type="submit" class="btn btn-outline">Update Password</button>
        </form>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><h2>Account Overview</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <?php $__currentLoopData = [['Account Type','Seller'],['Status', ucfirst(auth()->user()->status)],['Member Since',auth()->user()->created_at?->format('M Y') ?? '—'],['Sex', ucfirst(auth()->user()->sex ?? '—')],['Birthday', auth()->user()->birthday?->format('M d, Y') ?? '—'],['Auth Method', ucfirst(auth()->user()->auth_method)]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$val]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--muted)"><?php echo e($label); ?></span>
          <span style="font-weight:600"><?php echo e($val); ?></span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>

    
    <div class="card" style="border-color:var(--danger-line)">
      <div class="card-head" style="border-color:var(--danger-line)"><div><h2 style="color:var(--danger)">Danger Zone</h2><p>Irreversible actions</p></div></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <button class="btn btn-danger btn-block">Deactivate Shop</button>
        <button class="btn btn-danger btn-block" data-logout><?php echo $__env->make('seller.partials.icon', ['name' => 'logout', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Sign Out</button>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/seller/account.blade.php ENDPATH**/ ?>