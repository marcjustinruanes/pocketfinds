<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle aria-label="Toggle navigation">
    <?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'menu']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'menu']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
  </button>
  <div class="page-heading">
    <h1><?php echo $__env->yieldContent('page-title', 'Logistics'); ?></h1>
    <p><?php echo $__env->yieldContent('page-sub', ''); ?></p>
  </div>
  <div class="topbar-search">
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
    <input type="text" placeholder="Search...">
  </div>
  <div class="topbar-actions">
    <div class="dropdown">
      <button class="icon-btn" data-dropdown-toggle="logisticsNotifPanel" aria-label="Notifications">
        <?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'bell']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bell']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
        <?php if(($pendingDeliveries ?? 0) > 0 || ($unassigned ?? 0) > 0): ?>
        <span class="dot-badge"></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-panel" id="logisticsNotifPanel">
        <div class="dropdown-head">
          <h3>Notifications</h3>
        </div>
        <div class="notif-list">
          <?php if(($pendingDeliveries ?? 0) > 0): ?>
          <a href="<?php echo e(route('logistics.requests')); ?>" class="notif-item" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p><?php echo e($pendingDeliveries); ?> delivery request<?php echo e($pendingDeliveries > 1 ? 's' : ''); ?> pending approval.</p></div>
          </a>
          <?php endif; ?>
          <?php if(($unassigned ?? 0) > 0): ?>
          <a href="<?php echo e(route('logistics.assign')); ?>" class="notif-item" style="text-decoration:none;color:inherit;display:flex">
            <div class="dot"></div>
            <div><p><?php echo e($unassigned); ?> shipment<?php echo e($unassigned > 1 ? 's' : ''); ?> unassigned.</p></div>
          </a>
          <?php endif; ?>
          <?php if(($pendingDeliveries ?? 0) === 0 && ($unassigned ?? 0) === 0): ?>
          <div class="notif-item read" style="display:flex">
            <div class="dot"></div>
            <div><p>No new notifications.</p></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <a href="<?php echo e(route('logistics.account')); ?>" class="topbar-avatar" aria-label="My Account">
      <?php echo e(strtoupper(substr(auth()->user()->first_name, 0, 1))); ?>

    </a>
  </div>
</header>
<?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/logistics/partials/topbar.blade.php ENDPATH**/ ?>