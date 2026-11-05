<div class="pd-price-group">
  <span class="pd-price" id="pdPrice" data-base-price="{{ $product['price'] }}" data-base-stock="{{ $product['stock'] }}" data-on-vacation="{{ !empty($product['on_vacation']) ? 'true' : 'false' }}">₱{{ number_format($product['price']) }}</span>
  @if($product['old_price'])<span class="pd-old">₱{{ number_format($product['old_price']) }}</span>@endif
</div>
