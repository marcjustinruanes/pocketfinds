@php
  $statusTabs = [
    ['all','category-all','All'], ['placed','bag','New'],
    ['confirmed','clipboard-check','Confirmed'], ['preparing','package-open','Preparing'],
    ['ready_for_pickup','box-check','Ready for pickup'], ['in_transit','map','In transit'],
    ['out_for_delivery','rider-delivery','Out for delivery'], ['delivered','package-received','Delivered'],
    ['completed','shield','Completed'], ['delivery_failed','x','Failed'],
    ['cancelled','ban','Cancelled'], ['refunds','wallet','Return / Refund'],
  ];
  $tabCount = fn ($key) => match ($key) {
    'all' => $statusCounts->sum(),
    'gcash' => (int) ($gcashOrderCount ?? 0),
    'refunds' => $refundOrderCount ?? $returnRequests->count(),
    'in_transit' => collect(\App\Models\Order::IN_TRANSIT_STATUSES)->sum(fn ($s) => (int) ($statusCounts[$s] ?? 0)),
    default => (int) ($statusCounts[$key] ?? 0),
  };
  $orderFilters = array_filter(request()->only(['q','from','to','period','payment']));
@endphp
<aside class="seller-order-categories" aria-label="Order status filters">
  <nav class="seller-order-category-list">
    @foreach($statusTabs as [$key,$icon,$label])
    <a href="{{ route('seller.orders', array_merge($orderFilters, $key === 'all' ? [] : ['status'=>$key])) }}" class="seller-order-category {{ $status === $key ? 'active' : '' }}" aria-label="{{ $label }} ({{ $tabCount($key) }})" title="{{ $label }}" @if($status === $key) aria-current="page" @endif>
      <span class="seller-order-category-icon">@if($key === 'all') @include('guest.partials.category-filter-icon',['category'=>null]) @else @include('buyer.partials.icon',['name'=>$icon,'size'=>19]) @endif</span>
      <span class="seller-order-category-label">{{ $label }}</span>
      @if($tabCount($key))<span class="seller-order-category-count">{{ $tabCount($key) }}</span>@endif
    </a>
    @endforeach
  </nav>
</aside>
