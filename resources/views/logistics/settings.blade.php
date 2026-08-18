@extends('logistics.layout')
@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-sub', 'Logistics preferences and configuration')

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif

<form method="POST" action="{{ route('logistics.settings.update') }}">
  @csrf
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">

    <div class="card">
      <div class="card-head"><h2>General</h2></div>
      <div class="card-pad">
        <div class="switch-row">
          <div>
            <strong>Email Notifications</strong>
            <span>Receive alerts for new delivery requests</span>
          </div>
          <label class="switch">
            <input type="checkbox" name="email_notifications" value="1" {{ ($settings['email_notifications'] ?? '0') === '1' ? 'checked' : '' }}>
            <span class="track"></span>
          </label>
        </div>
        <div class="switch-row">
          <div>
            <strong>Auto-assign Couriers</strong>
            <span>Automatically assign available couriers to pending shipments on save</span>
          </div>
          <label class="switch">
            <input type="checkbox" name="auto_assign" value="1" {{ ($settings['auto_assign'] ?? '0') === '1' ? 'checked' : '' }}>
            <span class="track"></span>
          </label>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Delivery Rules</h2></div>
      <div class="card-pad">
        <div class="form-row">
          <label>Max Deliveries per Courier</label>
          <input type="number" name="max_deliveries_per_courier" value="{{ $settings['max_deliveries_per_courier'] ?? 10 }}" min="1" max="50" required>
          @error('max_deliveries_per_courier')<div style="color:var(--danger);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
          <label>Delivery Timeout (hours)</label>
          <input type="number" name="delivery_timeout_hours" value="{{ $settings['delivery_timeout_hours'] ?? 24 }}" min="1" max="72" required>
          @error('delivery_timeout_hours')<div style="color:var(--danger);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
        </div>
      </div>
    </div>

  </div>
  <div style="margin-top:18px">
    <button class="btn btn-primary" type="submit">Save Settings</button>
  </div>
</form>
@endsection
