@extends('logistics.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names)

@push('head')
<link rel="stylesheet" href="{{ asset('css/logistics-dashboard.css') }}?v={{ filemtime(public_path('css/logistics-dashboard.css')) }}">
@endpush

@section('content')
<div class="logistics-dashboard">
  <section class="dashboard-welcome" aria-labelledby="company-overview">
    <div>
      <span class="dashboard-eyebrow">Logistics operations</span>
      <h2 id="company-overview">{{ auth()->user()->business_name }}</h2>
      <p>Manage pickups, hub transfers and deliveries across your company.</p>
    </div>
    <div class="dashboard-actions">
      <a href="{{ route('logistics.scan') }}" class="btn btn-primary"><x-admin-icon name="scan" /> Scan parcel</a>
      <a href="{{ route('logistics.transfers') }}" class="btn btn-outline"><x-admin-icon name="truck" /> Hub transfers</a>
    </div>
  </section>

  <section aria-label="Company shipment totals" class="dashboard-summary">
    @foreach([
      ['Total shipments', $total, 'All company shipments', 'box', 'neutral'],
      ['Active shipments', $active, 'Pickup, hub handling and delivery', 'truck', 'info'],
      ['Completed', $completed, 'Delivered or completed', 'check-circle', 'success'],
      ['Failed deliveries', $failed, 'Recorded delivery failures', 'flag', 'danger'],
    ] as [$label, $value, $description, $icon, $tone])
    <article class="dashboard-metric dashboard-metric-{{ $tone }}">
      <div class="dashboard-metric-head"><span>{{ $label }}</span><span class="dashboard-metric-icon"><x-admin-icon :name="$icon" /></span></div>
      <strong>{{ number_format($value) }}</strong>
      <p>{{ $description }}</p>
    </article>
    @endforeach
  </section>

  <section class="card dashboard-pipeline" aria-labelledby="pipeline-title">
    <div class="card-head"><div><h2 id="pipeline-title">Shipment pipeline</h2><p class="dashboard-caption">Current workload across your network</p></div><a href="{{ route('logistics.monitor') }}" class="btn btn-sm btn-outline">Open live monitor</a></div>
    <div class="dashboard-pipeline-grid">
      @foreach([
        ['Awaiting review', $pending, 'Pickup requests to review', 'logistics.requests', 'edit'],
        ['Awaiting pickup rider', $forVerify, 'Approved pickups without a rider', 'logistics.requests', 'users'],
        ['Parcels in transit', $inTransit, 'Picked up, at a hub or transferring', 'logistics.monitor', 'truck'],
        ['Sorted', $available, 'Parcels at the sorted stage', 'logistics.assignments', 'layers'],
        ['Deliveries in progress', $inProgress, 'Assigned or out for delivery', 'logistics.monitor', 'pin'],
      ] as [$label, $value, $description, $destination, $icon])
      <a class="dashboard-stage" href="{{ route($destination) }}">
        <span class="dashboard-stage-icon"><x-admin-icon :name="$icon" /></span>
        <strong>{{ number_format($value) }}</strong>
        <span class="dashboard-stage-label">{{ $label }}</span>
        <span class="dashboard-caption">{{ $description }}</span>
      </a>
      @endforeach
    </div>
  </section>

  <div class="dashboard-workspace">
    <section class="card dashboard-shipments" aria-labelledby="recent-shipments-title">
      <div class="card-head"><div><h2 id="recent-shipments-title">Recent shipments</h2><p class="dashboard-caption">Latest shipments in your company</p></div><a href="{{ route('logistics.monitor') }}" class="btn btn-sm btn-outline">View all</a></div>
      <div class="table-wrap">
        <table class="dtable">
          <thead><tr><th scope="col">Tracking number</th><th scope="col">Buyer</th><th scope="col">Courier</th><th scope="col">Status</th><th scope="col">Created</th></tr></thead>
          <tbody>
            @forelse($recent as $s)
            @php($buyerName = trim(($s->order?->buyer?->given_names ?? '') . ' ' . ($s->order?->buyer?->last_name ?? '')))
            @php($courierName = trim(($s->courier?->given_names ?? '') . ' ' . ($s->courier?->last_name ?? '')))
            <tr>
              <td class="mono dashboard-tracking">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
              <td>{{ $buyerName !== '' ? $buyerName : 'Unavailable' }}</td>
              <td>{{ $courierName !== '' ? $courierName : 'Unassigned' }}</td>
              <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $s->shipping_status)) }}</span></td>
              <td class="dashboard-date">{{ $s->created_at?->format('M d, Y') ?? '?' }}</td>
            </tr>
            @empty
            <tr><td colspan="5"><div class="empty dashboard-empty"><span class="dashboard-empty-icon"><x-admin-icon name="box" /></span><h3>No shipments yet</h3><p>Your company's latest shipments will appear here.</p></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <aside class="dashboard-resources" aria-labelledby="resources-title">
      <div class="dashboard-section-heading"><span class="dashboard-eyebrow">Personnel & resources</span><h2 id="resources-title">Keep your network moving</h2></div>
      <a class="dashboard-resource" href="{{ route('logistics.riders') }}"><span class="dashboard-resource-icon"><x-admin-icon name="users" /></span><span><strong>Rider Management</strong><span>{{ number_format($activeRiders) }} approved ? {{ number_format($pendingRiders) }} pending</span></span><span aria-hidden="true">?</span></a>
      <a class="dashboard-resource" href="{{ route('logistics.staff') }}"><span class="dashboard-resource-icon"><x-admin-icon name="account" /></span><span><strong>Hub Staff</strong><span>{{ number_format($pendingStaff) }} pending applications</span></span><span aria-hidden="true">?</span></a>
      <a class="dashboard-resource" href="{{ route('logistics.hubs') }}"><span class="dashboard-resource-icon"><x-admin-icon name="pin" /></span><span><strong>Coverage Hubs</strong><span>{{ number_format($municipalHubs) }} hubs ? manage coverage and hiring</span></span><span aria-hidden="true">?</span></a>
      <div class="dashboard-exceptions"><div><x-admin-icon name="flag" /><strong>{{ number_format($cancelled) }}</strong></div><p>Cancelled, failed or returned shipments</p><a href="{{ route('logistics.history') }}">Review delivery history <span aria-hidden="true">?</span></a></div>
    </aside>
  </div>

  <section class="card dashboard-activity" aria-labelledby="activity-title">
    <div class="card-head"><div><h2 id="activity-title">Recent activity</h2><p class="dashboard-caption">Latest recorded actions by your company team</p></div><a class="btn btn-sm btn-outline" href="{{ route('logistics.activity') }}">View logs</a></div>
    <div class="table-wrap"><table class="dtable"><thead><tr><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Time</th></tr></thead><tbody>
      @forelse($recentActivities as $entry)
      <tr><td>{{ $entry->username ?? 'Deleted account' }}</td><td>{{ $entry->action }}</td><td class="mono">{{ $entry->record_id }}</td><td class="dashboard-date">{{ $entry->created_at }}</td></tr>
      @empty
      <tr><td colspan="4"><div class="empty dashboard-empty"><h3>No recorded activity yet</h3><p>Recorded team actions will appear here.</p></div></td></tr>
      @endforelse
    </tbody></table></div>
  </section>
</div>
@endsection
