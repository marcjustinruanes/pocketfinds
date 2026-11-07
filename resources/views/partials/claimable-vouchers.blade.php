@if($claimableVouchers->isNotEmpty())
<section class="claim-vouchers" aria-label="Shop vouchers">
 <div class="claim-vouchers-heading"><h3>Shop vouchers</h3><span>{{ $claimableVouchers->count() }}</span></div>
 <div class="claim-vouchers-list">
 @foreach($claimableVouchers as $voucher)
  <article class="claim-voucher">
   <span class="claim-voucher-icon" aria-hidden="true">@if($voucher->kind==='shipping')@include('buyer.partials.icon',['name'=>'truck','size'=>24])@else @include('partials.discount-icon') @endif</span>
   <div class="claim-voucher-copy">
    <strong>{{ $voucher->discount_type==='percent' ? number_format($voucher->amount,0).'%' : '₱'.number_format($voucher->amount,2) }} {{ $voucher->kind==='shipping' ? 'shipping discount' : 'off' }}</strong>
    <span>{{ $voucher->code }}</span>
    <small>Min. spend ₱{{ number_format($voucher->minimum_spend,2) }}@if($voucher->cap) · Up to ₱{{ number_format($voucher->cap,2) }}@endif</small>
    <small>Use by {{ \Illuminate\Support\Carbon::parse($voucher->ends_at)->format('M j, Y') }}</small>
   </div>
   @if(auth()->check() && auth()->user()->account_type==='buyer')
    <form method="POST" action="{{ route('buyer.commerce.claim',$voucher->id) }}">@csrf<button type="submit" class="claim-voucher-button" @disabled($voucher->claimed || $voucher->used)>{{ $voucher->used ? 'Used' : ($voucher->claimed ? 'Claimed' : 'Claim') }}</button></form>
   @else<button type="button" class="claim-voucher-button" data-protected>Claim</button>@endif
  </article>
 @endforeach
 </div>
</section>
@once
<style>
.claim-vouchers{margin:16px 0;min-width:0}
.claim-vouchers-heading{display:flex;align-items:center;gap:8px;margin-bottom:10px}.claim-vouchers-heading h3{margin:0;font:650 12px/1.5 Inter,system-ui,sans-serif;color:#593346}
.claim-vouchers-heading>span{padding:1px 7px;border:1px solid #e6bcd3;border-radius:999px;background:#fcf1f7;color:#bc387f;font:600 10px/1.5 Inter,system-ui,sans-serif}
.claim-vouchers-list{display:flex;flex-direction:column;gap:9px;max-height:280px;overflow-y:auto;scrollbar-width:thin;padding:1px}
.claim-voucher{display:flex;align-items:center;gap:12px;min-width:0;padding:12px;border:1px solid #ead5e1;border-radius:9px;background:#fff9fc}
.claim-voucher-icon{display:grid;place-items:center;flex:none;width:34px;height:34px;color:#bc387f}.claim-voucher-icon svg{width:100%;max-width:32px;max-height:32px}
.claim-voucher-copy{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0;font-family:Inter,system-ui,sans-serif}
.claim-voucher-copy strong{font-size:14px;line-height:1.4;color:#bc387f}.claim-voucher-copy>span{font-size:10px;font-weight:650;letter-spacing:.04em;color:#593346;overflow-wrap:anywhere}.claim-voucher-copy small{font-size:10px;line-height:1.5;color:#8c7381}
.claim-voucher form{margin:0;flex:none}.claim-voucher-button{padding:6px 11px;min-height:30px;border:1px solid #e6bcd3;border-radius:6px;background:#fff;color:#a52d6e;font:600 11px/1.4 Inter,system-ui,sans-serif;cursor:pointer;white-space:nowrap}
.claim-voucher-button:hover{background:#fcf1f7}.claim-voucher-button:disabled{opacity:.55;cursor:default}.claim-voucher-button:focus-visible{outline:2px solid #bc387f;outline-offset:3px}
@media(max-width:480px){.claim-voucher{gap:9px;padding:10px}.claim-voucher-icon{width:28px;height:28px}.claim-voucher-copy strong{font-size:13px}}
</style>
@endonce
@endif
