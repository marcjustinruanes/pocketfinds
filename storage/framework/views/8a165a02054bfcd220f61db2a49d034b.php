<?php $__env->startSection('title', 'My Orders'); ?>
<?php $__env->startSection('page-title', 'My Orders'); ?>
<?php $__env->startSection('page-sub', 'Track and manage your orders'); ?>

<?php $__env->startSection('content'); ?>
<?php $tab = request('tab', 'to_ship'); ?>

<div class="tabs">
  <?php
  $orderTabs = [
    ['to_ship',          'package', 'To Ship'],
    ['in_transit',       'truck',   'In Transit'],
    ['out_for_delivery', 'bike',    'Out for Delivery'],
    ['completed',        'check',   'Completed'],
    ['cancelled',        'x',       'Cancelled'],
  ];
  ?>
  <?php $__currentLoopData = $orderTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$key, $icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <a href="<?php echo e(route('buyer.orders')); ?>?tab=<?php echo e($key); ?>" class="tab <?php echo e($tab === $key ? 'active' : ''); ?>">
    <span style="display:inline-flex;align-items:center;gap:5px">
      <?php echo $__env->make('buyer.partials.icon', ['name' => $icon, 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php echo e($label); ?>

    </span>
  </a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<?php if(session('success')): ?><div class="auth-success" style="margin-bottom:16px"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="order-card">
  <div class="order-card-head"><strong><?php echo e($order->seller?->business_name ?: ($order->seller?->given_names ?: 'Seller not provided')); ?></strong><span class="stamp stamp-<?php echo e($order->status === 'to_ship' ? 'new' : $order->status); ?>"><?php echo e(str_replace('_', ' ', ucfirst($order->status))); ?></span></div>
  <div class="order-meta"><span class="mono"><?php echo e($order->order_number); ?></span><span><?php echo e($order->created_at->format('M d, Y h:i A')); ?></span></div>
  <div class="order-items"><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($item['name'] ?: 'Product not provided'); ?> × <?php echo e($item['qty']); ?> <?php if($item['color'] || $item['size']): ?><span style="color:var(--muted)">(<?php echo e(collect([$item['color'], $item['size']])->filter()->join(', ')); ?>)</span><?php endif; ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
  <div class="order-meta"><span>Deliver to: <?php echo e(collect([$order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null, $order->shipping_address['province'] ?? null])->filter()->join(', ') ?: 'Address not provided'); ?></span><span>Payment: <?php echo e($order->paymentMethod?->name ?: 'Payment not provided'); ?></span></div>
  <div class="order-meta"><span>Shipping: PHP <?php echo e(number_format($order->shipping_amount, 2)); ?></span><strong>Total: PHP <?php echo e(number_format($order->total, 2)); ?></strong></div>
  <div class="order-actions"><span style="font-size:12px;color:var(--muted)">Order confirmed and waiting for seller to ship</span></div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="card"><div class="card-pad"><div class="empty"><div class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'package', 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div><h3>No orders yet</h3><p>Orders with status "<?php echo e(str_replace('_', ' ', ucfirst($tab))); ?>" will appear here.</p><?php if($tab === 'to_ship'): ?><a href="<?php echo e(route('buyer.browse')); ?>" class="btn btn-primary" style="margin-top:14px">Start Shopping</a><?php endif; ?></div></div></div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/buyer/orders.blade.php ENDPATH**/ ?>