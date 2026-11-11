@php($productVariation = collect([$item['variation_group'] ?? null, $item['variation_value'] ?? $item['color'] ?? null, $item['size'] ?? null])->filter()->unique()->join(': '))
<div class="order-summary-product">
 <div class="order-summary-product-image">@if(!empty($item['img']))<img src="{{ $item['img'] }}" alt="{{ $item['name'] ?? 'Product' }}" loading="lazy">@else @include('buyer.partials.icon',['name'=>'package','size'=>22]) @endif</div>
 <div><strong>{{ $item['name'] ?? 'Product not provided' }}</strong>@if($productVariation)<small>{{ $productVariation }}</small>@endif</div>
</div>
