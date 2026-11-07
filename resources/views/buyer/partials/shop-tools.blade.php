@php
  $catalog = app(\App\Services\ShopCatalog::class);
  $shopCategories = $catalog->categories($seller->id)->filter(fn($category) => !isset($category->is_visible) || $category->is_visible);
  $shopVouchers = $catalog->vouchers($seller->id);
  $selectedCategory = (string)request('shop_category', '');
@endphp
<dialog class="shop-voucher-dialog" id="shopVouchersModal" aria-labelledby="shopVouchersTitle">
<form method="dialog" class="shop-voucher-dialog-close"><button type="submit" aria-label="Close shop vouchers">&times;</button></form>
<section class="shop-vouchers" aria-label="Shop vouchers">
  <h2 id="shopVouchersTitle">Shop vouchers <span class="sp-products-count">{{ $shopVouchers->count() }}</span></h2>
  <div class="shop-voucher-list">
  @forelse($shopVouchers as $voucher)
    <article class="wallet-voucher">
      <span class="wallet-voucher-mark {{ $voucher->kind === 'shipping' ? '' : 'wallet-discount-icon' }}" aria-hidden="true">@if($voucher->kind === 'shipping')@include('buyer.partials.icon',['name'=>'truck','size'=>24])@else @include('partials.discount-icon',['size'=>28]) @endif</span>
      <div class="wallet-voucher-details">
        <div class="wallet-voucher-topline"><strong class="wallet-voucher-code">{{ $voucher->code }}</strong><span class="wallet-voucher-kind">{{ $voucher->kind === 'shipping' ? 'Shipping' : ucfirst($voucher->kind) }}</span></div>
        <div class="wallet-voucher-summary"><strong class="wallet-voucher-value">{{ $voucher->discount_type === 'percent' ? number_format($voucher->amount,0).'%' : '₱'.number_format($voucher->amount,2) }} {{ $voucher->kind === 'shipping' ? 'off shipping' : 'off' }}</strong><span class="wallet-voucher-terms">Min. spend ₱{{ number_format($voucher->minimum_spend,2) }}</span><span class="wallet-voucher-dates">Use by {{ \Illuminate\Support\Carbon::parse($voucher->ends_at)->format('M j, Y') }}</span></div>
        @if($voucher->cap)<small class="wallet-voucher-cap">Up to ₱{{ number_format($voucher->cap,2) }}</small>@endif
      </div>
      @if($voucher->claimed || $voucher->used)
        <span class="wallet-voucher-available">{{ $voucher->used ? 'Used' : 'Saved' }}</span>
      @else
        <form method="POST" action="{{ route('buyer.commerce.claim',$voucher->id) }}">@csrf<button class="btn btn-outline wallet-voucher-claim">Claim</button></form>
      @endif
    </article>
  @empty
    <p class="shop-vouchers-empty">This shop has no available vouchers right now.</p>
  @endforelse
  </div>
</section>
</dialog>
