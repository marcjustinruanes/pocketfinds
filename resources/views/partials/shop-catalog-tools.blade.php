@php
 $shopCatalog=app(\App\Services\ShopCatalog::class);
 $shopCategories=$shopCatalog->categories($seller->id);
 $shopVouchers=$shopCatalog->vouchers($seller->id);
 $shopRoute=($buyerMode??false)?'buyer.shop':'guest.shop';
@endphp
<nav aria-label="Shop categories" style="display:flex;gap:8px;flex-wrap:wrap;margin:18px 0">
 <a class="btn btn-outline" href="{{ route($shopRoute,$seller->username) }}">All products</a>
 @foreach($shopCategories->whereNull('parent_id') as $parent)
  <details style="border:1px solid var(--border);border-radius:8px;padding:8px 12px" @if((string)request('shop_category')===(string)$parent->id || $shopCategories->where('parent_id',$parent->id)->contains('id',(int)request('shop_category'))) open @endif>
   <summary>{{ $parent->name }}</summary>
   <a href="{{ route($shopRoute,[$seller->username,'shop_category'=>$parent->id]) }}">All {{ $parent->name }}</a>
   @foreach($shopCategories->where('parent_id',$parent->id) as $child)<a style="display:block;padding-top:8px" href="{{ route($shopRoute,[$seller->username,'shop_category'=>$child->id]) }}">{{ $child->name }}</a>@endforeach
  </details>
 @endforeach
 @if($shopCategories->isNotEmpty())<a class="btn btn-outline" href="{{ route($shopRoute,[$seller->username,'shop_category'=>'uncategorized']) }}">Uncategorized</a>@endif
</nav>
@include('partials.claimable-vouchers',['claimableVouchers'=>$shopVouchers])
