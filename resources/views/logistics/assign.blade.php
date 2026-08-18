@extends('logistics.layout')
@section('title', 'Assign Couriers')
@section('page-title', 'Assign Couriers')
@section('page-sub', 'Assign riders to approved shipments')

@section('content')
<div class="card">
  <div class="card-head"><h2>Shipments</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead><tr><th>Shipment</th><th>Buyer</th><th>Status</th><th>Assigned Courier</th><th>Assign</th></tr></thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">#{{ $s->id }}</td>
          <td>{{ optional(optional($s->order)->buyer)->first_name }} {{ optional(optional($s->order)->buyer)->last_name }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
          <td>{{ optional(optional($s->assignment)->courier)->first_name }} {{ optional(optional($s->assignment)->courier)->last_name ?? '—' }}</td>
          <td>
            <form method="POST" action="{{ route('logistics.assign.courier', $s->id) }}" style="display:flex;gap:8px">
              @csrf @method('PATCH')
              <select name="courier_id" class="select" required>
                <option value="">Select courier</option>
                @foreach($couriers as $c)
                <option value="{{ $c->id }}" {{ optional(optional($s->assignment)->courier)->id == $c->id ? 'selected' : '' }}>{{ $c->first_name }} {{ $c->last_name }}</option>
                @endforeach
              </select>
              <button class="btn btn-sm btn-primary">Assign</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No shipments to assign</h3></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
