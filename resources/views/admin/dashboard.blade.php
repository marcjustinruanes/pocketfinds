@extends('admin.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->given_names)

@section('content')
@php
  $buyers   = \App\Models\User::where('account_type','buyer')->where('is_admin',false)->count();
  $sellers  = \App\Models\User::where('account_type','seller')->where('is_admin',false)->count();
  $riders   = \App\Models\User::where('account_type','rider')->where('is_admin',false)->count();
  $approved = \App\Models\User::where('status','approved')->where('is_admin',false)->count();

  $breakdownTotal = max($buyers + $sellers + $riders, 1);
  $buyerPct  = round($buyers  / $breakdownTotal * 100);
  $sellerPct = round($sellers / $breakdownTotal * 100);
  $riderPct  = max(100 - $buyerPct - $sellerPct, 0);
  $donutGradient = ($buyers + $sellers + $riders) > 0
      ? "conic-gradient(var(--info) 0 {$buyerPct}%, var(--pink) {$buyerPct}% ".($buyerPct+$sellerPct)."%, var(--warning) ".($buyerPct+$sellerPct)."% 100%)"
      : "conic-gradient(var(--border) 0 100%)";
@endphp

@php
  $chartW = 460; $chartH = 170; $padTop = 6; $padBottom = 4;
  $plotH  = $chartH - $padTop - $padBottom;
  $peak   = $salesSeries->max('total');
  // round the axis ceiling up to a "nice" number (nearest power-of-ten step)
  $niceMax  = $peak > 0 ? ceil($peak / pow(10, floor(log10($peak)))) * pow(10, floor(log10($peak))) : 10;
  $count    = $salesSeries->count();
  $stepX    = $count > 1 ? $chartW / ($count - 1) : 0;
  $points   = $salesSeries->values()->map(fn ($d, $i) => [
      round($i * $stepX, 2),
      round($padTop + $plotH - ($d['total'] / $niceMax * $plotH), 2),
  ]);
  $linePath = 'M' . $points->map(fn ($p) => $p[0].','.$p[1])->implode(' L');
  $areaPath = $linePath . " L{$chartW},{$chartH} L0,{$chartH} Z";
  $yTicks   = [$niceMax, $niceMax * 0.5, 0];
@endphp

<div class="dash-grid" style="grid-template-columns:minmax(0,1.9fr) minmax(280px,330px)">
  <div class="stack">
    <div class="kpi-grid" style="margin-bottom:0">
      <div class="kpi tone-info">
        <div class="label">Total Users</div>
        <div class="value">{{ number_format($totalUsers) }}</div>
        <div class="delta up">All accounts</div>
      </div>
      <div class="kpi tone-warning">
        <div class="label">Pending Registrations</div>
        <div class="value">{{ $pendingCount }}</div>
        <div class="delta {{ $pendingCount > 0 ? 'down' : 'up' }}">Awaiting review</div>
      </div>
      <div class="kpi tone-danger">
        <div class="label">Open Disputes</div>
        <div class="value">{{ $openDisputes }}</div>
        <div class="delta {{ $openDisputes > 0 ? 'down' : 'up' }}">Active cases</div>
      </div>
      <div class="kpi tone-success">
        <div class="label">Approved Users</div>
        <div class="value">{{ $approved }}</div>
        <div class="delta up">Active accounts</div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div><h2>Sales Performance</h2><p>₱{{ number_format($salesTotal, 2) }} total this period</p></div>
        <select class="chip-select" onchange="location.href='{{ route('admin.dashboard') }}?days=' + this.value">
          @foreach([7 => '7 days', 14 => '14 days', 30 => '30 days', 90 => '90 days'] as $val => $label)
          <option value="{{ $val }}" {{ $salesDays === $val ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="card-pad">
        @if($salesTotal <= 0)
        <div class="empty" style="padding:34px 20px">
          <div class="ic"><x-admin-icon name="chart" /></div>
          <h3>No sales yet</h3>
          <p>Completed buyer orders will show up here as a trend.</p>
        </div>
        @else
        <div class="sales-chart">
          <div class="sales-chart-yaxis">
            @foreach($yTicks as $tick)
            <span>₱{{ $tick >= 1000 ? number_format($tick / 1000, $tick % 1000 ? 1 : 0).'k' : number_format($tick) }}</span>
            @endforeach
          </div>
          <div class="sales-chart-plot">
            <svg class="sales-chart-svg" viewBox="0 0 {{ $chartW }} {{ $chartH }}" preserveAspectRatio="none" role="img" aria-label="Sales total per day for the selected period">
              <defs>
                <linearGradient id="salesFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" style="stop-color:var(--pink);stop-opacity:.32" />
                  <stop offset="100%" style="stop-color:var(--pink);stop-opacity:0" />
                </linearGradient>
              </defs>
              <line class="grid-line" x1="0" y1="{{ $padTop }}" x2="{{ $chartW }}" y2="{{ $padTop }}" />
              <line class="grid-line" x1="0" y1="{{ $padTop + $plotH / 2 }}" x2="{{ $chartW }}" y2="{{ $padTop + $plotH / 2 }}" />
              <line class="grid-line" x1="0" y1="{{ $padTop + $plotH }}" x2="{{ $chartW }}" y2="{{ $padTop + $plotH }}" />
              <path d="{{ $areaPath }}" fill="url(#salesFill)" stroke="none" />
              <path d="{{ $linePath }}" fill="none" stroke="var(--pink)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
            </svg>
            <div class="sales-chart-axis">
              <span>{{ $salesSeries->first()['date']->format('M j') }}</span>
              <span>{{ $salesSeries->get((int) floor(($count - 1) / 2))['date']->format('M j') }}</span>
              <span>Today</span>
            </div>
          </div>
        </div>
        @endif
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div><h2>Recent Registrations</h2><p>Latest 5 submissions</p></div>
        <a href="{{ route('admin.registrations') }}" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="table-wrap">
        <table class="dtable">
          <thead>
            <tr><th>Applicant</th><th>Type</th><th>Auth</th><th>Date</th><th>Status</th></tr>
          </thead>
          <tbody>
            @forelse($recentUsers as $user)
            <tr class="rail-row rail-{{ $user->status }}">
              <td>
                <div class="cell-user">
                  <x-user-avatar :user="$user" size="30" class="avatar-sm" />
                  <div><strong>{{ $user->given_names }} {{ $user->last_name }}</strong><span>{{ $user->email }}</span></div>
                </div>
              </td>
              <td><span class="stamp stamp-{{ $user->account_type }}">{{ ucfirst($user->account_type) }}</span></td>
              <td class="mono" style="font-size:11px">{{ ucfirst($user->auth_method) }}</td>
              <td class="mono">{{ $user->created_at->format('M d, Y') }}</td>
              <td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="5"><div class="empty"><div class="ic"><x-admin-icon name="users" /></div><h3>No users yet</h3></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h2>User Breakdown</h2><p>By account type</p></div></div>
      <div class="card-pad">
        <div class="donut-wrap donut-wrap-sm">
          <div class="donut-chart donut-chart-sm" style="background:{{ $donutGradient }}">
            <div class="donut-hole donut-hole-sm">
              <strong>{{ number_format($buyers + $sellers + $riders) }}</strong>
              <span>Users</span>
            </div>
          </div>
          <div class="donut-legend">
            <div class="donut-legend-row">
              <span class="donut-legend-dot" style="background:var(--info)"></span>
              <span class="name">Buyers</span>
              <span class="count">{{ $buyers }}</span>
              <span class="pct">{{ $buyerPct }}%</span>
            </div>
            <div class="donut-legend-row">
              <span class="donut-legend-dot" style="background:var(--pink)"></span>
              <span class="name">Sellers</span>
              <span class="count">{{ $sellers }}</span>
              <span class="pct">{{ $sellerPct }}%</span>
            </div>
            <div class="donut-legend-row">
              <span class="donut-legend-dot" style="background:var(--warning)"></span>
              <span class="name">Riders</span>
              <span class="count">{{ $riders }}</span>
              <span class="pct">{{ $riderPct }}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Quick Actions</h2><p>Jump to what needs review</p></div></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:9px">
        <a href="{{ route('admin.registrations') }}" class="action-tile tone-warning">
          <span class="ic"><x-admin-icon name="edit" /></span>
          <span class="copy"><strong>Review Registrations</strong></span>
          @if($pendingCount > 0)<span class="badge-count">{{ $pendingCount }}</span>@endif
          <span class="chev"><x-admin-icon name="chevron-right" /></span>
        </a>
        <a href="{{ route('admin.complaints') }}" class="action-tile tone-danger">
          <span class="ic"><x-admin-icon name="flag" /></span>
          <span class="copy"><strong>Open Disputes</strong></span>
          @if($openDisputes > 0)<span class="badge-count" style="background:var(--danger)">{{ $openDisputes }}</span>@endif
          <span class="chev"><x-admin-icon name="chevron-right" /></span>
        </a>
        <a href="{{ route('admin.doc-requests') }}" class="action-tile tone-info">
          <span class="ic"><x-admin-icon name="file" /></span>
          <span class="copy"><strong>Document Requests</strong></span>
          @if(!empty($pendingDocs))<span class="badge-count" style="background:var(--info)">{{ $pendingDocs }}</span>@endif
          <span class="chev"><x-admin-icon name="chevron-right" /></span>
        </a>
        <a href="{{ route('admin.products') }}" class="action-tile tone-success">
          <span class="ic"><x-admin-icon name="bag" /></span>
          <span class="copy"><strong>Product Reviews</strong></span>
          <span class="chev"><x-admin-icon name="chevron-right" /></span>
        </a>
        <a href="{{ route('admin.reports') }}" class="action-tile">
          <span class="ic"><x-admin-icon name="chart" /></span>
          <span class="copy"><strong>View Reports</strong></span>
          <span class="chev"><x-admin-icon name="chevron-right" /></span>
        </a>
      </div>
    </div>

    @if($latestAnnouncement)
    <div class="card">
      <div class="card-head">
        <div><h2>Latest Announcement</h2><p>Posted {{ $latestAnnouncement->created_at->format('M d, Y') }}</p></div>
        <a href="{{ route('admin.announcements') }}" class="btn btn-sm btn-outline">Manage</a>
      </div>
      <div class="card-pad">
        <div style="display:flex;align-items:flex-start;gap:12px">
          <span class="kpi-icon" style="margin-bottom:0"><x-admin-icon name="megaphone" /></span>
          <div style="min-width:0">
            <div style="font-weight:700;font-size:13.5px">{{ $latestAnnouncement->title }}</div>
            <p style="margin:4px 0 0;font-size:12.5px;color:var(--muted);line-height:1.55">{{ Str::limit($latestAnnouncement->body, 160) }}</p>
            <span class="stamp stamp-active" style="margin-top:8px">{{ ucfirst($latestAnnouncement->audience) }}</span>
          </div>
        </div>
      </div>
    </div>
    @endif
  </div>
</div>
@endsection
