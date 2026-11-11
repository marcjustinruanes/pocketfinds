@php
use Illuminate\Support\Facades\Storage;
$existingImagesJs = collect($product->images ?? ($product->image ? [$product->image] : []))
    ->map(fn ($p) => ['path' => $p, 'url' => Storage::url($p)])->values();
$prepareVariation = function ($group) use (&$prepareVariation) {
    $group['options'] = collect($group['options'] ?? [])->map(function ($option) {
        $option['image_path'] = $option['image'] ?? null;
        $option['image_url'] = !empty($option['image']) ? Storage::url($option['image']) : null;
        return $option;
    })->all();
    if (isset($group['axes'])) $group['axes'] = array_map($prepareVariation, $group['axes']);
    return $group;
};
$existingVariationsJs = collect($product->variations ?? [])->map($prepareVariation)->values();
@endphp
<div class="modal-overlay product-editor-overlay" id="editProductModal-{{ $product->id }}">
  <div class="modal product-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="editProductTitle-{{ $product->id }}">
    <div class="modal-head product-editor-header">
      <div class="product-editor-title"><span class="product-details-eyebrow">Edit product</span><h3 id="editProductTitle-{{ $product->id }}">{{ $product->name }}</h3></div>
    </div>
    <form id="editProductForm-{{ $product->id }}" method="POST" action="{{ route('seller.inventory.update', $product) }}" enctype="multipart/form-data" data-role="product-form">
      @csrf
      @method('PATCH')
      <input type="hidden" name="_product_editor_id" value="{{ $product->id }}">
      <div class="modal-body product-editor-body">
        <div class="product-form-error" data-product-form-error role="alert" @if(!$errors->any() || (string)old('_product_editor_id') !== (string)$product->id) hidden @endif>{{ (string)old('_product_editor_id') === (string)$product->id ? $errors->first() : '' }}</div>

        <div class="product-editor-notice">
          @include('seller.partials.icon',['name'=>'bell','size'=>14])
          <span>Saving changes will resubmit this product for admin review. It will show as <strong>Pending</strong> to you and stay hidden from buyers until approved again.</span>
        </div>

        <section class="product-editor-section product-editor-media"><h4>Photos & video</h4>
        {{-- Images --}}
        <div class="form-row">
          <label>Cover Photos <span style="color:var(--muted);font-weight:400">(optional if every variation option below has its own photo)</span></label>
          <div data-role="img-dropzone" style="border:2px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:border-color .2s">
            <div data-role="img-thumbs" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center"></div>
            <div data-role="img-placeholder" style="display:flex;flex-direction:column;align-items:center;gap:5px;color:var(--muted)">
              @include('seller.partials.icon',['name'=>'image','size'=>26])
              <span style="font-size:12px">Click to upload or drag & drop (up to 9 photos)</span>
              <span style="font-size:11px">JPG, PNG, WEBP — max 4MB each. First photo is the cover.</span>
            </div>
          </div>
          <input type="file" data-role="images-input" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none">
          @error('images')<span style="font-size:11px;color:var(--danger);margin-top:3px;display:block">{{ $message }}</span>@enderror
        </div>

        {{-- Video --}}
        <div class="form-row">
          <label>Product Video <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <div data-role="video-dropzone" style="border:2px dashed var(--border);border-radius:10px;padding:14px;text-align:center;cursor:pointer">
            <div data-role="video-placeholder" style="display:flex;flex-direction:column;align-items:center;gap:5px;color:var(--muted)">
              @include('seller.partials.icon',['name'=>'video','size'=>22])
              <span style="font-size:12px">Click to upload a short product video</span>
              <span style="font-size:11px">MP4, MOV, WEBM — max 50MB</span>
            </div>
            <div data-role="video-selected" style="display:none;font-size:12px;color:var(--text)">
              <span data-role="video-filename"></span>
              <button type="button" data-role="video-remove-btn" style="border:0;background:none;color:var(--danger);cursor:pointer;font-size:12px;margin-left:6px">Remove</button>
            </div>
          </div>
          <input type="file" data-role="video-input" name="video" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm" style="display:none">
          <input type="hidden" name="keep_video" value="{{ $product->video ? '1' : '' }}" data-role="keep-video-input">
          @error('video')<span style="font-size:11px;color:var(--danger);margin-top:3px;display:block">{{ $message }}</span>@enderror
        </div>

        </section>
        <section class="product-editor-section product-editor-basics"><h4>Product information</h4>
        {{-- Name --}}
        <div class="form-row">
          <label>Product Name <span style="color:var(--danger)">*</span></label>
          <input type="text" name="name" placeholder="e.g. Cotton T-Shirt" required value="{{ old('name', $product->name) }}">
        </div>

        {{-- Price + SKU --}}
        <div class="form-grid-2">
          <div class="form-row">
            <label>Price (₱) <span style="color:var(--danger)">*</span></label>
            <input type="number" name="price" placeholder="0.00" min="0" step="0.01" required value="{{ old('price', $product->price) }}">
          </div>
          <div class="form-row">
            <label>SKU</label>
            <input type="text" value="{{ $product->sku }}" placeholder="Generated automatically when saved" readonly aria-label="Automatically generated SKU">
          </div>
        </div>
        <div data-role="no-variation-stock" style="{{ $existingVariationsJs->isNotEmpty() ? 'display:none' : '' }}">
          <div class="form-row">
            <label>Stock <span style="color:var(--danger)">*</span></label>
            <input type="number" name="stock" data-role="stock-input" placeholder="0" min="0" value="{{ old('stock', $product->stock) }}" style="max-width:140px">
          </div>
        </div>
        <div class="product-discount-restock-row">
          <div class="form-row">
            <label>Discount Price (₱)</label>
            <input type="number" name="discount_price" placeholder="Leave blank for no discount" min="0" step="0.01" value="{{ old('discount_price', $product->discount_price) }}">
          </div>
          <div class="form-row">
            <label for="editProductRestock-{{ $product->id }}">Restock date <span style="color:var(--muted);font-weight:400">(optional)</span></label>
            <input id="editProductRestock-{{ $product->id }}" type="date" name="restock_date" min="{{ min(now()->toDateString(), $product->restock_date?->format('Y-m-d') ?? now()->toDateString()) }}" value="{{ old('restock_date', $product->restock_date?->format('Y-m-d')) }}">
          </div>
        </div>

        {{-- Description --}}
        <div class="form-row">
          <label>Description <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <textarea name="description" rows="2" placeholder="Brief description of the product…">{{ old('description', $product->description) }}</textarea>
        </div>

        </section>
        {{-- Divider --}}


        @include('seller.partials.product-package-fields', ['packageProduct'=>$product])

        {{-- Variations --}}
        <div class="product-editor-section product-editor-variations">
          <div class="product-options-heading">
            <div>
              <div style="font-size:13px;font-weight:650">Product options</div>
              <div class="product-options-intro">Add the colors, sizes or measurements you sell.</div>
            </div>
            <button type="button" data-role="add-variation-btn" class="btn btn-sm btn-outline" style="display:inline-flex;align-items:center;gap:5px">
              @include('seller.partials.icon',['name'=>'plus','size'=>12]) Add Variation
            </button>
          </div>

          {{-- Variation rows --}}
          <div data-role="variations-list" style="display:flex;flex-direction:column;gap:10px"></div>
        </div>

        {{-- Divider --}}


        {{-- Product Details --}}
        <div class="product-editor-section product-editor-specifications">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <div>
              <div style="font-size:13px;font-weight:650">Product Details</div>
              <div style="font-size:11px;color:var(--muted)">Add extra details like texture, material or care instructions.</div>
            </div>
            <button type="button" data-role="add-detail-btn" class="btn btn-sm btn-outline" style="display:inline-flex;align-items:center;gap:5px">
              @include('seller.partials.icon',['name'=>'plus','size'=>12]) Add Detail
            </button>
          </div>
          <div data-role="details-list" style="display:flex;flex-direction:column;gap:8px"></div>
        </div>

        {{-- Hidden JSON fields --}}
        <input type="hidden" name="variations" data-role="variations-json">
        <input type="hidden" name="details" data-role="details-json">
      </div>

      <div class="modal-foot product-editor-footer">
        <button class="btn btn-outline" type="button" data-discard-product>Cancel</button>
        <button class="btn btn-primary" type="submit" style="display:inline-flex;align-items:center;gap:7px">
          @include('seller.partials.icon',['name'=>'send','size'=>14]) Save & submit for review
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
 const root = document.getElementById('editProductModal-{{ $product->id }}');
 const template = root.cloneNode(true);
 const options = {
  existingImages: @json($existingImagesJs),
  existingVideoName: {{ $product->video ? json_encode(basename($product->video)) : 'null' }},
  existingVariations: @json($existingVariationsJs),
  existingDetails: @json($product->details ?? []),
 };
 function setup(editor) {
  initProductForm(editor, JSON.parse(JSON.stringify(options)));
  const cancel = editor.querySelector('[data-discard-product]');
  const requestDiscard = () => window.confirmDiscardProductEditing(() => {
   const clean = template.cloneNode(true);
   clean.classList.remove('open');
   editor.replaceWith(clean);
   setup(clean);
  }, cancel);
  cancel.addEventListener('click', requestDiscard);
  editor.addEventListener('click', event => {
   if (event.target === editor) { event.stopImmediatePropagation(); requestDiscard(); }
  }, true);
 }
 setup(root);
 @if($errors->any() && (string)old('_product_editor_id') === (string)$product->id)
 root.classList.add('open');
 @endif
})();
</script>
