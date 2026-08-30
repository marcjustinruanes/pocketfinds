<?php $__env->startSection('title', 'Browse Products'); ?>
<?php $__env->startSection('page-title', 'Browse Products'); ?>
<?php $__env->startSection('page-sub', 'Discover items from local sellers'); ?>

<?php $__env->startSection('content'); ?>
<div class="filter-bar">
  <div class="search-mini">
    <span class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'search', 'size' => 13], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
    <input type="text" id="browseSearch" placeholder="Search products…" oninput="filterBrowse()">
  </div>
  <select class="select" id="browseCategory" onchange="filterBrowse()">
    <option value="">All Categories</option>
    <option>Food & Drinks</option>
    <option>Clothing</option>
    <option>Beauty</option>
    <option>Electronics</option>
    <option>Home & Living</option>
    <option>Hobbies</option>
    <option>Sports</option>
  </select>
  <select class="select">
    <option>Sort: Newest</option>
    <option>Price: Low to High</option>
    <option>Price: High to Low</option>
    <option>Most Popular</option>
  </select>
</div>

<div class="card">
  <div class="card-pad">
    <div class="product-grid product-grid-lg" id="browseGrid">
      <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div data-name="<?php echo e(strtolower($p['name'])); ?>" data-cat="<?php echo e($p['cat']); ?>">
        <?php echo $__env->make('buyer.partials.product-card', ['p' => $p], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <div class="empty" id="browseEmpty" style="display:none">
      <div class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'bag', 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
      <h3>No products found</h3>
      <p>Try adjusting your search or filters.</p>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/buyer/browse.blade.php ENDPATH**/ ?>