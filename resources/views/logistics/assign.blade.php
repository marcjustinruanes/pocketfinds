@extends('logistics.layout')
@section('title', 'Assign Courier')
@section('page-title', 'Assign Courier')
@section('page-sub', 'Assign or reassign couriers to deliveries')

@section('content')
<div class="dash-grid">
  <div class="card">
    <div class="card-head"><div><h2>Unassigned / Pending Deliveries</h2><p>{{ $shipments->count() }} deliveries</p></div></div>
    <div class="card-pad">
      <div class="filter-bar">
        <div class="search-mini">
          <span class="ic">🔍</span>
          <input type="text" placeholder="Search…" data-table-search="assignTable">
        </div>
      </div>
      <div class="table-wrap">
        <table class="dtable" id="assignTable">
          <thead><tr><th>Tracking #</th><th>Buyer</th><th>Current Courier</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse($shipments as $s)
            <tr class="rail-row rail-{{ $s->shipping_status }}">
              <td class="mono">{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</td>
              <td>{{ $s->order?->buyer ? $s->order->buyer->first_name.' '.$s->order->buyer->last_name : '—' }}</td>
              <td>{!! $s->courier ? $s->courier->first_name.' '.$s->courier->last_name : '<span style="color:var(--muted)">Unassigned</span>' !!}</td>
              <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
              <td>
                <div class="row-actions">
                  <button class="btn btn-sm btn-primary" data-modal-open="assignModal-{{ $s->id }}">Assign</button>
                </div>
              </td>
            </tr>

            <div class="modal-overlay" id="assignModal-{{ $s->id }}">
              <div class="modal">
                <div class="modal-head">
                  <div><h3>Assign Courier</h3><p>{{ strtoupper(substr($s->tracking_number ?? $s->id, 0, 10)) }}</p></div>
                  <button class="modal-close" data-modal-close>✕</button>
                </div>
                <form method="POST" action="{{ route('logistics.assign.courier', $s->id) }}">
                  @csrf @method('PATCH')
                  <div class="modal-body">
                    <div class="form-row">
                      <label>Select Courier</label>
                      <select name="courier_id" class="select" style="width:100%" required>
                        <option value="">— Choose courier —</option>
                        @foreach($couriers as $courier)
                        <option value="{{ $courier->id }}" {{ $s->courier_id == $courier->id ? 'selected' : '' }}>
                          {{ $courier->first_name }} {{ $courier->last_name }} ({{ $courier->contact_no }})
                        </option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                  <div class="modal-foot">
                    <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
                    <button class="btn btn-primary" type="submit">Assign</button>
                  </div>
                </form>
              </div>
            </div>
            @empty
            <tr><td colspan="5"><div class="empty"><div class="ic">👤</div><h3>No deliveries to assign</h3></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Available Couriers</h2><p>{{ $couriers->count() }} riders</p></div>
    <div class="table-wrap">
      <table class="dtable">
        <thead><tr><th>Courier</th><th>Contact</th><th>Deliveries</th></tr></thead>
        <tbody>
          @forelse($couriers as $courier)
          <tr class="rail-row rail-active">
            <td>
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($courier->first_name,0,1).substr($courier->last_name,0,1)) }}</div>
                <div><strong>{{ $courier->first_name }} {{ $courier->last_name }}</strong><span>{{ $courier->email }}</span></div>
              </div>
            </td>
            <td class="mono">{{ $courier->contact_no }}</td>
            <td class="mono">{{ $courier->shipments->count() }}</td>
          </tr>
          @empty
          <tr><td colspan="3"><div class="empty"><div class="ic">👤</div><h3>No couriers available</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
