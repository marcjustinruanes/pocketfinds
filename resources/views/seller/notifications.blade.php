@extends('seller.layout')
@section('title', 'Notifications')
@section('page-title', 'Order Notifications')
@section('page-sub', 'New orders and alerts requiring your attention')

@section('content')
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div><h2>New Orders</h2><p>Requires your action</p></div>
        <button class="btn btn-sm btn-outline">Mark all read</button>
      </div>
      <div class="card-pad">
        @php $notifs = [
          ['New order received','Order #00001 — 1 item · ₱299.00','2 min ago', false],
          ['New order received','Order #00002 — 3 items · ₱850.00','15 min ago', false],
          ['Low stock alert','Sample Product has only 2 units left','1 hr ago', false],
          ['Order delivered','Order #99998 was confirmed delivered','Yesterday', true],
        ]; @endphp
        @foreach($notifs as [$title,$sub,$time,$read])
        <div class="notif-row">
          <div class="notif-dot {{ $read ? 'read' : '' }}"></div>
          <div class="notif-body">
            <div class="notif-title">{{ $title }}</div>
            <div class="notif-sub">{{ $sub }}</div>
          </div>
          <div class="notif-time">{{ $time }}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        @foreach([['bell','Unread Notifications',3,'stamp-new'],['orders','Pending Orders',2,'stamp-pending'],['truck','In Transit',0,'stamp-transit'],['check-circle','Delivered Today',1,'stamp-delivered']] as [$icon,$label,$count,$stamp])
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:9px">
          <span style="color:var(--pink-dark)">@include('seller.partials.icon', ['name' => $icon, 'size' => 18])</span>
          <span style="font-size:13px;font-weight:600;flex:1">{{ $label }}</span>
          <span class="stamp {{ $stamp }}">{{ $count }}</span>
        </div>
        @endforeach
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('seller.orders') }}" class="btn btn-primary">@include('seller.partials.icon', ['name' => 'orders', 'size' => 15]) View All Orders</a>
        <a href="{{ route('seller.prepare') }}" class="btn btn-outline">@include('seller.partials.icon', ['name' => 'package', 'size' => 15]) Prepare Orders</a>
      </div>
    </div>
  </div>
</div>
@endsection
