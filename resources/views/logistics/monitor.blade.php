@extends('logistics.layout')
@section('title', 'Monitor Deliveries')
@section('page-title', 'Monitor Deliveries')
@section('page-sub', 'Track and manage ongoing delivery statuses')

@section('content')
<div class="card">
  <div class="card-head"><h2>Active Deliveries</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Courier</th>
          <th>Current Status</th>
          <th>Last Update</th>
          <th>Update Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->given_names ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</strong>
              </div>
            </div>
          </td>
          <td>{{ optional($s->courier)->given_names ? optional($s->courier)->given_names . ' ' . optional($s->courier)->last_name : '—' }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
          <td class="mono" style="font-size:11.5px">{{ $s->updated_at?->format('M d, Y H:i') ?? '—' }}</td>
          <td>
            <form method="POST" action="{{ route('logistics.status.update', $s->id) }}" style="display:flex;gap:8px">
              @csrf @method('PATCH')
              <select name="status" class="select">
                @foreach(['pending','for_verification','verified','available','accepted','picked_up','out_for_delivery','delivered','completed','cancelled','failed'] as $st)
                <option value="{{ $st }}" {{ $s->shipping_status === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                @endforeach
              </select>
              <button class="btn btn-sm btn-primary">Update</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty"><h3>No active deliveries</h3><p>Deliveries in progress will appear here.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
