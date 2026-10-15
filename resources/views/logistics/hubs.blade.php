@extends('logistics.layout')
@section('title', 'Coverage Hubs & Hiring')
@section('page-title', 'Coverage Hubs & Hiring')
@section('page-sub', 'Manage your company hubs and applications for Riders and Hub Staff')

@section('content')
<style>
.coverage-page{display:grid;gap:20px}.coverage-header,.coverage-hub-header,.coverage-role-header{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}.coverage-actions{display:flex;gap:10px;flex-wrap:wrap}.coverage-page h2{font-size:17px}.coverage-page h3{font-size:16px}.coverage-page p{font-size:13px;line-height:1.6;color:var(--muted)}.coverage-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.coverage-stat{padding:18px}.coverage-stat strong{display:block;font-size:26px;color:var(--text);margin-bottom:5px}.coverage-stat span{font-size:12px;color:var(--muted)}.coverage-tools .logistics-toolbar{margin:0}.coverage-tools .tabs{margin-top:16px;flex-wrap:wrap}.coverage-hub{overflow:hidden;scroll-margin-top:24px}.coverage-hub-header{padding:20px;border-bottom:1px solid var(--border)}.coverage-hub-header p{margin-top:4px}.coverage-tags{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.coverage-roles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;padding:20px}.coverage-role{border:1px solid var(--border);border-radius:12px;padding:18px;min-width:0;background:var(--surface)}.coverage-role-header{margin-bottom:16px}.coverage-role-header h4{font-size:15px}.coverage-counts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:12px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);margin-bottom:14px}.coverage-counts dt{font-size:11px;color:var(--muted);margin-bottom:5px}.coverage-counts dd{font-size:18px;font-weight:700;color:var(--text);margin:0}.coverage-hiring-form{display:grid;gap:14px}.coverage-hiring-form label{display:grid;gap:6px;font-size:12px;color:var(--text);min-width:0}.coverage-hiring-form input,.coverage-hiring-form select{width:100%;min-width:0;box-sizing:border-box;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font:inherit}.coverage-hiring-form input:focus,.coverage-hiring-form select:focus{outline:2px solid var(--primary);outline-offset:2px}.coverage-hiring-form small{font-size:11px;color:var(--muted);line-height:1.5}.coverage-hiring-form button{width:100%;justify-content:center}.coverage-notice{padding:10px 12px;border:1px solid var(--border);border-radius:8px;margin-bottom:14px;font-size:12px;line-height:1.5;color:var(--muted)}.coverage-details{border-top:1px solid var(--border);padding:16px 20px}.coverage-details summary{cursor:pointer;font-size:13px;font-weight:600;color:var(--text);padding:4px 0}.coverage-empty{padding:36px 24px;text-align:center}.coverage-empty h3{margin:12px 0 8px}.coverage-empty .coverage-actions{justify-content:center;margin-top:18px}.coverage-page .logistics-pagination{margin:0}.coverage-section-heading{scroll-margin-top:24px}.coverage-create{scroll-margin-top:24px}.coverage-create>p{margin-top:6px}
@media(max-width:1050px){.coverage-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.coverage-roles{grid-template-columns:1fr}}
@media(max-width:560px){.coverage-header .coverage-actions{width:100%}.coverage-header .coverage-actions .btn{flex:1;justify-content:center}.coverage-stats{gap:10px}.coverage-stat{padding:14px}.coverage-stat strong{font-size:23px}.coverage-hub-header,.coverage-roles{padding:16px}.coverage-role{padding:14px}.coverage-counts dd{font-size:16px}.coverage-tools .logistics-toolbar>*{width:100%}.coverage-details{padding:14px 16px}}
</style>
<div class="coverage-page">
  <div class="coverage-header">
    <div><h2>{{ auth()->user()->business_name }}</h2><p>Choose a hub, set the role to Open, then save its hiring settings.</p></div>
    <div class="coverage-actions"><a href="#hiring-management" class="btn btn-primary">Manage Hiring</a><a href="#add-hub" class="btn btn-outline">Add Hub</a></div>
  </div>

  @if(session('success'))<div class="logistics-alert" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="logistics-alert" role="alert"><strong>Changes could not be saved</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

  <div class="coverage-stats">
    @foreach([[$totalHubs,'Company hubs'],[$regionalHubsCount,'Regional gateways'],[$riderHiringCount,'Hubs accepting Riders'],[$staffHiringCount,'Hubs accepting Hub Staff']] as [$value,$label])
    <div class="card coverage-stat"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
    @endforeach
  </div>

  <div class="card card-pad coverage-tools">
    <form method="GET" action="{{ route('logistics.hubs') }}" class="logistics-toolbar">
      <input type="search" name="search" value="{{ $search }}" placeholder="Search province or municipality" aria-label="Search company hubs">
      @if($hiringFilter)<input type="hidden" name="hiring" value="{{ $hiringFilter }}">@endif
      <button type="submit" class="btn btn-outline">Search</button>
      @if($search || $hiringFilter)<a href="{{ route('logistics.hubs') }}" class="btn btn-outline">Clear filters</a>@endif
    </form>
    <nav class="tabs" aria-label="Hub hiring filters">
      <a class="tab {{ !$hiringFilter ? 'active' : '' }}" href="{{ route('logistics.hubs', array_filter(['search'=>$search])) }}">All hubs <span class="tab-count">{{ $totalHubs }}</span></a>
      <a class="tab {{ $hiringFilter === 'hiring' ? 'active' : '' }}" href="{{ route('logistics.hubs', array_filter(['search'=>$search,'hiring'=>'hiring'])) }}">Accepting applications <span class="tab-count">{{ $hiringHubsCount }}</span></a>
      <a class="tab {{ $hiringFilter === 'not_hiring' ? 'active' : '' }}" href="{{ route('logistics.hubs', array_filter(['search'=>$search,'hiring'=>'not_hiring'])) }}">Not accepting <span class="tab-count">{{ $notHiringHubsCount }}</span></a>
    </nav>
  </div>

  <div id="hiring-management" class="coverage-section-heading"><h2>Hiring Management</h2><p>Rider and Hub Staff hiring are independent. All closing times use {{ config('app.timezone') }}.</p></div>
  @forelse($allHubs as $hub)
  <article id="hiring-hub-{{ $hub->id }}" class="card coverage-hub">
    <header class="coverage-hub-header">
      <div><h3>{{ $hub->name ?: $hub->municipality }}</h3><p>{{ $hub->municipality }}, {{ $hub->province }}</p></div>
      <div class="coverage-tags"><span class="stamp {{ $hub->is_operational ? 'stamp-approved' : 'stamp-suspended' }}">{{ $hub->is_operational ? 'Operational' : 'Hub closed' }}</span><span class="stamp">{{ $hub->is_regional_hub ? 'Regional gateway' : 'Municipal hub' }}</span></div>
    </header>
    <div class="coverage-roles">
      @foreach(['rider'=>'Rider','staff'=>'Hub Staff'] as $prefix=>$label)
      @php($state = $hub->{$prefix.'_hiring_state'})
      @php($fieldId = 'hub-'.$hub->id.'-'.$prefix)
      <section class="coverage-role" aria-labelledby="{{ $fieldId }}-heading">
        <div class="coverage-role-header"><h4 id="{{ $fieldId }}-heading">{{ $label }} Hiring</h4><span class="stamp {{ $state['eligible'] ? 'stamp-approved' : 'stamp-suspended' }}">{{ $state['status'] }}</span></div>
        <dl class="coverage-counts"><div><dt>Approved</dt><dd>{{ $state['count'] }}</dd></div><div><dt>Hire limit</dt><dd>{{ $state['limit'] === null ? 'Unlimited' : $state['limit'] }}</dd></div><div><dt>Vacancies</dt><dd>{{ $state['remaining'] === null ? 'Unlimited' : $state['remaining'] }}</dd></div></dl>
        @if(!$hub->is_operational)<div class="coverage-notice">This hub is closed. New applications are blocked for both roles.</div>@elseif($state['over_capacity'])<div class="coverage-notice">Over capacity. Existing personnel remain assigned; additional approvals are blocked.</div>@elseif($state['status'] === 'Full')<div class="coverage-notice">The approved-hire limit has been reached. New applications and approvals are blocked.</div>@elseif($state['status'] === 'Deadline passed')<div class="coverage-notice">The closing deadline has passed. Clear or change it to accept new applications.</div>@endif
        <form method="POST" action="{{ route('logistics.hubs.toggle-hiring', $hub->id) }}" class="coverage-hiring-form">
          @csrf @method('PATCH')
          <input type="hidden" name="role" value="{{ $prefix === 'rider' ? 'rider' : 'hub_staff' }}">
          <input type="hidden" name="revision" value="{{ $hub->hiring_revision }}">
          <label for="{{ $fieldId }}-open">Accept new {{ $label === 'Rider' ? 'Rider' : 'Hub Staff' }} applications<select id="{{ $fieldId }}-open" name="is_hiring"><option value="1" @selected($hub->{$prefix.'_hiring_open'} ?? $hub->is_hiring)>Open</option><option value="0" @selected(!($hub->{$prefix.'_hiring_open'} ?? $hub->is_hiring))>Closed</option></select></label>
          <label for="{{ $fieldId }}-limit">Maximum approved hires<input id="{{ $fieldId }}-limit" type="number" name="limit" min="0" max="100000" value="{{ $hub->{$prefix.'_hire_limit'} }}" placeholder="Unlimited" aria-describedby="{{ $fieldId }}-limit-help"><small id="{{ $fieldId }}-limit-help">Leave blank for unlimited hires. Zero means no vacancies.</small></label>
          <label for="{{ $fieldId }}-deadline">Closing date and time ({{ config('app.timezone') }})<input id="{{ $fieldId }}-deadline" type="datetime-local" name="closes_at" value="{{ $hub->{$prefix.'_hiring_closes_at'}?->format('Y-m-d\TH:i') }}" aria-describedby="{{ $fieldId }}-deadline-help"><small id="{{ $fieldId }}-deadline-help">Optional. Clear this field to remove the deadline.</small></label>
          <button type="submit" class="btn btn-primary">Save {{ $label }} hiring</button>
        </form>
      </section>
      @endforeach
    </div>
    <details class="coverage-details"><summary>Hub details and operational settings</summary><p>{{ $hub->staff_count }} assigned Hub Staff accounts; {{ $hub->riders_count }} assigned Rider accounts, across all statuses.</p>
      <form method="POST" action="{{ route('logistics.hubs.update',$hub->id) }}" onsubmit="return confirm('Update this hub?')">@csrf @method('PATCH')
        @include('logistics.partials.hub-address-fields', ['addressHub'=>$hub])
        <button type="submit" class="btn btn-outline" data-hub-save disabled>Save hub details</button>
      </form>
    </details>
  </article>
  @empty
  <div class="card coverage-empty"><x-admin-icon name="pin" style="width:32px;height:32px;color:var(--muted)" />
    @if($totalHubs === 0)<h3>Add your first company hub</h3><p>Hiring settings become available once your company has a hub. Create one below, then open Rider or Hub Staff hiring.</p><div class="coverage-actions"><a href="#add-hub" class="btn btn-primary">Add Hub</a></div>
    @else<h3>No hubs match this view</h3><p>Clear the filters or select another page to see your company hubs.</p><div class="coverage-actions"><a href="{{ route('logistics.hubs') }}" class="btn btn-outline">Show all company hubs</a></div>@endif
  </div>
  @endforelse
  <div class="logistics-pagination">{{ $hubPages->links() }}</div>
  @include('logistics.partials.hub-management')
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/hub-address.js') }}" defer></script>
@endpush
