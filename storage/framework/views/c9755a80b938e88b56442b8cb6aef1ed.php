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
        <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="mono"><?php echo e($order->order_number); ?></td>
          <td style="color:var(--muted);font-size:12px"><?php echo e($order->created_at->format('M d, Y')); ?></td>
          <td><?php echo e($order->buyer?->given_names ?: 'Customer not provided'); ?> <?php echo e($order->buyer?->last_name); ?></td>
          <td><?php echo e(count($order->items)); ?> item<?php echo e(count($order->items) === 1 ? '' : 's'); ?></td>
          <td class="mono">PHP <?php echo e(number_format($order->total, 2)); ?></td>
          <td><span class="stamp stamp-<?php echo e($order->status === 'to_ship' ? 'new' : $order->status); ?>"><?php echo e(str_replace('_', ' ', ucfirst($order->status))); ?></span></td>
          <td><a href="#order-<?php echo e($order->id); ?>" class="btn btn-sm btn-outline"><?php echo $__env->make('seller.partials.icon', ['name' => 'eye', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View</a></td>
        </tr>
        <tr id="order-<?php echo e($order->id); ?>"><td colspan="7" style="background:var(--paper);font-size:12px;line-height:1.7"><strong>Delivery:</strong> <?php echo e(collect([$order->shipping_address['house_no'] ?? null, $order->shipping_address['street'] ?? null, $order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null, $order->shipping_address['province'] ?? null])->filter()->join(', ') ?: 'Address not provided'); ?><br><strong>Payment:</strong> <?php echo e($order->paymentMethod?->name ?: 'Payment not provided'); ?><br><strong>Items:</strong> <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e($item['name'] ?: 'Product not provided'); ?> × <?php echo e($item['qty']); ?><?php echo e(!$loop->last ? ', ' : ''); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><br><strong>Shipping:</strong> PHP <?php echo e(number_format($order->shipping_amount, 2)); ?> | <strong>Total:</strong> PHP <?php echo e(number_format($order->total, 2)); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="7"><div class="empty" style="padding:30px 20px"><h3>No orders yet</h3><p>Orders will appear here once customers place them.</p></div></td></tr>
        <?php endif; ?>
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

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/seller/orders.blade.php ENDPATH**/ ?>