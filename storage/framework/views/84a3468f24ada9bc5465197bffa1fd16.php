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
<?php $firstItem = collect($order->items ?? [])->first() ?? []; ?>
<div class="order-card">
  <div class="order-card-head">
    <div><span class="order-kicker">Seller</span><strong><?php echo e($order->seller?->business_name ?: ($order->seller?->given_names ?: 'Seller not provided')); ?></strong><div class="seller-actions"><a href="<?php echo e(route('buyer.messages', ['seller' => $order->seller?->username])); ?>">Chat</a><a href="<?php echo e(route('buyer.shop', $order->seller?->username)); ?>">View Shop</a></div></div>
    <span class="stamp stamp-<?php echo e($order->status === 'to_ship' ? 'new' : $order->status); ?>"><?php echo e(str_replace('_', ' ', ucfirst($order->status))); ?></span>
  </div>
  <div class="order-meta"><span class="mono"><?php echo e($order->order_number); ?></span><span><?php echo e($order->created_at->format('M d, Y h:i A')); ?></span></div>
  <div class="order-preview">
    <div class="order-product-image"><?php if(!empty($firstItem['img'])): ?><img src="<?php echo e($firstItem['img']); ?>" alt="<?php echo e($firstItem['name'] ?? 'Product'); ?>"><?php else: ?><span>IMG</span><?php endif; ?></div>
    <div class="order-product-copy"><strong><?php echo e($firstItem['name'] ?? 'Product not provided'); ?></strong><span>Qty <?php echo e($firstItem['qty'] ?? 1); ?> <?php if(count($order->items ?? []) > 1): ?> · <?php echo e(count($order->items) - 1); ?> more item(s) <?php endif; ?></span></div>
    <button type="button" class="btn btn-outline order-details-button" data-order-details="orderDetails<?php echo e($order->id); ?>">Order Details</button>
  </div>
  <div class="order-actions"><span>Order confirmed and waiting for seller to ship</span><div><small>Shipping PHP <?php echo e(number_format($order->shipping_amount, 2)); ?></small><strong>Total PHP <?php echo e(number_format($order->total, 2)); ?></strong></div></div>
</div>
<div class="order-details-modal" id="orderDetails<?php echo e($order->id); ?>" aria-hidden="true">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="orderDetailsTitle<?php echo e($order->id); ?>">
    <div class="order-details-head"><div><span class="order-kicker">Order details</span><h3 id="orderDetailsTitle<?php echo e($order->id); ?>"><?php echo e($order->order_number); ?></h3></div><button type="button" class="modal-close order-details-close" aria-label="Close order details">&times;</button></div>
    <?php
      $trackingSteps = [
        ['Order placed', 'We received your order.'],
        ['Seller is reviewing', 'The seller is checking your order.'],
        ['Seller is preparing', 'Your package is being packed.'],
        ['Package in transit', 'The rider is delivering your package.'],
        ['Delivered', 'Enjoy your purchase.'],
      ];
      $currentStep = match($order->status) { 'in_transit' => 3, 'out_for_delivery' => 4, 'completed' => 5, default => 2 };
    ?>
    <div class="order-tracking"><span class="order-kicker">Delivery tracking</span><div class="tracking-steps"><?php $__currentLoopData = $trackingSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step => [$label, $copy]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="tracking-step <?php echo e($step + 1 < $currentStep ? 'is-done' : ($step + 1 === $currentStep ? 'is-current' : '')); ?>"><span class="tracking-dot"><?php echo e($step + 1 < $currentStep ? '✓' : $step + 1); ?></span><div><strong><?php echo e($label); ?></strong><small><?php echo e($step + 1 === $currentStep ? $copy : $copy); ?></small></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></div>
    <div class="order-details-list"><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="order-item"><span><?php echo e($item['name'] ?: 'Product not provided'); ?> <?php if(($item['color'] ?? '') || ($item['size'] ?? '')): ?><small><?php echo e(collect([$item['color'] ?? null, $item['size'] ?? null])->filter()->join(', ')); ?></small><?php endif; ?></span><strong><?php echo e($item['qty']); ?> × PHP <?php echo e(number_format($item['price'], 2)); ?></strong></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
    <div class="order-details"><div><span>Deliver to</span><strong><?php echo e(collect([$order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null, $order->shipping_address['province'] ?? null])->filter()->join(', ') ?: 'Address not provided'); ?></strong></div><div><span>Payment</span><strong><?php echo e($order->paymentMethod?->name ?: $order->payment_method ?: 'Payment not provided'); ?></strong></div></div>
    <?php if($order->status === 'to_ship'): ?>
      <div class="order-modal-actions"><button type="button" class="btn btn-danger order-cancel-open">Cancel Order</button></div>
      <div class="order-cancel-panel" hidden>
        <form method="POST" action="<?php echo e(route('buyer.orders.cancel', $order)); ?>">
          <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
          <strong>Why are you cancelling?</strong>
          <select name="cancellation_reason" required><option value="" selected disabled>Select a reason</option><option>Changed my mind</option><option>Found a better price</option><option>Ordered by mistake</option><option>Payment issue</option><option>Other</option></select>
          <textarea name="cancellation_note" rows="3" maxlength="500" placeholder="Add a note (optional)"></textarea>
          <div class="order-modal-actions"><button type="button" class="btn btn-outline order-cancel-back">Keep Order</button><button type="submit" class="btn btn-danger">Confirm Cancellation</button></div>
        </form>
      </div>
    <?php elseif($order->status === 'cancelled'): ?>
      <div class="order-cancelled-note">Cancelled: <?php echo e($order->cancellation_reason ?: 'Reason not provided'); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="card"><div class="card-pad"><div class="empty"><div class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'package', 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div><h3>No orders yet</h3><p>Orders with status "<?php echo e(str_replace('_', ' ', ucfirst($tab))); ?>" will appear here.</p><?php if($tab === 'to_ship'): ?><a href="<?php echo e(route('buyer.browse')); ?>" class="btn btn-primary" style="margin-top:14px">Start Shopping</a><?php endif; ?></div></div></div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('head'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-order-details]').forEach(button => {
    const modal = document.getElementById(button.dataset.orderDetails);
    const close = () => { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); };
    button.addEventListener('click', () => { modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); });
    modal.querySelector('.order-details-close').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    const cancelOpen = modal.querySelector('.order-cancel-open');
    const cancelPanel = modal.querySelector('.order-cancel-panel');
    cancelOpen?.addEventListener('click', () => { cancelPanel.hidden = false; cancelOpen.hidden = true; });
    modal.querySelector('.order-cancel-back')?.addEventListener('click', () => { cancelPanel.hidden = true; cancelOpen.hidden = false; });
  });
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\buyer\orders.blade.php ENDPATH**/ ?>