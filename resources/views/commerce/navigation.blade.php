@if(\App\Services\BuyerCommerce::installed())
<nav class="buyer-shopping-tools" aria-label="Shopping tools">
@if(auth()->user()->is_admin)<a href="{{ route('admin.commerce.index') }}">Platform vouchers & returns</a>
@elseif(auth()->user()->account_type==='seller')<a href="{{ route('seller.commerce.index') }}">Vacation, shipping, promotions & returns</a>
@endif
</nav>
<style>
.buyer-shopping-tools{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 20px;padding:10px;border:1px solid var(--line,#eadfe8);background:var(--card,#fff);border-radius:12px}.buyer-shopping-tools a{padding:9px 12px;border-radius:8px;color:var(--ink,#352630);text-decoration:none;font-size:13px;font-weight:600}.buyer-shopping-tools a:hover,.buyer-shopping-tools a[aria-current=page]{background:#f7e6f0;color:#9b215d}.buyer-shopping-tools a:focus-visible{outline:2px solid #9b215d;outline-offset:2px}@media(max-width:600px){.buyer-shopping-tools{flex-wrap:nowrap;overflow-x:auto}.buyer-shopping-tools a{white-space:nowrap;flex-shrink:0}}
</style>
@endif
