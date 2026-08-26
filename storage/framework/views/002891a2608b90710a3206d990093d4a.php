<?php $__env->startSection('title', 'Registrations'); ?>
<?php $__env->startSection('page-title', 'Registrations'); ?>
<?php $__env->startSection('page-sub', 'Review and approve new account applications'); ?>
<?php use Illuminate\Support\Facades\Storage; ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head">
    <div><h2>Applications</h2><p><?php echo e($users->count()); ?> total submissions</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic">🔍</span>
        <input type="text" placeholder="Search name or email…" data-table-search="regTable">
      </div>
      <select class="select" onchange="filterType(this.value)">
        <option value="all">All Types</option>
        <option value="buyer">Buyer</option>
        <option value="seller">Seller</option>
        <option value="rider">Rider</option>
      </select>
    </div>

    <div data-tabs>
      <a class="tab active" data-tab="all">All</a>
      <a class="tab" data-tab="pending">Pending</a>
      <a class="tab" data-tab="approved">Approved</a>
      <a class="tab" data-tab="rejected">Rejected</a>
    </div>

    <div class="table-wrap">
      <table class="dtable" id="regTable">
        <thead>
          <tr><th>Applicant</th><th>Type</th><th>Username</th><th>Method</th><th>Submitted</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="rail-row rail-<?php echo e($user->status); ?>" data-type="<?php echo e($user->status); ?>">
            <td>
              <div class="cell-user">
                <div class="avatar-sm"><?php echo e(strtoupper(substr($user->given_names,0,1).substr($user->last_name,0,1))); ?></div>
                <div>
                  <strong><?php echo e($user->given_names); ?> <?php echo e($user->last_name); ?></strong>
                  <span><?php echo e($user->email); ?></span>
                </div>
              </div>
            </td>
            <td><?php echo e(ucfirst($user->account_type)); ?></td>
            <td class="mono" style="font-size:12px"><?php echo e($user->username ?? '—'); ?></td>
            <td><?php echo e(ucfirst($user->auth_method)); ?></td>
            <td class="mono"><?php echo e($user->created_at->format('M d, Y')); ?></td>
            <td><span class="stamp stamp-<?php echo e($user->status); ?>"><?php echo e(ucfirst($user->status)); ?></span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="reviewModal-<?php echo e($user->id); ?>">Review</button>
              </div>
            </td>
          </tr>

          
          <div class="modal-overlay" id="reviewModal-<?php echo e($user->id); ?>">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div>
                  <h3>Review Application</h3>
                  <p><?php echo e($user->given_names); ?> <?php echo e($user->last_name); ?> — <?php echo e(ucfirst($user->account_type)); ?></p>
                </div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <div class="modal-body">

                
                <p class="crumb" style="margin-bottom:10px">Personal Information</p>
                <div class="detail-grid">
                  <div><div class="field-label">Full Name</div><div class="field-value"><?php echo e($user->given_names); ?> <?php echo e($user->middle_name ? $user->middle_name.' ' : ''); ?><?php echo e($user->last_name); ?></div></div>
                  <div><div class="field-label">Username</div><div class="field-value mono"><?php echo e($user->username ?? '—'); ?></div></div>
                  <div><div class="field-label">Sex</div><div class="field-value"><?php echo e(ucfirst($user->sex ?? '—')); ?></div></div>
                  <div><div class="field-label">Birthday</div><div class="field-value mono"><?php echo e($user->birthday?->format('M d, Y') ?? '—'); ?></div></div>
                  <div><div class="field-label">Age</div><div class="field-value"><?php echo e($user->age ?? '—'); ?></div></div>
                  <div><div class="field-label">Account Type</div><div class="field-value"><?php echo e(ucfirst($user->account_type)); ?></div></div>
                </div>

                
                <p class="crumb" style="margin:14px 0 10px">Contact & Auth</p>
                <div class="detail-grid">
                  <div><div class="field-label">Email</div><div class="field-value"><?php echo e($user->email); ?></div></div>
                  <div><div class="field-label">Contact No.</div><div class="field-value mono"><?php echo e($user->contact_no); ?></div></div>
                  <div><div class="field-label">Auth Method</div><div class="field-value"><?php echo e(ucfirst($user->auth_method)); ?></div></div>
                  <div><div class="field-label">Submitted</div><div class="field-value mono"><?php echo e($user->created_at->format('M d, Y')); ?></div></div>
                </div>

                
                <p class="crumb" style="margin:14px 0 10px">Address</p>
                <div class="detail-grid">
                  <div class="full"><div class="field-label">Full Address</div><div class="field-value">@__raw_block_1__{{ collect([$user->house_no, $user->street, $user->barangay, $mun, $prov])->filter()->implode(', ') ?: '—' }}</div></div>
                </div>

                
                <p class="crumb" style="margin:14px 0 10px">Submitted Documents</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                  <?php if($user->id_file): ?>
                  <a href="<?php echo e(Storage::url($user->id_file)); ?>" target="_blank" class="doc-chip">📄 View ID</a>
                  <?php endif; ?>
                  <?php if($user->selfie_file): ?>
                  <a href="<?php echo e(Storage::url($user->selfie_file)); ?>" target="_blank" class="doc-chip">🤳 View Selfie</a>
                  <?php endif; ?>
                  <?php if($user->account_type === 'seller' && $user->business_permit_file): ?>
                  <a href="<?php echo e(Storage::url($user->business_permit_file)); ?>" target="_blank" class="doc-chip">📋 Business Permit</a>
                  <?php endif; ?>
                  <?php if(!$user->id_file && !$user->selfie_file): ?>
                  <span style="font-size:12px;color:var(--muted)">No documents uploaded.</span>
                  <?php endif; ?>
                </div>

                
                <?php if($user->account_type === 'seller' && $user->business_name): ?>
                <p class="crumb" style="margin:14px 0 10px">Seller Details</p>
                <div class="detail-grid">
                  <div><div class="field-label">Business Name</div><div class="field-value"><?php echo e($user->business_name); ?></div></div>
                  <?php if($user->category_id): ?>
                  <div><div class="field-label">Category</div><div class="field-value"><?php echo e(\App\Models\Category::find($user->category_id)?->name ?? '—'); ?></div></div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>

              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Cancel</button>
                <form method="POST" action="<?php echo e(route('admin.registrations.reject', $user->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-danger" type="submit">Reject</button>
                </form>
                <form method="POST" action="<?php echo e(route('admin.registrations.approve', $user->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-success" type="submit">Approve</button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="7"><div class="empty"><div class="ic">✎</div><h3>No registrations yet</h3></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\registrations.blade.php ENDPATH**/ ?>