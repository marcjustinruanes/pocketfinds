@extends('rider.layout')
@section('title', 'My Hub Transfers')
@section('page-title', 'My Hub Transfers')
@section('page-sub', 'Confirm pickup from the origin hub before heading out — the destination hub can only mark it received after you do')

@section('content')
@if(session('success'))<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>@endif
@if($errors->any())<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first() }}</div>@endif

<div class="card">
  <div class="card-head"><h2>My Hub Transfers</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Route</th>
          <th>Leg</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($legs as $leg)
        <tr>
          <td class="mono">{{ $leg->shipment->tracking_number ?? substr($leg->shipment_id, 0, 8) }}</td>
          <td><strong>{{ $leg->from_hub }}</strong> &rarr; <strong>{{ $leg->to_hub }}</strong></td>
          <td>{{ ucfirst($leg->leg_type) }}</td>
          <td>
            @if($leg->rider_confirmed_pickup_at)
              <span class="stamp stamp-active">Picked up — en route</span>
            @else
              <span class="stamp stamp-pending">Confirm pickup to depart</span>
            @endif
          </td>
          <td>
            @if(!$leg->rider_confirmed_pickup_at)
            <form method="POST" action="{{ route('rider.hub-transfers.confirm', $leg->id) }}" onsubmit="return confirm('Confirm you picked up this parcel from ' + '{{ $leg->from_hub }}' + '?')">
              @csrf @method('PATCH')
              <button type="submit" class="btn btn-sm btn-primary">Confirm Pickup</button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No hub transfers assigned</h3><p>Legs your admin or a hub assigns you will appear here.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
