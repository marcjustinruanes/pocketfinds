@extends('buyer.layout')
@section('title', 'My Orders')
@section('page-title', 'My Orders')
@section('page-sub', 'Track and manage your orders')

@section('content')
@php $tab = request('tab', 'to_ship'); @endphp

<div class="tabs">
  @foreach([['to_ship','📦 To Ship'],['in_transit','🚚 In Transit'],['out_for_delivery','🏍 Out for Delivery'],['completed','✅ Completed'],['cancelled','✕ Cancelled']] as [$key,$label])
  <a href="{{ route('buyer.orders') }}?tab={{ $key }}" class="tab {{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
  @endforeach
</div>

<div class="card">
  <div class="card-pad">
    <div class="empty">
      <div class="ic">📦</div>
      <h3>No orders yet</h3>
      <p>Orders with status "{{ str_replace('_',' ', ucfirst($tab)) }}" will appear here.</p>
      @if($tab === 'to_ship')
      <a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>
      @endif
    </div>
  </div>
</div>
@endsection
