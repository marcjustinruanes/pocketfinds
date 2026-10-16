<div id="matrixSelectors">
@foreach($product['variant_axes'] as $index => $axis)
<div class="pd-opt-group"><div class="pd-opt-label">{{ $axis['name'] }}</div><div class="pd-opt-row">
@foreach($axis['options'] as $option)
<button type="button" class="pd-opt-btn" data-matrix-axis="{{ $index }}" data-matrix-option="{{ $option['id'] }}">{{ $option['value'] }}</button>
@endforeach
</div></div>
@endforeach
<p id="matrixStock" role="status">Please select a variation.</p>
</div>
<script id="matrixProductData" type="application/json">@json(['axes' => $product['variant_axes'], 'rows' => $product['variant_rows']])</script>
<script src="{{ asset('js/product-matrix.js') }}" defer></script>
