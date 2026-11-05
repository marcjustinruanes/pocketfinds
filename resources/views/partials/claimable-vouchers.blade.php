@if($claimableVouchers->isNotEmpty())
<div aria-label="Shop vouchers" style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0">
 @foreach($claimableVouchers as $voucher)
 <div style="border:1px dashed #ddc4d2;border-radius:8px;padding:10px;font-size:12px">
  <strong>{{ $voucher->code }}</strong> · {{ $voucher->discount_type==='percent' ? number_format($voucher->amount,0).'%' : '₱'.number_format($voucher->amount,2) }} off
  <div>Min. ₱{{ number_format($voucher->minimum_spend,2) }} @if($voucher->cap) · Up to ₱{{ number_format($voucher->cap,2) }} @endif</div>
  <div>Until {{ \Illuminate\Support\Carbon::parse($voucher->ends_at)->format('M j, g:i A') }}</div>
  @if(auth()->check() && auth()->user()->account_type==='buyer')
   <form method="POST" action="{{ route('buyer.commerce.claim',$voucher->id) }}">@csrf<button class="btn btn-outline" style="font-size:11px;padding:5px 9px" @disabled($voucher->claimed || $voucher->used)>{{ $voucher->used ? 'Used' : ($voucher->claimed ? 'Claimed' : 'Claim') }}</button></form>
  @else<button type="button" class="btn btn-outline" data-protected>Sign in to claim</button>@endif
 </div>
 @endforeach
</div>
@endif
