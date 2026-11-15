@extends('logistics.layout')
@section('title', 'Hub Staff')
@section('page-title', 'Hub Staff')
@section('page-sub', "Approve/disapprove hub staff applications, activate or suspend accounts")

@section('body-class','platform-accounts-page logistics-team-page')
@push('account-head')
<link rel="stylesheet" href="{{ asset('css/platform-accounts.css') }}?v={{ filemtime(public_path('css/platform-accounts.css')) }}">
<link rel="stylesheet" href="{{ asset('css/logistics-team.css') }}?v={{ filemtime(public_path('css/logistics-team.css')) }}">
@endpush
@section('workspace-actions')
<div class="rider-workspace-actions"><form method="GET" id="staff-search" class="rider-search-form" role="search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg><input name="search" value="{{ request('search') }}" placeholder="Search name, username or email" aria-label="Search hub staff" oninput="clearTimeout(this._staffSearchTimer);this._staffSearchTimer=setTimeout(()=>this.form.requestSubmit(),350)"></form><button type="button" class="btn btn-primary rider-create-button" data-modal-open="create-personnel">Create hub staff account</button></div>
@endsection
@section('content')
<section class="personnel-page hub-staff-page">



@include('logistics.partials.personnel-create-modal', ['creationRole'=>'hub_staff'])





