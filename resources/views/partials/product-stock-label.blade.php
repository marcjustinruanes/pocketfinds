@if(array_key_exists('stock', $p) && $p['stock'] <= 0)
<span class="product-stock-overlay" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(48,39,51,.25);z-index:2;pointer-events:none"><span style="padding:7px 12px;border-radius:6px;background:rgba(48,39,51,.85);color:#fff;font:600 12px/1.4 Inter,system-ui,sans-serif;text-align:center">Out of stock</span></span>
@endif
