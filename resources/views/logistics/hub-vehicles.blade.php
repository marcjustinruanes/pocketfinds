@extends('logistics.layout')
@section('title', 'Hub Vehicles')
@section('page-title', 'Hub Vehicles')
@section('page-sub', 'Vehicles assigned to your hub by your company admin — mark them in or out of maintenance as needed')

@section('content')
@if(session('success'))<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div class="card">
  <div class="card-head">
    <div>
      <h2>Vehicles at {{ $hub->municipality }}, {{ $hub->province }}</h2>
      <p>{{ $vehicles->count() }} vehicle{{ $vehicles->count() === 1 ? '' : 's' }} assigned to this hub</p>
    </div>
  </div>
  <div class="card-pad">
    <div class="table-wrap">
      <table class="dtable">
        <thead>
          <tr><th>Vehicle</th><th>Type</th><th>Platform Status</th><th>Availability</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($vehicles as $vehicle)
          <tr>
            <td>
              <strong>{{ $vehicle->brand }} {{ $vehicle->model }}</strong>
              <div class="mono" style="font-size:11.5px;color:var(--muted)">{{ $vehicle->plate_number }}</div>
            </td>
            <td>{{ str_replace('_', ' ', ucfirst($vehicle->vehicle_type)) }}</td>
            <td>
              @if($vehicle->platform_status === 'pending')
                <span class="stamp stamp-pending">Awaiting PocketFinds Review</span>
              @elseif($vehicle->platform_status === 'approved')
                <span class="stamp stamp-active">Approved</span>
              @elseif($vehicle->platform_status === 'rejected')
                <span class="stamp stamp-rejected">Rejected</span>
                @if($vehicle->platform_status_reason)
                  <div style="font-size:11px;color:var(--danger);margin-top:3px">{{ $vehicle->platform_status_reason }}</div>
                @endif
              @endif
            </td>
            <td>
              @if($vehicle->platform_status === 'approved')
                <span class="stamp {{ $vehicle->is_available ? 'stamp-active' : 'stamp-suspended' }}">
                  {{ $vehicle->is_available ? 'Available' : 'In Maintenance' }}
                </span>
              @else
                <span style="color:var(--muted);font-size:12px">—</span>
              @endif
            </td>
            <td>
              @if($vehicle->platform_status === 'approved')
              <form method="POST" action="{{ route('logistics.hub.vehicles.toggle', $vehicle->id) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-sm btn-outline">
                  {{ $vehicle->is_available ? 'Mark In Maintenance' : 'Mark Available' }}
                </button>
              </form>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="5">
            <div class="empty">
              <div class="ic"><x-admin-icon name="shield" /></div>
              <h3>No vehicles assigned to this hub yet</h3>
              <p>Your company admin adds vehicles from the Fleet page and assigns them to hubs.</p>
            </div>
          </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
