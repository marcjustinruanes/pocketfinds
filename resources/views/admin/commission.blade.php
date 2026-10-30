@extends('admin.layout')
@section('title', 'Historical Commission Archive')
@section('page-title', 'Historical Commission Archive')
@section('page-sub', 'Historical records only; current payments have no platform commission')

@section('content')
<p>These preserved legacy entries are not proof of collected fees, actual Seller payments or current platform revenue. No current commission deduction is calculated. Use <a href="{{ route('admin.reports') }}">Reports</a> for current sales and payment records.</p>
<div class="card">
  <div class="card-head"><div><h2>Historical commission entries</h2><p>Stored values, not recalculated or applied to current payouts</p></div></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Seller</th><th>Order ID</th><th>Sale Amount</th><th>Rate</th><th>Stored legacy commission</th><th>Stored legacy allocation (not payment)</th><th>Date</th></tr></thead>
      <tbody>
        @forelse($commissions as $c)
        <tr class="rail-row rail-approved">
          <td>
            @if($c->seller)
            <div class="cell-user">
              <x-user-avatar :user="$c->seller" size="30" class="avatar-sm" />
              <div><strong>{{ $c->seller->given_names }} {{ $c->seller->last_name }}</strong></div>
            </div>
            @else
            <span style="color:var(--muted)">Unknown</span>
            @endif
          </td>
          <td class="mono">{{ strtoupper(substr($c->order_id ?? $c->id, 0, 8)) }}</td>
          <td class="mono">₱{{ number_format($c->order_amount, 2) }}</td>
          <td class="mono">{{ $c->commission_rate }}%</td>
          <td class="mono">₱{{ number_format($c->commission_amount, 2) }}</td>
          <td class="mono">₱{{ number_format($c->seller_earnings, 2) }}</td>
          <td class="mono">{{ $c->created_at?->format('Y-m-d') }}</td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty"><div class="ic"><x-admin-icon name="peso" /></div><h3>No commission records yet</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
