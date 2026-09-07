@extends('logistics.layout')
@section('title', 'Coverage Hubs & Hiring')
@section('page-title', 'Coverage Hubs')
@section('page-sub', 'Manage your company’s coverage hubs, regional gateways, and hub staff hiring status')

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">
  {{ session('success') }}
</div>
@endif

{{-- Metric cards --}}
<div class="kpi-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px">
  <div class="card" style="padding:16px 18px;display:flex;align-items:center;gap:14px">
    <div style="width:42px;height:42px;border-radius:10px;background:rgba(217,70,143,0.1);color:var(--primary);display:flex;align-items:center;justify-content:center">
      <x-admin-icon name="pin" />
    </div>
    <div>
      <div style="font-size:22px;font-weight:800;color:var(--text)">{{ $totalHubs }}</div>
      <div style="font-size:12px;color:var(--muted)">Total Hubs</div>
    </div>
  </div>

  <div class="card" style="padding:16px 18px;display:flex;align-items:center;gap:14px">
    <div style="width:42px;height:42px;border-radius:10px;background:rgba(234,179,8,0.12);color:#ca8a04;display:flex;align-items:center;justify-content:center">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    </div>
    <div>
      <div style="font-size:22px;font-weight:800;color:var(--text)">{{ $regionalHubsCount }}</div>
      <div style="font-size:12px;color:var(--muted)">Regional Gateways</div>
    </div>
  </div>

  <div class="card" style="padding:16px 18px;display:flex;align-items:center;gap:14px">
    <div style="width:42px;height:42px;border-radius:10px;background:var(--success-soft);color:var(--success);display:flex;align-items:center;justify-content:center">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div>
      <div style="font-size:22px;font-weight:800;color:var(--success)">{{ $hiringHubsCount }}</div>
      <div style="font-size:12px;color:var(--muted)">Actively Hiring</div>
    </div>
  </div>

  <div class="card" style="padding:16px 18px;display:flex;align-items:center;gap:14px">
    <div style="width:42px;height:42px;border-radius:10px;background:#f3f4f6;color:#6b7280;display:flex;align-items:center;justify-content:center">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
    </div>
    <div>
      <div style="font-size:22px;font-weight:800;color:#6b7280">{{ $notHiringHubsCount }}</div>
      <div style="font-size:12px;color:var(--muted)">Not Hiring</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head" style="flex-wrap:wrap;gap:12px">
    <div>
      <h2>Company Hubs List</h2>
      <p style="margin-top:2px;font-size:12px;color:var(--muted)">Toggle hiring status on each hub to control whether staff applicants can register for it.</p>
    </div>

    {{-- Search & Filter --}}
    <form method="GET" action="{{ route('logistics.hubs') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="text" name="search" value="{{ $search }}" placeholder="Search province or city…"
        style="padding:6px 12px;border:1px solid var(--border);border-radius:8px;font-size:12.5px;min-width:180px">
      @if($hiringFilter)
        <input type="hidden" name="hiring" value="{{ $hiringFilter }}">
      @endif
      <button type="submit" class="btn btn-sm btn-outline">Search</button>
      @if($search || $hiringFilter)
        <a href="{{ route('logistics.hubs') }}" class="btn btn-sm" style="color:var(--muted);text-decoration:none">Clear</a>
      @endif
    </form>
  </div>

  <div class="card-pad">
    {{-- Tabs --}}
    <div data-tabs style="margin-bottom:18px">
      <a class="tab {{ !$hiringFilter ? 'active' : '' }}" href="{{ route('logistics.hubs', array_filter(['search' => $search])) }}">
        All Hubs <span class="tab-count">{{ $totalHubs }}</span>
      </a>
      <a class="tab {{ $hiringFilter === 'hiring' ? 'active' : '' }}" href="{{ route('logistics.hubs', array_filter(['search' => $search, 'hiring' => 'hiring'])) }}">
        Hiring <span class="tab-count">{{ $hiringHubsCount }}</span>
      </a>
      <a class="tab {{ $hiringFilter === 'not_hiring' ? 'active' : '' }}" href="{{ route('logistics.hubs', array_filter(['search' => $search, 'hiring' => 'not_hiring'])) }}">
        Not Hiring <span class="tab-count">{{ $notHiringHubsCount }}</span>
      </a>
    </div>

    @forelse($groupedHubs as $province => $hubs)
    <div style="margin-bottom:24px;border:1px solid var(--border);border-radius:12px;overflow:hidden">
      {{-- Province Header --}}
      <div style="background:#f8fafc;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:8px">
          <strong style="font-size:14px;color:var(--text)">{{ $province }}</strong>
          <span style="font-size:11px;font-weight:700;color:var(--primary);background:rgba(217,70,143,0.08);padding:2px 8px;border-radius:999px">
            {{ $hubs->count() }} {{ \Illuminate\Support\Str::plural('hub', $hubs->count()) }}
          </span>
        </div>
        <div style="font-size:11px;color:var(--muted)">
          {{ $hubs->where('is_hiring', true)->count() }} currently hiring
        </div>
      </div>

      {{-- Hubs Table in this Province --}}
      <div class="table-wrap" style="margin:0">
        <table class="dtable" style="margin:0">
          <thead>
            <tr style="background:#fff">
              <th style="padding-left:18px">Municipality / City</th>
              <th>Hub Type</th>
              <th>Active Staff</th>
              <th>Hiring Status</th>
              <th style="text-align:right;padding-right:18px">Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach($hubs as $hub)
            <tr id="hubRow-{{ $hub->id }}">
              <td style="padding-left:18px">
                <div style="display:flex;align-items:center;gap:8px">
                  <span style="font-weight:700;color:var(--text);font-size:13px">{{ $hub->municipality }}</span>
                </div>
              </td>
              <td>
                @if($hub->is_regional_hub)
                  <span class="stamp stamp-active" style="background:#fef9c3;color:#854d0e;border-color:#fde047;font-size:10.5px">★ Regional Gateway</span>
                @else
                  <span style="font-size:12px;color:var(--muted)">Local Hub</span>
                @endif
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:6px">
                  <span class="mono" style="font-weight:700;color:var(--text)">{{ $staffPerHub[$hub->id] ?? 0 }}</span>
                  <span style="font-size:11px;color:var(--muted)">staff</span>
                </div>
              </td>
              <td>
                <span id="hiringBadge-{{ $hub->id }}" class="stamp {{ $hub->is_hiring ? 'stamp-approved' : 'stamp-suspended' }}"
                  style="font-size:11px;display:inline-flex;align-items:center;gap:4px">
                  <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block"></span>
                  {{ $hub->is_hiring ? 'Hiring' : 'Not Hiring' }}
                </span>
              </td>
              <td style="text-align:right;padding-right:18px">
                <button type="button"
                  id="toggleBtn-{{ $hub->id }}"
                  class="btn btn-sm {{ $hub->is_hiring ? 'btn-outline' : 'btn-primary' }}"
                  onclick="toggleHubHiring({{ $hub->id }})"
                  style="min-width:110px">
                  {{ $hub->is_hiring ? 'Close Hiring' : 'Open Hiring' }}
                </button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @empty
    <div style="text-align:center;padding:48px 16px;color:var(--muted)">
      <x-admin-icon name="pin" style="width:36px;height:36px;opacity:0.4;margin-bottom:8px" />
      <p style="font-size:14px;margin:0">No hubs found matching your query.</p>
    </div>
    @endforelse
  </div>
</div>

<script>
async function toggleHubHiring(hubId) {
  const btn = document.getElementById('toggleBtn-' + hubId);
  const badge = document.getElementById('hiringBadge-' + hubId);
  if (!btn) return;

  const prevText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Updating…';

  try {
    const res = await fetch(`{{ url('/logistics/hubs') }}/${hubId}/toggle-hiring`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    const data = await res.json();
    if (data.success) {
      if (data.is_hiring) {
        badge.className = 'stamp stamp-approved';
        badge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block"></span> Hiring';
        btn.className = 'btn btn-sm btn-outline';
        btn.textContent = 'Close Hiring';
      } else {
        badge.className = 'stamp stamp-suspended';
        badge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block"></span> Not Hiring';
        btn.className = 'btn btn-sm btn-primary';
        btn.textContent = 'Open Hiring';
      }
    } else {
      alert(data.message || 'Could not update status.');
      btn.textContent = prevText;
    }
  } catch (err) {
    alert('Network error. Please try again.');
    btn.textContent = prevText;
  } finally {
    btn.disabled = false;
  }
}
</script>
@endsection

