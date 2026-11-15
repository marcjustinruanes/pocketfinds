@php
 $selectedProductIds=is_array($selectedProductIds)?array_values(array_filter($selectedProductIds,fn($id)=>is_string($id)||is_int($id))):[];
@endphp
<input type="hidden" name="sync_products" value="1">
<input type="hidden" name="_category_manager" value="1">
<details class="category-product-picker">
 <summary>Assign products <span data-category-selection-count>{{ count($selectedProductIds) }} selected</span></summary>
 <input type="search" class="category-product-search" placeholder="Search products or SKU" aria-label="Search category products" data-category-product-search>
 <div class="category-product-options">
 @forelse($products as $categoryProduct)
  <label class="category-product-option">
   <input type="checkbox" name="product_ids[]" value="{{ $categoryProduct->id }}" @checked(in_array((string)$categoryProduct->id,array_map('strval',$selectedProductIds),true))>
   @if($categoryProduct->image)<img src="{{ \Illuminate\Support\Facades\Storage::url($categoryProduct->image) }}" alt="" loading="lazy">@endif
   <span><strong>{{ $categoryProduct->name }}</strong><small>{{ $categoryProduct->sku ?: ucfirst($categoryProduct->status) }}</small></span>
  </label>
 @empty<p class="category-manager-subtitle">Add products to your inventory first. You can assign them here later.</p>@endforelse
 </div>
</details>
