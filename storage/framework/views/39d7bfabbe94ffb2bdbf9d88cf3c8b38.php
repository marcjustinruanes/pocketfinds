<?php $__env->startSection('title', 'Product Reviews'); ?>
<?php $__env->startSection('page-title', 'Product Reviews'); ?>
<?php $__env->startSection('page-sub', 'Review and manage seller product submissions'); ?>
<?php use Illuminate\Support\Facades\Storage; ?>

<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<?php
  $pendingProducts   = $products->where('status', 'pending')->count();
  $activeProducts    = $products->where('status', 'active')->count();
  $rejectedProducts  = $products->where('status', 'rejected')->count();
  $outOfStockProducts = $products->filter(fn($p) => $p->total_stock <= 0)->count();
?>
<div class="kpi-grid">
  <button type="button" class="kpi kpi-filter active" data-status-kpi="">
    <div class="label">Total Submissions</div>
    <div class="value"><?php echo e($products->count()); ?></div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="pending">
    <div class="label">Pending Review</div>
    <div class="value"><?php echo e($pendingProducts); ?></div>
    <div class="delta <?php echo e($pendingProducts > 0 ? 'down' : 'up'); ?>">Needs attention</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="active">
    <div class="label">Approved</div>
    <div class="value"><?php echo e($activeProducts); ?></div>
    <div class="delta up">Live in shop</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="rejected">
    <div class="label">Rejected</div>
    <div class="value"><?php echo e($rejectedProducts); ?></div>
  </button>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Product Submissions</h2><p><?php echo e($products->count()); ?> total</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span>
        <input type="text" placeholder="Search product, seller or SKU..." data-table-search="productsTable">
      </div>
      <select class="select" id="statusFilter">
        <option value="">All Status</option>
        <option value="pending">Pending Review</option>
        <option value="active">Approved</option>
        <option value="rejected">Rejected / Declined</option>
        <option value="outofstock">Out of Stock</option>
      </select>
    </div>

    <div class="table-wrap">
    <table class="dtable" id="productsTable">
      <thead>
        <tr><th>Product</th><th>Seller</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Submitted</th><th></th></tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr class="rail-row rail-<?php echo e($product->status); ?>" data-status="<?php echo e($product->status); ?>" data-stock="<?php echo e($product->total_stock <= 0 ? 'out' : 'in'); ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if($product->image): ?>
                <img src="<?php echo e(Storage::url($product->image)); ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid var(--border)">
              <?php else: ?>
                <div style="width:44px;height:44px;background:var(--paper);border-radius:8px;display:grid;place-items:center;flex-shrink:0;color:var(--muted)">
                  <?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'bag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bag']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
                </div>
              <?php endif; ?>
              <div style="min-width:0">
                <div style="font-weight:650;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px"><?php echo e($product->name); ?></div>
                <?php if($product->sku): ?>
                  <div style="font-size:11px;color:var(--muted)" class="mono">SKU: <?php echo e($product->sku); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm"><?php echo e(strtoupper(substr($product->seller->business_name ?? $product->seller->given_names, 0, 1))); ?></div>
              <div>
                <strong><?php echo e($product->seller->business_name ?? ($product->seller->given_names.' '.$product->seller->last_name)); ?></strong>
                <span><?php echo e($product->seller->given_names); ?> <?php echo e($product->seller->last_name); ?></span>
              </div>
            </div>
          </td>
          <td style="font-size:12px"><?php echo e($product->category->name ?? '—'); ?></td>
          <td class="mono">₱<?php echo e(number_format($product->price, 2)); ?></td>
          <td class="mono" style="<?php echo e($product->total_stock <= 0 ? 'color:var(--danger);font-weight:700' : ''); ?>">
            <?php echo e($product->total_stock); ?><?php echo e($product->total_stock <= 0 ? ' · Out' : ''); ?>

          </td>
          <td>
            <?php if($product->status === 'pending'): ?>
              <span class="stamp stamp-pending">Pending</span>
            <?php elseif($product->status === 'active'): ?>
              <span class="stamp stamp-active">Approved</span>
            <?php elseif($product->status === 'rejected'): ?>
              <span class="stamp stamp-rejected">Rejected</span>
            <?php endif; ?>
          </td>
          <td class="mono" style="font-size:12px"><?php echo e($product->created_at->format('M d, Y')); ?></td>
          <td>
            <div class="row-actions">
              <button class="btn btn-sm btn-outline" data-modal-open="productModal-<?php echo e($product->id); ?>"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'eye']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'eye']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> Review</button>
            </div>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="8"><div class="empty"><div class="ic"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'bag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bag']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></div><h3>No product submissions yet</h3><p>Products sellers submit for review will appear here.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>


