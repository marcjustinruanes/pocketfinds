<?php $__env->startSection('title', 'Inventory'); ?>
<?php $__env->startSection('page-title', 'Inventory'); ?>
<?php $__env->startSection('page-sub', 'Manage your product listings'); ?>
<?php use Illuminate\Support\Facades\Storage; ?>

<?php $__env->startSection('content'); ?>

<?php if(!$seller->category_id): ?>
  <div style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);padding:12px 16px;border-radius:9px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:10px">
    <?php echo $__env->make('seller.partials.icon',['name'=>'bell','size'=>15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <span>You haven't set a shop category yet. <a href="<?php echo e(route('seller.account')); ?>" style="font-weight:700;color:var(--warning);text-decoration:underline">Set it in Account → Shop Information</a> before adding products.</span>
  </div>
<?php endif; ?>

<?php if(session('product_success')): ?>
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 16px;border-radius:9px;font-size:13px;margin-bottom:18px"><?php echo e(session('product_success')); ?></div>
<?php endif; ?>
<?php $__errorArgs = ['image'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
  <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 16px;border-radius:9px;font-size:13px;margin-bottom:18px"><?php echo e($message); ?></div>
<?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

<div class="filter-bar">
  <div class="search-mini">
    <span class="ic"><?php echo $__env->make('seller.partials.icon',['name'=>'search','size'=>13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
    <input type="text" placeholder="Search products…" id="productSearch">
  </div>
  <select class="select" id="statusFilter">
    <option value="">All Status</option>
    <option value="pending">Pending</option>
    <option value="active">Active</option>
    <option value="rejected">Rejected</option>
  </select>
  <button class="btn btn-primary" data-modal="addProductModal" <?php echo e(!$seller->category_id ? 'disabled title=Set shop category first' : ''); ?>>
    <?php echo $__env->make('seller.partials.icon',['name'=>'plus','size'=>14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Add Product
  </button>
</div>

<div class="card">
  <div class="card-pad" style="padding:0">
    <table class="tbl" id="productTable">
      <thead>
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr data-status="<?php echo e($product->status); ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="inv-img">
                <?php if($product->image): ?>
                  <img src="<?php echo e(Storage::url($product->image)); ?>" style="width:40px;height:40px;object-fit:cover;border-radius:7px">
                <?php else: ?>
                  <?php echo $__env->make('seller.partials.icon',['name'=>'bag','size'=>20], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endif; ?>
              </div>
              <div>
                <div style="font-weight:650;font-size:13px"><?php echo e($product->name); ?></div>
                <?php if($product->sku): ?>
                  <div style="font-size:11px;color:var(--muted)"><?php echo e($product->sku); ?></div>
                <?php endif; ?>
                <?php if($product->description): ?>
                  <div style="font-size:11px;color:var(--muted);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo e($product->description); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?php echo e($product->category->name ?? '—'); ?></td>
          <td class="mono">₱<?php echo e(number_format($product->price, 2)); ?></td>
          <td>
            <?php $stock = $product->total_stock; ?>
            <span style="font-size:13px;font-weight:600"><?php echo e($stock); ?></span>
            <?php if(!empty($product->variations)): ?>
              <div style="font-size:11px;color:var(--muted);margin-top:2px">
                <?php echo e(collect($product->variations)->pluck('name')->join(', ')); ?>

              </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if($product->status === 'pending'): ?>
              <span class="stamp stamp-pending" style="display:inline-flex;align-items:center;gap:5px">
                <?php echo $__env->make('seller.partials.icon',['name'=>'clock','size'=>11], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Pending Review
              </span>
            <?php elseif($product->status === 'active'): ?>
              <span class="stamp stamp-active" style="display:inline-flex;align-items:center;gap:5px">
                <?php echo $__env->make('seller.partials.icon',['name'=>'check-circle','size'=>11], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Active
              </span>
            <?php elseif($product->status === 'rejected'): ?>
              <div>
                <span class="stamp stamp-rejected" style="display:inline-flex;align-items:center;gap:5px">
                  <?php echo $__env->make('seller.partials.icon',['name'=>'x','size'=>11], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Rejected
                </span>
                <?php if($product->rejection_note): ?>
                  <button type="button" class="btn btn-sm btn-outline rejection-note-btn"
                    data-note="<?php echo e($product->rejection_note); ?>"
                    style="margin-top:4px;display:inline-flex;align-items:center;gap:5px;font-size:11px">
                    <?php echo $__env->make('seller.partials.icon',['name'=>'file','size'=>11], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> See Reason
                  </button>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span class="stamp stamp-pending"><?php echo e(ucfirst($product->status)); ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if(in_array($product->status, ['pending','rejected'])): ?>
              <form method="POST" action="<?php echo e(route('seller.inventory.destroy', $product->id)); ?>" onsubmit="return confirm('Remove this product?')">
                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                <button class="btn btn-sm btn-danger" type="submit" title="Remove">
                  <?php echo $__env->make('seller.partials.icon',['name'=>'x','size'=>13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
          <td colspan="6">
            <div class="empty" style="padding:40px 20px">
              <?php echo $__env->make('seller.partials.icon',['name'=>'bag','size'=>28,'class'=>'ic'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              <h3>No products yet</h3>
              <p>Add your first product to get started.</p>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php echo $__env->make('seller.partials.add-product-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<div id="rejectionModal" style="display:none;position:fixed;inset:0;background:rgba(27,22,32,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;width:min(480px,100%);box-shadow:0 24px 60px rgba(27,22,32,.3)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:8px;color:var(--danger)">
        <?php echo $__env->make('seller.partials.icon',['name'=>'x','size'=>16], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <span style="font-weight:700;font-size:14px">Product Rejected</span>
      </div>
      <button id="rejectionModalClose" style="border:0;background:var(--paper);width:30px;height:30px;border-radius:50%;cursor:pointer;display:grid;place-items:center">
        <?php echo $__env->make('seller.partials.icon',['name'=>'x','size'=>14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </button>
    </div>
    <div style="padding:20px">
      <p style="font-size:12px;color:var(--muted);margin:0 0 8px">Reason from admin:</p>
      <div id="rejectionNoteText" style="background:var(--danger-soft);border:1px solid var(--danger-line);border-radius:9px;padding:12px 14px;font-size:13px;color:var(--danger)"></div>
      <p style="font-size:12px;color:var(--muted);margin:12px 0 0">You may remove this product and resubmit with corrections.</p>
    </div>
  </div>
</div>



<script>
// Search filter
document.getElementById('productSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#productTable tbody tr[data-status]').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Status filter
document.getElementById('statusFilter').addEventListener('change', function () {
  const val = this.value;
  document.querySelectorAll('#productTable tbody tr[data-status]').forEach(row => {
    row.style.display = (!val || row.dataset.status === val) ? '' : 'none';
  });
});

// Rejection note modal
const rejModal      = document.getElementById('rejectionModal');
const rejNoteText   = document.getElementById('rejectionNoteText');
document.getElementById('rejectionModalClose').addEventListener('click', () => rejModal.style.display = 'none');
rejModal.addEventListener('click', e => { if (e.target === rejModal) rejModal.style.display = 'none'; });
document.querySelectorAll('.rejection-note-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    rejNoteText.textContent = btn.dataset.note;
    rejModal.style.display = 'flex';
  });
});


</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\inventory.blade.php ENDPATH**/ ?>