<?php $__env->startSection('title', 'Complaints & Disputes'); ?>
<?php $__env->startSection('page-title', 'Complaints & Disputes'); ?>
<?php $__env->startSection('page-sub', 'Manage buyer/seller disputes and platform complaints'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-head"><div><h2>Dispute Cases</h2><p><?php echo e($complaints->count()); ?> total cases</p></div></div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
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
        <input type="text" placeholder="Search case..." data-table-search="dispTable">
      </div>
    </div>
    <?php
      $complaintsByStatus = $complaints->countBy('status');
    ?>
    <div data-tabs>
      <a class="tab active" data-tab="all">All <span class="tab-count"><?php echo e($complaints->count()); ?></span></a>
      <a class="tab" data-tab="open">Open <span class="tab-count"><?php echo e($complaintsByStatus->get('open', 0)); ?></span></a>
      <a class="tab" data-tab="escalated">Escalated <span class="tab-count"><?php echo e($complaintsByStatus->get('escalated', 0)); ?></span></a>
      <a class="tab" data-tab="resolved">Resolved <span class="tab-count"><?php echo e($complaintsByStatus->get('resolved', 0)); ?></span></a>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="dispTable">
        <thead><tr><th>Case ID</th><th>Filed By</th><th>Against</th><th>Type</th><th>Subject</th><th>Date</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $complaints; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr class="rail-row rail-<?php echo e($c->status); ?>" data-type="<?php echo e($c->status); ?>">
            <td class="mono">#<?php echo e(strtoupper(substr($c->id, 0, 8))); ?></td>
            <td>
              <?php if($c->complainant): ?>
                <div class="cell-user">
                  <div class="avatar-sm"><?php echo e(strtoupper(substr($c->complainant->given_names,0,1).substr($c->complainant->last_name,0,1))); ?></div>
                  <div><strong><?php echo e($c->complainant->given_names); ?> <?php echo e($c->complainant->last_name); ?></strong><span><?php echo e(ucfirst($c->complainant->account_type)); ?></span></div>
                </div>
              <?php else: ?> — <?php endif; ?>
            </td>
            <td>
              <?php if($c->respondent): ?>
                <div class="cell-user">
                  <div class="avatar-sm"><?php echo e(strtoupper(substr($c->respondent->given_names,0,1).substr($c->respondent->last_name,0,1))); ?></div>
                  <div><strong><?php echo e($c->respondent->given_names); ?> <?php echo e($c->respondent->last_name); ?></strong><span><?php echo e(ucfirst($c->respondent->account_type)); ?></span></div>
                </div>
              <?php else: ?> — <?php endif; ?>
            </td>
            <td style="font-size:12px"><?php echo e($c->complaint_type ?? '—'); ?></td>
            <td style="font-size:12.5px;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo e($c->subject); ?></td>
            <td class="mono" style="font-size:12px"><?php echo e($c->created_at?->format('M d, Y')); ?></td>
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
                <div class="modal-head-main">
                  <span class="modal-icon"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'flag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'flag']); ?>
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
                    <h3>Case #<?php echo e(strtoupper(substr($c->id, 0, 8))); ?></h3>
                    <p><?php echo e($c->complainant?->given_names); ?> vs <?php echo e($c->respondent?->given_names); ?></p>
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
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Filed By</div><div class="field-value"><?php echo e($c->complainant ? $c->complainant->given_names.' '.$c->complainant->last_name.' ('.ucfirst($c->complainant->account_type).')' : '—'); ?></div></div>
                  <div><div class="field-label">Against</div><div class="field-value"><?php echo e($c->respondent ? $c->respondent->given_names.' '.$c->respondent->last_name.' ('.ucfirst($c->respondent->account_type).')' : '—'); ?></div></div>
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
          <tr><td colspan="8"><div class="empty"><div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'flag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'flag']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></div><h3>No complaints yet</h3></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/complaints.blade.php ENDPATH**/ ?>