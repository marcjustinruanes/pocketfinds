@extends('buyer.layout')
@section('title', 'My Orders')
@section('page-title', 'My Orders')
@section('page-sub', 'Track and manage your orders')

@section('content')
@php $tab = request('tab', 'to_ship'); @endphp

<div class="tabs">
  @php
  $orderTabs = [
    ['to_ship',          'package', 'To Ship'],
    ['in_transit',       'truck',   'In Transit'],
    ['out_for_delivery', 'bike',    'Out for Delivery'],
    ['completed',        'check',   'Completed'],
    ['cancelled',        'x',       'Cancelled'],
  ];
  @endphp
  @foreach($orderTabs as [$key, $icon, $label])
  <a href="{{ route('buyer.orders') }}?tab={{ $key }}" class="tab {{ $tab === $key ? 'active' : '' }}">
    <span style="display:inline-flex;align-items:center;gap:5px">
      @include('buyer.partials.icon', ['name' => $icon, 'size' => 14])
      {{ $label }}
    </span>
  </a>
  @endforeach
</div>

@if(session('success'))<div class="auth-success" style="margin-bottom:16px">{{ session('success') }}</div>@endif
@forelse($orders as $order)
<div class="order-card">
  <div class="order-card-head"><strong>{{ $order->seller?->business_name ?: ($order->seller?->given_names ?: 'Seller not provided') }}</strong><span class="stamp stamp-{{ $order->status === 'to_ship' ? 'new' : $order->status }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span></div>
  <div class="order-meta"><span class="mono">{{ $order->order_number }}</span><span>{{ $order->created_at->format('M d, Y h:i A') }}</span></div>
  <div class="order-items">@foreach($order->items as $item)<div>{{ $item['name'] ?: 'Product not provided' }} × {{ $item['qty'] }} @if($item['color'] || $item['size'])<span style="color:var(--muted)">({{ collect([$item['color'], $item['size']])->filter()->join(', ') }})</span>@endif</div>@endforeach</div>
  <div class="order-meta"><span>Deliver to: {{ collect([$order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null, $order->shipping_address['province'] ?? null])->filter()->join(', ') ?: 'Address not provided' }}</span><span>Payment: {{ $order->paymentMethod?->name ?: 'Payment not provided' }}</span></div>
  <div class="order-meta"><span>Shipping: PHP {{ number_format($order->shipping_amount, 2) }}</span><strong>Total: PHP {{ number_format($order->total, 2) }}</strong></div>
  <div class="order-actions"><span style="font-size:12px;color:var(--muted)">Order confirmed and waiting for seller to ship</span></div>
</div>
@empty
<div class="card"><div class="card-pad"><div class="empty"><div class="ic">@include('buyer.partials.icon', ['name' => 'package', 'size' => 28])</div><h3>No orders yet</h3><p>Orders with status "{{ str_replace('_', ' ', ucfirst($tab)) }}" will appear here.</p>@if($tab === 'to_ship')<a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>@endif</div></div></div>
@endforelse
@endsection
