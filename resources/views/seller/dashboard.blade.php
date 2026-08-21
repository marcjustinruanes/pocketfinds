@extends('seller.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names . '!')

@section('content')
<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Total Sales</div>
    <div class="value">₱0</div>
    <div class="delta up">This month</div>
  </div>
  <div class="kpi">
    <div class="label">New Orders</div>
    <div class="value">0</div>
    <div class="delta">Pending action</div>
  </div>
  <div class="kpi">
    <div class="label">Products Listed</div>
    <div class="value">0</div>
    <div class="delta">Active listings</div>
  </div>
  <div class="kpi">
    <div class="label">Avg. Rating</div>
    <div class="value">—</div>
    <div class="delta">From reviews</div>
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
          @foreach([40,65,50,80,55,90,70] as $h)
          <div class="chart-bar {{ $h === 90 ? 'highlight' : '' }}" style="height:{{ $h }}%"></div>
          @endforeach
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;font-family:var(--font-mono);font-size:10px;color:var(--muted)">
          @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
          <span>{{ $d }}</span>
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
            <tr>
              <td class="mono">#00001</td>
              <td>Sample Customer</td>
              <td class="mono">₱299.00</td>
              <td><span class="stamp stamp-new">New</span></td>
              <td><a href="{{ route('seller.orders') }}" class="btn btn-sm btn-outline">View</a></td>
            </tr>
            <tr>
              <td colspan="5"><div class="empty" style="padding:30px 20px"><h3>No more orders</h3><p>New orders will appear here.</p></div></td>
            </tr>
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
          ['bell','New Orders','notifications','stamp-new',3],
          ['package','To Prepare','prepare','stamp-pending',1],
          ['truck','With Courier','shipments','stamp-transit',0],
          ['check-circle','Delivered','deliveries','stamp-delivered',0],
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
        <div class="empty"><div class="ic">@include('seller.partials.icon', ['name' => 'inventory', 'size' => 26])</div><h3>All stocked up</h3><p>No low-stock items right now.</p></div>
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
@endsection
