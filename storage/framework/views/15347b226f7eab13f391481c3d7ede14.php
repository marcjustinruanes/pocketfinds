<div class="modal-overlay" id="addProductModal">
  <div class="modal" style="max-width:620px;width:100%">
    <div class="modal-head">
      <div><h3>Add Product</h3><p>Submit a product for admin review</p></div>
      <button class="modal-close" data-modal-close><?php echo $__env->make('seller.partials.icon',['name'=>'x','size'=>14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></button>
    </div>
    <form method="POST" action="<?php echo e(route('seller.inventory.store')); ?>" enctype="multipart/form-data" id="addProductForm">
      <?php echo csrf_field(); ?>
      <div class="modal-body" style="max-height:70vh;overflow-y:auto;display:flex;flex-direction:column;gap:14px">

        
        <div class="form-row">
          <label>Product Image <span style="color:var(--danger)">*</span></label>
          <div id="imgDropZone" style="border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;cursor:pointer;transition:border-color .2s" onclick="document.getElementById('imageInput').click()">
            <img id="imgPreview" src="" alt="" style="display:none;max-height:120px;max-width:100%;border-radius:8px;margin:0 auto">
            <div id="imgPlaceholder" style="display:flex;flex-direction:column;align-items:center;gap:5px;color:var(--muted)">
              <?php echo $__env->make('seller.partials.icon',['name'=>'image','size'=>26], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              <span style="font-size:12px">Click to upload or drag & drop</span>
              <span style="font-size:11px">JPG, PNG, WEBP — max 4MB</span>
            </div>
          </div>
          <input type="file" id="imageInput" name="image" accept="image/jpeg,image/png,image/webp" required style="display:none">
          <?php $__errorArgs = ['image'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span style="font-size:11px;color:var(--danger);margin-top:3px;display:block"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        
        <div class="form-row">
          <label>Product Name <span style="color:var(--danger)">*</span></label>
          <input type="text" name="name" placeholder="e.g. Cotton T-Shirt" required value="<?php echo e(old('name')); ?>">
        </div>

        
        <div class="form-grid-2">
          <div class="form-row">
            <label>Price (₱) <span style="color:var(--danger)">*</span></label>
            <input type="number" name="price" placeholder="0.00" min="0" step="0.01" required value="<?php echo e(old('price')); ?>">
          </div>
          <div class="form-row">
            <label>SKU <span style="color:var(--muted);font-weight:400">(optional)</span></label>
            <input type="text" name="sku" placeholder="e.g. SKU-001" value="<?php echo e(old('sku')); ?>">
          </div>
        </div>

        
        <div class="form-row">
          <label>Description <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <textarea name="description" rows="2" placeholder="Brief description of the product…"><?php echo e(old('description')); ?></textarea>
        </div>

        
        <div style="border-top:1px solid var(--border);margin:2px 0"></div>

        
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <div>
              <div style="font-size:13px;font-weight:650">Variations</div>
              <div style="font-size:11px;color:var(--muted)">e.g. Size, Color. Leave empty if product has no variation.</div>
            </div>
            <button type="button" id="addVariationBtn" class="btn btn-sm btn-outline" style="display:inline-flex;align-items:center;gap:5px">
              <?php echo $__env->make('seller.partials.icon',['name'=>'plus','size'=>12], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Add Variation
            </button>
          </div>

          
          <div id="noVariationStock">
            <div class="form-row">
              <label>Stock <span style="color:var(--danger)">*</span></label>
              <input type="number" name="stock" id="stockInput" placeholder="0" min="0" value="0" style="max-width:140px">
            </div>
          </div>

          
          <div id="variationsList" style="display:flex;flex-direction:column;gap:10px"></div>
        </div>

        
        <div style="border-top:1px solid var(--border);margin:2px 0"></div>

        
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <div>
              <div style="font-size:13px;font-weight:650">Product Details</div>
              <div style="font-size:11px;color:var(--muted)">Add specs like Material, Weight, Dimensions, etc.</div>
            </div>
            <button type="button" id="addDetailBtn" class="btn btn-sm btn-outline" style="display:inline-flex;align-items:center;gap:5px">
              <?php echo $__env->make('seller.partials.icon',['name'=>'plus','size'=>12], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Add Detail
            </button>
          </div>
          <div id="detailsList" style="display:flex;flex-direction:column;gap:8px"></div>
        </div>

        
        <input type="hidden" name="variations" id="variationsJson">
        <input type="hidden" name="details" id="detailsJson">

        <div style="background:var(--info-soft);border:1px solid var(--info-line);border-radius:9px;padding:10px 14px;font-size:12px;color:var(--info);display:flex;align-items:flex-start;gap:8px">
          <?php echo $__env->make('seller.partials.icon',['name'=>'bell','size'=>14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <span>Your product will be reviewed by admin to ensure it matches your shop category <strong><?php echo e(optional(\DB::table('categories')->where('id', auth()->user()->category_id)->first())->name ?? '—'); ?></strong> before going live.</span>
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
        <button class="btn btn-primary" type="submit" style="display:inline-flex;align-items:center;gap:7px">
          <?php echo $__env->make('seller.partials.icon',['name'=>'send','size'=>14], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Submit for Review
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Image upload ──────────────────────────────────────────────
const imgInput       = document.getElementById('imageInput');
const imgPreview     = document.getElementById('imgPreview');
const imgPlaceholder = document.getElementById('imgPlaceholder');
const imgDropZone    = document.getElementById('imgDropZone');

imgInput.addEventListener('change', function () { if (this.files[0]) showImgPreview(this.files[0]); });
['dragover','dragleave','drop'].forEach(evt => imgDropZone.addEventListener(evt, e => {
  e.preventDefault();
  imgDropZone.style.borderColor = evt === 'dragover' ? 'var(--primary)' : 'var(--border)';
  if (evt === 'drop' && e.dataTransfer.files[0]) { imgInput.files = e.dataTransfer.files; showImgPreview(e.dataTransfer.files[0]); }
}));
function showImgPreview(file) {
  const r = new FileReader();
  r.onload = e => { imgPreview.src = e.target.result; imgPreview.style.display = 'block'; imgPlaceholder.style.display = 'none'; };
  r.readAsDataURL(file);
}

// ── Variations ────────────────────────────────────────────────
const variationsList   = document.getElementById('variationsList');
const noVariationStock = document.getElementById('noVariationStock');
let varCount = 0;

document.getElementById('addVariationBtn').addEventListener('click', () => {
  noVariationStock.style.display = 'none';
  document.getElementById('stockInput').value = 0;
  const idx = varCount++;
  const div = document.createElement('div');
  div.dataset.varIdx = idx;
  div.style.cssText = 'background:var(--paper);border:1px solid var(--border);border-radius:10px;padding:12px 14px';
  div.innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
      <input type="text" placeholder="Variation name (e.g. Size, Color)" class="var-name-input"
        style="flex:1;border:1px solid var(--border);border-radius:7px;padding:7px 10px;font-size:13px"
        oninput="syncVariations()">
      <button type="button" onclick="removeVariation(this)" style="border:0;background:none;cursor:pointer;color:var(--danger);padding:4px">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="options-list" style="display:flex;flex-direction:column;gap:6px"></div>
    <button type="button" class="btn btn-sm btn-outline" onclick="addOption(this)" style="margin-top:8px;font-size:11px;display:inline-flex;align-items:center;gap:4px">
      <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Option
    </button>`;
  variationsList.appendChild(div);
  addOption(div.querySelector('[onclick="addOption(this)"]'));
});

function addOption(btn) {
  const optList = btn.closest('[data-var-idx]').querySelector('.options-list');
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:8px';
  row.innerHTML = `
    <input type="text" placeholder="Option (e.g. Red, XL)" class="opt-value"
      style="flex:1;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:12px"
      oninput="syncVariations()">
    <input type="number" placeholder="Stock" min="0" class="opt-stock"
      style="width:80px;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:12px"
      value="0" oninput="syncVariations()">
    <button type="button" onclick="this.closest('div').remove();syncVariations()" style="border:0;background:none;cursor:pointer;color:var(--muted);padding:2px">
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>`;
  optList.appendChild(row);
}

function removeVariation(btn) {
  btn.closest('[data-var-idx]').remove();
  if (variationsList.children.length === 0) noVariationStock.style.display = '';
  syncVariations();
}

function syncVariations() {
  const vars = [];
  variationsList.querySelectorAll('[data-var-idx]').forEach(varDiv => {
    const name = varDiv.querySelector('.var-name-input').value.trim();
    const options = [];
    varDiv.querySelectorAll('.options-list > div').forEach(row => {
      const val   = row.querySelector('.opt-value').value.trim();
      const stock = parseInt(row.querySelector('.opt-stock').value) || 0;
      if (val) options.push({ value: val, stock });
    });
    if (name) vars.push({ name, options });
  });
  document.getElementById('variationsJson').value = vars.length ? JSON.stringify(vars) : '';
}

// ── Details ───────────────────────────────────────────────────
const detailsList = document.getElementById('detailsList');

document.getElementById('addDetailBtn').addEventListener('click', () => {
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:8px';
  row.innerHTML = `
    <input type="text" placeholder="Label (e.g. Material)" class="detail-label"
      style="flex:1;border:1px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px"
      oninput="syncDetails()">
    <input type="text" placeholder="Value (e.g. Cotton)" class="detail-value"
      style="flex:1;border:1px solid var(--border);border-radius:7px;padding:7px 10px;font-size:12px"
      oninput="syncDetails()">
    <button type="button" onclick="this.closest('div').remove();syncDetails()" style="border:0;background:none;cursor:pointer;color:var(--muted);padding:2px">
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>`;
  detailsList.appendChild(row);
});

function syncDetails() {
  const details = [];
  detailsList.querySelectorAll('div').forEach(row => {
    const label = row.querySelector('.detail-label').value.trim();
    const value = row.querySelector('.detail-value').value.trim();
    if (label && value) details.push({ label, value });
  });
  document.getElementById('detailsJson').value = details.length ? JSON.stringify(details) : '';
}

// Sync before submit
document.getElementById('addProductForm').addEventListener('submit', () => {
  syncVariations();
  syncDetails();
});
</script>
<?php /**PATH C:\Users\Administrator\pocketfinds\resources\views\seller\partials\add-product-modal.blade.php ENDPATH**/ ?>