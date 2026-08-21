<div class="modal-overlay" id="addProductModal">
  <div class="modal">
    <div class="modal-head">
      <div><h3>Add Product</h3><p>List a new item in your store</p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div class="form-row"><label>Product Name</label><input type="text" placeholder="e.g. Handmade Candle Set"></div>
      <div class="form-grid-2">
        <div class="form-row"><label>Price (₱)</label><input type="number" placeholder="0.00" min="0" step="0.01"></div>
        <div class="form-row"><label>Stock Qty</label><input type="number" placeholder="0" min="0"></div>
      </div>
      <div class="form-row">
        <label>Category</label>
        <select>
          <option>Food & Drinks</option><option>Clothing</option><option>Beauty</option>
          <option>Electronics</option><option>Home & Living</option><option>Hobbies</option>
        </select>
      </div>
      <div class="form-row"><label>Description</label><textarea rows="3" placeholder="Describe your product…"></textarea></div>
      <div class="form-grid-2">
        <div class="form-row"><label>Discount (%)</label><input type="number" placeholder="0" min="0" max="100"></div>
        <div class="form-row"><label>Voucher Code</label><input type="text" placeholder="e.g. SAVE10"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Cancel</button>
      <button class="btn btn-primary"><?php echo $__env->make('seller.partials.icon', ['name' => 'plus', 'size' => 14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Add Product</button>
    </div>
  </div>
</div>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views/seller/partials/add-product-modal.blade.php ENDPATH**/ ?>