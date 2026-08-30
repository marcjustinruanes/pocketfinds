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

    <?php
      $usersByType = $users->countBy('account_type');
    ?>
    <div data-tabs>
      <a class="tab active" data-tab="all">All <span class="tab-count"><?php echo e($users->count()); ?></span></a>
      <a class="tab" data-tab="buyer">Buyers <span class="tab-count"><?php echo e($usersByType->get('buyer', 0)); ?></span></a>
      <a class="tab" data-tab="seller">Sellers <span class="tab-count"><?php echo e($usersByType->get('seller', 0)); ?></span></a>
      <a class="tab" data-tab="rider">Riders <span class="tab-count"><?php echo e($usersByType->get('rider', 0)); ?></span></a>
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
            <td><span class="stamp stamp-<?php echo e($user->account_type); ?>"><?php echo e(ucfirst($user->account_type)); ?></span></td>
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
                <div class="modal-head-main">
                  <span class="modal-icon"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
<?php endif; ?></span>
                  <div class="modal-head-copy">
                    <h3>Manage User</h3>
                    <p><?php echo e($user->given_names); ?> <?php echo e($user->last_name); ?> — <?php echo e(ucfirst($user->account_type)); ?></p>
                  </div>
                </div>
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

                
                <div class="section-card">
                  <div class="section-head"><span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'account']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'account']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span><span>Personal Information</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Full Name</div><div class="field-value"><?php echo e($user->given_names); ?> <?php echo e($user->middle_name ? $user->middle_name.' ' : ''); ?><?php echo e($user->last_name); ?></div></div>
                    <div><div class="field-label">Username</div><div class="field-value mono"><?php echo e($user->username ?? '—'); ?></div></div>
                    <div><div class="field-label">Sex</div><div class="field-value"><?php echo e(ucfirst($user->sex ?? '—')); ?></div></div>
                    <div><div class="field-label">Birthday</div><div class="field-value mono"><?php echo e($user->birthday?->format('M d, Y') ?? '—'); ?></div></div>
                    <div><div class="field-label">Age</div><div class="field-value"><?php echo e($user->age ?? '—'); ?></div></div>
                    <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-<?php echo e($user->status); ?>"><?php echo e(ucfirst($user->status)); ?></span></div></div>
                  </div>
                </div>

                
                <div class="section-card">
                  <div class="section-head"><span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'mail']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'mail']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span><span>Contact &amp; Auth</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Email</div><div class="field-value"><?php echo e($user->email); ?></div></div>
                    <div><div class="field-label">Contact No.</div><div class="field-value mono"><?php echo e($user->contact_no); ?></div></div>
                    <div><div class="field-label">Auth Method</div><div class="field-value"><?php echo e(ucfirst($user->auth_method)); ?></div></div>
                    <div><div class="field-label">Joined</div><div class="field-value mono"><?php echo e($user->created_at->format('M d, Y')); ?></div></div>
                  </div>
                </div>

                
                <div class="section-card">
                  <div class="section-head"><span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'pin']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'pin']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span><span>Address</span></div>
                  <div class="detail-grid">
                    <div class="full"><div class="field-label">Full Address</div><div class="field-value"><?php echo e(collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: '—'); ?></div></div>
                  </div>
                </div>

                
                <div class="section-card">
                  <div class="section-head"><span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'shield']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shield']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span><span>Verification Documents</span></div>
                  <div class="doc-grid">
                    <?php if (isset($component)) { $__componentOriginald06f2463bb36160272e1fa28b235771f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald06f2463bb36160272e1fa28b235771f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-doc-thumb','data' => ['path' => $user->id_file,'label' => 'Government ID']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-doc-thumb'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user->id_file),'label' => 'Government ID']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $attributes = $__attributesOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__attributesOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $component = $__componentOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__componentOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginald06f2463bb36160272e1fa28b235771f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald06f2463bb36160272e1fa28b235771f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-doc-thumb','data' => ['path' => $user->selfie_file,'label' => 'Selfie with ID']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-doc-thumb'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user->selfie_file),'label' => 'Selfie with ID']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $attributes = $__attributesOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__attributesOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $component = $__componentOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__componentOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
                    <?php if($user->account_type === 'seller'): ?>
                    <?php if (isset($component)) { $__componentOriginald06f2463bb36160272e1fa28b235771f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald06f2463bb36160272e1fa28b235771f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-doc-thumb','data' => ['path' => $user->business_permit_file,'label' => 'Business Permit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-doc-thumb'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user->business_permit_file),'label' => 'Business Permit']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $attributes = $__attributesOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__attributesOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $component = $__componentOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__componentOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
                    <?php endif; ?>
                  </div>
                </div>

                
                <?php if($user->account_type === 'seller'): ?>
                <div class="section-card">
                  <div class="section-head"><span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'bag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bag']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span><span>Seller Details</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Business Name</div><div class="field-value"><?php echo e($user->business_name ?? '—'); ?></div></div>
                    <div class="full"><div class="field-label">Categories</div><div class="field-value"><?php echo e($user->categories->pluck('name')->push($user->category_other)->filter()->implode(', ') ?: '—'); ?></div></div>
                  </div>
                </div>
                <?php endif; ?>

                
                <?php if($user->account_type === 'rider'): ?>
                <?php $riderProfile = \App\Models\RiderProfile::where('user_id', $user->id)->first(); ?>
                <?php if($riderProfile): ?>
                <div class="section-card">
                  <div class="section-head"><span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'truck']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'truck']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span><span>Vehicle Information</span></div>
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
                  <div class="doc-grid" style="margin-top:10px">
                    <?php if (isset($component)) { $__componentOriginald06f2463bb36160272e1fa28b235771f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald06f2463bb36160272e1fa28b235771f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-doc-thumb','data' => ['path' => $riderProfile->or_file,'label' => 'OR']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-doc-thumb'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($riderProfile->or_file),'label' => 'OR']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $attributes = $__attributesOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__attributesOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $component = $__componentOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__componentOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginald06f2463bb36160272e1fa28b235771f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald06f2463bb36160272e1fa28b235771f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-doc-thumb','data' => ['path' => $riderProfile->cr_file,'label' => 'CR']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-doc-thumb'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($riderProfile->cr_file),'label' => 'CR']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $attributes = $__attributesOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__attributesOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $component = $__componentOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__componentOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginald06f2463bb36160272e1fa28b235771f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald06f2463bb36160272e1fa28b235771f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-doc-thumb','data' => ['path' => $riderProfile->license_file,'label' => 'Driver\'s License']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-doc-thumb'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($riderProfile->license_file),'label' => 'Driver\'s License']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $attributes = $__attributesOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__attributesOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald06f2463bb36160272e1fa28b235771f)): ?>
<?php $component = $__componentOriginald06f2463bb36160272e1fa28b235771f; ?>
<?php unset($__componentOriginald06f2463bb36160272e1fa28b235771f); ?>
<?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
                <?php if($user->status !== 'suspended'): ?>
                <form method="POST" action="<?php echo e(route('admin.users.suspend', $user->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-outline" type="submit">Suspend</button>
                </form>
                <?php endif; ?>
                <?php if($user->status !== 'rejected'): ?>
                <form method="POST" action="<?php echo e(route('admin.registrations.reject', $user->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-outline-danger" type="submit">Reject</button>
                </form>
                <?php endif; ?>
                <?php if($user->status !== 'approved'): ?>
                <form method="POST" action="<?php echo e(route('admin.users.approve', $user->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-success" type="submit">Activate</button>
                </form>
                <?php endif; ?>
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

<?php echo $__env->make('admin.partials.doc-lightbox', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/users.blade.php ENDPATH**/ ?>