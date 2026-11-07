@extends('buyer.layout')
@section('title','My Wishlist')
@section('hide-page-heading', true)
@section('body-class','buyer-wishlist-page cart-page-layout')
@push('head')
<style>{!! file_get_contents(public_path('css/cart-page.css')) !!}</style>
<link rel="stylesheet" href="{{ asset('css/buyer-wishlist.css') }}?v={{ filemtime(public_path('css/buyer-wishlist.css')) }}">
@endpush
@section('content')
@include('commerce.messages')
@php
  $shops = $wishlist->groupBy(fn($item) => $wishlistProducts->get($item->product_id)['seller_slug'] ?? 'unavailable');
@endphp
<div class="cart-layout wishlist-cart-layout"><div class="card cart-items-card">
  <div class="card-head"><h2>Your items <span class="cart-count">{{ $wishlist->count() }}</span></h2>
    @if($wishlist->isNotEmpty())<div class="cart-header-actions"><input type="checkbox" data-wishlist-all checked aria-label="Select all wishlist items"><button type="button" class="cart-bulk-delete" data-wishlist-delete="all" aria-label="Remove selected wishlist items" title="Remove selected items">@include('buyer.partials.icon',['name'=>'trash','size'=>15])</button></div>@endif
  </div>
  <div class="card-pad">
  @forelse($shops as $slug=>$shopItems)
    @php
      $shopProduct = $wishlistProducts->get($shopItems->first()->product_id);
    @endphp
    <section class="cart-shop wishlist-shop">
      <div class="cart-shop-head"><div class="cart-check-wrap"><input type="checkbox" data-wishlist-shop checked aria-label="Select items from {{ $shopProduct['seller'] ?? 'unavailable products' }}">
      @if($shopProduct)<a href="{{ route('buyer.shop',$slug) }}">{{ $shopProduct['seller'] }} @include('buyer.partials.icon',['name'=>'shop','size'=>17])</a><span class="cart-shop-message-divider" aria-hidden="true">|</span><button type="button" class="cart-shop-message" data-messages-trigger data-messages-url="{{ route('buyer.messages',['seller'=>$slug]) }}" aria-label="Message {{ $shopProduct['seller'] }}" title="Message shop">@include('buyer.partials.icon',['name'=>'chat','size'=>16])</button>@else<span>Unavailable products</span>@endif
      </div><div class="cart-header-actions"><span class="cart-count">{{ $shopItems->count() }} item{{ $shopItems->count()===1?'':'s' }}</span><button type="button" class="cart-bulk-delete" data-wishlist-delete="shop" aria-label="Remove selected items from this shop" title="Remove selected shop items">@include('buyer.partials.icon',['name'=>'trash','size'=>15])</button></div></div>
      <div class="cart-items">
      @foreach($shopItems as $item)
        @php
          $p = $wishlistProducts->get($item->product_id);
          $outOfStock = $p && ($p['stock'] ?? 0) <= 0;
        @endphp
        <article class="cart-item wishlist-item">
          <input type="checkbox" class="wishlist-select" checked aria-label="Select {{ $p['name'] ?? 'unavailable product' }}">
          <div class="cart-item-image wishlist-image">@if(!empty($p['img']))<img src="{{ $p['img'] }}" alt="{{ $p['name'] }}" loading="lazy">@else @include('buyer.partials.icon',['name'=>'package','size'=>26]) @endif @if($outOfStock)<span class="wishlist-stock-label">Out of stock</span>@endif</div>
          <div class="wishlist-item-details">
            @if($p)<a class="cart-item-name" href="{{ route('buyer.product',$p['id']) }}">{{ $p['name'] }}</a>@else<strong class="cart-item-name">Unavailable product</strong>@endif
            @if(!empty($p['variants']))<div class="wishlist-options">{{ implode(' · ',array_keys($p['variants'])) }} options available</div>@endif
            <div class="wishlist-selected-options" hidden></div>
            @unless($p)<small>This product is unavailable.</small>@endunless
          </div>
          <div class="cart-item-pricing">@if($p)<div class="cart-item-total">₱{{ number_format($p['price'],2) }}</div>@if($p['old_price']??null)<del class="wishlist-old-price">₱{{ number_format($p['old_price'],2) }}</del>@endif @endif</div>
          <div class="cart-item-actions">
            @if($p)<button type="button" class="cart-action-btn" data-messages-trigger data-wishlist-message data-messages-url="{{ route('buyer.messages',['seller'=>$slug,'product'=>$p['id']]) }}" aria-label="Message about {{ $p['name'] }}" title="Message about this product">@include('buyer.partials.icon',['name'=>'chat','size'=>15])</button>@endif
            @if($p)<div class="pc-actions" data-wishlist-product="{{ json_encode($p) }}"><button type="button" class="cart-action-btn" data-wishlist-options aria-label="Edit options for {{ $p['name'] }}" title="Edit options" @disabled($outOfStock || ($p['on_vacation']??false))>@include('buyer.partials.icon',['name'=>'edit','size'=>15])</button><button type="button" class="wishlist-add-cart" data-wishlist-add aria-label="Add {{ $p['name'] }} to cart" title="Add to cart" @disabled($outOfStock || ($p['on_vacation']??false))>@include('buyer.partials.icon',['name'=>'cart','size'=>15])</button></div>@endif
            <form method="POST" action="{{ route('buyer.commerce.wishlist',$item->product_id) }}" class="wishlist-remove-form">@csrf<input type="hidden" name="saved" value="0"><button type="submit" class="cart-action-btn danger" aria-label="Remove {{ $p['name']??'product' }} from wishlist" title="Remove item">@include('buyer.partials.icon',['name'=>'trash','size'=>15])</button></form>
          </div>
        </article>
      @endforeach
      </div>
    </section>
  @empty
    <div class="wishlist-empty">@include('buyer.partials.icon',['name'=>'wishlist','size'=>36])<h3>Your wishlist is empty</h3><p>Save products you like to find them here.</p></div>
  @endforelse
  <p id="wishlistError" role="alert" hidden></p>
  </div>
