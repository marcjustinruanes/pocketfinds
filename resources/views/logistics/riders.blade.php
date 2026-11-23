@extends('logistics.layout')
@section('title', 'Rider Management')
@section('page-title', 'Rider Management')
@section('page-sub', 'Review courier applications, manage rider access and coverage')

@section('workspace-actions')
<div class="rider-workspace-actions">
  <form method="GET" class="rider-search-form" id="rider-search" role="search">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg>
    <input name="search" value="{{ request('search') }}" placeholder="Search name, username, or email" aria-label="Search riders" oninput="clearTimeout(this._riderSearchTimer); this._riderSearchTimer = setTimeout(() => this.form.requestSubmit(), 350)">

  </form>
  <button type="button" class="btn btn-primary rider-create-button" data-modal-open="create-personnel">Create rider account</button>
</div>
@endsection

@section('body-class','platform-accounts-page logistics-team-page')
@push('account-head')
<link rel="stylesheet" href="{{ asset('css/platform-accounts.css') }}?v={{ filemtime(public_path('css/platform-accounts.css')) }}">
<link rel="stylesheet" href="{{ asset('css/logistics-team.css') }}?v={{ filemtime(public_path('css/logistics-team.css')) }}">
@endpush
@section('content')
<section class="personnel-page rider-management-page">

@include('logistics.partials.personnel-create-modal',['creationRole'=>'rider'])

<div class="card rider-applications-card">
  <div class="card-pad rider-applications-body">


    <div class="table-wrap">
      <table class="dtable">
        <thead>
          <tr><th>Applicant</th><th>Vehicle &amp; plate</th><th>Submitted</th><th>@include('logistics.partials.team-status-filter',['filterForm'=>'rider-search','teamRole'=>'riders','filterStatuses'=>['all'=>'All statuses','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','suspended'=>'Suspended']])</th><th>Manage</th></tr>
        </thead>
        <tbody>
          @forelse($riders as $rider)
          @php($activationPending = app(\App\Services\RiderActivation::class)->pending($rider))
          <tr>
            <td>
              <div class="cell-user">
                <x-user-avatar :user="$rider" size="30" class="avatar-sm" />
                <div><strong>{{ $rider->given_names }} {{ $rider->last_name }}</strong><span>{{ $rider->email }}</span></div>
              </div>
            </td>
            <td>{{ $rider->vehicle_type ? \Illuminate\Support\Str::title(str_replace('_', ' ', $rider->vehicle_type)) : '—' }}<div class="mono" style="font-size:11px;color:var(--muted)">{{ $rider->vehicle_ownership === 'company' ? 'Company vehicle' : ($rider->plate_number ?: 'Plate not provided') }}</div><div style="font-size:11px;color:var(--muted)">Service Municipality: {{ $rider->logisticsHub?->municipality ?? 'Not assigned' }}</div></td>
            <td class="mono">{{ $rider->created_at?->format('M d, Y') ?? '—' }}</td>
            <td><span class="stamp stamp-{{ $rider->status }}">{{ $activationPending && $rider->status === 'pending' ? 'Pending activation' : ucfirst($rider->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button type="button" class="btn platform-detail-button" data-modal-open="riderModal-{{ $rider->id }}" aria-label="Manage rider" title="Manage rider"><span aria-hidden="true">&#8599;</span></button>
              </div>
            </td>
          </tr>

          @push('rider-review-modals')
          <div class="modal-overlay" id="riderModal-{{ $rider->id }}">
            <div class="modal modal-lg rider-application-modal">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="truck" /></span>
                  <div class="modal-head-copy">
                    <h3>Review Courier Application</h3>
                    <p>{{ $rider->given_names }} {{ $rider->last_name }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
@include('admin.partials.review-summary',['user'=>$rider])
@include('admin.partials.user-profile-body',['user'=>$rider])
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button><a class="btn btn-outline" href="{{ route('logistics.accounts.edit',$rider->id) }}">Edit account & hub</a>
                @if($rider->status === 'pending')
                  <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('reject', '{{ route('logistics.riders.reject', $rider->id) }}')">Reject</button>
                  @if($activationPending)
                  <form method="POST" action="{{ route('logistics.riders.activation.resend', $rider->id) }}">@csrf<button class="btn btn-primary" type="submit">Resend activation email</button></form>
                  @else
                  <form method="POST" enctype="multipart/form-data" action="{{ route('logistics.riders.approve', $rider->id) }}" style="display:inline" data-confirm data-confirm-title="Approve this Rider?" data-confirm-message="This will approve the Rider account and enable the next activation step." data-confirm-action="Approve Rider" data-confirm-tone="success">
                    @csrf @method('PATCH')
                    <button class="btn btn-success" type="submit">Approve</button>
                  </form>
                  @endif
                @elseif($rider->status === 'rejected')
                  <span>{{ $rider->can_resubmit ? 'Resubmission allowed' : 'Final rejection' }}</span>
                  <button type="button" class="btn btn-outline" onclick="openReasonModal('reject', '{{ route('logistics.riders.reject', $rider->id) }}')">Update rejection decision</button>
                  @if($activationPending && $rider->can_resubmit)<form method="POST" action="{{ route('logistics.riders.activation.resend', $rider->id) }}">@csrf<button class="btn btn-primary" type="submit">Resend correction link</button></form>@endif
                @elseif($rider->status === 'approved')
                  <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('suspend', '{{ route('logistics.riders.suspend', $rider->id) }}')">Suspend</button>
                @elseif($rider->status === 'suspended')
                  <button type="button" class="btn btn-success" onclick="openReasonModal('activate', '{{ route('logistics.riders.activate', $rider->id) }}')">Reactivate</button>
                @endif
              </div>
            </div>
          </div>
          @endpush
          @empty
          <tr><td colspan="6"><div class="empty"><div class="ic"><x-admin-icon name="truck" /></div><h3>No {{ $status }} courier applications</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@stack('rider-review-modals')
@include('admin.partials.doc-lightbox')
@include('admin.partials.reason-modals', ['reasonModalTypes'=>['reject','suspend','activate']])
{{ $riders->links() }}
<script src="{{ asset('js/registration-unsaved.js') }}?v={{ filemtime(public_path('js/registration-unsaved.js')) }}"></script>
<script src="{{ asset('js/license-number-input.js') }}?v={{ filemtime(public_path('js/license-number-input.js')) }}"></script>
</section>
@endsection
