<?php $__env->startSection('title', 'Document Requests'); ?>
<?php $__env->startSection('page-title', 'Document Requests'); ?>
<?php $__env->startSection('page-sub', 'Review seller document update requests'); ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div><h2>Document Update Requests</h2><p><?php echo e($requests->count()); ?> total</p></div>
  </div>
  <div class="card-pad">
    <div class="table-wrap">
      <table class="dtable">
        <thead>
          <tr><th>Seller</th><th>Submitted</th><th>ID Type</th><th>Files</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
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
                  <button type="button" class="btn btn-sm btn-outline doc-preview-btn"
                    data-src="<?php echo e(asset('storage/'.$req->id_file)); ?>"
                    data-type="<?php echo e(in_array(strtolower(pathinfo($req->id_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf'); ?>">
                    ID File
                  </button>
                <?php endif; ?>
                <?php if($req->business_permit_file): ?>
                  <button type="button" class="btn btn-sm btn-outline doc-preview-btn"
                    data-src="<?php echo e(asset('storage/'.$req->business_permit_file)); ?>"
                    data-type="<?php echo e(in_array(strtolower(pathinfo($req->business_permit_file,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf'); ?>">
                    Permit
                  </button>
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
                  <button class="btn btn-sm btn-danger" onclick="openReject(<?php echo e($req->id); ?>)">Reject</button>
                </div>
              <?php else: ?>
                <span style="font-size:12px;color:var(--muted)"><?php echo e($req->reviewed_at?->format('M d, Y') ?? '—'); ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="6"><div class="empty"><h3>No document requests</h3></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(27,22,32,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;width:min(480px,100%);box-shadow:0 24px 60px rgba(27,22,32,.3)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <span style="font-weight:700;font-size:14px">Reject Request</span>
      <button onclick="closeReject()" style="border:0;background:var(--paper);width:30px;height:30px;border-radius:50%;cursor:pointer">✕</button>
    </div>
    <form id="rejectForm" method="POST">
      <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
      <div style="padding:20px">
        <div class="form-row"><label>Reason (optional)</label><textarea name="note" rows="3" placeholder="Tell the seller why their request was rejected…"></textarea></div>
      </div>
      <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px">
        <button type="button" onclick="closeReject()" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject</button>
      </div>
    </form>
  </div>
</div>


<div id="docModal" style="display:none;position:fixed;inset:0;background:rgba(27,22,32,.7);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;width:min(780px,100%);max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(27,22,32,.3);overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <span id="docModalTitle" style="font-weight:700;font-size:14px">Document Preview</span>
      <button id="docModalClose" style="border:0;background:var(--paper);width:30px;height:30px;border-radius:50%;font-size:16px;cursor:pointer;display:grid;place-items:center">✕</button>
    </div>
    <div id="docModalBody" style="flex:1;overflow:auto;padding:20px;display:flex;align-items:center;justify-content:center;min-height:300px"></div>
  </div>
</div>

<script>
function openReject(id) {
  document.getElementById('rejectForm').action = `/admin/doc-requests/${id}/reject`;
  document.getElementById('rejectModal').style.display = 'flex';
}
function closeReject() {
  document.getElementById('rejectModal').style.display = 'none';
}

const docModal     = document.getElementById('docModal');
const docModalBody = document.getElementById('docModalBody');
document.getElementById('docModalClose').addEventListener('click', () => { docModal.style.display='none'; docModalBody.innerHTML=''; });
docModal.addEventListener('click', e => { if(e.target===docModal){ docModal.style.display='none'; docModalBody.innerHTML=''; } });
document.querySelectorAll('.doc-preview-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const src = btn.dataset.src, type = btn.dataset.type;
    document.getElementById('docModalTitle').textContent = type==='pdf' ? 'Document Preview' : 'Image Preview';
    docModalBody.innerHTML = type==='image'
      ? `<img src="${src}" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain">`
      : `<iframe src="${src}" style="width:100%;height:70vh;border:0;border-radius:8px"></iframe>`;
    docModal.style.display = 'flex';
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/doc-requests.blade.php ENDPATH**/ ?>