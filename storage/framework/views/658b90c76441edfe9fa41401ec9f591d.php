<?php $__env->startSection('title', 'Product Reviews'); ?>
<?php $__env->startSection('page-title', 'Product Reviews'); ?>
<?php $__env->startSection('page-sub', 'Review and approve seller product submissions'); ?>
<?php use Illuminate\Support\Facades\Storage; ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div><h2>Product Submissions</h2><p><?php echo e($products->count()); ?> total</p></div>
    <div style="display:flex;gap:8px">
      <select class="select" id="statusFilter">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="active">Active</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>
  </div>
  <div class="card-pad" style="padding:0">
    <table class="tbl" id="productsTable">
      <thead>
        <tr><th>Product</th><th>Seller</th><th>Category</th><th>Price</th><th>Submitted</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr data-status="<?php echo e($product->status); ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if($product->image): ?>
                <img src="<?php echo e(Storage::url($product->image)); ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0">
              <?php else: ?>
                <div style="width:44px;height:44px;background:var(--paper);border-radius:8px;display:grid;place-items:center;flex-shrink:0">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
              <?php endif; ?>
              <div>
                <div style="font-weight:650;font-size:13px"><?php echo e($product->name); ?></div>
                <?php if($product->sku): ?>
                  <div style="font-size:11px;color:var(--muted)" class="mono"><?php echo e($product->sku); ?></div>
                <?php endif; ?>
                <?php if($product->description): ?>
                  <div style="font-size:11px;color:var(--muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo e($product->description); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm"><?php echo e(strtoupper(substr($product->seller->given_names,0,1).substr($product->seller->last_name,0,1))); ?></div>
              <div>
                <strong><?php echo e($product->seller->given_names); ?> <?php echo e($product->seller->last_name); ?></strong>
                <span><?php echo e($product->seller->email); ?></span>
              </div>
            </div>
          </td>
          <td style="font-size:12px"><?php echo e($product->category->name ?? '—'); ?></td>
          <td class="mono">₱<?php echo e(number_format($product->price, 2)); ?></td>
          <td class="mono" style="font-size:12px"><?php echo e($product->created_at->format('M d, Y')); ?></td>
          <td>
            <?php if($product->status === 'pending'): ?>
              <span class="stamp stamp-pending">Pending</span>
            <?php elseif($product->status === 'active'): ?>
              <span class="stamp stamp-active">Active</span>
            <?php elseif($product->status === 'rejected'): ?>
              <span class="stamp stamp-rejected">Rejected</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if($product->status === 'pending'): ?>
              <div style="display:flex;gap:6px">
                <form method="POST" action="<?php echo e(route('admin.products.approve', $product->id)); ?>">
                  <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                  <button class="btn btn-sm btn-success" type="submit">Approve</button>
                </form>
                <button class="btn btn-sm btn-danger" onclick="openReject('<?php echo e($product->id); ?>')">Reject</button>
              </div>
            <?php elseif($product->status === 'rejected' && $product->rejection_note): ?>
              <span style="font-size:11px;color:var(--muted);max-width:140px;display:block"><?php echo e($product->rejection_note); ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="7"><div class="empty"><h3>No product submissions yet</h3></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>


<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(27,22,32,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;width:min(480px,100%);box-shadow:0 24px 60px rgba(27,22,32,.3)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <span style="font-weight:700;font-size:14px">Reject Product</span>
      <button onclick="closeReject()" style="border:0;background:var(--paper);width:30px;height:30px;border-radius:50%;cursor:pointer;display:grid;place-items:center">✕</button>
    </div>
    <form id="rejectForm" method="POST">
      <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
      <div style="padding:20px">
        <div class="form-row">
          <label>Reason for rejection <span style="color:var(--danger)">*</span></label>
          <textarea name="note" rows="4" placeholder="e.g. This product does not match your shop category. Please only submit related products." required></textarea>
          <span style="font-size:11px;color:var(--muted);margin-top:4px;display:block">This message will be shown to the seller.</span>
        </div>
      </div>
      <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px">
        <button type="button" onclick="closeReject()" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject & Notify Seller</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('statusFilter').addEventListener('change', function () {
  const val = this.value;
  document.querySelectorAll('#productsTable tbody tr[data-status]').forEach(row => {
    row.style.display = (!val || row.dataset.status === val) ? '' : 'none';
  });
});

function openReject(id) {
  document.getElementById('rejectForm').action = `/admin/products/${id}/reject`;
  document.getElementById('rejectModal').style.display = 'flex';
}
function closeReject() {
  document.getElementById('rejectModal').style.display = 'none';
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\admin\products.blade.php ENDPATH**/ ?>