<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('page-title', 'Dashboard'); ?>
<?php $__env->startSection('page-sub', 'Welcome back, ' . auth()->user()->given_names); ?>

<?php $__env->startSection('content'); ?>
<?php
  $buyers   = \App\Models\User::where('account_type','buyer')->where('is_admin',false)->count();
  $sellers  = \App\Models\User::where('account_type','seller')->where('is_admin',false)->count();
  $riders   = \App\Models\User::where('account_type','rider')->where('is_admin',false)->count();
  $approved = \App\Models\User::where('status','approved')->where('is_admin',false)->count();
?>

<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Total Users</div>
    <div class="value"><?php echo e(number_format($totalUsers)); ?></div>
    <div class="delta up">All accounts</div>
  </div>
  <div class="kpi">
    <div class="label">Pending Registrations</div>
    <div class="value"><?php echo e($pendingCount); ?></div>
    <div class="delta <?php echo e($pendingCount > 0 ? 'down' : 'up'); ?>">Awaiting review</div>
  </div>
  <div class="kpi">
    <div class="label">Open Disputes</div>
    <div class="value"><?php echo e($openDisputes); ?></div>
    <div class="delta">Active cases</div>
  </div>
  <div class="kpi">
    <div class="label">Approved Users</div>
    <div class="value"><?php echo e($approved); ?></div>
    <div class="delta up">Active accounts</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div><h2>Recent Registrations</h2><p>Latest 5 submissions</p></div>
        <a href="<?php echo e(route('admin.registrations')); ?>" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="table-wrap">
        <table class="dtable">
          <thead>
            <tr><th>Applicant</th><th>Type</th><th>Auth</th><th>Date</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $recentUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr class="rail-row rail-<?php echo e($user->status); ?>">
              <td>
                <div class="cell-user">
                  <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => $user,'size' => '30','class' => 'avatar-sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user),'size' => '30','class' => 'avatar-sm']); ?>
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
                  <div><strong><?php echo e($user->given_names); ?> <?php echo e($user->last_name); ?></strong><span><?php echo e($user->email); ?></span></div>
                </div>
              </td>
              <td><span class="stamp stamp-<?php echo e($user->account_type); ?>" style="text-transform:capitalize"><?php echo e(ucfirst($user->account_type)); ?></span></td>
              <td class="mono" style="font-size:11px"><?php echo e(ucfirst($user->auth_method)); ?></td>
              <td class="mono"><?php echo e($user->created_at->format('M d, Y')); ?></td>
              <td><span class="stamp stamp-<?php echo e($user->status); ?>"><?php echo e(ucfirst($user->status)); ?></span></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="5"><div class="empty"><div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'users']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'users']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></div><h3>No users yet</h3></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>User Breakdown</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:14px">
        <?php $__currentLoopData = [['Buyers','buyer',$buyers,'#3457c2'],['Sellers','seller',$sellers,'#1a7f5a'],['Riders','rider',$riders,'#a8670a']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$type,$count,$color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px">
          <div style="display:flex;align-items:center;gap:8px">
            <span style="width:8px;height:8px;border-radius:50%;background:<?php echo e($color); ?>;display:inline-block"></span>
            <span><?php echo e($label); ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <span class="mono"><?php echo e($count); ?></span>
            <?php if($totalUsers > 0): ?>
            <span style="font-size:11px;color:var(--muted)"><?php echo e(round($count / $totalUsers * 100)); ?>%</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if($totalUsers > 0): ?>
        <div style="height:4px;background:var(--border);border-radius:99px;margin-top:-8px">
          <div style="height:100%;width:<?php echo e(round($count / $totalUsers * 100)); ?>%;background:<?php echo e($color); ?>;border-radius:99px"></div>
        </div>
        <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="<?php echo e(route('admin.registrations')); ?>" class="btn btn-outline">
          Review Pending Registrations
          <?php if($pendingCount > 0): ?><span style="margin-left:auto;background:var(--pink);color:#fff;font-size:10px;padding:1px 7px;border-radius:20px"><?php echo e($pendingCount); ?></span><?php endif; ?>
        </a>
        <a href="<?php echo e(route('admin.complaints')); ?>" class="btn btn-outline">
          Open Disputes
          <?php if($openDisputes > 0): ?><span style="margin-left:auto;background:var(--danger);color:#fff;font-size:10px;padding:1px 7px;border-radius:20px"><?php echo e($openDisputes); ?></span><?php endif; ?>
        </a>
        <a href="<?php echo e(route('admin.compliance')); ?>" class="btn btn-outline">Seller Compliance Queue</a>
        <a href="<?php echo e(route('admin.reports')); ?>" class="btn btn-outline">View Reports</a>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>