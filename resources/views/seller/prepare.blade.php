@extends('seller.layout')
@section('title', 'Prepare Orders')
@section('page-title', 'Prepare Orders')
@section('page-sub', 'Pack items and print shipping labels')

@section('content')
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div><h2>Orders to Prepare</h2><p>Pack and label before handing to courier</p></div>
        <button class="btn btn-sm btn-outline">@include('seller.partials.icon', ['name' => 'printer', 'size' => 13]) Print All Labels</button>
      </div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        @php $orders = [
          ['#00001','Sample Customer','1 item — Sample Product','₱299.00','Cash on Delivery'],
          ['#00002','Another Customer','3 items — Various Products','₱850.00','GCash'],
        ]; @endphp
        @foreach($orders as [$id,$customer,$items,$total,$payment])
        <div class="order-card">
          <div class="order-card-head">
            <span class="order-id">{{ $id }}</span>
            <span class="stamp stamp-pending">To Prepare</span>
          </div>
          <div class="order-items">{{ $items }}</div>
          <div class="order-meta">
            <span>@include('seller.partials.icon', ['name' => 'user', 'size' => 12]) {{ $customer }}</span>
            <span class="mono">{{ $total }}</span>
            <span>{{ $payment }}</span>
          </div>
          <div class="order-actions">
            <button class="btn btn-sm btn-outline">@include('seller.partials.icon', ['name' => 'printer', 'size' => 13]) Print Waybill</button>
            <button class="btn btn-sm btn-outline">@include('seller.partials.icon', ['name' => 'eye', 'size' => 13]) View Details</button>
            <a href="{{ route('seller.shipments') }}" class="btn btn-sm btn-primary" style="margin-left:auto">@include('seller.partials.icon', ['name' => 'truck', 'size' => 13]) Hand to Courier</a>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Packing Checklist</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        @foreach(['Verify items match order','Wrap items securely','Attach printed waybill','Seal the package','Label fragile items if needed'] as $step)
        <label style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer">
          <input type="checkbox" style="width:16px;height:16px;accent-color:var(--pink)">
          {{ $step }}
        </label>
        @endforeach
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Courier Info</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px;font-size:13px">
        <div><div class="field-label">Assigned Courier</div><div class="field-value">J&T Express</div></div>
        <div><div class="field-label">Pickup Schedule</div><div class="field-value">Today, 2:00 PM – 5:00 PM</div></div>
        <a href="{{ route('seller.shipments') }}" class="btn btn-outline" style="margin-top:6px">@include('seller.partials.icon', ['name' => 'truck', 'size' => 15]) Manage Shipments</a>
      </div>
    </div>
  </div>
</div>
@endsection
