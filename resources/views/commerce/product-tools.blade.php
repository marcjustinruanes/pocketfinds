@if(\App\Services\BuyerCommerce::installed())
<div style="padding:12px;border:1px solid #eed8e5;border-radius:8px;margin-bottom:12px">
@if($product['on_vacation']??false)<p role="status"><strong>On vacation</strong> — this shop is not accepting new purchases.</p>@endif
@if($product['flash_sale']??null)<p>Flash sale: <strong>₱{{ number_format($product['price'],2) }}</strong> · {{ $product['flash_sale']['remaining'] }} units left · Ends in <span data-countdown="{{ $product['flash_sale']['ends_at'] }}"></span></p>@endif
@php($isSaved=\Illuminate\Support\Facades\DB::table('commerce_wishlist')->where('buyer_id',auth()->id())->where('product_id',$product['id'])->exists())
<form method="POST" action="{{ route('buyer.commerce.wishlist',$product['id']) }}">@csrf<input type="hidden" name="saved" value="{{ $isSaved?0:1 }}"><button class="btn btn-outline">{{ $isSaved?'Remove from wishlist':'Save to wishlist' }}</button> <a href="{{ route('buyer.wishlist') }}">View wishlist</a></form>
</div>
<script src="{{ asset('js/commerce.js') }}?v={{ filemtime(public_path('js/commerce.js')) }}"></script>
@endif
