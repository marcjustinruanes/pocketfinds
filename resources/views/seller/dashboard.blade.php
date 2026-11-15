@extends('seller.layout')
@section('title', 'Shop overview')
@section('body-class', 'seller-dashboard-page')
@section('page-title', 'Shop overview')
@push('head')
<link rel="stylesheet" href="{{ asset('css/seller-dashboard.css') }}?v={{ filemtime(public_path('css/seller-dashboard.css')) }}">
@endpush

@section('content')
<div class="kpi-grid">
  <div class="kpi">
    <span class="dashboard-stat-icon">@include('seller.partials.icon', ['name' => 'chart', 'size' => 20])</span>
    <div class="label">Total sales</div>
    <div class="value">₱{{ number_format($totalSales, 2) }}</div>
  </div>
  <div class="kpi">
    <span class="dashboard-stat-icon">@include('seller.partials.icon', ['name' => 'orders', 'size' => 20])</span>
    <div class="label">Awaiting confirmation</div>
    <div class="value">{{ number_format($newOrders) }}</div>
  </div>
  <div class="kpi">
    <span class="dashboard-stat-icon">@include('seller.partials.icon', ['name' => 'inventory', 'size' => 20])</span>
    <div class="label">Products listed</div>
    <div class="value">{{ number_format($productsListed) }}</div>
  </div>
  <div class="kpi">
    <span class="dashboard-stat-icon">@include('seller.partials.icon', ['name' => 'star', 'size' => 20])</span>
    <div class="label">Average rating</div>
    <div class="value">{{ $avgRating ?: '—' }}</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    {{-- Sales chart --}}
    <div class="card dashboard-sales">
      <div class="card-head">
        <div class="dashboard-sales-heading"><h2>{{ $chartPeriod === 'year' ? 'Sales by month' : ($chartMonth->isSameMonth(now()) ? 'Sales this month' : 'Monthly sales') }}</h2><a class="dashboard-report-arrow" href="{{ route('seller.reports',['from'=>$chartStart->toDateString(),'to'=>$chartEnd->toDateString()]) }}" aria-label="Open sales report" title="Open sales report"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg></a></div>
        <form method="GET" action="{{ route('seller.dashboard') }}" class="dashboard-month-filter">
          <label><span class="dashboard-sr-only">Chart view</span><select name="period" aria-label="Chart view" onchange="this.form.requestSubmit()"><option value="month" @selected($chartPeriod==='month')>Month review</option><option value="year" @selected($chartPeriod==='year')>All months</option></select></label>
          @if($chartPeriod==='year')
            <label><span class="dashboard-sr-only">Sales year</span><input type="number" name="year" value="{{ $chartYear }}" min="2000" max="{{ now()->year }}" required aria-label="Sales year" onchange="this.form.requestSubmit()"></label>
          @else
            <label><span class="dashboard-sr-only">Sales month</span><input type="month" name="month" value="{{ $chartMonth->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required aria-label="Sales month" onchange="this.form.requestSubmit()"></label>
          @endif
          <noscript><button type="submit" class="btn btn-outline">Apply</button></noscript>
        </form>
      </div>
      <div class="card-pad">
        <div class="dashboard-chart-total"><strong>₱{{ number_format(collect($salesChart)->sum('amount'), 2) }}</strong><span>{{ $chartLabel }}</span></div>
        <div class="dashboard-chart {{ $chartPeriod==='year' ? 'dashboard-chart-year' : '' }}" style="--chart-days:{{ $salesChart->count() }}" aria-label="{{ $chartPeriod==='year' ? 'Monthly' : 'Daily' }} completed sales in {{ $chartLabel }}">
          @foreach($salesChart as $day)
          <div class="dashboard-chart-column"><div class="dashboard-chart-track"><div class="dashboard-chart-bar {{ $day['amount'] > 0 && $day['amount'] == $chartMax ? 'highlight' : '' }}" style="height:{{ ($day['amount'] / max(1, $chartMax)) * 100 }}%" tabindex="0" aria-label="{{ $day['date'] }}: ₱{{ number_format($day['amount'], 2) }}"><span class="dashboard-chart-tooltip">{{ $day['date'] }} · ₱{{ number_format($day['amount'], 2) }}</span></div></div><span>{{ $day['show_label'] ? $day['label'] : '' }}</span></div>
          @endforeach
        </div>
        @if(!collect($salesChart)->sum('amount'))<p class="dashboard-chart-note">No completed sales in this period.</p>@endif
      </div>
    </div>

    {{-- Recent orders --}}
    <div class="card dashboard-recent-orders">
      <div class="card-head">
        <div><h2>Recent orders</h2><p>Latest incoming orders</p></div>
        <a href="{{ route('seller.orders') }}" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="card-pad" style="padding:0">
        <div class="table-wrap">
        <table class="tbl">
          <thead><tr>
            <th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th><span class="dashboard-sr-only">View order</span></th>
          </tr></thead>
          <tbody>
            @forelse($recentOrders as $order)
            <tr><td class="mono">{{ $order->order_number }}</td><td>{{ $order->buyer?->given_names }} {{ $order->buyer?->last_name }}</td><td class="mono">₱{{ number_format($order->total, 2) }}</td><td><span class="stamp stamp-{{ $order->status === 'placed' ? 'new' : $order->status }}">{{ $order->status==='placed' ? 'Awaiting confirmation' : str_replace('_', ' ', ucfirst($order->status)) }}</span></td><td><a href="{{ route('seller.orders', ['q' => $order->order_number]) }}" class="dashboard-view-order" aria-label="View order {{ $order->order_number }}" title="View order"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg></a></td></tr>
            @empty
            <tr><td colspan="5"><div class="empty" style="padding:30px 20px"><h3>No orders yet</h3><p>Incoming orders will appear here.</p></div></td></tr>
            @endforelse
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    @include('seller.partials.dashboard-flash-sales')

    {{-- Low stock alert --}}
    <div class="card dashboard-stock">
      <div class="card-head">
        <div><h2>Stock alerts</h2><p>Items running low</p></div>
        <a href="{{ route('seller.inventory') }}" class="btn btn-sm btn-outline">Manage</a>
      </div>
      <div class="card-pad">
        @forelse($lowStock as $product)
          <a href="{{ route('seller.inventory') }}" class="stock-row"><span class="dashboard-stock-icon">@include('seller.partials.icon', ['name' => 'package', 'size' => 18])</span><strong>{{ $product->name }}</strong><span class="dashboard-stock-count {{ $product->total_stock <= 0 ? 'is-empty' : '' }}">{{ $product->total_stock <= 0 ? 'Out of stock' : $product->total_stock.' left' }}</span></a>
        @empty
          <div class="empty"><div class="ic">@include('seller.partials.icon', ['name' => 'inventory', 'size' => 26])</div><h3>All stocked up</h3><p>No low-stock items right now.</p></div>
        @endforelse
      </div>
    </div>


  </div>
