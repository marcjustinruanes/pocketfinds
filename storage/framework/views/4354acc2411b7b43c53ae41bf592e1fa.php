<?php $__env->startSection('title', 'Customer Feedback'); ?>
<?php $__env->startSection('page-title', 'Customer Feedback'); ?>
<?php $__env->startSection('page-sub', 'Reviews and ratings from your buyers'); ?>

<?php $__env->startSection('content'); ?>
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="kpi"><div class="label">Avg. Rating</div><div class="value">4.8</div><div class="delta up">Out of 5</div></div>
  <div class="kpi"><div class="label">Total Reviews</div><div class="value">0</div><div class="delta">All time</div></div>
  <div class="kpi"><div class="label">5 Stars</div><div class="value">0%</div><div class="delta up">—</div></div>
  <div class="kpi"><div class="label">Needs Response</div><div class="value">0</div><div class="delta">Unanswered</div></div>
</div>

<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h2>All Reviews</h2><p>Customer feedback on your products</p></div>
        <select class="select"><option>All Ratings</option><option>5 Stars</option><option>4 Stars</option><option>3 Stars</option><option>1-2 Stars</option></select>
      </div>
      <div class="card-pad">
        <?php $reviews = [
          ['Alice','Sample Product',5,'Great product! Fast delivery too.','2025-01-10',true],
          ['Bob','Sample Product',4,'Good quality, will buy again.','2025-01-08',false],
        ]; ?>
        <?php $__currentLoopData = $reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$user,$product,$rating,$comment,$date,$replied]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="padding:16px 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:12px"><?php echo e(strtoupper(substr($user,0,1))); ?></div>
              <div>
                <div style="font-size:13px;font-weight:650"><?php echo e($user); ?></div>
                <div style="font-size:11px;color:var(--muted)"><?php echo e($product); ?></div>
              </div>
            </div>
            <span style="font-family:var(--font-mono);font-size:10.5px;color:var(--muted)"><?php echo e($date); ?></span>
          </div>
          <div style="margin-bottom:6px">
            <?php for($s=1;$s<=5;$s++): ?><span style="color:<?php echo e($s<=$rating ? '#f59e0b' : 'var(--border)'); ?>;font-size:15px">★</span><?php endfor; ?>
          </div>
          <p style="margin:0 0 10px;font-size:13px;color:var(--text)"><?php echo e($comment); ?></p>
          <?php if(!$replied): ?>
          <button class="btn btn-sm btn-outline" data-modal="replyModal"><?php echo $__env->make('seller.partials.icon', ['name' => 'send', 'size' => 12], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Reply</button>
          <?php else: ?>
          <span class="stamp stamp-active">Replied</span>
          <?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Rating Breakdown</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <?php $__currentLoopData = [5,4,3,2,1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $star): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div style="display:flex;align-items:center;gap:10px;font-size:12px">
          <span style="width:14px;text-align:right;color:var(--muted)"><?php echo e($star); ?></span>
          <span style="color:#f59e0b">★</span>
          <div style="flex:1;height:8px;background:var(--border);border-radius:4px;overflow:hidden">
            <div style="height:100%;background:<?php echo e($star>=4 ? '#f59e0b' : 'var(--border)'); ?>;width:<?php echo e($star===5?'70%':($star===4?'20%':'5%')); ?>;border-radius:4px"></div>
          </div>
          <span style="width:28px;text-align:right;color:var(--muted);font-family:var(--font-mono)">0</span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>
</div>


<div class="modal-overlay" id="replyModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-head">
      <div><h3>Reply to Review</h3><p>Your response will be visible to all buyers</p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div class="form-row"><label>Your Reply</label><textarea rows="4" placeholder="Thank the customer or address their concern…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Cancel</button>
      <button class="btn btn-primary"><?php echo $__env->make('seller.partials.icon', ['name' => 'send', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Send Reply</button>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\feedback.blade.php ENDPATH**/ ?>