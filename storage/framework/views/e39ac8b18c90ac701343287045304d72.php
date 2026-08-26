<?php $__env->startSection('title', 'User Accounts'); ?>
<?php $__env->startSection('page-title', 'User Accounts'); ?>
<?php $__env->startSection('page-sub', 'Manage all platform accounts'); ?>
<?php use Illuminate\Support\Facades\Storage; ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head">
    <div><h2>All Users</h2><p><?php echo e($users->count()); ?> total accounts</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span>
        <input type="text" placeholder="Search name, email or username…" data-table-search="usersTable">
      </div>
    </div>

    <div data-tabs>
      <a class="tab active" data-tab="all">All</a>
      <a class="tab" data-tab="buyer">Buyers</a>
      <a class="tab" data-tab="seller">Sellers</a>
      <a class="tab" data-tab="rider">Riders</a>
    </div>

    <div class="table-wrap">
      <table class="dtable" id="usersTable">
        <thead>
          <tr><th>User</th><th>Type</th><th>Username</th><th>Contact</th><th>Joined</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="rail-row rail-<?php echo e($user->status); ?>" data-type="<?php echo e($user->account_type); ?>">
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
            <td><?php echo e(ucfirst($user->account_type)); ?></td>
            <td class="mono" style="font-size:12px"><?php echo e($user->username ?? '—'); ?></td>
            <td class="mono"><?php echo e($user->contact_no); ?></td>
            <td class="mono"><?php echo e($user->created_at->format('M d, Y')); ?></td>
            <td><span class="stamp stamp-<?php echo e($user->status); ?>"><?php echo e(ucfirst($user->status)); ?></span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="userModal-<?php echo e($user->id); ?>">Manage</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="userModal-<?php echo e($user->id); ?>">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div><h3>Manage User</h3><p><?php echo e($user->given_names); ?> <?php echo e($user->last_name); ?> — <?php echo e(ucfirst($user->account_type)); ?></p></div>
                <button class="modal-close" data-modal-close aria-label="Close"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></button>
              </div>
              <div class="modal-body">

                
                <p class="crumb" style="margin-bottom:10px">Personal Information</p>
                <div class="detail-grid">
                  <div><div class="field-label">Full Name</div><div class="field-value"><?php echo e($user->given_names); ?> <?php echo e($user->middle_name ? $user->middle_name.' ' : ''); ?><?php echo e($user->last_name); ?></div></div>
                  <div><div class="field-label">Username</div><div class="field-value mono"><?php echo e($user->username ?? '—'); ?></div></div>
                  <div><div class="field-label">Sex</div><div class="field-value"><?php echo e(ucfirst($user->sex ?? '—')); ?></div></div>
                  <div><div class="field-label">Birthday</div><div class="field-value mono"><?php echo e($user->birthday?->format('M d, Y') ?? '—'); ?></div></div>
                  <div><div class="field-label">Age</div><div class="field-value"><?php echo e($user->age ?? '—'); ?></div></div>
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-<?php echo e($user->status); ?>"><?php echo e(ucfirst($user->status)); ?></span></div></div>
                </div>

                
                <p class="crumb" style="margin:14px 0 10px">Contact & Auth</p>
                <div class="detail-grid">
                  <div><div class="field-label">Email</div><div class="field-value"><?php echo e($user->email); ?></div></div>
                  <div><div class="field-label">Contact No.</div><div class="field-value mono"><?php echo e($user->contact_no); ?></div></div>
                  <div><div class="field-label">Auth Method</div><div class="field-value"><?php echo e(ucfirst($user->auth_method)); ?></div></div>
                  <div><div class="field-label">Joined</div><div class="field-value mono"><?php echo e($user->created_at->format('M d, Y')); ?></div></div>
                </div>

                
                <p class="crumb" style="margin:14px 0 10px">Address</p>
                <div class="detail-grid">
                  <div class="full"><div class="field-label">Full Address</div><div class="field-value"><?php echo e(collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: '—'); ?></div></div>
                </div>

                
                <p class="crumb" style="margin:14px 0 10px">Documents</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                  <?php if($user->id_file): ?>
                  <a href="<?php echo e(Storage::url($user->id_file)); ?>" target="_blank" class="doc-chip">📄 View ID</a>
                  <?php endif; ?>
                  <?php if($user->selfie_file): ?>
                  <a href="<?php echo e(Storage::url($user->selfie_file)); ?>" target="_blank" class="doc-chip">🤳 View Selfie</a>
                  <?php endif; ?>
                  <?php if($user->account_type === 'seller' && $user->business_permit_file): ?>
                  <a href="<?php echo e(Storage::url($user->business_permit_file)); ?>" target="_blank" class="doc-chip">📋 Business Permit</a>
                  <?php endif; ?>
                  <?php if(!$user->id_file && !$user->selfie_file): ?>
                  <span style="font-size:12px;color:var(--muted)">No documents uploaded.</span>
                  <?php endif; ?>
                </div>

                
                <?php if($user->account_type === 'seller'): ?>
                <p class="crumb" style="margin:14px 0 10px">Seller Details</p>
                <div class="detail-grid">
                  <div><div class="field-label">Business Name</div><div class="field-value"><?php echo e($user->business_name ?? '—'); ?></div></div>
                  <div><div class="field-label">Category</div><div class="field-value"><?php echo e(\App\Models\Category::find($user->category_id)?->name ?? '—'); ?></div></div>
                </div>
                <?php endif; ?>

                
                <?php if($user->account_type === 'rider'): ?>
                <?php $riderProfile = \App\Models\RiderProfile::where('user_id', $user->id)->first(); ?>
                <?php if($riderProfile): ?>
                <p class="crumb" style="margin:14px 0 10px">Vehicle Information</p>
                <div class="detail-grid">
                  <div><div class="field-label">Vehicle Type</div><div class="field-value"><?php echo e(ucfirst(str_replace('_',' ',$riderProfile->vehicle_type))); ?></div></div>
                  <div><div class="field-label">Brand / Model</div><div class="field-value"><?php echo e($riderProfile->vehicle_brand); ?> <?php echo e($riderProfile->vehicle_model); ?></div></div>
                  <?php if($riderProfile->plate_number): ?>
                  <div><div class="field-label">Plate Number</div><div class="field-value mono"><?php echo e($riderProfile->plate_number); ?></div></div>
                  <?php endif; ?>
                  <?php if($riderProfile->license_number): ?>
                  <div><div class="field-label">License No.</div><div class="field-value mono"><?php echo e($riderProfile->license_number); ?></div></div>
                  <div><div class="field-label">License Expiry</div><div class="field-value mono"><?php echo e($riderProfile->license_expiry?->format('M d, Y')); ?></div></div>
                  <?php endif; ?>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
                  <?php if($riderProfile->or_file): ?><a href="<?php echo e(Storage::url($riderProfile->or_file)); ?>" target="_blank" class="doc-chip">📄 OR</a><?php endif; ?>
                  <?php if($riderProfile->cr_file): ?><a href="<?php echo e(Storage::url($riderProfile->cr_file)); ?>" target="_blank" class="doc-chip">📄 CR</a><?php endif; ?>
                  <?php if($riderProfile->license_file): ?><a href="<?php echo e(Storage::url($riderProfile->license_file)); ?>" target="_blank" class="doc-chip">🪪 License</a><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>

              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
                <form method="POST" action="<?php echo e(route('admin.users.suspend', $user->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-danger" type="submit">Suspend</button>
                </form>
                <form method="POST" action="<?php echo e(route('admin.users.approve', $user->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-success" type="submit">Activate</button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="7"><div class="empty"><div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/users.blade.php ENDPATH**/ ?>