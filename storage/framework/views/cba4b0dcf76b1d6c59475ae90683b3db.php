<?php $__env->startSection('title', 'Seller Compliance'); ?>
<?php $__env->startSection('page-title', 'Seller Compliance'); ?>
<?php $__env->startSection('page-sub', 'Review seller documents and listings'); ?>
<?php use Illuminate\Support\Facades\Storage; ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head">
    <div><h2>Seller Accounts</h2><p><?php echo e($sellers->count()); ?> sellers</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic">🔍</span>
        <input type="text" placeholder="Search seller…" data-table-search="compTable">
      </div>
    </div>
    <div data-tabs>
      <a class="tab active" data-tab="all">All</a>
      <a class="tab" data-tab="pending">Pending</a>
      <a class="tab" data-tab="approved">Approved</a>
      <a class="tab" data-tab="rejected">Rejected</a>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="compTable">
        <thead>
          <tr><th>Seller</th><th>Business</th><th>Contact</th><th>Registered</th><th>Documents</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $sellers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $seller): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="rail-row rail-<?php echo e($seller->status); ?>" data-type="<?php echo e($seller->status); ?>">
            <td>
              <div class="cell-user">
                <div class="avatar-sm"><?php echo e(strtoupper(substr($seller->given_names,0,1).substr($seller->last_name,0,1))); ?></div>
                <div>
                  <strong><?php echo e($seller->given_names); ?> <?php echo e($seller->last_name); ?></strong>
                  <span><?php echo e($seller->email); ?></span>
                </div>
              </div>
            </td>
            <td style="font-size:12.5px"><?php echo e($seller->business_name ?? '—'); ?></td>
            <td class="mono"><?php echo e($seller->contact_no); ?></td>
            <td class="mono"><?php echo e($seller->created_at->format('M d, Y')); ?></td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap">
                <?php if($seller->id_file): ?>
                  <a href="<?php echo e(Storage::url($seller->id_file)); ?>" target="_blank" class="doc-chip" style="padding:4px 8px;font-size:11px">📄 ID</a>
                <?php endif; ?>
                <?php if($seller->selfie_file): ?>
                  <a href="<?php echo e(Storage::url($seller->selfie_file)); ?>" target="_blank" class="doc-chip" style="padding:4px 8px;font-size:11px">🤳 Selfie</a>
                <?php endif; ?>
                <?php if($seller->business_permit_file): ?>
                  <a href="<?php echo e(Storage::url($seller->business_permit_file)); ?>" target="_blank" class="doc-chip" style="padding:4px 8px;font-size:11px">📋 Permit</a>
                <?php endif; ?>
                <?php if(!$seller->id_file && !$seller->selfie_file && !$seller->business_permit_file): ?>
                  <span style="color:var(--muted);font-size:12px">None</span>
                <?php endif; ?>
              </div>
            </td>
            <td><span class="stamp stamp-<?php echo e($seller->status); ?>"><?php echo e(ucfirst($seller->status)); ?></span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="compModal-<?php echo e($seller->id); ?>">Review</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="compModal-<?php echo e($seller->id); ?>">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div>
                  <h3>Compliance Review</h3>
                  <p><?php echo e($seller->given_names); ?> <?php echo e($seller->last_name); ?> — <?php echo e($seller->business_name ?? 'No business name'); ?></p>
                </div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <div class="modal-body">

                
                <p class="crumb" style="margin-bottom:10px">Personal Information</p>
                <div class="detail-grid">
                  <div><div class="field-label">Full Name</div><div class="field-value"><?php echo e($seller->given_names); ?> <?php echo e($seller->middle_name ? $seller->middle_name.' ' : ''); ?><?php echo e($seller->last_name); ?></div></div>
                  <div><div class="field-label">Username</div><div class="field-value mono"><?php echo e($seller->username ?? '—'); ?></div></div>
                  <div><div class="field-label">Email</div><div class="field-value"><?php echo e($seller->email); ?></div></div>
                  <div><div class="field-label">Contact No.</div><div class="field-value mono"><?php echo e($seller->contact_no); ?></div></div>
                  <div><div class="field-label">Sex</div><div class="field-value"><?php echo e(ucfirst($seller->sex ?? '—')); ?></div></div>
                  <div><div class="field-label">Birthday</div><div class="field-value mono"><?php echo e($seller->birthday?->format('M d, Y') ?? '—'); ?></div></div>
                  <div><div class="field-label">Age</div><div class="field-value"><?php echo e($seller->age ?? '—'); ?></div></div>
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-<?php echo e($seller->status); ?>"><?php echo e(ucfirst($seller->status)); ?></span></div></div>
                  <div class="full"><div class="field-label">Address</div><div class="field-value"><?php echo e(collect([$seller->house_no, $seller->street, $seller->barangay, $seller->municipality, $seller->province])->filter()->implode(', ') ?: '—'); ?></div></div>
                </div>

                
                <p class="crumb" style="margin:14px 0 10px">Seller Details</p>
                <div class="detail-grid">
                  <div><div class="field-label">Business Name</div><div class="field-value"><?php echo e($seller->business_name ?? '—'); ?></div></div>
                  <div><div class="field-label">Category</div><div class="field-value"><?php echo e(\App\Models\Category::find($seller->category_id)?->name ?? '—'); ?></div></div>
                  <div><div class="field-label">Auth Method</div><div class="field-value"><?php echo e(ucfirst($seller->auth_method)); ?></div></div>
                  <div><div class="field-label">Registered</div><div class="field-value mono"><?php echo e($seller->created_at->format('M d, Y')); ?></div></div>
                </div>

                
                <p class="crumb" style="margin:14px 0 10px">Submitted Documents</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                  <?php if($seller->id_file): ?>
                  <a href="<?php echo e(Storage::url($seller->id_file)); ?>" target="_blank" class="doc-chip">📄 View ID</a>
                  <?php endif; ?>
                  <?php if($seller->selfie_file): ?>
                  <a href="<?php echo e(Storage::url($seller->selfie_file)); ?>" target="_blank" class="doc-chip">🤳 View Selfie</a>
                  <?php endif; ?>
                  <?php if($seller->business_permit_file): ?>
                  <a href="<?php echo e(Storage::url($seller->business_permit_file)); ?>" target="_blank" class="doc-chip">📋 Business Permit</a>
                  <?php endif; ?>
                  <?php if(!$seller->id_file && !$seller->selfie_file && !$seller->business_permit_file): ?>
                  <span style="font-size:12px;color:var(--muted)">No documents uploaded.</span>
                  <?php endif; ?>
                </div>

              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Cancel</button>
                <form method="POST" action="<?php echo e(route('admin.registrations.reject', $seller->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-danger" type="submit">Reject</button>
                </form>
                <form method="POST" action="<?php echo e(route('admin.registrations.approve', $seller->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-success" type="submit">Approve</button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="7"><div class="empty"><div class="ic">🛡</div><h3>No sellers yet</h3></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\compliance.blade.php ENDPATH**/ ?>