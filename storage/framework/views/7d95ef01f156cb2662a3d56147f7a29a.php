<?php $__env->startSection('title', 'Complaints & Disputes'); ?>
<?php $__env->startSection('page-title', 'Complaints & Disputes'); ?>
<?php $__env->startSection('page-sub', 'Manage buyer/seller disputes and platform complaints'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><div><h2>Dispute Cases</h2><p><?php echo e($complaints->count()); ?> total cases</p></div></div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic">🔍</span>
        <input type="text" placeholder="Search case…" data-table-search="dispTable">
      </div>
    </div>
    <div data-tabs>
      <a class="tab active" data-tab="all">All</a>
      <a class="tab" data-tab="open">Open</a>
      <a class="tab" data-tab="escalated">Escalated</a>
      <a class="tab" data-tab="resolved">Resolved</a>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="dispTable">
        <thead><tr><th>Case ID</th><th>Filed By</th><th>Against</th><th>Type</th><th>Subject</th><th>Date</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $complaints; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="rail-row rail-<?php echo e($c->status); ?>" data-type="<?php echo e($c->status); ?>">
            <td class="mono">#<?php echo e(strtoupper(substr($c->id, 0, 8))); ?></td>
            <td><?php echo e($c->complainant ? $c->complainant->first_name.' '.$c->complainant->last_name : '—'); ?></td>
            <td><?php echo e($c->respondent ? $c->respondent->first_name.' '.$c->respondent->last_name : '—'); ?></td>
            <td><?php echo e($c->complaint_type ?? '—'); ?></td>
            <td><?php echo e($c->subject); ?></td>
            <td class="mono"><?php echo e($c->created_at?->format('Y-m-d')); ?></td>
            <td><span class="stamp stamp-<?php echo e($c->status); ?>"><?php echo e(ucfirst($c->status)); ?></span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="dispModal-<?php echo e($c->id); ?>">Open</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="dispModal-<?php echo e($c->id); ?>">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div><h3>Case #<?php echo e(strtoupper(substr($c->id, 0, 8))); ?></h3>
                  <p><?php echo e($c->complainant?->first_name); ?> vs <?php echo e($c->respondent?->first_name); ?></p></div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Filed By</div><div class="field-value"><?php echo e($c->complainant ? $c->complainant->first_name.' '.$c->complainant->last_name.' ('.ucfirst($c->complainant->account_type).')' : '—'); ?></div></div>
                  <div><div class="field-label">Against</div><div class="field-value"><?php echo e($c->respondent ? $c->respondent->first_name.' '.$c->respondent->last_name.' ('.ucfirst($c->respondent->account_type).')' : '—'); ?></div></div>
                  <div><div class="field-label">Type</div><div class="field-value"><?php echo e($c->complaint_type ?? '—'); ?></div></div>
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-<?php echo e($c->status); ?>"><?php echo e(ucfirst($c->status)); ?></span></div></div>
                  <div class="full"><div class="field-label">Subject</div><div class="field-value"><?php echo e($c->subject); ?></div></div>
                  <?php if($c->description): ?>
                  <div class="full"><div class="field-label">Description</div><div class="field-value" style="font-size:13px;line-height:1.6"><?php echo e($c->description); ?></div></div>
                  <?php endif; ?>
                  <?php if($c->shop_name || $c->message_id): ?>
                  <div class="full"><div class="field-label">Reported Shop</div><div class="field-value"><?php echo e($c->shop_name ?: '—'); ?></div></div>
                  <div class="full"><div class="field-label">Reported Message</div><div class="field-value" style="font-size:13px;line-height:1.6"><?php echo e($c->message_body ?: ucfirst($c->message_type ?: 'Attachment')); ?></div></div>
                  <?php endif; ?>
                  <?php if($c->evidence_path): ?>
                  <div class="full"><div class="field-label">Evidence</div>
                    <?php if($c->evidence_type === 'video'): ?>
                      <video src="<?php echo e(route('report.evidence', ['path' => $c->evidence_path])); ?>" controls style="max-width:100%;max-height:280px;border-radius:9px"></video>
                    <?php else: ?>
                      <img src="<?php echo e(route('report.evidence', ['path' => $c->evidence_path])); ?>" alt="Report evidence" style="max-width:100%;max-height:280px;object-fit:contain;border-radius:9px">
                    <?php endif; ?>
                    <div style="font-size:11px;color:var(--muted);margin-top:5px"><?php echo e($c->evidence_name); ?></div>
                  </div>
                  <?php endif; ?>
                  <?php if($c->resolution): ?>
                  <div class="full"><div class="field-label">Resolution</div><div class="field-value"><?php echo e($c->resolution); ?></div></div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
                <?php if($c->status !== 'resolved'): ?>
                <form method="POST" action="<?php echo e(route('admin.complaints.resolve', $c->id)); ?>">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-success" type="submit">Mark Resolved</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="8"><div class="empty"><div class="ic">⚑</div><h3>No complaints yet</h3></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\complaints.blade.php ENDPATH**/ ?>