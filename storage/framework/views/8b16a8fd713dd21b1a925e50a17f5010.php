<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('page-title', 'Dashboard'); ?>
<?php $__env->startSection('page-sub', 'Welcome back, ' . auth()->user()->given_names . '!'); ?>

<?php $__env->startSection('content'); ?>

<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Active Orders</div>
    <div class="value">0</div>
    <div class="delta">In progress</div>
  </div>
  <div class="kpi">
    <div class="label">Cart Items</div>
    <div class="value">0</div>
    <div class="delta">Ready to checkout</div>
  </div>
  <div class="kpi">
    <div class="label">Completed Orders</div>
    <div class="value">0</div>
    <div class="delta up">All time</div>
  </div>
  <div class="kpi">
    <div class="label">Unread Messages</div>
    <div class="value">0</div>
    <div class="delta">From sellers</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    
    <div class="card">
      <div class="card-head">
        <div><h2>Browse by Category</h2><p>Find what you're looking for</p></div>
        <a href="<?php echo e(route('buyer.browse')); ?>" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="card-pad">
        <div class="category-grid">
          <?php
          $categories = [
            ['food',   'Food & Drinks'],
            ['shirt',  'Clothing'],
            ['sparkle','Beauty'],
            ['phone',  'Electronics'],
            ['home',   'Home & Living'],
            ['puzzle', 'Hobbies'],
          ];
          ?>
          <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e(route('buyer.browse')); ?>?category=<?php echo e(urlencode($label)); ?>" class="category-chip">
            <span class="category-icon"><?php echo $__env->make('buyer.partials.icon', ['name' => $icon, 'size' => 24], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
            <span><?php echo e($label); ?></span>
          </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head">
        <div><h2>Featured Products</h2><p>Handpicked for you</p></div>
        <a href="<?php echo e(route('buyer.browse')); ?>" class="btn btn-sm btn-outline">See more</a>
      </div>
      <div class="card-pad">
        <div class="product-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
          <?php $__empty_1 = true; $__currentLoopData = $featured; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php echo $__env->make('buyer.partials.product-card', ['p' => $p], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="empty" style="grid-column:1/-1;padding:30px 0">
            <?php echo $__env->make('buyer.partials.icon',['name'=>'bag','size'=>28,'class'=>'ic'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <h3>No products yet</h3>
            <p>Check back soon!</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    
    <div class="card">
      <div class="card-head"><h2>My Orders</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <?php
        $orderStatuses = [
          ['package', 'To Ship',           'to_ship'],
          ['truck',   'In Transit',         'in_transit'],
          ['bike',    'Out for Delivery',   'out_for_delivery'],
          ['check',   'Completed',          'completed'],
        ];
        ?>
        <?php $__currentLoopData = $orderStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon, $label, $tab]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('buyer.orders')); ?>?tab=<?php echo e($tab); ?>" class="order-status-row">
          <span class="order-status-ic"><?php echo $__env->make('buyer.partials.icon', ['name' => $icon, 'size' => 18], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
          <span><?php echo e($label); ?></span>
          <span class="order-status-count mono">0</span>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>

    
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="<?php echo e(route('buyer.browse')); ?>" class="btn btn-outline">
          <?php echo $__env->make('buyer.partials.icon', ['name' => 'bag', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Browse Products
        </a>
        <a href="<?php echo e(route('buyer.cart')); ?>" class="btn btn-outline">
          <?php echo $__env->make('buyer.partials.icon', ['name' => 'cart', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> View Cart
        </a>
        <a href="<?php echo e(route('buyer.orders')); ?>" class="btn btn-outline">
          <?php echo $__env->make('buyer.partials.icon', ['name' => 'package', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Track Orders
        </a>
        <a href="<?php echo e(route('buyer.messages')); ?>" class="btn btn-outline">
          <?php echo $__env->make('buyer.partials.icon', ['name' => 'mail', 'size' => 15], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Messages
        </a>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/buyer/dashboard.blade.php ENDPATH**/ ?>