<div class="modal-overlay {{ $errors->any() && old('_category_manager') ? 'open' : '' }}" id="inventoryCategoriesModal">
 <div class="modal inventory-categories-dialog" role="dialog" aria-modal="true" aria-labelledby="inventoryCategoriesTitle">
  <div class="modal-head"><div><h3 id="inventoryCategoriesTitle">Manage categories</h3><p class="category-manager-subtitle">Organize your products into shop categories.</p></div><button type="button" class="modal-close" data-modal-close aria-label="Close">@include('buyer.partials.icon',['name'=>'x','size'=>16])</button></div>
  <div class="modal-body">
   @if($errors->any() && old('_category_manager'))<p class="category-manager-error" role="alert">{{ $errors->first() }}</p>@endif
   <form class="inventory-category-form" method="POST" action="{{ route('seller.shop-categories.save') }}">@csrf
    <label class="form-row">Category name<input name="name" required maxlength="50" placeholder="e.g. Home and Living" value="{{ !old('id') ? old('name') : '' }}"></label><button class="btn btn-primary" type="submit">Create category</button>
    @include('seller.partials.category-product-picker',['selectedProductIds'=>!old('id') ? old('product_ids',[]) : []])
   </form>
   <div class="category-manager-list-heading"><strong>Your categories</strong><span>{{ $shopCategories->count() }}</span></div>
   <div class="category-manager-list">
   @forelse($shopCategories as $shopCategory)
    @php
      $assignedProductIds=(string)old('id')===(string)$shopCategory->id ? old('product_ids',[]) : $shopAssignments->flatten(1)->where('shop_category_id',$shopCategory->id)->pluck('product_id')->all();
      $assignedProductIds=is_array($assignedProductIds)?$assignedProductIds:[];
    @endphp
    <details class="inventory-category-row category-manager-item" @if($errors->any() && (string)old('id')===(string)$shopCategory->id) open @endif>
     <summary class="category-manager-item-heading"><span><strong>{{ $shopCategory->name }}</strong><small>{{ count($assignedProductIds) }} {{ count($assignedProductIds)===1?'product':'products' }}</small></span><span class="category-manager-edit-label">Edit</span></summary>
     <form method="POST" action="{{ route('seller.shop-categories.save') }}">@csrf<input type="hidden" name="id" value="{{ $shopCategory->id }}">
      @if(isset($shopCategory->is_visible))<input type="hidden" name="is_visible" value="{{ (int)$shopCategory->is_visible }}">@endif
      @if(isset($shopCategory->sort_order))<input type="hidden" name="sort_order" value="{{ $shopCategory->sort_order }}">@endif
      <label class="category-manager-name"><span>Rename category</span><input name="name" value="{{ (string)old('id')===(string)$shopCategory->id ? old('name',$shopCategory->name) : $shopCategory->name }}" required maxlength="50" aria-label="Category name"></label><button class="btn btn-outline" type="submit">Save changes</button>
      @include('seller.partials.category-product-picker',['selectedProductIds'=>$assignedProductIds])
     </form>
     <details class="category-remove-confirm"><summary>Remove category</summary><form method="POST" action="{{ route('seller.shop-categories.delete',$shopCategory->id) }}">@csrf @method('DELETE')<p>Remove this category?</p><button class="btn btn-danger" type="submit">Remove category</button></form></details>
    </details>
   @empty<div class="category-manager-empty"><strong>No categories yet</strong><p>Add your first category above to organize your inventory.</p></div>@endforelse
   </div>
  </div>
  <div class="category-manager-footer"><span>Removing a category keeps its products.</span><button type="button" class="btn btn-outline" data-modal-close>Close</button></div>
 </div>
</div>