<?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
  $seller = $product->seller;
  $hasVariations = !empty($product->variations);
  $hasDetails = !empty($product->details);
  $galleryImages = collect();
  if ($product->image) $galleryImages->push(Storage::url($product->image));
  foreach ($product->images as $img) { $galleryImages->push(Storage::url($img->image_url)); }
  $galleryImages = $galleryImages->unique()->values();
?>
<div class="modal-overlay" id="productModal-<?php echo e($product->id); ?>">
  <div class="modal modal-xl">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'bag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bag']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span>
        <div class="modal-head-copy">
          <h3><?php echo e($product->name); ?>

            <?php if($product->status === 'pending'): ?><span class="stamp stamp-pending">Pending</span>
            <?php elseif($product->status === 'active'): ?><span class="stamp stamp-active">Approved</span>
            <?php elseif($product->status === 'rejected'): ?><span class="stamp stamp-rejected">Rejected</span><?php endif; ?>
          </h3>
          <p><?php echo e($seller->business_name ?? ($seller->given_names.' '.$seller->last_name)); ?> · Submitted <?php echo e($product->created_at->format('M d, Y g:i A')); ?></p>
        </div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></button>
    </div>

    <div class="modal-body">
      <div class="modal-tabs" data-modal-tabs>
        <button type="button" class="tab active" data-tab-target="overview-<?php echo e($product->id); ?>">Overview</button>
        <button type="button" class="tab" data-tab-target="description-<?php echo e($product->id); ?>">Description</button>
        <?php if($hasVariations): ?>
        <button type="button" class="tab" data-tab-target="variations-<?php echo e($product->id); ?>">Variations</button>
        <?php endif; ?>
        <button type="button" class="tab" data-tab-target="seller-<?php echo e($product->id); ?>">Seller</button>
        <button type="button" class="tab" data-tab-target="metadata-<?php echo e($product->id); ?>">Metadata</button>
      </div>

      
      <div data-tab-panel="overview-<?php echo e($product->id); ?>" class="active">
        <div class="dash-grid" style="grid-template-columns:minmax(0,1fr) minmax(240px,1fr)">
          <div class="pv-gallery">
            <button type="button" class="pv-main-img" data-lightbox-trigger data-src="<?php echo e($galleryImages->first() ?? ''); ?>">
              <?php if($galleryImages->isNotEmpty()): ?>
                <img src="<?php echo e($galleryImages->first()); ?>" alt="<?php echo e($product->name); ?>" id="pvMainImg-<?php echo e($product->id); ?>">
              <?php else: ?>
                <span style="color:var(--muted)"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'bag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bag']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span>
              <?php endif; ?>
            </button>
            <?php if($galleryImages->count() > 1): ?>
            <div class="pv-thumbs">
              <?php $__currentLoopData = $galleryImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $src): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <button type="button" class="pv-thumb <?php echo e($i === 0 ? 'active' : ''); ?>" data-pv-thumb data-src="<?php echo e($src); ?>" data-target="pvMainImg-<?php echo e($product->id); ?>">
                <img src="<?php echo e($src); ?>" alt="Image <?php echo e($i + 1); ?>">
              </button>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php endif; ?>
          </div>
          <div>
            <div class="detail-grid" style="grid-template-columns:1fr">
              <div><div class="field-label">Category</div><div class="field-value"><?php echo e($product->category->name ?? '—'); ?></div></div>
              <div><div class="field-label">Price</div><div class="field-value mono" style="font-size:16px;font-weight:700;color:var(--pink-dark)">₱<?php echo e(number_format($product->price, 2)); ?></div></div>
              <div>
                <div class="field-label">Stock</div>
                <div class="field-value <?php echo e($product->total_stock <= 0 ? 'mono' : 'mono'); ?>" style="<?php echo e($product->total_stock <= 0 ? 'color:var(--danger);font-weight:700' : ''); ?>">
                  <?php echo e($product->total_stock); ?> <?php echo e($hasVariations ? '(across all variations)' : 'units'); ?>

                  <?php if($product->total_stock <= 0): ?> — Out of Stock <?php endif; ?>
                </div>
              </div>
              <?php if($product->sku): ?>
              <div><div class="field-label">SKU</div><div class="field-value mono"><?php echo e($product->sku); ?></div></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      
      <div data-tab-panel="description-<?php echo e($product->id); ?>">
        <p class="crumb" style="margin-bottom:8px">Full Description</p>
        <p style="font-size:13.5px;line-height:1.7;color:var(--text);white-space:pre-line;margin:0 0 18px"><?php echo e($product->description ?: 'No description provided.'); ?></p>
        <?php if($hasDetails): ?>
        <p class="crumb" style="margin-bottom:8px">Specifications</p>
        <div class="kv-table">
          <?php $__currentLoopData = $product->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="kv-row"><div class="kv-key"><?php echo e($row['label'] ?? '—'); ?></div><div class="kv-val"><?php echo e($row['value'] ?? '—'); ?></div></div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
      </div>

      
      <?php if($hasVariations): ?>
      <div data-tab-panel="variations-<?php echo e($product->id); ?>">
        <?php $__currentLoopData = $product->variations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="variation-group">
          <div class="variation-group-head"><?php echo e($group['name'] ?? 'Option'); ?></div>
          <div class="variation-options">
            <?php $__empty_1 = true; $__currentLoopData = ($group['options'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="variation-option">
              <span class="opt-name"><?php echo e($opt['value'] ?? '—'); ?></span>
              <span class="opt-stock <?php echo e(($opt['stock'] ?? 0) <= 0 ? 'zero' : ''); ?>"><?php echo e($opt['stock'] ?? 0); ?> in stock</span>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <span style="font-size:12px;color:var(--muted)">No options listed.</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <?php endif; ?>

      
      <div data-tab-panel="seller-<?php echo e($product->id); ?>">
        <div class="seller-card">
          <div class="avatar-lg"><?php echo e(strtoupper(substr($seller->business_name ?? $seller->given_names, 0, 1))); ?></div>
          <div>
            <div class="seller-card-name"><?php echo e($seller->business_name ?? ($seller->given_names.' '.$seller->last_name)); ?></div>
            <div class="seller-card-sub"><?php echo e($seller->given_names); ?> <?php echo e($seller->last_name); ?> · <span class="stamp stamp-<?php echo e($seller->status); ?>" style="vertical-align:middle"><?php echo e(ucfirst($seller->status)); ?></span></div>
          </div>
        </div>
        <div class="detail-grid">
          <div><div class="field-label">Email</div><div class="field-value"><?php echo e($seller->email); ?></div></div>
          <div><div class="field-label">Contact No.</div><div class="field-value mono"><?php echo e($seller->contact_no ?? '—'); ?></div></div>
          <div class="full"><div class="field-label">Shop Categories</div><div class="field-value"><?php echo e($seller->categories->pluck('name')->push($seller->category_other)->filter()->implode(', ') ?: '—'); ?></div></div>
          <div><div class="field-label">Seller Since</div><div class="field-value mono"><?php echo e($seller->created_at?->format('M d, Y') ?? '—'); ?></div></div>
          <div><div class="field-label">Account Status</div><div class="field-value"><span class="stamp stamp-<?php echo e($seller->status); ?>"><?php echo e(ucfirst($seller->status)); ?></span></div></div>
        </div>
      </div>

      
      <div data-tab-panel="metadata-<?php echo e($product->id); ?>">
        <div class="kv-table">
          <div class="kv-row"><div class="kv-key">Product ID</div><div class="kv-val mono"><?php echo e($product->id); ?></div></div>
          <div class="kv-row"><div class="kv-key">Current Status</div><div class="kv-val"><span class="stamp stamp-<?php echo e($product->status); ?>"><?php echo e(ucfirst($product->status === 'active' ? 'approved' : $product->status)); ?></span></div></div>
          <div class="kv-row"><div class="kv-key">Date Submitted</div><div class="kv-val mono"><?php echo e($product->created_at->format('M d, Y g:i A')); ?></div></div>
          <div class="kv-row"><div class="kv-key">Last Updated</div><div class="kv-val mono"><?php echo e($product->updated_at->format('M d, Y g:i A')); ?></div></div>
          <?php if($product->status === 'rejected'): ?>
          <div class="kv-row"><div class="kv-key">Rejection Reason</div><div class="kv-val"><?php echo e($product->rejection_note ?: 'No reason was recorded.'); ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Close</button>
      <?php if($product->status === 'pending'): ?>
        <button type="button" class="btn btn-outline-danger" onclick="openReject('<?php echo e($product->id); ?>', '<?php echo e(addslashes($product->name)); ?>')"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> Reject</button>
        <form method="POST" action="<?php echo e(route('admin.products.approve', $product->id)); ?>" style="display:inline">
          <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
          <button class="btn btn-success" type="submit"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'edit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'edit']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> Approve Product</button>
        </form>
      <?php elseif($product->status === 'active'): ?>
        <button type="button" class="btn btn-danger" onclick="openReject('<?php echo e($product->id); ?>', '<?php echo e(addslashes($product->name)); ?>')"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> Reject &amp; Take Down</button>
      <?php elseif($product->status === 'rejected'): ?>
        <form method="POST" action="<?php echo e(route('admin.products.approve', $product->id)); ?>" style="display:inline">
          <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
          <button class="btn btn-success" type="submit"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'edit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'edit']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?> Approve Product</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>


<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon" style="background:var(--danger-soft);color:var(--danger)"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'flag']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'flag']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></span>
        <div class="modal-head-copy">
          <h3>Reject Product</h3>
          <p id="rejectModalSub">Tell the seller why this product is being rejected.</p>
        </div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></button>
    </div>
    <form id="rejectForm" method="POST">
      <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
      <div class="modal-body">
        <div class="form-row">
          <label>Reason for rejection <span style="color:var(--danger)">*</span></label>
          <textarea name="note" rows="4" placeholder="e.g. This product does not match your shop category. Please only submit related products." required></textarea>
          <span class="hint" style="margin-top:4px;display:block">This message will be shown to the seller.</span>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Reject &amp; Notify Seller</button>
      </div>
    </form>
  </div>
</div>


<div class="modal-overlay" id="mediaLightbox">
  <div class="modal" style="width:min(720px,100%);max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-head">
      <div><h3>Image Preview</h3></div>
      <button class="modal-close" data-modal-close aria-label="Close"><?php if (isset($component)) { $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $attributes = $__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__attributesOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92)): ?>
<?php $component = $__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92; ?>
<?php unset($__componentOriginalc4dbd72dbbda5b9097ae9fdad9927c92); ?>
<?php endif; ?></button>
    </div>
    <div style="flex:1;overflow:auto;padding:20px;display:flex;align-items:center;justify-content:center;min-height:300px">
      <img id="mediaLightboxImg" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain" alt="Product preview">
    </div>
  </div>
</div>

<script>
function applyProductFilter(val) {
  document.querySelectorAll('#productsTable tbody tr[data-status]').forEach(row => {
    let show;
    if (!val) show = true;
    else if (val === 'outofstock') show = row.dataset.stock === 'out';
    else show = row.dataset.status === val;
    row.style.display = show ? '' : 'none';
  });
  document.querySelectorAll('.kpi-filter').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.statusKpi === val);
  });
}

