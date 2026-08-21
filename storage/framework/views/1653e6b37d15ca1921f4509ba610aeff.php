<?php $__env->startSection('title', 'My Cart'); ?>
<?php $__env->startSection('page-title', 'My Cart'); ?>
<?php $__env->startSection('page-sub', 'Review your selected items'); ?>

<?php $__env->startSection('content'); ?>
<?php ($subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['qty'])); ?>
<div class="cart-layout">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Cart Items</h2></div>
      <div class="card-pad">
        <?php if(count($items)): ?>
          <label class="cart-select-all">
            <input id="cartSelectAll" type="checkbox" checked>
            <span>Select all items (<?php echo e($items->count()); ?>)</span>
          </label>
          <div class="cart-column-head" aria-hidden="true">
            <span></span><span></span><span>Product / unit price</span><span>Subtotal</span><span>Quantity</span>
          </div>
          <?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shopItems): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <section class="cart-shop">
            <div class="cart-shop-head">
              <label class="cart-check-wrap">
                <input class="cart-shop-select" type="checkbox" checked>
                <a href="<?php echo e(route('buyer.shop', $shopItems->first()['seller_slug'])); ?>"><?php echo e($shopItems->first()['seller']); ?></a>
              </label>
              <span><?php echo e($shopItems->count()); ?> item<?php echo e($shopItems->count() === 1 ? '' : 's'); ?></span>
            </div>
            <div class="cart-items">
            <?php $__currentLoopData = $shopItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div class="cart-item">
                <input class="cart-select" type="checkbox" checked data-price="<?php echo e($item['price']); ?>" data-qty="<?php echo e($item['qty']); ?>" data-shop="<?php echo e($item['seller_slug']); ?>">
                <div class="cart-item-image">
                  <?php if(filled($item['img'])): ?>
                    <img src="<?php echo e($item['img']); ?>" alt="<?php echo e($item['name']); ?>">
                  <?php else: ?>
                    <?php echo $__env->make('buyer.partials.icon', ['name' => 'package', 'size' => 26], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                  <?php endif; ?>
                </div>
                <div style="min-width:0">
                  <a class="cart-item-name" href="<?php echo e(route('buyer.product', $item['product_id'])); ?>"><?php echo e($item['name']); ?></a>
                  <?php if($item['color'] || $item['size']): ?>
                    <div class="cart-item-variant"><?php echo e(collect([$item['color'], $item['size']])->filter()->join(' · ')); ?></div>
                  <?php endif; ?>
                  <div class="cart-item-quantity">Unit price: &#8369;<?php echo e(number_format($item['price'], 2)); ?></div>
                </div>
                <div class="cart-item-total">₱<?php echo e(number_format($item['price'] * $item['qty'], 2)); ?></div>
                <div class="cart-item-actions">
                  <form method="POST" action="<?php echo e(route('buyer.cart.update', ['key' => $item['key']])); ?>" class="cart-qty-form">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PATCH'); ?>
                    <label class="sr-only" for="qty-<?php echo e(md5($item['key'])); ?>">Quantity</label>
                    <button type="button" aria-label="Decrease quantity" onclick="changeCartQuantity(this, -1)">&minus;</button>
                    <input id="qty-<?php echo e(md5($item['key'])); ?>" name="qty" type="number" min="1" max="99" value="<?php echo e($item['qty']); ?>" onchange="this.form.submit()">
                    <button type="button" aria-label="Increase quantity" onclick="changeCartQuantity(this, 1)">+</button>
                  </form>
                  <form method="POST" action="<?php echo e(route('buyer.cart.remove', ['key' => $item['key']])); ?>">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="cart-remove">Remove</button>
                  </form>
                </div>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
          </section>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php else: ?>
        <div class="empty">
          <div class="ic"><?php echo $__env->make('buyer.partials.icon', ['name' => 'cart', 'size' => 28], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
          <h3>Your cart is empty</h3>
          <p>Browse products and add items to your cart.</p>
          <a href="<?php echo e(route('buyer.browse')); ?>" class="btn btn-primary" style="margin-top:14px">Browse Products</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="stack cart-summary">
    <div class="card">
      <div class="card-head"><h2>Order Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div class="cart-summary-row"><span>Subtotal</span><span class="mono" id="cartSubtotal">₱<?php echo e(number_format($subtotal, 2)); ?></span></div>
        <div class="cart-summary-row"><span>Shipping</span><span class="mono">₱0.00</span></div>
        <div class="cart-summary-row" style="color:var(--success)"><span>Discount</span><span class="mono">- ₱0.00</span></div>
        <hr style="border:0;border-top:1px solid var(--border)">
        <div class="cart-summary-total"><span id="cartSelectedCount"><?php echo e($items->count()); ?> item<?php echo e($items->count() === 1 ? '' : 's'); ?></span><span class="mono" id="cartTotal">₱<?php echo e(number_format($subtotal, 2)); ?></span></div>

        <div class="cart-benefits">
          <div class="cart-benefit-head"><span>Vouchers</span><button type="button" class="cart-view-vouchers" data-voucher-modal-open>View all</button></div>
          <?php $__empty_1 = true; $__currentLoopData = $voucherData['applicable']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <button type="button" class="cart-offer" data-voucher-id="<?php echo e($voucher['id']); ?>" data-voucher-group="<?php echo e($voucher['type']); ?>" data-discount="<?php echo e($voucher['discount']); ?>" data-minimum="<?php echo e($voucher['minimum']); ?>" data-shop="<?php echo e($voucher['shop_slug']); ?>">
              <span><b><?php echo e($voucher['code']); ?></b><small><?php echo e($voucher['shop']); ?>: PHP <?php echo e(number_format($voucher['discount'])); ?> off</small></span><em>Use</em>
            </button>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="cart-no-voucher">No vouchers apply to the selected cart yet. <button type="button" data-voucher-modal-open>View vouchers</button></p>
          <?php endif; ?>
          <div class="cart-benefit-head"><span>Shipping options</span></div>
          <?php $__currentLoopData = $shippingOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <label class="cart-shipping-option"><input type="radio" name="shipping" value="<?php echo e($option['amount']); ?>" <?php echo e($loop->first ? 'checked' : ''); ?>><span><?php echo e($option['label']); ?> <small><?php echo e($option['detail']); ?></small></span><b>PHP <?php echo e(number_format($option['amount'])); ?></b></label>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <div class="form-row" style="margin:0">
          <label>Voucher Code</label>
          <div style="display:flex;gap:8px">
            <input type="text" placeholder="Enter code…" style="flex:1;border:1px solid var(--border);border-radius:9px;padding:8px 12px;font-size:13px">
            <button class="btn btn-outline">Apply</button>
          </div>
        </div>

        <div class="form-row" style="margin:0">
          <label>Payment Method</label>
          <select class="select" style="width:100%">
            <option>Cash on Delivery</option>
            <option>GCash</option>
            <option>Bank Transfer</option>
          </select>
        </div>

        <button class="btn btn-primary btn-block" id="checkoutButton" type="button" style="margin-top:4px" <?php echo e($items->isEmpty() ? 'disabled' : ''); ?>>Proceed to Checkout</button>
      </div>
    </div>
  </div>
</div>
<div class="cart-voucher-modal" id="voucherModal" aria-hidden="true">
  <div class="cart-voucher-dialog" role="dialog" aria-modal="true" aria-labelledby="voucherModalTitle">
    <div class="cart-voucher-modal-head">
      <div><h3 id="voucherModalTitle">Available vouchers</h3><p>Offers are checked against your current cart.</p></div>
      <button type="button" class="modal-close" data-voucher-modal-close aria-label="Close vouchers">&times;</button>
    </div>
    <div class="cart-voucher-modal-body">
      <?php $__currentLoopData = $voucherData['all']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <article class="voucher-modal-item <?php echo e($voucher['applicable'] ? 'is-available' : ''); ?>">
          <div class="voucher-modal-value">PHP <?php echo e(number_format($voucher['discount'])); ?> OFF</div>
          <div class="voucher-modal-copy">
            <strong><?php echo e($voucher['code']); ?></strong>
            <span><?php echo e($voucher['shop']); ?> &middot; Minimum spend PHP <?php echo e(number_format($voucher['minimum'])); ?></span>
            <?php if($voucher['applicable']): ?>
              <span class="voucher-available">Applicable to your selected items</span>
            <?php else: ?>
              <span class="voucher-nearest">Add PHP <?php echo e(number_format($voucher['shortfall'])); ?> more from <a href="<?php echo e(route('buyer.shop', $voucher['shop_slug'])); ?>"><?php echo e($voucher['shop']); ?></a> to unlock this voucher.</span>
            <?php endif; ?>
          </div>
          <?php if($voucher['applicable']): ?>
            <button type="button" class="voucher-modal-use" data-voucher-id="<?php echo e($voucher['id']); ?>" data-voucher-group="<?php echo e($voucher['type']); ?>" data-discount="<?php echo e($voucher['discount']); ?>">Use</button>
          <?php else: ?>
            <a href="<?php echo e(route('buyer.shop', $voucher['shop_slug'])); ?>" class="voucher-modal-shop">Add items</a>
          <?php endif; ?>
        </article>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('head'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const itemChecks = [...document.querySelectorAll('.cart-select')];
  const shopChecks = [...document.querySelectorAll('.cart-shop-select')];
  const selectAll = document.getElementById('cartSelectAll');
  const checkoutButton = document.getElementById('checkoutButton');
  const voucherButtons = [...document.querySelectorAll('[data-voucher-id]')];
  const shippingOptions = [...document.querySelectorAll('input[name="shipping"]')];
  const voucherModal = document.getElementById('voucherModal');
  let appliedVoucher = null;
  if (!selectAll || !checkoutButton) return;
  const money = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function refreshSummary() {
    const selected = itemChecks.filter(input => input.checked);
    const total = selected.reduce((sum, input) => sum + Number(input.dataset.price) * Number(input.dataset.qty), 0);
    document.getElementById('cartSubtotal').textContent = '₱' + money.format(total);
    document.getElementById('cartTotal').textContent = '₱' + money.format(total);
    const shopTotals = selected.reduce((totals, input) => {
      totals[input.dataset.shop] = (totals[input.dataset.shop] || 0) + Number(input.dataset.price) * Number(input.dataset.qty);
      return totals;
    }, {});
    document.querySelectorAll('.cart-offer').forEach(offer => {
      const voucherTotal = offer.dataset.voucherGroup === 'shop' ? (shopTotals[offer.dataset.shop] || 0) : total;
      offer.hidden = voucherTotal < Number(offer.dataset.minimum);
    });
    const appliedVoucherTotal = appliedVoucher
      ? (appliedVoucher.dataset.voucherGroup === 'shop' ? (shopTotals[appliedVoucher.dataset.shop] || 0) : total)
      : 0;
    if (appliedVoucher && appliedVoucherTotal < Number(appliedVoucher.dataset.minimum)) {
      document.querySelectorAll(`[data-voucher-id="${appliedVoucher.dataset.voucherId}"]`).forEach(offer => offer.classList.remove('selected'));
      appliedVoucher = null;
    }
    const discount = appliedVoucher ? Number(appliedVoucher.dataset.discount) : 0;
    const shipping = selected.length ? Number(shippingOptions.find(input => input.checked)?.value || 0) : 0;
    const summaryRows = document.querySelectorAll('.cart-summary-row');
    summaryRows[1].querySelector('.mono').textContent = shipping ? 'PHP ' + money.format(shipping) : 'FREE';
    summaryRows[2].querySelector('.mono').textContent = '- PHP ' + money.format(Math.min(discount, total));
    document.getElementById('cartTotal').textContent = 'PHP ' + money.format(Math.max(0, total + shipping - discount));
    document.getElementById('cartSelectedCount').textContent = `${selected.length} item${selected.length === 1 ? '' : 's'}`;
    checkoutButton.disabled = selected.length === 0;

    shopChecks.forEach(check => {
      const shopItems = [...check.closest('.cart-shop').querySelectorAll('.cart-select')];
      const selectedCount = shopItems.filter(item => item.checked).length;
      check.checked = selectedCount === shopItems.length;
      check.indeterminate = selectedCount > 0 && selectedCount < shopItems.length;
    });
    selectAll.checked = selected.length === itemChecks.length;
    selectAll.indeterminate = selected.length > 0 && selected.length < itemChecks.length;
  }

  itemChecks.forEach(input => input.addEventListener('change', () => {
    refreshSummary();
  }));
  shopChecks.forEach(input => input.addEventListener('change', () => {
    input.closest('.cart-shop').querySelectorAll('.cart-select').forEach(check => check.checked = input.checked);
    refreshSummary();
  }));
  selectAll.addEventListener('change', () => {
    itemChecks.forEach(check => check.checked = selectAll.checked);
    refreshSummary();
  });
  voucherButtons.forEach(button => button.addEventListener('click', () => {
    const voucherId = button.dataset.voucherId;
    document.querySelectorAll(`[data-voucher-group="${button.dataset.voucherGroup}"]`).forEach(offer => offer.classList.remove('selected'));
    document.querySelectorAll(`[data-voucher-id="${voucherId}"]`).forEach(offer => offer.classList.add('selected'));
    appliedVoucher = button;
    voucherModal.classList.remove('open');
    voucherModal.setAttribute('aria-hidden', 'true');
    refreshSummary();
  }));
  shippingOptions.forEach(input => input.addEventListener('change', refreshSummary));
  document.querySelectorAll('[data-voucher-modal-open]').forEach(button => button.addEventListener('click', () => {
    voucherModal.classList.add('open');
    voucherModal.setAttribute('aria-hidden', 'false');
  }));
  document.querySelectorAll('[data-voucher-modal-close]').forEach(button => button.addEventListener('click', () => {
    voucherModal.classList.remove('open');
    voucherModal.setAttribute('aria-hidden', 'true');
  }));
  voucherModal.addEventListener('click', event => {
    if (event.target === voucherModal) event.currentTarget.querySelector('[data-voucher-modal-close]').click();
  });

  checkoutButton.addEventListener('click', () => {
    const selected = itemChecks.filter(input => input.checked).length;
    if (selected) showToast(`Checkout for ${selected} selected item${selected === 1 ? '' : 's'} will be available next.`, 'buy');
  });

  refreshSummary();
});

function changeCartQuantity(button, change) {
  const form = button.closest('form');
  const input = form.querySelector('input[name="qty"]');
  input.value = Math.min(99, Math.max(1, Number(input.value) + change));
  form.submit();
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\buyer\cart.blade.php ENDPATH**/ ?>