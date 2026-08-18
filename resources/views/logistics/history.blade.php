@extends('logistics.layout')
@section('title', 'Delivery History')
@section('page-title', 'Delivery History')
@section('page-sub', 'View all completed deliveries')

@section('content')
<div class="card">
  <div class="card-head"><div><h2>Completed Deliveries</h2><p>{{ $shipments->count() }} records</p></div></div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic">🔍</span>
        <input type="text" placeholder="Search tracking # or courier…" data-table-search="histTable">
      </div>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="histTable">
        <thead><tr><th>Tracking #</th><th>Buyer</th><th>Courier</th><th>Picked Up</th><th>Delivered</th><th>Total</th><th></th></tr></thead>
        <tbody>
          @forelse($shipments as $s)
          <tr class="rail-row rail-approved">
            <td class="mono">{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</td>
            <td>{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</td>
            <td>{{ $s->courier ? $s->courier->first_name.' '.$s->courier->last_name : '—' }}</td>
            <td class="mono">{{ $s->picked_up_at?->format('Y-m-d H:i') ?? '—' }}</td>
            <td class="mono">{{ $s->delivered_at?->format('Y-m-d H:i') ?? '—' }}</td>
            <td class="mono">₱{{ number_format($s->order?->total_amount ?? 0, 2) }}</td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-ghost" data-modal-open="histModal-{{ $s->id }}">View</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="histModal-{{ $s->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div><h3>Delivery Details</h3><p>{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</p></div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Buyer</div><div class="field-value">{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</div></div>
                  <div><div class="field-label">Courier</div><div class="field-value">{{ $s->courier ? $s->courier->first_name.' '.$s->courier->last_name : '—' }}</div></div>
                  <div><div class="field-label">Picked Up</div><div class="field-value mono">{{ $s->picked_up_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                  <div><div class="field-label">In Transit</div><div class="field-value mono">{{ $s->in_transit_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                  <div><div class="field-label">Out for Delivery</div><div class="field-value mono">{{ $s->out_for_delivery_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                  <div><div class="field-label">Delivered</div><div class="field-value mono">{{ $s->delivered_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                  <div><div class="field-label">Total Amount</div><div class="field-value mono">₱{{ number_format($s->order?->total_amount ?? 0, 2) }}</div></div>
                  <div><div class="field-label">Payment</div><div class="field-value">{{ ucfirst($s->order?->payment_method ?? '—') }}</div></div>
                </div>
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="7"><div class="empty"><div class="ic">📋</div><h3>No completed deliveries yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
