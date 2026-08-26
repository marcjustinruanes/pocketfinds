<?php $__env->startSection('title', 'Messages'); ?>
<?php $__env->startSection('page-title', 'Messages'); ?>
<?php $__env->startSection('page-sub', 'Platform messaging and support inbox'); ?>

<?php $__env->startSection('content'); ?>
<style>
  .content { padding: 0 !important; }
  .chat-shell { border-radius: 0; border-left: 0; border-right: 0; border-bottom: 0; height: calc(100vh - 66px); display: flex; }
  .chat-main { display: flex; flex-direction: column; flex: 1; min-height: 0; }
  .chat-body { flex: 1; overflow-y: auto; }
  .chat-input { flex-shrink: 0; }
  .chat-list { overflow-y: auto; }
</style>
<?php if(session('success')): ?>
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  <?php echo e(session('success')); ?>

</div>
<?php endif; ?>
<?php $__errorArgs = ['body'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  <?php echo e($message); ?>

</div>
<?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

<div class="chat-shell">
  <div class="chat-list">
    <div class="chat-list-head">
      <input type="text" data-chat-search style="width:100%;border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px" placeholder="Search users...">
    </div>
    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php
      $unread = \App\Models\Message::where('sender_id', $user->id)->where('receiver_id', auth()->id())->where('read', false)->count();
    ?>
    <a href="<?php echo e(route('admin.messages.user', $user)); ?>" class="chat-conv <?php echo e($selectedUser && $selectedUser->id === $user->id ? 'active' : ''); ?>" data-chat-user>
      <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => $user,'size' => '36','class' => 'avatar-sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user),'size' => '36','class' => 'avatar-sm']); ?>
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
      <div class="meta">
        <strong><?php echo e($user->first_name); ?> <?php echo e($user->last_name); ?></strong>
        <div class="role-tag"><?php echo e(ucfirst($user->account_type)); ?></div>
        <p><?php echo e($user->email); ?></p>
      </div>
      <?php if($unread): ?>
      <span class="unread"><?php echo e($unread); ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px">No users yet.</div>
    <?php endif; ?>
  </div>

  <div class="chat-main">
    <?php if($selectedUser): ?>
    <div class="chat-head">
      <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => $selectedUser,'size' => '36','class' => 'avatar-sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedUser),'size' => '36','class' => 'avatar-sm']); ?>
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
      <div>
        <strong><?php echo e($selectedUser->first_name); ?> <?php echo e($selectedUser->last_name); ?></strong>
        <div style="font-size:11px;color:var(--muted)"><?php echo e(ucfirst($selectedUser->account_type)); ?> - <?php echo e($selectedUser->email); ?></div>
      </div>
    </div>
    <div class="chat-body" id="chatBody">
      <?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="bubble <?php echo e($message->sender_id === auth()->id() ? 'out' : 'in'); ?>">
        <?php echo e($message->body); ?>

        <time><?php echo e($message->created_at?->format('M d, Y g:i A')); ?></time>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="empty" style="margin:auto">
        <div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
<?php endif; ?></div>
        <h3>No messages yet</h3>
        <p>Start the conversation with <?php echo e($selectedUser->first_name); ?>.</p>
      </div>
      <?php endif; ?>
    </div>
    <form class="chat-input" method="POST" action="<?php echo e(route('admin.messages.send', $selectedUser)); ?>">
      <?php echo csrf_field(); ?>
      <input type="text" name="body" value="<?php echo e(old('body')); ?>" placeholder="Type a message..." maxlength="2000" required>
      <button class="btn btn-primary" type="submit">Send</button>
    </form>
    <?php else: ?>
    <div class="empty" style="margin:auto">
      <div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
<?php endif; ?></div>
      <h3>No users to message</h3>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
  const chatBody = document.getElementById('chatBody');
  if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;

  document.querySelector('[data-chat-search]')?.addEventListener('input', (event) => {
    const query = event.target.value.trim().toLowerCase();
    document.querySelectorAll('[data-chat-user]').forEach((item) => {
      item.hidden = query && !item.textContent.toLowerCase().includes(query);
    });
  });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/messages.blade.php ENDPATH**/ ?>