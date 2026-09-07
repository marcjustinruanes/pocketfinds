@extends('logistics.layout')
@section('title', 'Delivery Assignments')
@section('page-title', 'Delivery Assignments')
@section('page-sub', 'Sorted parcels ready for a rider — assign directly, or let riders self-accept first come, first served')

@section('content')
@if($errors->any())<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first() }}</div>@endif
<div class="card">
  <div class="card-head">
    <h2>Delivery Assignments</h2>
    <div style="display:flex;gap:8px">
      <span class="stamp stamp-pending">{{ $shipments->where('shipping_status','sorted')->count() }} awaiting courier</span>
      <span class="stamp stamp-active">{{ $shipments->whereIn('shipping_status',['assigned_to_rider','out_for_delivery'])->count() }} in progress</span>
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
          <td>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
          <td>
            @if($courier->given_names)
              <div class="cell-user">
                <div class="avatar-sm">{{ strtoupper(substr($courier->given_names,0,1)) }}</div>
                <div>
                  <strong>{{ $courier->given_names }} {{ $courier->last_name }}</strong>
                  <div style="font-size:11px;color:var(--muted)">{{ $courier->email }}</div>
                </div>
              </div>
            @elseif($s->shipping_status === 'sorted')
              <form method="POST" action="{{ route('logistics.assignments.assign', $s->id) }}" style="display:flex;gap:6px">
                @csrf @method('PATCH')
                <select name="courier_id" class="select" required>
                  <option value="" selected disabled>Select courier…</option>
                  @foreach($couriers as $rider)
                  <option value="{{ $rider->id }}">{{ $rider->given_names }} {{ $rider->last_name }}</option>
                  @endforeach
                </select>
                <button class="btn btn-sm btn-primary">Assign</button>
              </form>
            @elseif($s->shipping_status === 'at_sorting_center' && $s->needsHubTransfer())
              <span style="color:var(--muted);font-size:12.5px">At {{ $s->origin_hub }} — assign a hub-transfer rider on the Scan page</span>
            @elseif($s->shipping_status === 'hub_transfer')
              @php($activeLeg = $s->activeHubLeg())
              <span style="color:var(--muted);font-size:12.5px">{{ optional($s->hubTransferRider)->given_names ?? 'A rider' }} en route {{ $activeLeg->from_hub ?? $s->origin_hub }} → {{ $activeLeg->to_hub ?? $s->destination_hub }}</span>
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
            {{ $s->assignment?->accepted_at ? \Carbon\Carbon::parse($s->assignment->accepted_at)->format('M d, H:i') : '—' }}
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
