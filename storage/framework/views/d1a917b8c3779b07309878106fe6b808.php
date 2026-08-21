<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark">A</div>
    <div>
      <div class="name">PocketFinds</div>
      <span class="tag">Admin Console</span>
    </div>
  </div>

  <div class="nav-groups">
    <div class="nav-label">Main</div>
    <a href="<?php echo e(route('admin.dashboard')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.dashboard') ? 'active' : ''); ?>">
      <span class="ic">⊞</span> Dashboard
    </a>
    <a href="<?php echo e(route('admin.registrations')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.registrations') ? 'active' : ''); ?>">
      <span class="ic">✎</span> Registrations
      <span class="count"><?php echo e($pendingRegistrations); ?></span>
    </a>
    <a href="<?php echo e(route('admin.users')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.users') ? 'active' : ''); ?>">
      <span class="ic">👤</span> User Accounts
    </a>

    <div class="nav-label">Compliance</div>
    <a href="<?php echo e(route('admin.compliance')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.compliance') ? 'active' : ''); ?>">
      <span class="ic">🛡</span> Seller Compliance
    </a>
    <a href="<?php echo e(route('admin.doc-requests')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.doc-requests') ? 'active' : ''); ?>">
      <span class="ic">📄</span> Doc Requests
      <?php $pendingDocs = \App\Models\DocumentUpdateRequest::where('status','pending')->count(); ?>
      <?php if($pendingDocs): ?> <span class="count"><?php echo e($pendingDocs); ?></span> <?php endif; ?>
    </a>
    <a href="<?php echo e(route('admin.complaints')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.complaints') ? 'active' : ''); ?>">
      <span class="ic">⚑</span> Complaints &amp; Disputes
      <span class="count"><?php echo e($openDisputes); ?></span>
    </a>

    <div class="nav-label">Finance</div>
    <a href="<?php echo e(route('admin.commission')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.commission') ? 'active' : ''); ?>">
      <span class="ic">₱</span> Commission
    </a>
    <a href="<?php echo e(route('admin.reports')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.reports') ? 'active' : ''); ?>">
      <span class="ic">📊</span> Reports
    </a>

    <div class="nav-label">System</div>
    <a href="<?php echo e(route('admin.messages')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.messages') ? 'active' : ''); ?>">
      <span class="ic">✉</span> Messages
    </a>
    <a href="<?php echo e(route('admin.settings')); ?>" class="nav-item <?php echo e(request()->routeIs('admin.settings') ? 'active' : ''); ?>">
      <span class="ic">⚙</span> Settings
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <div class="avatar"><?php echo e(strtoupper(substr(auth()->user()->first_name, 0, 1))); ?></div>
      <div class="who">
        <strong><?php echo e(auth()->user()->first_name); ?> <?php echo e(auth()->user()->last_name); ?></strong>
        <span><?php echo e(ucfirst(auth()->user()->account_type)); ?></span>
      </div>
    </div>
    <button class="logout-btn" data-logout>✕ Sign out</button>
  </div>
</nav>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\partials\sidebar.blade.php ENDPATH**/ ?>