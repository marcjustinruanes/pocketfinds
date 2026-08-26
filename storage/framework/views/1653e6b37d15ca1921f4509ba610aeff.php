<?php $__env->startSection('title', 'My Cart'); ?>
<?php $__env->startSection('page-title', 'My Cart'); ?>
<?php $__env->startSection('page-sub', 'Review your selected items'); ?>

<?php $__env->startSection('content'); ?>
<?php ($subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['qty'])); ?>
<div class="cart-layout">
  <div class="stack">
    <div class="card cart-items-card">
      <div class="card-head"><h2>Cart Items</h2></div>
      <div class="card-pad">
        <?php if(count($items)): ?>
          <label class="cart-select-all">
            <input id="cartSelectAll" type="checkbox" checked>
            <span>Select all items (<?php echo e($items->count()); ?>)</span>
          </label>
          <div class="cart-column-head" aria-hidden="true">
            <span></span><span></span><span>Product</span><span>Subtotal</span><span>Actions</span>
          </div>
          <div class="cart-items-scroll">
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
                  <div class="cart-item-quantity">&#8369;<?php echo e(number_format($item['price'], 2)); ?> each</div>
                </div>
                <div class="cart-item-total">₱<?php echo e(number_format($item['price'] * $item['qty'], 2)); ?></div>
                <div class="cart-item-actions">
                  <button type="button" class="cart-edit" aria-label="Edit item options" title="Edit item options"
                    data-edit-key="<?php echo e($item['key']); ?>" data-edit-qty="<?php echo e($item['qty']); ?>" data-edit-color="<?php echo e($item['color']); ?>" data-edit-size="<?php echo e($item['size']); ?>" data-edit-options="<?php echo e(json_encode($item['variation_options'])); ?>">
                    <?php echo $__env->make('buyer.partials.icon', ['name' => 'edit', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                  </button>
                  <form method="POST" action="<?php echo e(route('buyer.cart.remove', ['key' => $item['key']])); ?>">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="cart-remove" aria-label="Remove item" title="Remove item"><?php echo $__env->make('buyer.partials.icon', ['name' => 'trash', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></button>
                  </form>
                </div>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
          </section>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
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
    <div class="card cart-summary-card">
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
            <p class="cart-no-voucher">No vouchers apply to the selected cart yet.</p>
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
          <select class="select" style="width:100%" name="payment_method">
            <?php $__empty_1 = true; $__currentLoopData = $paymentMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paymentMethod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
              <option value="<?php echo e($paymentMethod->id); ?>"><?php echo e($paymentMethod->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <option value="" disabled selected>No payment methods available</option>
            <?php endif; ?>
          </select>
        </div>

        <form method="POST" action="<?php echo e(route('buyer.checkout')); ?>" id="checkoutForm">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="shipping_amount" id="checkoutShipping">
          <input type="hidden" name="payment_method" id="checkoutPayment">
          <button class="btn btn-primary btn-block" id="checkoutButton" type="submit" style="margin-top:4px" <?php echo e($items->isEmpty() ? 'disabled' : ''); ?>>Proceed to Checkout</button>
        </form>
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
<div class="cart-voucher-modal" id="itemEditModal" aria-hidden="true">
  <div class="cart-voucher-dialog" role="dialog" aria-modal="true" aria-labelledby="itemEditTitle">
    <div class="cart-voucher-modal-head">
      <div><h3 id="itemEditTitle">Edit item</h3><p>Update the options and quantity for this cart item.</p></div>
      <button type="button" class="modal-close" data-item-edit-close aria-label="Close item editor">&times;</button>
    </div>
    <form method="POST" id="itemEditForm">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PATCH'); ?>
      <div class="cart-voucher-modal-body">
        <div class="form-row" id="editColorRow" hidden><label for="editColor">Color</label><select name="color" id="editColor"></select></div>
        <div class="form-row" id="editSizeRow" hidden><label for="editSize">Size</label><select name="size" id="editSize"></select></div>
        <div class="form-row"><label for="editQty">Quantity</label><input id="editQty" name="qty" type="number" min="1" max="99" required></div>
        <button type="submit" class="btn btn-primary btn-block">Save item</button>
      </div>
    </form>
  </div>
</div>
<div class="cart-voucher-modal" id="paymentModal" aria-hidden="true">
  <div class="cart-voucher-dialog" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
    <div class="cart-voucher-modal-head">
      <div><h3 id="paymentModalTitle">Payment details</h3><p>Enter the account details needed for this payment method.</p></div>
      <button type="button" class="modal-close" data-payment-close aria-label="Close payment details">&times;</button>
    </div>
    <div class="cart-voucher-modal-body">
      <div class="form-row payment-detail-field" data-payment-detail="gcash"><label for="gcashNumber">GCash number</label><input id="gcashNumber" type="tel" inputmode="numeric" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX"></div>
      <div class="form-row payment-detail-field" data-payment-detail="gcash"><label for="gcashName">GCash account name</label><input id="gcashName" type="text" placeholder="Account name"></div>
      <div class="form-row payment-detail-field" data-payment-detail="bank"><label for="bankName">Bank name</label><input id="bankName" type="text" placeholder="Bank name"></div>
      <div class="form-row payment-detail-field" data-payment-detail="bank"><label for="bankAccount">Bank account number</label><input id="bankAccount" type="text" inputmode="numeric" placeholder="Account number"></div>
      <div class="form-row payment-detail-field" data-payment-detail="bank"><label for="bankOwner">Account holder name</label><input id="bankOwner" type="text" placeholder="Account holder name"></div>
      <button type="button" class="btn btn-outline payment-detail-field" id="sendPaymentCode" data-payment-detail="verification">Send verification code</button>
      <div class="form-row payment-detail-field verification-code-field" data-payment-detail="verification" hidden><label for="paymentCode">Verification code</label><input id="paymentCode" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code"></div>
      <p id="paymentNoDetails" class="cart-no-voucher">No extra payment details are required.</p>
      <button type="button" class="btn btn-primary btn-block" id="paymentVerify">Verify details and code</button>
      <p id="paymentStatus" class="cart-no-voucher" hidden></p>
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
  const checkoutForm = document.getElementById('checkoutForm');
  const voucherButtons = [...document.querySelectorAll('[data-voucher-id]')];
  const shippingOptions = [...document.querySelectorAll('input[name="shipping"]')];
  const voucherModal = document.getElementById('voucherModal');
  const itemEditModal = document.getElementById('itemEditModal');
  const itemEditForm = document.getElementById('itemEditForm');
  const paymentModal = document.getElementById('paymentModal');
  const paymentSelect = document.querySelector('[name="payment_method"]');
  let appliedVoucher = null;
  if (!selectAll || !checkoutButton) return;
  const money = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function refreshSummary() {
    itemChecks.forEach(input => {
      const row = input.closest('.cart-item');
      row.querySelector('.cart-item-total').textContent = '₱' + money.format(Number(input.dataset.price) * Number(input.dataset.qty));
    });
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
  window.cartRefreshSummary = refreshSummary;

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
  document.querySelectorAll('.cart-edit').forEach(button => button.addEventListener('click', () => {
    const options = JSON.parse(button.dataset.editOptions || '{}');
    const setOptions = (field, rowId, values, selected) => {
      const row = document.getElementById(rowId);
      const select = row.querySelector('select');
      select.innerHTML = '<option value="">No ' + field + ' selected</option>';
      values.forEach(value => {
        const option = new Option(value, value, false, value === selected);
        select.add(option);
      });
      row.hidden = values.length === 0;
    };
    setOptions('color', 'editColorRow', options.color || [], button.dataset.editColor);
    setOptions('size', 'editSizeRow', options.size || [], button.dataset.editSize);
    document.getElementById('editQty').value = button.dataset.editQty;
    itemEditForm.action = '<?php echo e(url('/buyer/cart')); ?>/' + encodeURIComponent(button.dataset.editKey) + '/edit';
    itemEditModal.classList.add('open');
    itemEditModal.setAttribute('aria-hidden', 'false');
  }));
  document.querySelector('[data-item-edit-close]').addEventListener('click', () => {
    itemEditModal.classList.remove('open');
    itemEditModal.setAttribute('aria-hidden', 'true');
  });
  paymentSelect?.addEventListener('change', () => {
    const name = paymentSelect.options[paymentSelect.selectedIndex]?.text.toLowerCase() || '';
    if (!name.includes('gcash') && !name.includes('bank')) return;
    document.querySelectorAll('.payment-detail-field[data-payment-detail="gcash"], .payment-detail-field[data-payment-detail="bank"]').forEach(field => {
      field.hidden = !(name.includes('gcash') && field.dataset.paymentDetail === 'gcash') && !(name.includes('bank') && field.dataset.paymentDetail === 'bank');
    });
    document.getElementById('sendPaymentCode').hidden = false;
    document.querySelector('.verification-code-field').hidden = true;
    document.getElementById('paymentCode').value = '';
    document.getElementById('paymentNoDetails').hidden = name.includes('gcash') || name.includes('bank');
    document.getElementById('paymentStatus').hidden = true;
    paymentModal.classList.add('open');
    paymentModal.setAttribute('aria-hidden', 'false');
  });
  document.querySelector('[data-payment-close]').addEventListener('click', () => {
    paymentModal.classList.remove('open');
    paymentModal.setAttribute('aria-hidden', 'true');
  });
  document.getElementById('paymentVerify').addEventListener('click', () => {
    const visibleFields = [...paymentModal.querySelectorAll('.payment-detail-field:not([hidden]) input')];
    const valid = visibleFields.every(input => input.value.trim() && input.checkValidity()) && paymentCode === document.getElementById('paymentCode').value.trim();
    const status = document.getElementById('paymentStatus');
    status.textContent = valid ? 'Details verified for this checkout.' : 'Please complete the required details.';
    status.hidden = false;
    status.style.color = valid ? 'var(--success)' : 'var(--danger)';
  });
  let paymentCode = '';
  document.getElementById('sendPaymentCode').addEventListener('click', () => {
    paymentCode = String(Math.floor(100000 + Math.random() * 900000));
    document.querySelector('.verification-code-field').hidden = false;
    document.getElementById('sendPaymentCode').textContent = 'Code sent';
  });
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

  checkoutForm.addEventListener('submit', event => {
    const selected = itemChecks.filter(input => input.checked);
    if (!selected.length || !confirm(`Are you sure you want to place this order for ${selected.length} item${selected.length === 1 ? '' : 's'}?`)) {
      event.preventDefault();
      return;
    }
    selected.forEach(input => {
      const key = input.closest('.cart-item').querySelector('.cart-edit')?.dataset.editKey;
      if (!key) return;
      const hidden = document.createElement('input');
      hidden.type = 'hidden'; hidden.name = 'items[]'; hidden.value = key;
      checkoutForm.appendChild(hidden);
    });
    document.getElementById('checkoutShipping').value = shippingOptions.find(input => input.checked)?.value || 0;
    document.getElementById('checkoutPayment').value = paymentSelect?.value || '';
  });

  refreshSummary();
});

</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('buyer.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\buyer\cart.blade.php ENDPATH**/ ?>