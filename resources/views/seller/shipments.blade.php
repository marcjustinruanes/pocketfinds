@extends('seller.layout')
@section('title', 'Shipments')
@section('page-title', 'Shipments')
@section('page-sub', 'Schedule courier pickup and track shipment status')

@section('content')
<div class="filter-bar">
  <div class="search-mini">
    <span class="ic">@include('seller.partials.icon', ['name' => 'search', 'size' => 13])</span>
    <input type="text" placeholder="Search by tracking number or order ID…">
  </div>
  <select class="select">
    <option>All Couriers</option><option>J&T Express</option><option>LBC</option><option>Ninja Van</option><option>GrabExpress</option>
  </select>
  <select class="select">
    <option>All Statuses</option><option>Awaiting Pickup</option><option>In Transit</option><option>Out for Delivery</option><option>Delivered</option>
  </select>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Active Shipments</h2><p>Track all packages in transit</p></div>
    <button class="btn btn-sm btn-primary" data-modal="schedulePickupModal">
      @include('seller.partials.icon', ['name' => 'truck', 'size' => 14]) Schedule Pickup
    </button>
  </div>
  <table class="tbl">
    <thead><tr>
      <th>Order ID</th><th>Tracking No.</th><th>Courier</th><th>Customer</th><th>Status</th><th>Est. Delivery</th><th>Actions</th>
    </tr></thead>
    <tbody>
      <tr>
        <td class="mono">#00001</td>
        <td class="mono">JT-000000001</td>
        <td>J&T Express</td>
        <td>Sample Customer</td>
        <td><span class="stamp stamp-transit">In Transit</span></td>
        <td style="font-size:12px;color:var(--muted)">{{ now()->addDays(2)->format('M d, Y') }}</td>
        <td>
          <div class="tbl-actions">
            <button class="btn btn-sm btn-outline">@include('seller.partials.icon', ['name' => 'map-pin', 'size' => 13]) Track</button>
            <button class="btn btn-sm btn-outline">@include('seller.partials.icon', ['name' => 'refresh', 'size' => 13]) Update</button>
          </div>
        </td>
      </tr>
      <tr><td colspan="7"><div class="empty" style="padding:30px 20px"><h3>No more shipments</h3><p>Handed-over orders will appear here.</p></div></td></tr>
    </tbody>
  </table>
</div>

{{-- Schedule pickup modal --}}
<div class="modal-overlay" id="schedulePickupModal">
  <div class="modal">
    <div class="modal-head">
      <div><h3>Schedule Courier Pickup</h3><p>Book a pickup for your packed orders</p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div class="form-row"><label>Courier</label>
        <select><option>J&T Express</option><option>LBC</option><option>Ninja Van</option><option>GrabExpress</option></select>
      </div>
      <div class="form-grid-2">
        <div class="form-row"><label>Pickup Date</label><input type="date" value="{{ now()->format('Y-m-d') }}"></div>
        <div class="form-row"><label>Time Slot</label>
          <select><option>9:00 AM – 12:00 PM</option><option>12:00 PM – 3:00 PM</option><option>2:00 PM – 5:00 PM</option></select>
        </div>
      </div>
      <div class="form-row"><label>Pickup Address</label><input type="text" placeholder="Your store address"></div>
      <div class="form-row"><label>Notes for Courier</label><textarea rows="2" placeholder="Optional instructions…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Cancel</button>
      <button class="btn btn-primary">@include('seller.partials.icon', ['name' => 'truck', 'size' => 14]) Confirm Pickup</button>
    </div>
  </div>
</div>
@endsection
