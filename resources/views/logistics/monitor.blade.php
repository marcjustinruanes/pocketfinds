@extends('logistics.layout')
@section('title', 'Monitor Deliveries')
@section('page-title', 'Monitor Deliveries')
@section('page-sub', 'Track ongoing deliveries in real time')

@section('content')
<div class="card">
  <div class="card-head"><div><h2>Active Deliveries</h2><p>{{ $shipments->count() }} in progress</p></div></div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic">🔍</span>
        <input type="text" placeholder="Search tracking # or courier…" data-table-search="monTable">
      </div>
      <select class="select" id="statusFilter">
        <option value="">All Statuses</option>
        <option value="assigned">Assigned</option>
        <option value="accepted">Accepted</option>
        <option value="picked_up">Picked Up</option>
        <option value="out_for_delivery">Out for Delivery</option>
      </select>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="monTable">
        <thead><tr><th>Tracking #</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Picked Up</th><th>Last Update</th><th></th></tr></thead>
        <tbody>
          @forelse($shipments as $s)
          <tr class="rail-row rail-{{ $s->shipping_status }}">
            <td class="mono">{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</td>
            <td>{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</td>
            <td>{!! $s->courier ? $s->courier->first_name.' '.$s->courier->last_name : '<span style="color:var(--muted)">Unassigned</span>' !!}</td>
            <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
            <td class="mono">{{ $s->picked_up_at?->format('Y-m-d H:i') ?? '—' }}</td>
            <td class="mono">{{ $s->in_transit_at?->format('Y-m-d H:i') ?? $s->created_at?->format('Y-m-d H:i') }}</td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="monModal-{{ $s->id }}">Update</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="monModal-{{ $s->id }}">
            <div class="modal">
              <div class="modal-head">
                <div><h3>Update Status</h3><p>{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</p></div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <form method="POST" action="{{ route('logistics.status.update', $s->id) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                  <div class="detail-grid">
                    <div><div class="field-label">Courier</div><div class="field-value">{{ $s->courier ? $s->courier->first_name.' '.$s->courier->last_name : '—' }}</div></div>
                    <div><div class="field-label">Current Status</div><div class="field-value"><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></div></div>
                  </div>
                  <div class="form-row" style="margin-top:12px">
                    <label>New Status</label>
                    <select name="status" class="select" style="width:100%" required>
                      @foreach(['pending','assigned','accepted','picked_up','out_for_delivery','delivered','completed','cancelled','failed'] as $st)
                      <option value="{{ $st }}" {{ $s->shipping_status === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="modal-foot">
                  <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
                  <button class="btn btn-primary" type="submit">Update</button>
                </div>
              </form>
            </div>
          </div>
          @empty
          <tr><td colspan="7"><div class="empty"><div class="ic">📡</div><h3>No active deliveries</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
