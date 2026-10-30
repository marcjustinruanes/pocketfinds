@extends('seller.layout')
@section('title','Shipping Payments')
@section('page-title','Shipping Payments')
@section('page-sub','Carrier shipping fees and payment verification')
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}?v={{ filemtime(public_path('css/logistics-cash-desk.css')) }}">@endpush
@section('content')
<div class="cash-desk"><section class="cash-card"><h2>Your shipping obligations</h2><p>GCash orders require full shipping payment to your selected carrier before pickup approval. Vouchers do not reduce the carrier fee. Platform-funded claims are tracked separately.</p>
  <div class="cash-table"><table><thead><tr><th>Order</th><th>Carrier</th><th>Shipping</th><th>Status</th><th>Details</th></tr></thead><tbody>
    @forelse($obligations as $ob)<tr><td class="cash-reference">{{ $ob->order_id }}</td><td>{{ $ob->company_name }}</td><td>PHP {{ number_format($ob->amount_cents/100,2) }}</td><td>{{ str_replace('_',' ',$ob->status) }}</td><td><a class="btn" href="{{ route('seller.shipping.show',$ob->id) }}">View shipping payment</a></td></tr>@empty<tr><td colspan="5">No new obligations. Select a matching approved carrier when preparing your order.</td></tr>@endforelse
  </tbody></table></div>{{ $obligations->links() }}
</section></div>
@endsection
