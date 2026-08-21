<?php $__env->startSection('title', 'Order Management'); ?>
<?php $__env->startSection('page-title', 'Order Management'); ?>
<?php $__env->startSection('page-sub', 'View and manage all your orders'); ?>

<?php $__env->startSection('content'); ?>
<div class="filter-bar">
  <div class="search-mini">
    <span class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'search', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
    <input type="text" placeholder="Search by order ID or customer…">
  </div>
  <select class="select">
    <option>All Statuses</option>
    <option>New</option><option>Preparing</option><option>Shipped</option><option>Delivered</option><option>Cancelled</option>
  </select>
  <select class="select">
    <option>All Time</option><option>Today</option><option>This Week</option><option>This Month</option>
  </select>
  <button class="btn btn-outline"><?php echo $__env->make('seller.partials.icon', ['name' => 'download', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Export</button>
</div>

<div class="card">
  <div class="tabs" style="padding:0 20px;margin-bottom:0" data-tabs>
    <button class="tab active" data-tab="all">All Orders</button>
    <button class="tab" data-tab="new">New</button>
    <button class="tab" data-tab="preparing">Preparing</button>
    <button class="tab" data-tab="shipped">Shipped</button>
    <button class="tab" data-tab="delivered">Delivered</button>
    <button class="tab" data-tab="cancelled">Cancelled</button>
  </div>
  <div data-panel="all">
    <table class="tbl">
      <thead><tr>
        <th>Order ID</th><th>Date</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <tr>
          <td class="mono">#00001</td>
          <td style="color:var(--muted);font-size:12px"><?php echo e(now()->format('M d, Y')); ?></td>
          <td>Sample Customer</td>
          <td>1 item</td>
          <td class="mono">₱299.00</td>
          <td><span class="stamp stamp-new">New</span></td>
          <td>
            <div class="tbl-actions">
              <button class="btn btn-sm btn-outline" data-modal="orderDetailModal"><?php echo $__env->make('seller.partials.icon', ['name' => 'eye', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View</button>
              <a href="<?php echo e(route('seller.prepare')); ?>" class="btn btn-sm btn-primary">Prepare</a>
            </div>
          </td>
        </tr>
        <tr><td colspan="7"><div class="empty" style="padding:30px 20px"><h3>No more orders</h3><p>Orders will appear here once customers place them.</p></div></td></tr>
      </tbody>
    </table>
  </div>
  <?php $__currentLoopData = ['new','preparing','shipped','delivered','cancelled']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $panel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div data-panel="<?php echo e($panel); ?>" style="display:none">
    <div class="empty" style="padding:40px 20px">
      <div class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'orders', 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
      <h3>No <?php echo e($panel); ?> orders</h3><p>Orders with this status will appear here.</p>
    </div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<div class="modal-overlay" id="orderDetailModal">
  <div class="modal">
    <div class="modal-head">
      <div><h3>Order #00001</h3><p><?php echo e(now()->format('M d, Y · h:i A')); ?></p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div><div class="field-label">Customer</div><div class="field-value">Sample Customer</div></div>
        <div><div class="field-label">Delivery Address</div><div class="field-value">Available when a customer places an order.</div></div>
        <div><div class="field-label">Payment Method</div><div class="field-value">Cash on Delivery</div></div>
        <div>
          <div class="field-label" style="margin-bottom:10px">Items Ordered</div>
          <div style="border:1px solid var(--border);border-radius:9px;overflow:hidden">
            <table class="tbl">
              <thead><tr><th>Product</th><th>Qty</th><th>Price</th></tr></thead>
              <tbody>
                <tr><td>Sample Product</td><td>1</td><td class="mono">₱299.00</td></tr>
                <tr><td colspan="2" style="text-align:right;font-weight:700">Total</td><td class="mono">₱299.00</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Close</button>
      <a href="<?php echo e(route('seller.prepare')); ?>" class="btn btn-primary"><?php echo $__env->make('seller.partials.icon', ['name' => 'package', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Prepare Order</a>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\orders.blade.php ENDPATH**/ ?>