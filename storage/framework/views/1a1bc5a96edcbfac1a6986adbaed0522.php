<?php $__env->startSection('title', 'Settings'); ?>
<?php $__env->startSection('page-title', 'Platform Settings'); ?>
<?php $__env->startSection('page-sub', 'Manage announcements and platform policies'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  <?php echo e(session('success')); ?>

</div>
<?php endif; ?>

<div data-tabs style="margin-bottom:20px">
  <a class="tab active" data-tab="announcements"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
<?php endif; ?> Announcements</a>
  <a class="tab" data-tab="policies"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'file']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'file']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> Platform Policies</a>
  <a class="tab" data-tab="general"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'settings']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'settings']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> General</a>
</div>

<div data-tab-panel="announcements">
  <div class="dash-grid">
    <div class="card">
      <div class="card-head"><div><h2>Post Announcement</h2><p>Broadcast a message to platform users</p></div></div>
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
          <button class="btn btn-primary" type="submit">Post Announcement</button>
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
</div>

<div data-tab-panel="policies" style="display:none">
  <div class="dash-grid">
    <div class="card">
      <div class="card-head"><div><h2>Add New Policy</h2><p>Create a platform policy document</p></div></div>
      <div class="card-pad">
        <form method="POST" action="<?php echo e(route('admin.settings.policies.store')); ?>">
          <?php echo csrf_field(); ?>
          <div class="form-row">
            <label>Policy Title</label>
            <input type="text" name="title" placeholder="e.g. Terms of Service" required value="<?php echo e(old('title')); ?>">
          </div>
          <div class="form-row">
            <label>Slug <span class="hint">(unique identifier, e.g. terms-of-service)</span></label>
            <input type="text" name="slug" placeholder="terms-of-service" required value="<?php echo e(old('slug')); ?>">
          </div>
          <div class="form-row">
            <label>Content</label>
            <textarea name="content" rows="6" placeholder="Write the policy content here..." required><?php echo e(old('content')); ?></textarea>
          </div>
          <button class="btn btn-primary" type="submit">Save Policy</button>
        </form>
      </div>
    </div>

    <div class="stack">
      <?php $__empty_1 = true; $__currentLoopData = $policies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $policy): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="card">
        <div class="card-head">
          <div><h2><?php echo e($policy->title); ?></h2><p>Last updated <?php echo e($policy->updated_at?->format('Y-m-d')); ?></p></div>
          <div style="display:flex;gap:8px">
            <button class="btn btn-sm btn-outline" data-modal-open="policyModal-<?php echo e($policy->id); ?>">Edit</button>
            <form method="POST" action="<?php echo e(route('admin.settings.policies.destroy', $policy->id)); ?>">
              <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
              <button class="btn btn-sm btn-danger icon-only" type="submit" onclick="return confirm('Delete this policy?')" aria-label="Delete policy"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
        <div class="card-pad">
          <p style="font-size:13px;color:var(--muted);margin:0;line-height:1.6"><?php echo e(Str::limit($policy->content, 160)); ?></p>
        </div>
      </div>

      <div class="modal-overlay" id="policyModal-<?php echo e($policy->id); ?>">
        <div class="modal modal-lg">
          <div class="modal-head">
            <div><h3>Edit Policy</h3><p><?php echo e($policy->title); ?></p></div>
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
          <form method="POST" action="<?php echo e(route('admin.settings.policies.update', $policy->id)); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
            <div class="modal-body">
              <div class="form-row">
                <label>Title</label>
                <input type="text" name="title" value="<?php echo e($policy->title); ?>" required>
              </div>
              <div class="form-row">
                <label>Content</label>
                <textarea name="content" rows="8" required><?php echo e($policy->content); ?></textarea>
              </div>
            </div>
            <div class="modal-foot">
              <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
              <button class="btn btn-primary" type="submit">Update Policy</button>
            </div>
          </form>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="card"><div class="empty"><div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'file']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'file']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></div><h3>No policies yet</h3><p>Add your first platform policy.</p></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div data-tab-panel="general" style="display:none">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
    <div class="card">
      <div class="card-head"><h2>General</h2></div>
      <div class="card-pad">
        <div class="form-row"><label>Platform Name</label><input type="text" value="PocketFinds"></div>
        <div class="form-row"><label>Support Email</label><input type="email" value="support@pocketfinds.com"></div>
        <div class="form-row"><label>Commission Rate (%)</label><input type="number" value="10" min="0" max="100"></div>
        <button class="btn btn-primary" data-toast="Settings saved!">Save Changes</button>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Feature Toggles</h2></div>
      <div class="card-pad">
        <div class="switch-row">
          <div><strong>Google Sign-In</strong><span>Allow users to register via Google</span></div>
          <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>New Registrations</strong><span>Accept new account applications</span></div>
          <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>Maintenance Mode</strong><span>Take the platform offline</span></div>
          <label class="switch"><input type="checkbox"><span class="track"></span></label>
        </div>
        <div class="switch-row">
          <div><strong>Email Notifications</strong><span>Send system emails to users</span></div>
          <label class="switch"><input type="checkbox" checked><span class="track"></span></label>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Danger Zone</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-danger" data-toast="Cache cleared!">Clear Application Cache</button>
        <button class="btn btn-danger" data-toast="Sessions cleared!">Clear All Sessions</button>
      </div>
    </div>
  </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.querySelectorAll('[data-tabs] .tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('[data-tabs] .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    document.querySelectorAll('[data-tab-panel]').forEach(p => p.style.display = 'none');
    document.querySelector('[data-tab-panel="' + tab.dataset.tab + '"]').style.display = '';
  });
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/settings.blade.php ENDPATH**/ ?>