@php
use Illuminate\Support\Facades\Storage;
$existingImagesJs = collect($product->images ?? ($product->image ? [$product->image] : []))
    ->map(fn ($p) => ['path' => $p, 'url' => Storage::url($p)])->values();
$existingVariationsJs = collect($product->variations ?? [])->map(function ($group) {
    return [
        'name' => $group['name'] ?? '',
        'options' => collect($group['options'] ?? [])->map(function ($opt) {
            return [
                'value' => $opt['value'] ?? '',
                'stock' => $opt['stock'] ?? 0,
                'price' => $opt['price'] ?? null,
                'image_path' => $opt['image'] ?? null,
                'image_url' => !empty($opt['image']) ? Storage::url($opt['image']) : null,
            ];
        })->values(),
    ];
})->values();
@endphp
<div class="modal-overlay" id="editProductModal-{{ $product->id }}">
  <div class="modal" style="max-width:620px;width:100%">
    <div class="modal-head">
      <div><h3>Edit Product</h3><p>Saving will resubmit this product for admin review</p></div>
      <button class="modal-close" data-modal-close>@include('seller.partials.icon',['name'=>'x','size'=>14])</button>
    </div>
    <form method="POST" action="{{ route('seller.inventory.update', $product) }}" enctype="multipart/form-data" data-role="product-form">
      @csrf
      @method('PATCH')
      <div class="modal-body" style="max-height:70vh;overflow-y:auto;display:flex;flex-direction:column;gap:14px">

        <div style="background:var(--info-soft);border:1px solid var(--info-line);border-radius:9px;padding:10px 14px;font-size:12px;color:var(--info);display:flex;align-items:flex-start;gap:8px">
          @include('seller.partials.icon',['name'=>'bell','size'=>14])
          <span>Saving changes will resubmit this product for admin review. It will show as <strong>Pending</strong> to you and stay hidden from buyers until approved again.</span>
        </div>

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
            <label>SKU <span style="color:var(--muted);font-weight:400">(optional)</span></label>
            <input type="text" name="sku" placeholder="e.g. SKU-001" value="{{ old('sku', $product->sku) }}">
          </div>
        </div>
        <div class="form-row">
          <label>Discount Price (₱) <span style="color:var(--muted);font-weight:400">(optional — shown as a sale price to buyers)</span></label>
          <input type="number" name="discount_price" placeholder="Leave blank for no discount" min="0" step="0.01" value="{{ old('discount_price', $product->discount_price) }}">
        </div>

        {{-- Description --}}
        <div class="form-row">
          <label>Description <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <textarea name="description" rows="2" placeholder="Brief description of the product…">{{ old('description', $product->description) }}</textarea>
        </div>

        {{-- Divider --}}
        <div style="border-top:1px solid var(--border);margin:2px 0"></div>

        {{-- Shipping --}}
        <div>
          <div style="font-size:13px;font-weight:650;margin-bottom:2px">Shipping</div>
          <div style="font-size:11px;color:var(--muted);margin-bottom:10px">Used to calculate courier fees for buyers.</div>
          <div class="form-grid-2">
            <div class="form-row">
              <label>Weight (grams) <span style="color:var(--danger)">*</span> <span style="color:var(--muted);font-weight:400">(max optional, for a range)</span></label>
              <div style="display:flex;align-items:center;gap:6px">
                <input type="number" name="weight_grams" placeholder="e.g. 250" min="1" step="1" required value="{{ old('weight_grams', $product->weight_grams) }}">
                <span style="color:var(--muted)">–</span>
                <input type="number" name="weight_grams_max" placeholder="optional max" min="1" step="1" value="{{ old('weight_grams_max', $product->weight_grams_max) }}">
              </div>
            </div>
            <div class="form-row">
              <label>Condition <span style="color:var(--danger)">*</span></label>
              <select name="condition" required>
                <option value="new" {{ old('condition', $product->condition) === 'new' ? 'selected' : '' }}>New</option>
                <option value="used" {{ old('condition', $product->condition) === 'used' ? 'selected' : '' }}>Used</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <label>Package Dimensions (cm) <span style="color:var(--muted);font-weight:400">(optional)</span></label>
            <div style="display:flex;gap:8px">
              <input type="number" name="length_cm" placeholder="Length" min="0" step="0.1" value="{{ old('length_cm', $product->length_cm) }}">
              <input type="number" name="width_cm" placeholder="Width" min="0" step="0.1" value="{{ old('width_cm', $product->width_cm) }}">
              <input type="number" name="height_cm" placeholder="Height" min="0" step="0.1" value="{{ old('height_cm', $product->height_cm) }}">
            </div>
          </div>
        </div>

        {{-- Divider --}}
        <div style="border-top:1px solid var(--border);margin:2px 0"></div>

        {{-- Variations --}}
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <div>
              <div style="font-size:13px;font-weight:650">Variations</div>
              <div style="font-size:11px;color:var(--muted)">e.g. Size, Color. Each option can have its own photo and price.</div>
            </div>
            <button type="button" data-role="add-variation-btn" class="btn btn-sm btn-outline" style="display:inline-flex;align-items:center;gap:5px">
              @include('seller.partials.icon',['name'=>'plus','size'=>12]) Add Variation
            </button>
          </div>

          {{-- No variation: single stock --}}
          <div data-role="no-variation-stock" style="{{ $existingVariationsJs->isNotEmpty() ? 'display:none' : '' }}">
            <div class="form-row">
              <label>Stock <span style="color:var(--danger)">*</span></label>
              <input type="number" name="stock" data-role="stock-input" placeholder="0" min="0" value="{{ old('stock', $product->stock) }}" style="max-width:140px">
            </div>
          </div>

          {{-- Variation rows --}}
          <div data-role="variations-list" style="display:flex;flex-direction:column;gap:10px"></div>
          <div class="form-row" style="margin-top:12px">
            <label>Restock date <span style="color:var(--muted);font-weight:400">(optional)</span></label>
            <input type="date" name="restock_date" min="{{ now()->toDateString() }}" value="{{ old('restock_date', $product->restock_date?->format('Y-m-d')) }}" style="max-width:190px">
          </div>
        </div>

        {{-- Divider --}}
        <div style="border-top:1px solid var(--border);margin:2px 0"></div>

        {{-- Product Details --}}
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <div>
              <div style="font-size:13px;font-weight:650">Product Details</div>
              <div style="font-size:11px;color:var(--muted)">Add specs like Material, Weight, Dimensions, etc.</div>
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

      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
        <button class="btn btn-primary" type="submit" style="display:inline-flex;align-items:center;gap:7px">
          @include('seller.partials.icon',['name'=>'send','size'=>14]) Save & Resubmit for Review
        </button>
      </div>
    </form>
  </div>
</div>

<script>
initProductForm(document.getElementById('editProductModal-{{ $product->id }}'), {
  existingImages: @json($existingImagesJs),
  existingVideoName: {{ $product->video ? json_encode(basename($product->video)) : 'null' }},
  existingVariations: @json($existingVariationsJs),
  existingDetails: @json($product->details ?? []),
});
</script>
