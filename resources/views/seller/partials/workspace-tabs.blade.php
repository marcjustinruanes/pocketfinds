@php
  $workspaceTabs = [];
  if (request()->routeIs('seller.orders*','seller.assignments.*','account.cancellation-refunds*')) {
    $workspaceTabs = [];
  } elseif (request()->routeIs('seller.inventory*','seller.shop-categories.*','seller.feedback','seller.reviews.*')) {
    $workspaceTabs = [];
  } elseif (request()->routeIs('seller.vouchers*','seller.promotions.*')) {
    $workspaceTabs = [];
  } elseif (request()->routeIs('commissions.*','seller.shipping*','seller.payouts*','seller.reports*')) {
    if (app(\App\Services\CommissionLedger::class)->installed()) $workspaceTabs[] = ['Commission statements','commissions.index',request()->routeIs('commissions.*')];
    $workspaceTabs = [...$workspaceTabs,['Shipping payments','seller.shipping',request()->routeIs('seller.shipping*')],['COD payouts','seller.payouts',request()->routeIs('seller.payouts*')],['Reports','seller.reports',request()->routeIs('seller.reports*')]];
  }
@endphp
@if($workspaceTabs)
<nav class="seller-workspace-tabs" aria-label="Section navigation">
  @foreach($workspaceTabs as [$label,$route,$active])<a href="{{ route($route) }}" class="{{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>
@endif
