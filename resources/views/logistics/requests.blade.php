@extends('logistics.layout')
@section('title', 'Delivery Requests')
@section('page-title', 'Delivery Requests')
@section('page-sub', 'Review and approve incoming delivery requests')

@section('content')
<div class="card">
  <div class="card-head"><div><h2>Incoming Requests</h2><p>{{ $shipments->count() }} pending</p></div></div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic">🔍</span>
        <input type="text" placeholder="Search tracking # or buyer…" data-table-search="reqTable">
      </div>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="reqTable">
        <thead><tr><th>Tracking #</th><th>Buyer</th><th>Items</th><th>Total</th><th>Payment</th><th>Requested</th><th></th></tr></thead>
        <tbody>
          @forelse($shipments as $s)
          <tr class="rail-row rail-pending">
            <td class="mono">{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</td>
            <td>{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</td>
            <td class="mono">{{ $s->order?->items->count() ?? 0 }}</td>
            <td class="mono">₱{{ number_format($s->order?->total_amount ?? 0, 2) }}</td>
            <td>{{ ucfirst($s->order?->payment_method ?? '—') }}</td>
            <td class="mono">{{ $s->created_at?->format('Y-m-d') }}</td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="reqModal-{{ $s->id }}">Review</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="reqModal-{{ $s->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div><h3>Delivery Request</h3><p>Tracking: {{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</p></div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Buyer</div><div class="field-value">{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</div></div>
                  <div><div class="field-label">Payment Method</div><div class="field-value">{{ ucfirst($s->order?->payment_method ?? '—') }}</div></div>
                  <div><div class="field-label">Subtotal</div><div class="field-value mono">₱{{ number_format($s->order?->subtotal ?? 0, 2) }}</div></div>
                  <div><div class="field-label">Shipping Fee</div><div class="field-value mono">₱{{ number_format($s->order?->shipping_fee ?? 0, 2) }}</div></div>
                  <div><div class="field-label">Total Amount</div><div class="field-value mono">₱{{ number_format($s->order?->total_amount ?? 0, 2) }}</div></div>
                  <div><div class="field-label">Requested</div><div class="field-value mono">{{ $s->created_at?->format('Y-m-d H:i') }}</div></div>
                  @if($s->order?->items->count())
                  <div class="full">
                    <div class="field-label">Order Items</div>
                    @foreach($s->order->items as $item)
                    <div style="font-size:13px;padding:4px 0;border-bottom:1px solid var(--border)">
                      {{ $item->product_name }} × {{ $item->quantity }} — <span class="mono">₱{{ number_format($item->subtotal, 2) }}</span>
                    </div>
                    @endforeach
                  </div>
                  @endif
                </div>
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Cancel</button>
                <form method="POST" action="{{ route('logistics.requests.reject', $s->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-danger" type="submit">Reject</button>
                </form>
                <form method="POST" action="{{ route('logistics.requests.approve', $s->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Approve</button>
                </form>
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="7"><div class="empty"><div class="ic">📥</div><h3>No pending requests</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
