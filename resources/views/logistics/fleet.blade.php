@extends('logistics.layout')
@section('title', 'Vehicle Fleet')
@section('page-title', 'Vehicle Fleet')
@section('page-sub', 'Add vehicles for your hubs — PocketFinds reviews each one before it appears to riders')

@section('content')
@if(session('success'))<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>@endif
@if($errors->any())<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first() }}</div>@endif

{{-- Add a vehicle --}}
<div class="card" style="margin-bottom:20px">
  <div class="card-head">
    <div>
      <h2>Add a Vehicle</h2>
      <p>Assign it to a hub. PocketFinds will review it before it becomes available for rider assignment.</p>
    </div>
  </div>
  <div class="card-pad">
    <form method="POST" action="{{ route('logistics.vehicles.store') }}">
      @csrf
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;align-items:end">
        <div class="form-row">
          <label>Hub <span style="color:var(--danger)">*</span></label>
          <select name="logistics_hub_id" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px">
            <option value="" disabled selected>Select hub</option>
            @foreach($hubs as $hub)
            <option value="{{ $hub->id }}">{{ $hub->municipality }}, {{ $hub->province }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row">
          <label>Vehicle Type <span style="color:var(--danger)">*</span></label>
          <select name="vehicle_type" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px">
            @foreach($vehicleTypes as $vt)
            <option value="{{ $vt->slug }}">{{ $vt->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row">
          <label>Brand <span style="color:var(--danger)">*</span></label>
          <input type="text" name="brand" placeholder="e.g. Honda" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px">
        </div>
        <div class="form-row">
          <label>Model <span style="color:var(--danger)">*</span></label>
          <input type="text" name="model" placeholder="e.g. Click 125i" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px">
        </div>
        <div class="form-row">
          <label>Plate Number <span style="color:var(--danger)">*</span></label>
          <input type="text" name="plate_number" placeholder="e.g. ABC 1234" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px">
        </div>
      </div>
      <div style="margin-top:12px">
        <button type="submit" class="btn btn-primary">Submit to PocketFinds for Review</button>
      </div>
    </form>
  </div>
</div>

{{-- Vehicle list --}}
<div class="card">
  <div class="card-head">
    <div><h2>Company Vehicles</h2><p>{{ $vehicles->total() }} total across {{ $hubs->count() }} hub{{ $hubs->count() === 1 ? '' : 's' }}</p></div>
  </div>
  <div class="card-pad">
    <div class="tabs" id="fleetTabs">
      @foreach(['all' => 'All', 'pending' => 'Awaiting Review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
      <a class="tab {{ $status === $key ? 'active' : '' }}" href="{{ route('logistics.vehicles', ['status' => $key]) }}">{{ $label }} <span class="tab-count">{{ $key === 'all' ? $platformCounts->sum() : ($platformCounts[$key] ?? 0) }}</span></a>
      @endforeach
    </div>

    <div class="table-wrap">
      <table class="dtable">
        <thead>
          <tr><th>Vehicle</th><th>Type</th><th>Hub</th><th>Platform Status</th><th>Availability</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($vehicles as $vehicle)
          <tr data-type="{{ $vehicle->platform_status }}">
            <td>
              <strong>{{ $vehicle->brand }} {{ $vehicle->model }}</strong>
              <div class="mono" style="font-size:11.5px;color:var(--muted)">{{ $vehicle->plate_number }}</div>
            </td>
            <td>{{ str_replace('_', ' ', ucfirst($vehicle->vehicle_type)) }}</td>
            <td>
              {{ $vehicle->hub->municipality ?? '—' }}<br>
              <span style="font-size:11.5px;color:var(--muted)">{{ $vehicle->hub->province ?? '' }}</span>
            </td>
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
                  {{ $vehicle->is_available ? 'Available at hub' : 'In maintenance' }}
                </span>
              @else
                <span style="color:var(--muted);font-size:12px">—</span>
              @endif
            </td>
            <td>
              @if($vehicle->platform_status === 'pending')
              <form method="POST" action="{{ route('logistics.vehicles.destroy', $vehicle->id) }}" onsubmit="return confirm('Remove this vehicle submission?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="6">
            <div class="empty">
              <div class="ic"><x-admin-icon name="shield" /></div>
              <h3>No vehicles yet</h3>
              <p>Add vehicles above and assign them to a hub — PocketFinds will review each one.</p>
            </div>
          </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="card"><div class="card-head"><h2>Vehicle availability and maintenance</h2></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Vehicle</th><th>Hub</th><th>Maintenance / availability</th></tr></thead><tbody>@forelse($vehicles as $vehicle)<tr><td>{{ $vehicle->plate_number }}<br>{{ $vehicle->brand }} {{ $vehicle->model }}</td><td>{{ $vehicle->hub?->municipality }}</td><td><form method="POST" action="{{ route('logistics.vehicles.update',$vehicle->id) }}">@csrf @method('PATCH')<div class="logistics-form-grid"><label>Availability<select name="is_available"><option value="1" @selected($vehicle->is_available)>Available</option><option value="0" @selected(!$vehicle->is_available)>Maintenance / unavailable</option></select></label><label>Maintenance note<input name="status_reason" value="{{ $vehicle->status_reason }}" maxlength="500"></label></div><button class="btn btn-sm btn-outline">Save condition</button></form></td></tr>@empty<tr><td colspan="3"><div class="empty"><h3>No company vehicles</h3></div></td></tr>@endforelse</tbody></table></div></div>
{{ $vehicles->links() }}
@endsection
