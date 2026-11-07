@extends('seller.layout')
@section('title', 'Dashboard')
@section('body-class', 'seller-dashboard-page')
@section('page-title', 'Dashboard')
@push('head')
<link rel="stylesheet" href="{{ asset('css/seller-dashboard.css') }}?v={{ filemtime(public_path('css/seller-dashboard.css')) }}">
@endpush

@section('content')
<div class="seller-dashboard-intro">
  <div><span class="dashboard-eyebrow">Your shop at a glance</span><h2>{{ auth()->user()->business_name ?: auth()->user()->given_names }}</h2><p>Keep track of sales, orders, and your inventory.</p></div>
  <button type="button" class="btn btn-primary" data-modal="addProductModal">@include('seller.partials.icon', ['name' => 'plus', 'size' => 16]) Add product</button>
</div>
<div class="kpi-grid">
  <div class="kpi">
    <span class="dashboard-stat-icon">@include('seller.partials.icon', ['name' => 'chart', 'size' => 20])</span>
    <div class="label">Total Sales</div>
    <div class="value">₱{{ number_format($totalSales, 2) }}</div>
    <div class="delta up">Completed orders</div>
  </div>
  <div class="kpi">
    <span class="dashboard-stat-icon">@include('seller.partials.icon', ['name' => 'orders', 'size' => 20])</span>
    <div class="label">New Orders</div>
    <div class="value">{{ number_format($newOrders) }}</div>
    <div class="delta">Orders to prepare</div>
  </div>
  <div class="kpi">
    <span class="dashboard-stat-icon">@include('seller.partials.icon', ['name' => 'inventory', 'size' => 20])</span>
    <div class="label">Products Listed</div>
    <div class="value">{{ number_format($productsListed) }}</div>
    <div class="delta">Active listings</div>
  </div>
  <div class="kpi">
    <span class="dashboard-stat-icon">@include('seller.partials.icon', ['name' => 'star', 'size' => 20])</span>
    <div class="label">Avg. Rating</div>
    <div class="value">{{ $avgRating ?: '—' }}</div>
    <div class="delta">{{ $avgRating ? 'Out of 5' : 'No ratings yet' }}</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    {{-- Sales chart --}}
    <div class="card dashboard-sales">
      <div class="card-head">
        <div><h2>Sales Overview</h2><p>Last 7 days</p></div>
        <a href="{{ route('seller.reports') }}" class="btn btn-sm btn-outline">Full Report</a>
      </div>
      <div class="card-pad">
        <div class="dashboard-chart-total"><strong>₱{{ number_format(collect($salesChart)->sum('amount'), 2) }}</strong><span>Sales over the last 7 days</span></div>
        <div class="dashboard-chart" aria-label="Sales over the last seven days">
          @foreach($salesChart as $day)
          <div class="dashboard-chart-column"><div class="dashboard-chart-track"><div class="dashboard-chart-bar {{ $day['amount'] > 0 && $day['amount'] == $chartMax ? 'highlight' : '' }}" style="height:{{ max(2, ($day['amount'] / max(1, $chartMax)) * 100) }}%" tabindex="0" aria-label="{{ $day['label'] }}: ₱{{ number_format($day['amount'], 2) }}"><span class="dashboard-chart-tooltip">₱{{ number_format($day['amount'], 2) }}</span></div></div><span>{{ $day['label'] }}</span></div>
          @endforeach
        </div>
        @if(!collect($salesChart)->sum('amount'))<p class="dashboard-chart-note">No completed sales in this period.</p>@endif
      </div>
    </div>

    {{-- Recent orders --}}
    <div class="card dashboard-recent-orders">
      <div class="card-head">
        <div><h2>Recent Orders</h2><p>Latest incoming orders</p></div>
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
            <tr><td class="mono">{{ $order->order_number }}</td><td>{{ $order->buyer?->given_names }} {{ $order->buyer?->last_name }}</td><td class="mono">₱{{ number_format($order->total, 2) }}</td><td><span class="stamp stamp-{{ $order->status === 'placed' ? 'new' : $order->status }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span></td><td><a href="{{ route('seller.orders') }}" class="btn btn-sm btn-outline">View</a></td></tr>
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
    {{-- Order pipeline --}}
    <div class="card dashboard-pipeline">
      <div class="card-head"><div><h2>Order activity</h2><p>What needs your attention</p></div>@include('seller.partials.icon', ['name' => 'orders', 'size' => 18])</div>
      <div class="card-pad dashboard-pipeline-list">
        @php $pipeline = [
          ['bell','New Orders','notifications','stamp-new',$pipelineCounts['new']],
          ['package','To Prepare','orders','stamp-pending',$pipelineCounts['prepare']],
          ['truck','With Courier','orders','stamp-transit',$pipelineCounts['shipments']],
          ['check-circle','Delivered','orders','stamp-delivered',$pipelineCounts['deliveries']],
        ]; @endphp
        @foreach($pipeline as [$icon,$label,$route,$stamp,$count])
        <a href="{{ route('seller.'.$route) }}" class="order-status-row dashboard-activity-{{ $loop->index }}">
          <span class="dashboard-activity-icon">@include('seller.partials.icon', ['name' => $icon, 'size' => 18])</span>
          <span>{{ $label }}</span>
          <span class="dashboard-activity-count">{{ $count }}</span>
        </a>
        @endforeach
      </div>
    </div>

    {{-- Low stock alert --}}
    <div class="card dashboard-stock">
      <div class="card-head">
        <div><h2>Stock Alerts</h2><p>Items running low</p></div>
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

    {{-- Quick actions --}}
    <div class="card dashboard-shortcuts">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad dashboard-shortcut-grid">
        <button class="btn btn-primary" data-modal="addProductModal">
          @include('seller.partials.icon', ['name' => 'plus', 'size' => 15]) Add Product
        </button>
        <a href="{{ route('seller.orders') }}" class="btn btn-outline">
          @include('seller.partials.icon', ['name' => 'orders', 'size' => 15]) Manage Orders
        </a>
        <a href="{{ route('seller.reports') }}" class="btn btn-outline">
          @include('seller.partials.icon', ['name' => 'chart', 'size' => 15]) View Reports
        </a>
        <a href="{{ route('seller.messages') }}" class="btn btn-outline">
          @include('seller.partials.icon', ['name' => 'mail', 'size' => 15]) Messages
        </a>
      </div>
    </div>
  </div>
</div>

@include('seller.partials.add-product-modal')
@include('seller.partials.product-result-modal')
@endsection
