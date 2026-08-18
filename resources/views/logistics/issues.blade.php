@extends('logistics.layout')
@section('title', 'Delivery Issues')
@section('page-title', 'Delivery Issues')
@section('page-sub', 'Handle failed and cancelled deliveries')

@section('content')
<div class="card">
  <div class="card-head"><div><h2>Reported Issues</h2><p>{{ $shipments->count() }} cases</p></div></div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic">🔍</span>
        <input type="text" placeholder="Search…" data-table-search="issueTable">
      </div>
    </div>
    <div data-tabs>
      <a class="tab active" data-tab="all">All</a>
      <a class="tab" data-tab="failed">Failed</a>
      <a class="tab" data-tab="cancelled">Cancelled</a>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="issueTable">
        <thead><tr><th>Tracking #</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
          @forelse($shipments as $s)
          <tr class="rail-row rail-{{ $s->shipping_status }}" data-type="{{ $s->shipping_status }}">
            <td class="mono">{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</td>
            <td>{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</td>
            <td>{!! $s->courier ? $s->courier->first_name.' '.$s->courier->last_name : '<span style="color:var(--muted)">Unassigned</span>' !!}</td>
            <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst($s->shipping_status) }}</span></td>
            <td class="mono">{{ $s->created_at?->format('Y-m-d') }}</td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="issueModal-{{ $s->id }}">Handle</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="issueModal-{{ $s->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div><h3>Handle Issue</h3><p>{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</p></div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Buyer</div><div class="field-value">{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</div></div>
                  <div><div class="field-label">Courier</div><div class="field-value">{{ $s->courier ? $s->courier->first_name.' '.$s->courier->last_name : '—' }}</div></div>
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst($s->shipping_status) }}</span></div></div>
                  <div><div class="field-label">Total</div><div class="field-value mono">₱{{ number_format($s->order?->total_amount ?? 0, 2) }}</div></div>
                  <div><div class="field-label">Date</div><div class="field-value mono">{{ $s->created_at?->format('Y-m-d H:i') }}</div></div>
                </div>
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
                <form method="POST" action="{{ route('logistics.status.update', $s->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <input type="hidden" name="status" value="pending">
                  <button class="btn btn-primary" type="submit">Retry Delivery</button>
                </form>
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="6"><div class="empty"><div class="ic">✔</div><h3>No delivery issues</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
