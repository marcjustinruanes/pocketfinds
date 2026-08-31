@extends('seller.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names . '!')

@section('content')
<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Total Sales</div>
    <div class="value">₱{{ number_format($totalSales, 2) }}</div>
    <div class="delta up">Completed orders</div>
  </div>
  <div class="kpi">
    <div class="label">New Orders</div>
    <div class="value">{{ number_format($newOrders) }}</div>
    <div class="delta">Orders to prepare</div>
  </div>
  <div class="kpi">
    <div class="label">Products Listed</div>
    <div class="value">{{ number_format($productsListed) }}</div>
    <div class="delta">Active listings</div>
  </div>
  <div class="kpi">
    <div class="label">Avg. Rating</div>
    <div class="value">{{ $avgRating ?: '—' }}</div>
    <div class="delta">{{ $avgRating ? 'Out of 5' : 'No ratings yet' }}</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    {{-- Sales chart --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Sales Overview</h2><p>Last 7 days</p></div>
        <a href="{{ route('seller.reports') }}" class="btn btn-sm btn-outline">Full Report</a>
      </div>
      <div class="card-pad">
        <div class="chart-area">
          @foreach($salesChart as $day)
          <div class="chart-bar {{ $day['amount'] === $chartMax ? 'highlight' : '' }}" style="height:{{ max(5, ($day['amount'] / $chartMax) * 100) }}%" title="PHP {{ number_format($day['amount'], 2) }}"></div>
          @endforeach
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;font-family:var(--font-mono);font-size:10px;color:var(--muted)">
          @foreach($salesChart as $day)
          <span>{{ $day['label'] }}</span>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Recent orders --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Recent Orders</h2><p>Latest incoming orders</p></div>
        <a href="{{ route('seller.orders') }}" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="card-pad" style="padding:0">
        <table class="tbl">
          <thead><tr>
            <th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>
            @forelse($recentOrders as $order)
            <tr><td class="mono">{{ $order->order_number }}</td><td>{{ $order->buyer?->given_names }} {{ $order->buyer?->last_name }}</td><td class="mono">₱{{ number_format($order->total, 2) }}</td><td><span class="stamp stamp-{{ $order->status === 'to_ship' ? 'new' : $order->status }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span></td><td><a href="{{ route('seller.orders') }}" class="btn btn-sm btn-outline">View</a></td></tr>
            @empty
            <tr><td colspan="5"><div class="empty" style="padding:30px 20px"><h3>No orders yet</h3><p>Incoming orders will appear here.</p></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    {{-- Order pipeline --}}
    <div class="card">
      <div class="card-head"><h2>Order Pipeline</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        @php $pipeline = [
          ['bell','New Orders','notifications','stamp-new',$pipelineCounts['new']],
          ['package','To Prepare','orders','stamp-pending',$pipelineCounts['prepare']],
          ['truck','With Courier','orders','stamp-transit',$pipelineCounts['shipments']],
          ['check-circle','Delivered','orders','stamp-delivered',$pipelineCounts['deliveries']],
        ]; @endphp
        @foreach($pipeline as [$icon,$label,$route,$stamp,$count])
        <a href="{{ route('seller.'.$route) }}" class="order-status-row" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-weight:600;color:var(--text);background:#fff">
          <span style="display:flex;align-items:center;color:var(--pink-dark)">@include('seller.partials.icon', ['name' => $icon, 'size' => 18])</span>
          <span>{{ $label }}</span>
          <span class="stamp {{ $stamp }}" style="margin-left:auto">{{ $count }}</span>
        </a>
        @endforeach
      </div>
    </div>

    {{-- Low stock alert --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Stock Alerts</h2><p>Items running low</p></div>
        <a href="{{ route('seller.inventory') }}" class="btn btn-sm btn-outline">Manage</a>
      </div>
      <div class="card-pad">
        @forelse($lowStock as $product)
          <div class="stock-row"><strong>{{ $product->name }}</strong><span>{{ $product->total_stock }} left</span></div>
        @empty
          <div class="empty"><div class="ic">@include('seller.partials.icon', ['name' => 'inventory', 'size' => 26])</div><h3>All stocked up</h3><p>No low-stock items right now.</p></div>
        @endforelse
      </div>
    </div>

    {{-- Quick actions --}}
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
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
