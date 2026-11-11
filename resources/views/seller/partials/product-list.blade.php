@php
 $productStates=['all'=>'All products','active'=>'Active','pending'=>'For review','rejected'=>'Rejected','archived'=>'Archived'];
@endphp
<section class="seller-product-workspace" aria-label="Product inventory" data-product-workspace>
 <div class="product-inventory-toolbar">
  @include('seller.partials.product-navigation')
  <label class="product-search-field">@include('buyer.partials.icon',['name'=>'search','size'=>18])<input type="search" id="productSearch" placeholder="Search by product name, category, or SKU" aria-label="Search products" autocomplete="off"></label>
  <button type="button" class="btn btn-primary product-add-button" data-modal="addProductModal" @disabled(!$seller->category_id) @if(!$seller->category_id) title="Set your shop category first" @endif>Add product</button>
 </div>
 <div class="product-category-tools" aria-label="Shop categories">
 <div class="product-list-meta"><p data-product-result-count aria-live="polite">{{ $products->count() }} {{ $products->count()===1?'product':'products' }}</p><button type="button" class="product-reset-filters" data-product-reset hidden>Clear filters</button></div>
  <label class="product-filter-field"><span>Category</span><select id="shopCategoryFilter" aria-label="Filter by shop category"><option value="all">All categories</option><option value="uncategorized">Uncategorized</option>@foreach($shopCategories as $shopCategory)<option value="{{ $shopCategory->id }}">{{ $shopCategory->name }}</option>@endforeach</select></label>
  <button type="button" class="btn btn-outline product-category-button" data-modal="inventoryCategoriesModal">Manage categories</button>
 </div>
 <div class="inventory-table-card product-inventory-list">
  <table class="tbl product-inventory-table" id="productTable">
   <caption class="product-visually-hidden">Your product listings with prices, stock, status, and management actions</caption>
   <thead><tr><th scope="col">Product</th><th scope="col">Price</th><th scope="col">Stock</th><th scope="col" class="product-status-column"><label class="product-column-filter"><select id="productStatusFilter" aria-label="Filter products by status">@foreach($productStates as $stateKey=>$stateLabel)<option value="{{ $stateKey }}">{{ $stateKey==='all'?'Status':$stateLabel }}</option>@endforeach</select><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></label></th><th scope="col">Manage</th></tr></thead>
   <tbody>
   @foreach($products as $product)
    @php
     $combination=\App\Support\ProductVariationSet::combination($product->variations);
     $needsStockSetup=$combination['requires_stock_setup']??false;
     $stock=$product->total_stock;
     $options=collect($product->variations??[])->flatMap(fn($group)=>$group['options']??[]);
     $prices=$options->pluck('price')->filter(fn($price)=>is_numeric($price))->map(fn($price)=>(float)$price)->push((float)$product->price);
     $minPrice=$prices->min();$maxPrice=$prices->max();
     $discounted=$product->discount_price && $product->discount_price<$product->price;
     $stockState=$needsStockSetup?'setup':($stock<=0?'out':($stock<=5?'low':'available'));
     $statusMeta=match($product->status){'active'=>['Active','check'],'pending'=>['For review','clock'],'draft'=>['Draft','file'],'rejected'=>['Rejected','x'],'archived'=>['Archived','package'],default=>[ucfirst($product->status),'package']};
    @endphp
    <tr data-product-row data-shop-categories="{{ ($shopAssignments->get($product->id,collect()))->pluck('shop_category_id')->implode(',') }}" data-status="{{ $product->status }}" data-product-name="{{ $product->name }}" data-search="{{ mb_strtolower($product->name.' '.($product->sku??'').' '.($product->category?->name??'')) }}" data-stock="{{ $stock }}" data-stock-state="{{ $stockState }}" data-price="{{ $discounted?$product->discount_price:$minPrice }}" data-updated="{{ ($product->updated_at??$product->created_at)?->timestamp??0 }}">
     <td data-label="Product" class="product-identity-cell"><div class="product-identity"><button type="button" class="product-thumbnail" data-modal="viewProductModal-{{ $product->id }}" aria-label="View {{ $product->name }}">@if($product->image)<img src="{{ \Illuminate\Support\Facades\Storage::url($product->image) }}" alt="{{ $product->name }}" loading="lazy">@else @include('buyer.partials.icon',['name'=>'package','size'=>25]) @endif</button><div class="product-identity-copy"><button type="button" class="product-name-button" data-modal="viewProductModal-{{ $product->id }}">{{ $product->name }}</button><span class="product-category">{{ $product->category?->name??'Uncategorised' }}</span><div class="product-identifiers">@if($product->sku)<span>SKU: {{ $product->sku }}</span>@endif @if($options->isNotEmpty())<span>{{ $options->count() }} {{ $options->count()===1?'variant':'variants' }}</span>@endif</div></div></div></td>
     <td data-label="Price" class="product-price-cell"><strong>@if($discounted)₱{{ number_format($product->discount_price,2) }}@elseif($maxPrice>$minPrice)₱{{ number_format($minPrice,2) }}<span class="product-price-dash">–</span>₱{{ number_format($maxPrice,2) }}@else ₱{{ number_format($product->price,2) }}@endif</strong>@if($discounted)<del>₱{{ number_format($product->price,2) }}</del>@endif</td>
     <td data-label="Stock"><div class="product-stock is-{{ $stockState }}"><strong>{{ $needsStockSetup?'Setup needed':number_format($stock) }}</strong></div><small class="product-stock-caption">{{ match($stockState){'setup'=>'Set combination stock','out'=>'Out of stock','low'=>'Low stock',default=>''} }}</small></td>
     <td data-label="Status"><span class="product-state is-{{ $product->status }}">{{ $statusMeta[0] }}</span>@if($product->status==='rejected' && $product->rejection_note)<button type="button" class="rejection-note-btn product-rejection-link" data-note="{{ $product->rejection_note }}">View reason</button>@endif</td>
     <td data-label="Manage" class="product-actions-cell"><div class="product-row-actions">
      <button type="button" class="product-icon-action" data-modal="viewProductModal-{{ $product->id }}" aria-label="View {{ $product->name }}" title="View product">@include('buyer.partials.icon',['name'=>'eye','size'=>16])</button>
      @if(in_array($product->status,['active','archived'],true))<form method="POST" action="{{ route('seller.inventory.archive',$product->id) }}">@csrf @method('PATCH')<input type="hidden" name="archived" value="{{ $product->status==='active'?1:0 }}"><button type="submit" class="product-icon-action" aria-label="{{ $product->status==='active'?'Archive':'Restore' }} {{ $product->name }}" title="{{ $product->status==='active'?'Archive product':'Restore product' }}">@include('buyer.partials.icon',['name'=>$product->status==='active'?'package':'check','size'=>16])</button></form>@endif
     </div></td>
    </tr>
   @endforeach
   </tbody>
  </table>
  <div class="product-inventory-empty" data-product-empty @if($products->isNotEmpty()) hidden @endif><span class="product-empty-icon">@include('buyer.partials.icon',['name'=>'package','size'=>28])</span><h3 data-product-empty-title>{{ $products->isEmpty()?'Your inventory starts here':'No products match your filters' }}</h3><p data-product-empty-message>{{ $products->isEmpty()?'Add your first product to start building your shop.':'Try another search or clear the filters.' }}</p>@if($products->isEmpty())<button type="button" class="btn btn-primary" data-modal="addProductModal" @disabled(!$seller->category_id)>@include('buyer.partials.icon',['name'=>'plus','size'=>16]) Add your first product</button>@else<button type="button" class="btn btn-outline" data-product-reset>Clear filters</button>@endif</div>
 </div>
</section>
