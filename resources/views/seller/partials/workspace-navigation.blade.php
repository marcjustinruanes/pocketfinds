@php
  $sellerWorkspaces = [
    ['Dashboard', 'dashboard', 'seller.dashboard', request()->routeIs('seller.dashboard')],
    ['Orders', 'orders', 'seller.orders', request()->routeIs('seller.orders*', 'seller.assignments.*', 'account.cancellation-refunds*')],
    ['Products', 'inventory', 'seller.inventory', request()->routeIs('seller.inventory*', 'seller.shop-categories.*', 'seller.feedback', 'seller.reviews.*')],
    ['Promotions', 'tag', 'seller.vouchers', request()->routeIs('seller.vouchers*', 'seller.promotions.*')],
    ['Finance', 'chart', app(\App\Services\CommissionLedger::class)->installed() ? 'commissions.index' : 'seller.reports', request()->routeIs('commissions.*', 'seller.shipping*', 'seller.payouts*', 'seller.reports*')],
  ];
@endphp
@foreach($sellerWorkspaces as [$label, $icon, $route, $active])
<a href="{{ route($route) }}" class="nav-item {{ $active ? 'active' : '' }}" aria-label="{{ $label }}" title="{{ $label }}" @if($active) aria-current="page" @endif>
  <span class="ic">@include('seller.partials.icon', ['name' => $icon, 'size' => 18])</span>{{ $label }}
</a>
@endforeach
