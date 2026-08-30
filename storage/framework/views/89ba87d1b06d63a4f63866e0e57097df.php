<?php $__env->startSection('title', 'Announcements'); ?>
<?php $__env->startSection('page-title', 'Announcements'); ?>
<?php $__env->startSection('page-sub', 'Broadcast messages to platform users'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  <?php echo e(session('success')); ?>

</div>
<?php endif; ?>

<div class="kpi-grid" style="margin-bottom:18px">
  <div class="kpi">
    <div class="label">Total Announcements</div><div class="value"><?php echo e(number_format($total)); ?></div>
  </div>
  <div class="kpi tone-success">
    <div class="label">Active</div><div class="value"><?php echo e(number_format($active)); ?></div><div class="delta up">Currently visible</div>
  </div>
  <div class="kpi tone-info">
    <div class="label">All Users</div><div class="value"><?php echo e(number_format($byAudience['all'] ?? 0)); ?></div>
  </div>
  <div class="kpi">
    <div class="label">Latest Posted</div><div class="value" style="font-size:15px"><?php echo e($latest?->created_at?->format('M d, Y') ?? '—'); ?></div>
  </div>
</div>

<div class="kpi-grid" style="margin-bottom:18px">
  <div class="kpi tone-info">
    <div class="label">Buyers Only</div><div class="value"><?php echo e(number_format($byAudience['buyer'] ?? 0)); ?></div>
  </div>
  <div class="kpi">
    <div class="label">Sellers Only</div><div class="value"><?php echo e(number_format($byAudience['seller'] ?? 0)); ?></div>
  </div>
  <div class="kpi tone-warning">
    <div class="label">Riders Only</div><div class="value"><?php echo e(number_format($byAudience['rider'] ?? 0)); ?></div>
  </div>
</div>

<div class="dash-grid">
  <div class="card">
    <div class="card-head">
      <div class="modal-head-main">
        <span class="modal-icon"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'megaphone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'megaphone']); ?>
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
        <div class="modal-head-copy"><h2 style="margin:0">Post Announcement</h2><p>Broadcast a message to platform users</p></div>
      </div>
    </div>
    <div class="card-pad">
      <form method="POST" action="<?php echo e(route('admin.settings.announcements.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-row">
          <label>Title</label>
          <input type="text" name="title" placeholder="Announcement title" required value="<?php echo e(old('title')); ?>">
        </div>
        <div class="form-row">
          <label>Message</label>
          <textarea name="body" rows="4" placeholder="Write your announcement here..." required><?php echo e(old('body')); ?></textarea>
        </div>
        <div class="form-row">
          <label>Audience</label>
          <select name="audience">
            <option value="all">All Users</option>
            <option value="buyer">Buyers Only</option>
            <option value="seller">Sellers Only</option>
            <option value="rider">Riders Only</option>
          </select>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'megaphone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'megaphone']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> Post Announcement</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Active Announcements</h2><p><?php echo e($announcements->count()); ?> total</p></div></div>
    <div style="max-height:480px;overflow-y:auto">
      <?php $__empty_1 = true; $__currentLoopData = $announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ann): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div style="padding:14px 18px;border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
          <div style="min-width:0">
            <div style="font-weight:700;font-size:13.5px"><?php echo e($ann->title); ?></div>
            <div style="font-size:12px;color:var(--muted);margin:3px 0"><?php echo e($ann->body); ?></div>
            <div style="display:flex;gap:8px;margin-top:6px;align-items:center">
              <span class="stamp stamp-approved"><?php echo e(ucfirst($ann->audience)); ?></span>
              <span style="font-size:11px;color:var(--muted);font-family:var(--font-mono)"><?php echo e($ann->created_at?->format('Y-m-d')); ?></span>
            </div>
          </div>
          <form method="POST" action="<?php echo e(route('admin.settings.announcements.destroy', $ann->id)); ?>" style="flex:none">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button class="btn btn-sm btn-danger icon-only" type="submit" onclick="return confirm('Delete this announcement?')" aria-label="Delete announcement"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'trash']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'trash']); ?>
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
          </form>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="empty"><div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'megaphone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'megaphone']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></div><h3>No announcements yet</h3></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/announcements.blade.php ENDPATH**/ ?>