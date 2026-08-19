@extends('logistics.layout')
@section('title', 'Courier Assignments')
@section('page-title', 'Courier Assignments')
@section('page-sub', 'System auto-assigns deliveries to the first courier who accepts')

@section('content')
<div class="card">
  <div class="card-head">
    <h2>Delivery Assignments</h2>
    <div style="display:flex;gap:8px">
      <span class="stamp stamp-pending">{{ $shipments->where('shipping_status','available')->count() }} awaiting courier</span>
      <span class="stamp stamp-active">{{ $shipments->whereIn('shipping_status',['accepted','picked_up','out_for_delivery'])->count() }} in progress</span>
    </div>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Delivery Status</th>
          <th>Assigned Courier</th>
          <th>Assignment Status</th>
          <th>Assigned At</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        @php $courier = optional(optional($s->assignment)->courier) @endphp
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
          <td>
            @if($courier->first_name)
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($courier->first_name,0,1)) }}</div>
                <div>
                  <strong>{{ $courier->first_name }} {{ $courier->last_name }}</strong>
                  <div style="font-size:11px;color:var(--muted)">{{ $courier->email }}</div>
                </div>
              </div>
            @else
              <span style="color:var(--muted);font-size:12.5px">Waiting for courier…</span>
            @endif
          </td>
          <td>
            @if($s->assignment)
              <span class="stamp stamp-{{ $s->assignment->status }}">{{ ucfirst($s->assignment->status) }}</span>
            @else
              <span class="stamp stamp-pending">Unassigned</span>
            @endif
          </td>
          <td style="font-size:12px;color:var(--muted)">
            {{ $s->assignment?->created_at ? \Carbon\Carbon::parse($s->assignment->created_at)->format('M d, H:i') : '—' }}
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty"><h3>No active assignments</h3><p>Approved deliveries will appear here once available.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
