@if(!empty($product['flash_sale']))
 <div style="margin-top:10px;padding:9px 12px;border-radius:8px;background:#fcf1f7;color:#a52d6e;font-size:12px" data-flash-end="{{ $product['flash_sale']['ends_at'] }}">
  <strong>Flash sale</strong> · <span data-flash-countdown>Calculating time remaining…</span>
  <div>{{ $product['flash_sale']['remaining'] }} flash-sale units remaining</div>
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
@if(isset($product['seller_id']))
@include('partials.claimable-vouchers',['claimableVouchers'=>app(\App\Services\ShopCatalog::class)->vouchers((int)$product['seller_id'])])
@endif
