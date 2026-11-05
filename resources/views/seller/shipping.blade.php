@extends('seller.layout')
@section('title','Shipping Payments')
@section('page-title','Shipping Payments')
@section('page-sub','Carrier shipping fees and payment verification')
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}?v={{ filemtime(public_path('css/logistics-cash-desk.css')) }}">@endpush
@section('content')
<div class="cash-desk seller-shipping-payments">
  <section class="cash-card shipping-payments-panel" aria-labelledby="shipping-payments-title">
    <div class="shipping-payments-heading">
      <div><span class="seller-finance-eyebrow">Order operations</span><h2 id="shipping-payments-title">Your shipping obligations</h2><p>Review carrier fees and payment status for orders awaiting shipment.</p></div>
      <span class="shipping-payments-icon">@include('seller.partials.icon',['name'=>'truck','size'=>18])</span>
    </div>
    <div class="shipping-payments-note"><span>@include('seller.partials.icon',['name'=>'check-circle','size'=>15])</span><p>GCash orders require full shipping payment to your selected carrier before pickup approval. Vouchers do not reduce the carrier fee. Platform-funded claims are tracked separately.</p></div>
    <div class="cash-table shipping-payments-table">
      <table>
        <thead><tr><th>Order</th><th>Carrier</th><th>Shipping fee</th><th>Status</th><th>Details</th></tr></thead>
        <tbody>
          @forelse($obligations as $ob)
          <tr>
            <td class="cash-reference"><span class="shipping-order-label">Order</span>{{ $ob->order_id }}</td>
            <td>{{ $ob->company_name }}</td>
            <td class="shipping-fee">PHP {{ number_format($ob->amount_cents/100,2) }}</td>
            <td><span class="shipping-payment-status status-{{ str_replace('_','-', $ob->status) }}">{{ str_replace('_',' ',$ob->status) }}</span></td>
            <td><a class="btn" href="{{ route('seller.shipping.show',$ob->id) }}">View payment <span aria-hidden="true">&rarr;</span></a></td>
          </tr>
          @empty
          <tr><td colspan="5"><div class="shipping-payments-empty"><span class="shipping-payments-icon">@include('seller.partials.icon',['name'=>'truck','size'=>18])</span><strong>No new shipping obligations</strong><p>Select a matching approved carrier when preparing an order. New shipping payments will appear here.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="shipping-payments-pagination">{{ $obligations->links() }}</div>
  </section>
</div>
@endsection