document.getElementById('statusFilter').addEventListener('change', function () {
  applyProductFilter(this.value);
});

document.querySelectorAll('.kpi-filter').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('statusFilter').value = btn.dataset.statusKpi;
    applyProductFilter(btn.dataset.statusKpi);
  });
});

function openReject(id, name) {
  document.getElementById('rejectForm').action = `/admin/products/${id}/reject`;
  document.getElementById('rejectModalSub').textContent = `Tell the seller why "${name}" is being rejected.`;
  document.getElementById('rejectModal').classList.add('open');
}

// In-modal section tabs (Overview / Description / Variations / Seller / Metadata)
document.addEventListener('click', (e) => {
  const tabBtn = e.target.closest('[data-modal-tabs] .tab');
  if (tabBtn) {
    const scope = tabBtn.closest('.modal');
    scope.querySelectorAll('[data-modal-tabs] .tab').forEach(t => t.classList.remove('active'));
    tabBtn.classList.add('active');
    scope.querySelectorAll('[data-tab-panel]').forEach(p => p.classList.toggle('active', p.dataset.tabPanel === tabBtn.dataset.tabTarget));
    return;
  }
  const thumb = e.target.closest('[data-pv-thumb]');
  if (thumb) {
    const wrap = thumb.closest('.pv-gallery');
    wrap.querySelectorAll('.pv-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
    const mainImg = document.getElementById(thumb.dataset.target);
    if (mainImg) mainImg.src = thumb.dataset.src;
    wrap.querySelector('.pv-main-img').dataset.src = thumb.dataset.src;
    return;
  }
  const lightboxTrigger = e.target.closest('[data-lightbox-trigger]');
  if (lightboxTrigger && lightboxTrigger.dataset.src) {
    document.getElementById('mediaLightboxImg').src = lightboxTrigger.dataset.src;
    document.getElementById('mediaLightbox').classList.add('open');
  }
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Chlouie Cabot\OneDrive\Desktop\pocketfinds\resources\views/admin/products.blade.php ENDPATH**/ ?>