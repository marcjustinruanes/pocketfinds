@extends('admin.layout')
@section('title', 'Order Review')
@section('page-title', 'Order Review')
@section('page-sub', 'Manual investigation')
@section('content')
<a class="btn btn-outline" href="{{ route('admin.ai', ['mode' => 'anomalies']) }}"><x-admin-icon name="flag" /> Review alerts</a>
<section style="margin-top:24px">
  <h2>{{ $order->order_number ?: $order->id }}</h2>
  <p>Pattern warnings are not proof of fraud. Review the evidence and consider legitimate explanations.</p>
  <dl style="display:grid;grid-template-columns:140px minmax(0,1fr);gap:12px;margin:24px 0">
    <dt>Status</dt><dd>{{ str_replace('_', ' ', $order->status) }}</dd>
    <dt>Order value</dt><dd>&#8369;{{ number_format((float) $order->total, 2) }}</dd>
    <dt>Placed</dt><dd>{{ $order->created_at?->format('M j, Y H:i') }}</dd>
    <dt>Buyer reference</dt><dd>Account #{{ $order->buyer_id }}</dd>
    <dt>Shop reference</dt><dd>Shop #{{ $order->seller_id }}</dd>
    <dt>Cancellation</dt><dd>{{ $order->cancellation_reason ?: 'None recorded' }}</dd>
  </dl>
  <h3>Recorded order items</h3>
  @forelse($order->items ?? [] as $item)
    @if(is_array($item))<div style="padding:12px 0;border-bottom:1px solid var(--border)"><strong>{{ $item['name'] ?? 'Unnamed item' }}</strong><p>{{ (int) ($item['qty'] ?? 0) }} units at &#8369;{{ number_format((float) ($item['price'] ?? 0), 2) }}</p></div>@endif
  @empty
    <p>No item details recorded.</p>
  @endforelse
  <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:24px"><a class="btn btn-outline" href="{{ route('admin.users') }}">Review accounts</a><a class="btn btn-outline" href="{{ route('admin.complaints') }}">Review complaints</a></div>
  <p style="margin-top:16px;color:var(--muted)">Read-only review. No orders or accounts have been changed.</p>
</section>
@endsection
