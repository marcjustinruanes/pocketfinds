@extends('logistics.layout')
@section('title', 'Live Monitor')
@section('page-title', 'Live Monitor')
@section('page-sub', 'Active deliveries in progress')

@section('content')
<div class="card">
  <div class="card-head"><h2>Active Shipments</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Courier</th><th>Status</th><th>Update Status</th></tr></thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">#{{ $s->id }}</td>
          <td>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td>{{ optional($s->courier)->first_name }} {{ optional($s->courier)->last_name ?? '—' }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
          <td>
            <form method="POST" action="{{ route('logistics.status.update', $s->id) }}" style="display:flex;gap:8px">
              @csrf @method('PATCH')
              <select name="status" class="select">
                @foreach(['assigned','accepted','picked_up','out_for_delivery','delivered','cancelled','failed'] as $st)
                <option value="{{ $st }}" {{ $s->shipping_status === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                @endforeach
              </select>
              <button class="btn btn-sm btn-primary">Update</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No active deliveries</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
