@extends('logistics.layout')
@section('title', 'Hub Staff')
@section('page-title', 'Hub Staff')
@section('page-sub', "Approve/disapprove hub staff applications, activate or suspend accounts")

@section('content')
@if(session('success'))<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div class="card">
  <div class="card-head">
    <div><h2>Hub Staff Applications</h2><p>{{ $staff->count() }} total</p></div>
  </div>
  <div class="card-pad">
    {{-- Client-side filter (see the shared [data-tabs] handler in admin.js) — switching
         tabs just shows/hides rows already on the page, no reload. --}}
    <div data-tabs id="staffTabs">
      <a class="tab active" data-tab="all">All <span class="tab-count">{{ $staff->count() }}</span></a>
      <a class="tab" data-tab="pending">Pending <span class="tab-count">{{ $statusCounts['pending'] ?? 0 }}</span></a>
      <a class="tab" data-tab="interview">Interview <span class="tab-count">{{ $statusCounts['interview'] ?? 0 }}</span></a>
      <a class="tab" data-tab="approved">Approved <span class="tab-count">{{ $statusCounts['approved'] ?? 0 }}</span></a>
      <a class="tab" data-tab="rejected">Rejected <span class="tab-count">{{ $statusCounts['rejected'] ?? 0 }}</span></a>
      <a class="tab" data-tab="suspended">Suspended <span class="tab-count">{{ $statusCounts['suspended'] ?? 0 }}</span></a>
    </div>

    <div class="table-wrap">
      <table class="dtable">
        <thead>
          <tr><th>Applicant</th><th>Hub</th><th>Submitted</th><th>Status</th><th></th></tr>
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
                @if($member->logisticsHub->is_regional_hub)<span class="stamp stamp-active" style="margin-left:4px">Regional</span>@endif
              @else
                <span style="color:var(--danger)">No hub linked</span>
              @endif
            </td>
            <td class="mono">{{ $member->created_at?->format('M d, Y') ?? '—' }}</td>
            <td><span class="stamp stamp-{{ $member->status }}">{{ ucfirst($member->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="staffModal-{{ $member->id }}">Review</button>
              </div>
            </td>
          </tr>

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
                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="account" /></span><span>Personal Information</span></div>
                  <div class="detail-grid">
                    <div><div class="field-label">Full Name</div><div class="field-value">{{ $member->given_names }} {{ $member->middle_name ? $member->middle_name.' ' : '' }}{{ $member->last_name }}</div></div>
                    <div><div class="field-label">Username</div><div class="field-value mono">{{ $member->username ?? '—' }}</div></div>
                    <div><div class="field-label">Sex</div><div class="field-value">{{ ucfirst($member->sex ?? '—') }}</div></div>
                    <div><div class="field-label">Birthday</div><div class="field-value mono">{{ $member->birthday?->format('M d, Y') ?? '—' }}</div></div>
                    <div><div class="field-label">Age</div><div class="field-value">{{ $member->age ?? '—' }}</div></div>
                    <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $member->contact_no }}</div></div>
                  </div>
                </div>

                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="pin" /></span><span>Address &amp; Hub</span></div>
                  <div class="detail-grid">
                    <div class="full"><div class="field-label">Full Address</div><div class="field-value">{{ collect([$member->house_no, $member->street, $member->barangay, $member->municipality, $member->province])->filter()->implode(', ') ?: '—' }}</div></div>
                    <div>
                      <div class="field-label">Hub</div>
                      <div class="field-value">
                        @if($member->logisticsHub)
                          {{ $member->logisticsHub->municipality }}, {{ $member->logisticsHub->province }}
                          {{ $member->logisticsHub->is_regional_hub ? ' — regional hub' : '' }}
                        @else
                          <span style="color:var(--danger)">No hub linked — their address may no longer match a registered coverage area.</span>
                        @endif
                      </div>
                    </div>
                  </div>
                </div>

                <div class="section-card">
                  <div class="section-head"><span class="ic"><x-admin-icon name="shield" /></span><span>Verification Documents</span></div>
                  <div class="doc-grid">
                    <x-admin-doc-thumb :path="$member->selfie_file" label="Selfie" />
                    <x-admin-doc-thumb :path="$member->id_file" label="Government ID" />
                    @if($member->resume_file)
                    <x-admin-doc-thumb :path="$member->resume_file" label="Resume / CV" />
                    @endif
                  </div>
                </div>

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
              {{--
                Buttons per status — every one of these emails the applicant:
                  pending   -> Interview, Reject
                  interview -> Approve, Reject
                  approved  -> Suspend
                  rejected  -> (none)
                  suspended -> Activate
              --}}
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Cancel</button>
                @if($member->status === 'pending')
                <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('reject', '{{ route('logistics.staff.reject', $member->id) }}')">Reject</button>
                <button type="button" class="btn btn-success" data-modal-open="interviewModal-{{ $member->id }}">Interview</button>
                @elseif($member->status === 'interview')
                <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('reject', '{{ route('logistics.staff.reject', $member->id) }}')">Reject</button>
                <button type="button" class="btn btn-success" data-modal-open="confirmInterviewModal-{{ $member->id }}">Approve</button>
                @elseif($member->status === 'approved')
                <button type="button" class="btn btn-outline" onclick="openReasonModal('suspend', '{{ route('logistics.staff.suspend', $member->id) }}')">Suspend</button>
                @elseif($member->status === 'suspended')
                <form method="POST" action="{{ route('logistics.staff.activate', $member->id) }}" style="display:inline">
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
              <form method="POST" action="{{ route('logistics.staff.approve', $member->id) }}">
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
                <div class="detail-grid">
                  <div><div class="field-label">Date</div><div class="field-value">{{ $member->interview_scheduled_at?->format('M d, Y') ?? '—' }}</div></div>
                  <div><div class="field-label">Time</div><div class="field-value">{{ $member->interview_scheduled_at?->format('g:i A') ?? '—' }}</div></div>
                  <div class="full"><div class="field-label">Location</div><div class="field-value">{{ $member->interview_location ?: '—' }}</div></div>
                </div>
                <p style="margin:12px 0 0;font-size:12px;color:var(--muted)">This immediately enables their account — they'll be able to log in right after.</p>
              </div>
              <form method="POST" action="{{ route('logistics.staff.confirm', $member->id) }}">
                @csrf @method('PATCH')
                <div class="modal-foot">
                  <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                  <button type="submit" class="btn btn-success">Confirm Approval</button>
                </div>
              </form>
            </div>
          </div>
          @endif
          @empty
          <tr><td colspan="5"><div class="empty"><div class="ic"><x-admin-icon name="account" /></div><h3>No hub staff applications yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@include('admin.partials.doc-lightbox')
@include('admin.partials.reason-modals')
@endsection
