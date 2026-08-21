<?php $__env->startSection('title', 'Reports'); ?>
<?php $__env->startSection('page-title', 'Reports'); ?>
<?php $__env->startSection('page-sub', 'Platform analytics'); ?>

<?php $__env->startSection('content'); ?>
<div class="kpi-grid" style="margin-bottom:18px">
  <div class="kpi"><div class="label">Total Users</div><div class="value"><?php echo e(number_format($totalUsers)); ?></div><div class="delta up">All accounts</div></div>
  <div class="kpi"><div class="label">Buyers</div><div class="value"><?php echo e(number_format($buyerCount)); ?></div></div>
  <div class="kpi"><div class="label">Sellers</div><div class="value"><?php echo e(number_format($sellerCount)); ?></div></div>
  <div class="kpi"><div class="label">Riders</div><div class="value"><?php echo e(number_format($riderCount)); ?></div></div>
</div>

<div class="dash-grid">
  <div class="card">
    <div class="card-head"><h2>User Registrations by Status</h2></div>
    <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
      <?php
        $pending  = \App\Models\User::where('status','pending')->where('is_admin',false)->count();
        $approved = \App\Models\User::where('status','approved')->where('is_admin',false)->count();
        $rejected = \App\Models\User::where('status','rejected')->where('is_admin',false)->count();
      ?>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="stamp stamp-pending">Pending</span><span class="mono"><?php echo e($pending); ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="stamp stamp-approved">Approved</span><span class="mono"><?php echo e($approved); ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="stamp stamp-rejected">Rejected</span><span class="mono"><?php echo e($rejected); ?></span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Export Reports</h2></div>
    <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
      <button class="btn btn-outline" data-toast="Downloading user report…">👤 User Report (CSV)</button>
      <button class="btn btn-outline" data-toast="Downloading seller report…">🛡 Seller Report (CSV)</button>
      <button class="btn btn-outline" data-toast="Downloading rider report…">🚴 Rider Report (CSV)</button>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\reports.blade.php ENDPATH**/ ?>