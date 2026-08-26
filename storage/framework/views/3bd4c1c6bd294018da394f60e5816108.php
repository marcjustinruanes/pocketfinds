<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('page-title', 'Dashboard'); ?>
<?php $__env->startSection('page-sub', 'Welcome back, ' . auth()->user()->given_names . '!'); ?>

<?php $__env->startSection('content'); ?>
<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Total Sales</div>
    <div class="value">₱<?php echo e(number_format($totalSales, 2)); ?></div>
    <div class="delta up">Completed orders</div>
  </div>
  <div class="kpi">
    <div class="label">New Orders</div>
    <div class="value"><?php echo e(number_format($newOrders)); ?></div>
    <div class="delta">Orders to prepare</div>
  </div>
  <div class="kpi">
    <div class="label">Products Listed</div>
    <div class="value"><?php echo e(number_format($productsListed)); ?></div>
    <div class="delta">Active listings</div>
  </div>
  <div class="kpi">
    <div class="label">Avg. Rating</div>
    <div class="value">—</div>
    <div class="delta">No ratings yet</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    
    <div class="card">
      <div class="card-head">
        <div><h2>Sales Overview</h2><p>Last 7 days</p></div>
        <a href="<?php echo e(route('seller.reports')); ?>" class="btn btn-sm btn-outline">Full Report</a>
      </div>
      <div class="card-pad">
        <div class="chart-area">
          <?php $__currentLoopData = $salesChart; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="chart-bar <?php echo e($day['amount'] === $chartMax ? 'highlight' : ''); ?>" style="height:<?php echo e(max(5, ($day['amount'] / $chartMax) * 100)); ?>%" title="PHP <?php echo e(number_format($day['amount'], 2)); ?>"></div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;font-family:var(--font-mono);font-size:10px;color:var(--muted)">
          <?php $__currentLoopData = $salesChart; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <span><?php echo e($day['label']); ?></span>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head">
        <div><h2>Recent Orders</h2><p>Latest incoming orders</p></div>
        <a href="<?php echo e(route('seller.orders')); ?>" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="card-pad" style="padding:0">
        <table class="tbl">
          <thead><tr>
            <th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $recentOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr><td class="mono"><?php echo e($order->order_number); ?></td><td><?php echo e($order->buyer?->given_names); ?> <?php echo e($order->buyer?->last_name); ?></td><td class="mono">₱<?php echo e(number_format($order->total, 2)); ?></td><td><span class="stamp stamp-<?php echo e($order->status === 'to_ship' ? 'new' : $order->status); ?>"><?php echo e(str_replace('_', ' ', ucfirst($order->status))); ?></span></td><td><a href="<?php echo e(route('seller.orders')); ?>" class="btn btn-sm btn-outline">View</a></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="5"><div class="empty" style="padding:30px 20px"><h3>No orders yet</h3><p>Incoming orders will appear here.</p></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    
    <div class="card">
      <div class="card-head"><h2>Order Pipeline</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <?php $pipeline = [
          ['bell','New Orders','notifications','stamp-new',$pipelineCounts['new']],
          ['package','To Prepare','prepare','stamp-pending',$pipelineCounts['prepare']],
          ['truck','With Courier','shipments','stamp-transit',$pipelineCounts['shipments']],
          ['check-circle','Delivered','deliveries','stamp-delivered',$pipelineCounts['deliveries']],
        ]; ?>
        <?php $__currentLoopData = $pipeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon,$label,$route,$stamp,$count]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('seller.'.$route)); ?>" class="order-status-row" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-weight:600;color:var(--text);background:#fff">
          <span style="display:flex;align-items:center;color:var(--pink-dark)"><?php echo $__env->make('seller.partials.icon', ['name' => $icon, 'size' => 18], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
          <span><?php echo e($label); ?></span>
          <span class="stamp <?php echo e($stamp); ?>" style="margin-left:auto"><?php echo e($count); ?></span>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head">
        <div><h2>Stock Alerts</h2><p>Items running low</p></div>
        <a href="<?php echo e(route('seller.inventory')); ?>" class="btn btn-sm btn-outline">Manage</a>
      </div>
      <div class="card-pad">
        <?php $__empty_1 = true; $__currentLoopData = $lowStock; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div class="stock-row"><strong><?php echo e($product->name); ?></strong><span><?php echo e($product->total_stock); ?> left</span></div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="empty"><div class="ic"><?php echo $__env->make('seller.partials.icon', ['name' => 'inventory', 'size' => 26], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div><h3>All stocked up</h3><p>No low-stock items right now.</p></div>
        <?php endif; ?>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <button class="btn btn-primary" data-modal="addProductModal">
          <?php echo $__env->make('seller.partials.icon', ['name' => 'plus', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Add Product
        </button>
        <a href="<?php echo e(route('seller.orders')); ?>" class="btn btn-outline">
          <?php echo $__env->make('seller.partials.icon', ['name' => 'orders', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Manage Orders
        </a>
        <a href="<?php echo e(route('seller.reports')); ?>" class="btn btn-outline">
          <?php echo $__env->make('seller.partials.icon', ['name' => 'chart', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View Reports
        </a>
        <a href="<?php echo e(route('seller.messages')); ?>" class="btn btn-outline">
          <?php echo $__env->make('seller.partials.icon', ['name' => 'mail', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Messages
        </a>
      </div>
    </div>
  </div>
</div>

<?php echo $__env->make('seller.partials.add-product-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('seller.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/seller/dashboard.blade.php ENDPATH**/ ?>