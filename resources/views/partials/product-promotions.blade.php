@if(!empty($product['flash_sale']))
 <div class="product-flash-timing" data-flash-end="{{ $product['flash_sale']['ends_at'] }}">
  <strong>Flash sale</strong><span data-flash-countdown>Calculating time remaining…</span><small>{{ $product['flash_sale']['remaining'] }} left</small>
 </div>
 @once
 <script>
 document.addEventListener('DOMContentLoaded',()=>{
  const tick=()=>document.querySelectorAll('[data-flash-end]').forEach(el=>{
   const seconds=Math.max(0,Math.floor((Date.parse(el.dataset.flashEnd)-Date.now())/1000));
   el.querySelector('[data-flash-countdown]').textContent=seconds ? `${Math.floor(seconds/3600)}h ${Math.floor(seconds%3600/60)}m ${seconds%60}s left` : 'Sale ended. Refresh for current prices.';
  });tick();setInterval(tick,1000);
 });
 </script>
 @endonce
@endif
@once<style>.product-flash-timing{display:inline-flex;align-items:center;flex-wrap:wrap;gap:2px 7px;margin-left:6px;color:#a52d6e;font:500 10px/1.4 Inter,system-ui,sans-serif}.product-flash-timing strong{font-weight:700}.product-flash-timing small{font-size:10px;color:#8c7381}</style>@endonce
