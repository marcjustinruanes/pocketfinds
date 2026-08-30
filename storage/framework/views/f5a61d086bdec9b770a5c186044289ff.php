<?php $__env->startSection('title', 'Messages'); ?>
<?php $__env->startSection('page-title', 'Messages'); ?>
<?php $__env->startSection('page-sub', 'Platform messaging and support inbox'); ?>

<?php $__env->startSection('content'); ?>
<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <div style="position:relative">
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted)" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" width="14" height="14"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M15 15l-3-3"/></svg>
        <input type="text" style="width:100%;border:1px solid var(--border);border-radius:9px;padding:8px 12px 8px 32px;font-size:13px;font-family:var(--font-body)" placeholder="Search users…">
      </div>
    </div>
    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <a href="<?php echo e(route('admin.messages.user', $u->id)); ?>" class="chat-conv <?php echo e(isset($selectedUser) && $selectedUser?->id == $u->id ? 'active' : ''); ?> <?php echo e($u->unread_count > 0 ? 'has-unread' : ''); ?>" style="text-decoration:none;color:inherit">
      <div class="avatar-sm"><?php echo e(strtoupper(substr($u->given_names,0,1).substr($u->last_name,0,1))); ?></div>
      <div class="meta">
        <strong><?php echo e($u->given_names); ?> <?php echo e($u->last_name); ?></strong>
        <div class="role-tag"><?php echo e(ucfirst($u->account_type)); ?></div>
        <p>
          <?php if($u->last_message): ?>
            <?php if($u->last_message->sender_id === auth()->id()): ?><span style="color:var(--muted)">You: </span><?php endif; ?>
            <?php echo e($u->last_message->body ?: '📎 Attachment'); ?>

          <?php else: ?>
            <?php echo e($u->email); ?>

          <?php endif; ?>
        </p>
      </div>
      <div class="chat-conv-side">
        <?php if($u->last_message): ?>
        <span class="chat-conv-time"><?php echo e($u->last_message->created_at->diffForHumans(null, true)); ?></span>
        <?php endif; ?>
        <?php if($u->unread_count > 0): ?>
        <span class="unread"><?php echo e($u->unread_count); ?></span>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="empty" style="padding:40px 20px">
      <div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
<?php endif; ?></div>
      <h3>No users yet</h3>
      <p>Platform accounts will appear here.</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="chat-main">
    <?php if(isset($selectedUser) && $selectedUser): ?>
    <div class="chat-head">
      <div class="avatar-sm"><?php echo e(strtoupper(substr($selectedUser->given_names,0,1).substr($selectedUser->last_name,0,1))); ?></div>
      <div>
        <strong style="font-size:13.5px;font-family:var(--font-body)"><?php echo e($selectedUser->given_names); ?> <?php echo e($selectedUser->last_name); ?></strong>
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)"><?php echo e(ucfirst($selectedUser->account_type)); ?> · <?php echo e($selectedUser->email); ?></div>
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
      <form method="POST" action="<?php echo e(route('admin.messages.send', $selectedUser->id)); ?>" style="display:flex;gap:10px;flex:1">
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
        <div style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">Choose a user from the list</div>
      </div>
    </div>
    <div class="chat-body">
      <div class="empty" style="margin:auto">
        <div class="ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>No conversation selected</h3>
        <p>Pick a user from the left to start messaging.</p>
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

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/messages.blade.php ENDPATH**/ ?>