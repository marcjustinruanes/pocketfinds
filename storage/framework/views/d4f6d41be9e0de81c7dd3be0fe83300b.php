<?php $__env->startSection('title', 'Document Requests'); ?>
<?php $__env->startSection('page-title', 'Document Requests'); ?>
<?php $__env->startSection('page-sub', 'Review seller document update requests'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<?php
  $pendingDocRequests  = $requests->where('status', 'pending')->count();
  $approvedDocRequests = $requests->where('status', 'approved')->count();
  $rejectedDocRequests = $requests->where('status', 'rejected')->count();
?>
<div class="kpi-grid">
  <button type="button" class="kpi kpi-filter" data-status-kpi="">
    <div class="label">Total Requests</div>
    <div class="value"><?php echo e($requests->count()); ?></div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="pending">
    <div class="label">Pending Review</div>
    <div class="value"><?php echo e($pendingDocRequests); ?></div>
    <div class="delta <?php echo e($pendingDocRequests > 0 ? 'down' : 'up'); ?>">Needs attention</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="approved">
    <div class="label">Approved</div>
    <div class="value"><?php echo e($approvedDocRequests); ?></div>
    <div class="delta up">Applied to account</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="rejected">
    <div class="label">Rejected</div>
    <div class="value"><?php echo e($rejectedDocRequests); ?></div>
  </button>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Document Update Requests</h2><p><?php echo e($requests->count()); ?> total</p></div>
  </div>
  <div class="card-pad">
    <div class="table-wrap">
      <table class="dtable" id="docRequestsTable">
        <thead>
          <tr><th>Seller</th><th>Submitted</th><th>ID Type</th><th>Files</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="rail-row rail-<?php echo e($req->status); ?>" data-status="<?php echo e($req->status); ?>">
            <td>
              <div class="cell-user">
                <div class="avatar-sm"><?php echo e(strtoupper(substr($req->user->given_names,0,1).substr($req->user->last_name,0,1))); ?></div>
                <div>
                  <strong><?php echo e($req->user->given_names); ?> <?php echo e($req->user->last_name); ?></strong>
                  <span><?php echo e($req->user->email); ?></span>
                </div>
              </div>
            </td>
            <td class="mono" style="font-size:12px"><?php echo e($req->created_at->format('M d, Y h:i A')); ?></td>
            <td><?php echo e($req->id_type_id ? ($idTypes[$req->id_type_id]->name ?? '—') : '—'); ?></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <?php if($req->id_file): ?>
                  <button type="button" class="btn btn-sm btn-outline" data-doc-trigger
                    data-src="<?php echo e(asset('storage/'.$req->id_file)); ?>"
                    data-type="<?php echo e(in_array(strtolower(pathinfo($req->id_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf'); ?>"
                    data-title="ID File"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'eye']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'eye']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> ID File</button>
                <?php endif; ?>
                <?php if($req->business_permit_file): ?>
                  <button type="button" class="btn btn-sm btn-outline" data-doc-trigger
                    data-src="<?php echo e(asset('storage/'.$req->business_permit_file)); ?>"
                    data-type="<?php echo e(in_array(strtolower(pathinfo($req->business_permit_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf'); ?>"
                    data-title="Business Permit"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'eye']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'eye']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> Permit</button>
                <?php endif; ?>
                <?php if(!$req->id_file && !$req->business_permit_file): ?> <span style="color:var(--muted);font-size:12px">None</span> <?php endif; ?>
              </div>
            </td>
            <td><span class="stamp stamp-<?php echo e($req->status); ?>"><?php echo e(ucfirst($req->status)); ?></span></td>
            <td>
              <?php if($req->status === 'pending'): ?>
                <div style="display:flex;gap:6px">
                  <form method="POST" action="<?php echo e(route('admin.doc-requests.approve', $req->id)); ?>">
                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                    <button class="btn btn-sm btn-success" type="submit">Approve</button>
                  </form>
                  <button class="btn btn-sm btn-outline-danger" onclick="openReject(<?php echo e($req->id); ?>)">Reject</button>
                </div>
              <?php else: ?>
                <span style="font-size:12px;color:var(--muted)"><?php echo e($req->reviewed_at?->format('M d, Y') ?? '—'); ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="6"><div class="empty"><div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
<?php endif; ?></div><h3>No document requests</h3></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon" style="background:var(--danger-soft);color:var(--danger)"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
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
<?php endif; ?></span>
        <div class="modal-head-copy">
          <h3>Reject Request</h3>
          <p>Let the seller know why their document update was declined.</p>
        </div>
      </div>
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
    <form id="rejectForm" method="POST">
      <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
      <div class="modal-body">
        <div class="form-row"><label>Reason (optional)</label><textarea name="note" rows="3" placeholder="Tell the seller why their request was rejected…"></textarea></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Reject</button>
      </div>
    </form>
  </div>
</div>

<script>
function applyDocRequestFilter(val) {
  document.querySelectorAll('#docRequestsTable tbody tr[data-status]').forEach(row => {
    row.style.display = (!val || row.dataset.status === val) ? '' : 'none';
  });
  document.querySelectorAll('.kpi-filter').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.statusKpi === val);
  });
}
document.querySelectorAll('.kpi-filter').forEach(btn => {
  btn.addEventListener('click', () => applyDocRequestFilter(btn.dataset.statusKpi));
});
applyDocRequestFilter('');

function openReject(id) {
  document.getElementById('rejectForm').action = `/admin/doc-requests/${id}/reject`;
  document.getElementById('rejectModal').classList.add('open');
}
</script>

<?php echo $__env->make('admin.partials.doc-lightbox', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/doc-requests.blade.php ENDPATH**/ ?>