@extends('logistics.layout')
@section('title', 'Monitor Deliveries')
@section('page-title', 'Monitor Deliveries')
@section('page-sub', 'Track and manage ongoing delivery statuses')

@section('content')
@if($errors->has('status'))
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first('status') }}</div>
@endif
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
            @php($allowedNext = $transitions[$s->shipping_status] ?? [])
            @if(count($allowedNext))
            <form method="POST" action="{{ route('logistics.status.update', $s->id) }}" style="display:flex;gap:8px">
              @csrf @method('PATCH')
              <select name="status" class="select">
                @foreach($allowedNext as $st)
                <option value="{{ $st }}">{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                @endforeach
              </select>
              <button class="btn btn-sm btn-primary">Update</button>
            </form>
            @elseif($s->shipping_status === 'available')
            <a href="{{ route('logistics.assignments') }}" style="color:var(--pink-dark);font-size:12px;font-weight:600">Assign a courier →</a>
            @else
            <span style="color:var(--muted);font-size:12px">No action needed</span>
            @endif
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
