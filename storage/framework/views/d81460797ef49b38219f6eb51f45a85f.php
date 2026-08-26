<?php $__env->startSection('title', 'Settings'); ?>
<?php $__env->startSection('page-title', 'Settings'); ?>
<?php $__env->startSection('page-sub', 'Logistics preferences and configuration'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  <?php echo e(session('success')); ?>

</div>
<?php endif; ?>

<form method="POST" action="<?php echo e(route('logistics.settings.update')); ?>">
  <?php echo csrf_field(); ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">

    <div class="card">
      <div class="card-head"><h2>General</h2></div>
      <div class="card-pad">
        <div class="switch-row">
          <div>
            <strong>Email Notifications</strong>
            <span>Receive alerts for new delivery requests</span>
          </div>
          <label class="switch">
            <input type="checkbox" name="email_notifications" value="1" <?php echo e(($settings['email_notifications'] ?? '0') === '1' ? 'checked' : ''); ?>>
            <span class="track"></span>
          </label>
        </div>
        <div class="switch-row">
          <div>
            <strong>Auto-assign Couriers</strong>
            <span>Automatically assign available couriers to pending shipments on save</span>
          </div>
          <label class="switch">
            <input type="checkbox" name="auto_assign" value="1" <?php echo e(($settings['auto_assign'] ?? '0') === '1' ? 'checked' : ''); ?>>
            <span class="track"></span>
          </label>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Delivery Rules</h2></div>
      <div class="card-pad">
        <div class="form-row">
          <label>Max Deliveries per Courier</label>
          <input type="number" name="max_deliveries_per_courier" value="<?php echo e($settings['max_deliveries_per_courier'] ?? 10); ?>" min="1" max="50" required>
          <?php $__errorArgs = ['max_deliveries_per_courier'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div style="color:var(--danger);font-size:12px;margin-top:4px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="form-row">
          <label>Delivery Timeout (hours)</label>
          <input type="number" name="delivery_timeout_hours" value="<?php echo e($settings['delivery_timeout_hours'] ?? 24); ?>" min="1" max="72" required>
          <?php $__errorArgs = ['delivery_timeout_hours'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div style="color:var(--danger);font-size:12px;margin-top:4px"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
      </div>
    </div>

  </div>
  <div style="margin-top:18px">
    <button class="btn btn-primary" type="submit">Save Settings</button>
  </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\settings.blade.php ENDPATH**/ ?>