</div></div>
<div class="modal-overlay" id="wishlistRemoveModal" aria-hidden="true"><section class="modal pf-confirm" role="dialog" aria-modal="true" aria-labelledby="wishlistRemoveTitle"><div class="modal-head"><h3 id="wishlistRemoveTitle">Remove selected items?</h3></div><div class="modal-body"><p id="wishlistRemoveMessage"></p></div><div class="modal-foot"><button type="button" class="btn btn-outline" data-wishlist-cancel>Cancel</button><button type="button" class="btn btn-danger" data-wishlist-confirm>Remove</button></div></section></div>
<div class="cart-voucher-modal" id="wishlistOptionsModal" aria-hidden="true">
  <div class="cart-voucher-dialog" role="dialog" aria-modal="true" aria-labelledby="wishlistOptionsTitle">
    <div class="cart-voucher-modal-head"><div><h3 id="wishlistOptionsTitle">Edit item</h3><p>Update the options and quantity for this item.</p></div><button type="button" class="modal-close" data-wishlist-options-cancel aria-label="Close item editor">&times;</button></div>
    <form id="wishlistOptionsForm"><div class="cart-voucher-modal-body">
      <div class="cart-edit-context"><div class="cart-edit-context-img" id="wishlistOptionImageWrap"><img id="wishlistOptionImage" alt=""></div><div><div class="cart-edit-context-name" id="wishlistOptionName"></div><div class="cart-edit-context-price" id="wishlistOptionPrice"></div></div></div>
      <div id="wishlistOptionFields"></div>
      <div class="form-row"><div class="cart-floating-field always-floating"><input id="wishlistOptionQty" type="number" min="1" max="99" value="1" placeholder=" " required><label for="wishlistOptionQty">Quantity</label><fieldset aria-hidden="true"><legend><span>Quantity</span></legend></fieldset></div><p id="wishlistOptionError" role="alert" hidden></p></div>
      <button type="submit" class="btn btn-primary btn-block" id="wishlistOptionsSave">Save item</button>
    </div></form>
  </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/buyer-wishlist.js') }}?v={{ filemtime(public_path('js/buyer-wishlist.js')) }}"></script>
<script src="{{ asset('js/wishlist-options.js') }}?v={{ filemtime(public_path('js/wishlist-options.js')) }}"></script>
@endpush
