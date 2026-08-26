<?php $__env->startSection('title', 'Messages'); ?>
<?php $__env->startSection('page-title', 'Messages'); ?>
<?php $__env->startSection('page-sub', 'Communicate with admins, couriers, and sellers'); ?>

<?php $__env->startPush('head'); ?>
<style>
  .content:has(.chat-shell) { padding: 0; }
  .chat-shell { height: calc(100vh - 66px); border-radius: 0; border-left: 0; border-right: 0; border-bottom: 0; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>document.querySelector('.chat-body')?.scrollTo(0, document.querySelector('.chat-body').scrollHeight);</script>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <div style="position:relative">
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted)" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" width="14" height="14"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M15 15l-3-3"/></svg>
        <input type="text" style="width:100%;border:1px solid var(--border);border-radius:9px;padding:8px 12px 8px 32px;font-size:13px;font-family:var(--font-body)" placeholder="Search…">
      </div>
    </div>

    <?php if($admins->isNotEmpty()): ?>
    <div style="padding:10px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Admin</div>
    <?php $__currentLoopData = $admins; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('logistics.messages.thread', $u->id)); ?>" class="chat-conv <?php echo e(isset($activeUser) && $activeUser?->id == $u->id ? 'active' : ''); ?>" style="text-decoration:none;color:inherit">
      <div class="avatar-sm"><?php echo e(strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1))); ?></div>
      <div class="meta">
        <strong><?php echo e($u->first_name); ?> <?php echo e($u->last_name); ?></strong>
        <div class="role-tag">Administrator</div>
        <p><?php echo e($u->email); ?></p>
      </div>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>

    <?php if($couriers->isNotEmpty()): ?>
    <div style="padding:10px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Couriers</div>
    <?php $__currentLoopData = $couriers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('logistics.messages.thread', $u->id)); ?>" class="chat-conv <?php echo e(isset($activeUser) && $activeUser?->id == $u->id ? 'active' : ''); ?>" style="text-decoration:none;color:inherit">
      <div class="avatar-sm"><?php echo e(strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1))); ?></div>
      <div class="meta">
        <strong><?php echo e($u->first_name); ?> <?php echo e($u->last_name); ?></strong>
        <div class="role-tag">Courier</div>
        <p><?php echo e($u->email); ?></p>
      </div>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>

    <?php if($sellers->isNotEmpty()): ?>
    <div style="padding:10px 16px 4px;font-family:var(--font-mono);font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)">Sellers</div>
    <?php $__currentLoopData = $sellers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('logistics.messages.thread', $u->id)); ?>" class="chat-conv <?php echo e(isset($activeUser) && $activeUser?->id == $u->id ? 'active' : ''); ?>" style="text-decoration:none;color:inherit">
      <div class="avatar-sm"><?php echo e(strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1))); ?></div>
      <div class="meta">
        <strong><?php echo e($u->first_name); ?> <?php echo e($u->last_name); ?></strong>
        <div class="role-tag">Seller</div>
        <p><?php echo e($u->email); ?></p>
      </div>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>

  </div>

  <div class="chat-main">
    <?php if($activeUser): ?>
    <div class="chat-head">
      <div class="avatar-sm"><?php echo e(strtoupper(substr($activeUser->first_name,0,1).substr($activeUser->last_name,0,1))); ?></div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)"><?php echo e($activeUser->first_name); ?> <?php echo e($activeUser->last_name); ?></strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)"><?php echo e(ucfirst($activeUser->account_type)); ?> · <?php echo e($activeUser->email); ?></div>
      </div>
    </div>
    <div class="chat-body">
      <?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $msg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="bubble <?php echo e($msg->sender_id == auth()->id() ? 'out' : 'in'); ?>">
        <?php echo e($msg->body); ?>

        <time><?php echo e(\Carbon\Carbon::parse($msg->created_at)->format('M d, H:i')); ?></time>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="empty" style="margin:auto">
        <div class="ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>No messages yet</h3>
        <p>Start the conversation below.</p>
      </div>
      <?php endif; ?>
    </div>
    <div class="chat-input">
      <form method="POST" action="<?php echo e(route('logistics.messages.send', $activeUser->id)); ?>" style="display:flex;gap:10px;flex:1">
        <?php echo csrf_field(); ?>
        <input type="text" name="body" placeholder="Type a message…" style="font-family:var(--font-body);font-size:13px" required autocomplete="off">
        <button class="btn btn-primary" type="submit">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M18 2L2 9l6 3 3 6 7-16z"/></svg>
          Send
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="chat-head">
      <div class="avatar-sm">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16"><circle cx="10" cy="7" r="3.5"/><path d="M3 17c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
      </div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)">Select a conversation</strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">Choose a contact from the list</div>
      </div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>No conversation selected</h3>
        <p>Pick a contact from the left to start messaging.</p>
      </div>
    </div>
    <div class="chat-input">
      <input type="text" placeholder="Type a message…" disabled style="font-family:var(--font-body);font-size:13px;opacity:.5">
      <button class="btn btn-primary" disabled style="opacity:.5">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M18 2L2 9l6 3 3 6 7-16z"/></svg>
        Send
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('logistics.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\logistics\messages.blade.php ENDPATH**/ ?>