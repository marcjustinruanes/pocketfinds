<?php $__env->startSection('title', 'Messages'); ?>
<?php $__env->startSection('page-title', 'Messages'); ?>
<?php $__env->startSection('page-sub', 'Platform messaging and support inbox'); ?>

<?php $__env->startSection('content'); ?>
<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <input type="text" style="width:100%;border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px" placeholder="Search users…">
    </div>
    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="chat-conv <?php echo e($loop->first ? 'active' : ''); ?>">
      <div class="avatar-sm"><?php echo e(strtoupper(substr($user->first_name,0,1).substr($user->last_name,0,1))); ?></div>
      <div class="meta">
        <strong><?php echo e($user->first_name); ?> <?php echo e($user->last_name); ?></strong>
        <div class="role-tag"><?php echo e(ucfirst($user->account_type)); ?></div>
        <p><?php echo e($user->email); ?></p>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px">No users yet.</div>
    <?php endif; ?>
  </div>

  <div class="chat-main">
    <?php if($users->isNotEmpty()): ?>
    <div class="chat-head">
      <div class="avatar-sm"><?php echo e(strtoupper(substr($users->first()->first_name,0,1).substr($users->first()->last_name,0,1))); ?></div>
      <div>
        <strong><?php echo e($users->first()->first_name); ?> <?php echo e($users->first()->last_name); ?></strong>
        <div style="font-size:11px;color:var(--muted)"><?php echo e(ucfirst($users->first()->account_type)); ?> · <?php echo e($users->first()->email); ?></div>
      </div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic">✉</div>
        <h3>No messages yet</h3>
        <p>Messaging functionality coming soon.</p>
      </div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Type a message…">
      <button class="btn btn-primary" data-toast="Message sent!">Send</button>
    </div>
    <?php else: ?>
    <div class="empty" style="margin:auto">
      <div class="ic">✉</div>
      <h3>No users to message</h3>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\messages.blade.php ENDPATH**/ ?>