<div class="dashboard-promotions-grid">
  <section class="card dashboard-vouchers">
    <div class="card-head"><h2>Live vouchers</h2><a class="dashboard-panel-link" href="{{ route('seller.vouchers') }}">View all ↗</a></div>
    <div class="card-pad">
      @forelse($dashboardVouchers as $voucher)
        <article class="dashboard-voucher-ticket">
          <span class="dashboard-voucher-icon" aria-hidden="true">@if($voucher->kind==='shipping')@include('buyer.partials.icon',['name'=>'truck','size'=>24])@else @include('partials.discount-icon') @endif</span>
          <div class="dashboard-voucher-copy">
            <div class="dashboard-voucher-topline"><b>{{ $voucher->code }}</b><strong>{{ $voucher->discount_type === 'percent' ? number_format($voucher->amount, 0).'%' : '₱'.number_format($voucher->amount, 2) }} {{ $voucher->kind==='shipping' ? 'off shipping' : 'off' }}</strong></div><div class="dashboard-voucher-terms"><small>Min. spend ₱{{ number_format($voucher->minimum_spend, 2) }}@if($voucher->cap) · Up to ₱{{ number_format($voucher->cap,2) }}@endif</small><small>Use by {{ \Illuminate\Support\Carbon::parse($voucher->ends_at)->format('M j, Y') }}</small></div>
          </div>
        </article>
      @empty
        <p class="dashboard-offer-empty">No live vouchers. <a href="{{ route('seller.vouchers') }}">Manage vouchers</a></p>
      @endforelse
    </div>
  </section>
    {{-- Order pipeline --}}
    <div class="card dashboard-pipeline">
      <div class="card-head"><div><h2>Shop activity</h2></div></div>
      <div class="card-pad dashboard-pipeline-list">
        @php $activityLinks = [
          ['star','Reviews to reply','seller.feedback',$unansweredReviews,[]],
          ['orders','Return / Refund','seller.orders',$refundOrderCount,['status'=>'refunds','refund_state'=>'open']],
          ['orders','Failed orders','seller.orders',$failedOrderCount,['status'=>'delivery_failed']],
        ]; @endphp
        @foreach($activityLinks as [$icon,$label,$route,$count,$query])
        <a href="{{ route($route,$query) }}" class="order-status-row dashboard-activity-{{ $loop->index }}">
          <span class="dashboard-activity-icon">@include('seller.partials.icon', ['name' => $icon, 'size' => 18])<span class="dashboard-activity-count">{{ $count }}</span></span>
          <span>{{ $label }}</span>
        </a>
        @endforeach
      </div>
    </div>


</div>
</div>
@endsection