<div class="card">

  <div class="card-pad">


    <div class="table-wrap">
      <table class="dtable">
        <thead>
          <tr><th>Applicant</th><th>Hub</th><th>Submitted</th><th>@include('logistics.partials.team-status-filter',['filterForm'=>'staff-search','teamRole'=>'hub staff','filterStatuses'=>['all'=>'All statuses','pending'=>'Pending','interview'=>'Interview','approved'=>'Approved','rejected'=>'Rejected','suspended'=>'Suspended']])</th><th>Manage</th></tr>
        </thead>
        <tbody>
          @forelse($staff as $member)
          <tr data-type="{{ $member->status }}">
            <td>
              <div class="cell-user">
                <x-user-avatar :user="$member" size="30" class="avatar-sm" />
                <div><strong>{{ $member->given_names }} {{ $member->last_name }}</strong><span>{{ $member->email }}</span></div>
              </div>
            </td>
            <td>
              @if($member->logisticsHub)
                {{ $member->logisticsHub->municipality }}, {{ $member->logisticsHub->province }}
              @else
                <span style="color:var(--danger)">No hub linked</span>
              @endif
            </td>
            <td class="mono">{{ $member->created_at?->format('M d, Y') ?? '—' }}</td>
            <td><span class="stamp stamp-{{ $member->status }}">{{ ucfirst($member->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button type="button" class="btn platform-detail-button" data-modal-open="staffModal-{{ $member->id }}" aria-label="Manage hub staff" title="Manage hub staff"><span aria-hidden="true">&#8599;</span></button>
              </div>
            </td>
          </tr>

          @push('staff-review-modals')
          <div class="modal-overlay" id="staffModal-{{ $member->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="account" /></span>
                  <div class="modal-head-copy">
                    <h3>Review Hub Staff Application</h3>
                    <p>{{ $member->given_names }} {{ $member->last_name }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
@include('admin.partials.review-summary',['user'=>$member])
@include('admin.partials.user-profile-body',['user'=>$member])
                @if($member->status === 'interview')
                <div class="section-card" style="background:var(--info-soft);border-color:var(--info-line)">
                  <div class="section-head"><span class="ic"><x-admin-icon name="account" /></span><span style="color:var(--info)">Awaiting Interview</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Date</div><div class="field-value">{{ $member->interview_scheduled_at?->format('M d, Y') ?? '—' }}</div></div>
                    <div><div class="field-label">Time</div><div class="field-value">{{ $member->interview_scheduled_at?->format('g:i A') ?? '—' }}</div></div>
                    <div class="full"><div class="field-label">Location</div><div class="field-value">{{ $member->interview_location ?: '—' }}</div></div>
                  </div>
                  <p style="margin:8px 0 0;font-size:12.5px;color:var(--info)">This applicant was emailed the details above and cannot log in yet. Once the interview has actually happened, click <strong>Approve</strong> below to enable their account.</p>
                </div>
                @endif

                @if(in_array($member->status, ['rejected', 'suspended'], true) && $member->status_reason)
                <div class="section-card" style="background:var(--danger-soft);border-color:var(--danger-line)">
                  <div class="section-head"><span class="ic"><x-admin-icon name="flag" /></span><span style="color:var(--danger)">Why {{ $member->status === 'rejected' ? 'Rejected' : 'Suspended' }}</span></div>
                  <p style="margin:0;font-size:12.5px;color:var(--danger)">{{ $member->status_reason }}</p>
                </div>
                @endif
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button><a class="btn btn-outline" href="{{ route('logistics.accounts.edit',$member->id) }}">Edit account & hub</a>
                @if($member->status === 'pending')
                <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('reject', '{{ route('logistics.staff.reject', $member->id) }}')">Reject</button>
                <button type="button" class="btn btn-success" onclick="openStaffFollowupModal('interviewModal-{{ $member->id }}')">Interview</button>
                @elseif($member->status === 'interview')
                <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('reject', '{{ route('logistics.staff.reject', $member->id) }}')">Reject</button>
                <button type="button" class="btn btn-success" onclick="openStaffFollowupModal('confirmInterviewModal-{{ $member->id }}')">Approve</button>
                @elseif($member->status === 'rejected')
                <span>{{ $member->can_resubmit ? 'Resubmission allowed' : 'Final rejection' }}</span>
                <button type="button" class="btn btn-outline" onclick="openReasonModal('reject', '{{ route('logistics.staff.reject', $member->id) }}')">Update rejection decision</button>
                @elseif($member->status === 'approved')
                <button type="button" class="btn btn-outline" onclick="openReasonModal('suspend', '{{ route('logistics.staff.suspend', $member->id) }}')">Suspend</button>
                @elseif($member->status === 'suspended')
                <form method="POST" enctype="multipart/form-data" action="{{ route('logistics.staff.activate', $member->id) }}" style="display:inline" data-confirm data-confirm-title="Activate this Hub Staff account?" data-confirm-message="This account will be enabled for Hub Staff access." data-confirm-action="Activate account" data-confirm-tone="success">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Activate</button>
                </form>
                @endif
              </div>
            </div>
          </div>

          @if($member->status === 'pending')
          <div class="modal-overlay" id="interviewModal-{{ $member->id }}">
            <div class="modal">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="account" /></span>
                  <div class="modal-head-copy">
                    <h3>Schedule Interview</h3>
                    <p>{{ $member->given_names }} {{ $member->last_name }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <form method="POST" enctype="multipart/form-data" action="{{ route('logistics.staff.approve', $member->id) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                  <p style="margin:0 0 14px;font-size:12.5px;color:var(--muted)">Set when and where to meet {{ $member->given_names }} — these details go straight into their invitation email.</p>
                  <div class="form-row">
                    <label>Date</label>
                    <input type="date" name="interview_date" min="{{ now()->toDateString() }}" value="{{ now()->addDay()->toDateString() }}" required>
                  </div>
                  <div class="form-row">
                    <label>Time</label>
                    <input type="time" name="interview_time" value="10:00" required>
                  </div>
                  <div class="form-row">
                    <label>Location <span class="hint">defaults to your own registered address — edit if it's somewhere else</span></label>
                    <textarea name="interview_location" rows="2" required>{{ collect([auth()->user()->house_no, auth()->user()->street, auth()->user()->barangay, auth()->user()->municipality, auth()->user()->province])->filter()->implode(', ') }}</textarea>
                  </div>
                </div>
                <div class="modal-foot">
                  <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                  <button type="submit" class="btn btn-success">Send Interview Invitation</button>
                </div>
              </form>
            </div>
          </div>
          @endif

          @if($member->status === 'interview')
          <div class="modal-overlay" id="confirmInterviewModal-{{ $member->id }}">
            <div class="modal">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="account" /></span>
                  <div class="modal-head-copy">
                    <h3>Confirm Interview</h3>
                    <p>{{ $member->given_names }} {{ $member->last_name }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
                <p style="margin:0 0 14px;font-size:13px;color:var(--text)">Confirm that the face-to-face interview below has actually happened and {{ $member->given_names }} should be approved.</p>
                <label style="display:flex;align-items:center;gap:12px;min-height:44px"><input type="checkbox" form="staff-approval-{{ $member->id }}" name="interview_completed" value="1" required> I attest that this applicant's interview has actually been completed.</label>
                <div class="detail-grid">
                  <div><div class="field-label">Date</div><div class="field-value">{{ $member->interview_scheduled_at?->format('M d, Y') ?? '—' }}</div></div>
                  <div><div class="field-label">Time</div><div class="field-value">{{ $member->interview_scheduled_at?->format('g:i A') ?? '—' }}</div></div>
                  <div class="full"><div class="field-label">Location</div><div class="field-value">{{ $member->interview_location ?: '—' }}</div></div>
                </div>
                <p style="margin:12px 0 0;font-size:12px;color:var(--muted)">This immediately enables their account — they'll be able to log in right after.</p>
              </div>
              <form method="POST" enctype="multipart/form-data" id="staff-approval-{{ $member->id }}" action="{{ route('logistics.staff.confirm', $member->id) }}">
                @csrf @method('PATCH')
                <div class="modal-foot">
                  <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                  <button type="submit" class="btn btn-success">Confirm Approval</button>
                </div>
              </form>
            </div>
          </div>
          @endif
          @endpush
          @empty
          <tr><td colspan="5"><div class="empty"><div class="ic"><x-admin-icon name="account" /></div><h3>No hub staff applications yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@stack('staff-review-modals')
@include('admin.partials.doc-lightbox')
@include('admin.partials.reason-modals')
{{ $staff->links() }}
<script>
function openStaffFollowupModal(id) {
  document.querySelectorAll('.modal-overlay.open').forEach(modal => modal.classList.remove('open'));
  const modal = document.getElementById(id);
  modal?.classList.add('open');
  modal?.querySelector('[data-modal-close]')?.focus();
}
</script>
<script src="{{ asset('js/registration-unsaved.js') }}?v={{ filemtime(public_path('js/registration-unsaved.js')) }}"></script>
<script src="{{ asset('js/license-number-input.js') }}?v={{ filemtime(public_path('js/license-number-input.js')) }}"></script>
</section>
@endsection
