<div class="modal-overlay" id="inventoryCategoriesModal">
 <div class="modal inventory-categories-dialog" role="dialog" aria-modal="true" aria-labelledby="inventoryCategoriesTitle">
  <div class="modal-head"><h3 id="inventoryCategoriesTitle">Shop categories</h3><button type="button" class="modal-close" data-modal-close aria-label="Close">@include('buyer.partials.icon',['name'=>'x','size'=>16])</button></div>
  <div class="modal-body">
   <form class="inventory-category-form" method="POST" action="{{ route('seller.shop-categories.save') }}">@csrf
    <label class="form-row">Category name<input name="name" required maxlength="50" placeholder="e.g. Home and Living"></label><button class="btn btn-primary" type="submit">Add category</button>
   </form>
   @forelse($shopCategories as $shopCategory)
    <div class="inventory-category-row">
     <form method="POST" action="{{ route('seller.shop-categories.save') }}">@csrf<input type="hidden" name="id" value="{{ $shopCategory->id }}">
      @if(isset($shopCategory->is_visible))<input type="hidden" name="is_visible" value="{{ (int)$shopCategory->is_visible }}">@endif
      @if(isset($shopCategory->sort_order))<input type="hidden" name="sort_order" value="{{ $shopCategory->sort_order }}">@endif
      <input name="name" value="{{ $shopCategory->name }}" required maxlength="50" aria-label="Category name"><button class="btn btn-outline" type="submit">Save</button>
     </form>
     <details><summary>Remove</summary><form method="POST" action="{{ route('seller.shop-categories.delete',$shopCategory->id) }}">@csrf @method('DELETE')<p>Products will stay in your inventory.</p><button class="btn btn-danger" type="submit">Remove category</button></form></details>
    </div>
   @empty<p class="field-label">No shop categories yet.</p>@endforelse
  </div>
 </div>
</div>
