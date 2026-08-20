@extends('seller.layout')
@section('title', 'Order Management')
@section('page-title', 'Order Management')
@section('page-sub', 'View and manage all your orders')

@section('content')
<div class="filter-bar">
  <div class="search-mini">
    <span class="ic">@include('seller.partials.icon', ['name' => 'search', 'size' => 13])</span>
    <input type="text" placeholder="Search by order ID or customer…">
  </div>
  <select class="select">
    <option>All Statuses</option>
    <option>New</option><option>Preparing</option><option>Shipped</option><option>Delivered</option><option>Cancelled</option>
  </select>
  <select class="select">
    <option>All Time</option><option>Today</option><option>This Week</option><option>This Month</option>
  </select>
  <button class="btn btn-outline">@include('seller.partials.icon', ['name' => 'download', 'size' => 14]) Export</button>
</div>

<div class="card">
  <div class="tabs" style="padding:0 20px;margin-bottom:0" data-tabs>
    <button class="tab active" data-tab="all">All Orders</button>
    <button class="tab" data-tab="new">New</button>
    <button class="tab" data-tab="preparing">Preparing</button>
    <button class="tab" data-tab="shipped">Shipped</button>
    <button class="tab" data-tab="delivered">Delivered</button>
    <button class="tab" data-tab="cancelled">Cancelled</button>
  </div>
  <div data-panel="all">
    <table class="tbl">
      <thead><tr>
        <th>Order ID</th><th>Date</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <tr>
          <td class="mono">#00001</td>
          <td style="color:var(--muted);font-size:12px">{{ now()->format('M d, Y') }}</td>
          <td>Sample Customer</td>
          <td>1 item</td>
          <td class="mono">₱299.00</td>
          <td><span class="stamp stamp-new">New</span></td>
          <td>
            <div class="tbl-actions">
              <button class="btn btn-sm btn-outline" data-modal="orderDetailModal">@include('seller.partials.icon', ['name' => 'eye', 'size' => 13]) View</button>
              <a href="{{ route('seller.prepare') }}" class="btn btn-sm btn-primary">Prepare</a>
            </div>
          </td>
        </tr>
        <tr><td colspan="7"><div class="empty" style="padding:30px 20px"><h3>No more orders</h3><p>Orders will appear here once customers place them.</p></div></td></tr>
      </tbody>
    </table>
  </div>
  @foreach(['new','preparing','shipped','delivered','cancelled'] as $panel)
  <div data-panel="{{ $panel }}" style="display:none">
    <div class="empty" style="padding:40px 20px">
      <div class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 28])</div>
      <h3>No {{ $panel }} orders</h3><p>Orders with this status will appear here.</p>
    </div>
  </div>
  @endforeach
</div>

{{-- Order detail modal --}}
<div class="modal-overlay" id="orderDetailModal">
  <div class="modal">
    <div class="modal-head">
      <div><h3>Order #00001</h3><p>{{ now()->format('M d, Y · h:i A') }}</p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div><div class="field-label">Customer</div><div class="field-value">Sample Customer</div></div>
        <div><div class="field-label">Delivery Address</div><div class="field-value">123 Sample St., Barangay Sample, City, Province</div></div>
        <div><div class="field-label">Payment Method</div><div class="field-value">Cash on Delivery</div></div>
        <div>
          <div class="field-label" style="margin-bottom:10px">Items Ordered</div>
          <div style="border:1px solid var(--border);border-radius:9px;overflow:hidden">
            <table class="tbl">
              <thead><tr><th>Product</th><th>Qty</th><th>Price</th></tr></thead>
              <tbody>
                <tr><td>Sample Product</td><td>1</td><td class="mono">₱299.00</td></tr>
                <tr><td colspan="2" style="text-align:right;font-weight:700">Total</td><td class="mono">₱299.00</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Close</button>
      <a href="{{ route('seller.prepare') }}" class="btn btn-primary">@include('seller.partials.icon', ['name' => 'package', 'size' => 14]) Prepare Order</a>
    </div>
  </div>
</div>
@endsection
