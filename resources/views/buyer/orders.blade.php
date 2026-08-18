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

<div class="card">
  <div class="card-pad">
    <div class="empty">
      <div class="ic">@include('buyer.partials.icon', ['name' => 'package', 'size' => 28])</div>
      <h3>No orders yet</h3>
      <p>Orders with status "{{ str_replace('_', ' ', ucfirst($tab)) }}" will appear here.</p>
      @if($tab === 'to_ship')
      <a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>
      @endif
    </div>
  </div>
</div>
@endsection
