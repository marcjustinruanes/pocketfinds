@extends('logistics.layout')
@section('title', ($account->account_type === 'rider' ? 'Rider' : 'Hub Staff') . ' — ' . $account->given_names . ' ' . $account->last_name)
@section('page-title', 'Personnel')
@section('page-sub', 'Manage rider and hub staff accounts')
@push('head')
<link rel="stylesheet" href="{{ asset('css/platform-accounts.css') }}?v={{ filemtime(public_path('css/platform-accounts.css')) }}">
<link rel="stylesheet" href="{{ asset('css/logistics-team.css') }}?v={{ filemtime(public_path('css/logistics-team.css')) }}">
<style>
.team-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;color:var(--muted);font-size:12px;font-weight:500;text-decoration:none}
.team-back:hover{color:var(--pink-dark)}
.team-back svg{width:14px;height:14px}
.team-modal-tabs{display:flex;gap:4px;padding:4px;border-bottom:1px solid var(--border);background:var(--surface);flex:none;overflow-x:auto;scrollbar-width:none}
.team-modal-tabs::-webkit-scrollbar{display:none}
.team-modal-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--muted);font:500 12px var(--font-body);cursor:pointer;white-space:nowrap;text-decoration:none}
.team-modal-tab:hover{background:var(--pink-soft);color:var(--pink-dark)}
.team-modal-tab.active{background:var(--pink-soft);color:var(--pink-dark);border-color:var(--pink-line);font-weight:600}
.team-modal-tab svg{width:13px;height:13px;flex:none}
.team-modal-panel{display:none}
.team-modal-panel.active{display:block}
.team-activation-notice{padding:14px 16px;border:1px solid var(--warning-line);border-radius:12px;background:var(--warning-soft);margin-bottom:16px;font-size:12px;line-height:1.6}
.team-activation-notice strong{display:block;font-size:13px;margin-bottom:4px;color:var(--warning)}
.team-activation-notice p{margin:0 0 10px;color:var(--text)}
.team-history-entry{padding:12px 0;border-bottom:1px solid var(--border);font-size:12px;line-height:1.7;color:var(--text)}
.team-history-entry:last-child{border-bottom:0}
.team-history-entry p{margin:0 0 4px}
.team-history-entry p:last-child{margin:0}
</style>
<link rel="stylesheet" href="{{ asset('css/personnel-edit.css') }}?v={{ filemtime(public_path('css/personnel-edit.css')) }}">
@endpush
@section('body-class','platform-accounts-page logistics-team-page')
@section('content')



{{-- Single modal, auto-opened --}}
<dialog class="team-edit-dialog" id="team-member-modal" aria-labelledby="team-modal-title" data-return-url="{{ route($account->account_type === 'rider' ? 'logistics.riders' : 'logistics.staff', [], false) }}">
  <div class="modal modal-lg" role="dialog" aria-modal="true" aria-labelledby="team-modal-title" style="display:flex;flex-direction:column;max-height:calc(100dvh - 48px)">

    {{-- Modal header --}}
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="truck" /></span>
        <div class="modal-head-copy">
          <span class="team-member-role-badge" style="display:inline-block;padding:2px 8px;border:1px solid var(--pink-line);border-radius:20px;background:var(--pink-soft);color:var(--pink-dark);font-size:10px;font-weight:600;margin-bottom:4px">{{ $account->account_type === 'rider' ? 'Rider' : 'Hub staff' }}</span>
          <h3 id="team-modal-title" style="margin:0;font-size:17px;font-weight:650;letter-spacing:-.02em">{{ $account->given_names }} {{ $account->last_name }}</h3>
          <p style="margin:3px 0 0;font-size:11px;color:var(--muted)">{{ $account->username }} &middot; {{ $account->email }} &middot; {{ $account->logisticsHub?->municipality ?? 'No hub' }} &middot; <span class="stamp stamp-{{ $account->status }}" style="font-size:10px">{{ ucfirst($account->status) }}</span></p>
        </div>
      </div>
      <a class="modal-close" href="{{ route($account->account_type === 'rider' ? 'logistics.riders' : 'logistics.staff') }}" aria-label="Back to personnel"><x-admin-icon name="close" /></a>
    </div>

    {{-- Tabs --}}
    <nav class="team-modal-tabs" aria-label="Member sections" id="teamModalTabs">
      <button type="button" class="team-modal-tab active" data-tab="tab-edit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit account
      </button>
      @if($account->account_type === 'rider')
      <button type="button" class="team-modal-tab" data-tab="tab-assignments">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Assignment history
      </button>
      @endif
      @if($account->account_type !== 'rider' && config('features.attendance'))
      <a class="team-modal-tab" href="{{ route('logistics.attendance', ['staff_id' => $account->id], false) }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        Attendance
      </a>
      @endif
      <button type="button" class="team-modal-tab" data-tab="tab-history">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Application history
      </button>
    </nav>

    {{-- Modal body --}}
    <div class="modal-body" style="flex:1;min-height:0;overflow-y:auto;padding:22px 24px;background:var(--paper)">

      @if($account->account_type === 'rider' && ($account->status !== 'rejected' || $account->can_resubmit) && app(\App\Services\RiderActivation::class)->pending($account))
      @php($activationInvitation = app(\App\Services\RiderActivation::class)->invitation($account))
      <div class="team-activation-notice">
        <strong>{{ $account->status === 'rejected' ? 'Application correction' : 'Pending activation' }}</strong>
        <p>{{ $activationInvitation->delivery_status === 'failed' ? 'The email could not be sent. Please resend it.' : ($account->status === 'rejected' ? 'The rider can use the emailed link to correct the rejected application.' : 'The rider must set a password through the activation email before signing in.') }}<br>Sent to {{ $activationInvitation->email }} &middot; Expires {{ $activationInvitation->expires_at }}</p>
        <form method="POST" action="{{ route('logistics.riders.activation.resend', $account->id) }}">@csrf<button class="btn btn-primary" type="submit" style="min-height:36px;padding:8px 14px;font-size:12px">{{ $account->status === 'rejected' ? 'Resend correction link' : 'Resend activation email' }}</button></form>
      </div>
      @endif

      {{-- Tab: Edit account --}}
      <div class="team-modal-panel active" id="tab-edit">
        <iframe class="personnel-edit-frame" title="Edit {{ $account->account_type === 'rider' ? 'rider' : 'hub staff' }} account" src="{{ route('logistics.accounts.edit', ['id' => $account->id, 'registration_edit' => 1], false) }}" allow="camera"></iframe>
      </div>

      {{-- Tab: Assignment history --}}
      @if($account->account_type === 'rider')
      <div class="team-modal-panel" id="tab-assignments">
        <div class="table-wrap" style="border:1px solid var(--border);border-radius:12px;overflow:hidden">
          <table class="dtable">
            <thead>
              <tr><th>Tracking</th><th>Leg</th><th>Assignment</th><th>Parcel status</th><th>Assigned at</th></tr>
            </thead>
            <tbody>
              @forelse($assignments as $assignment)
              <tr>
                <td>{{ $assignment->shipment?->tracking_number ?? $assignment->shipment_id }}</td>
                <td>{{ ucfirst(str_replace('_',' ',$assignment->leg)) }}</td>
                <td>{{ $assignment->status }}</td>
                <td>{{ $assignment->shipment?->shipping_status }}</td>
                <td>{{ $assignment->accepted_at?->format('M d, Y g:i A') }}</td>
              </tr>
              @empty
              <tr><td colspan="5"><div class="empty"><h3>No rider assignments</h3></div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div style="margin-top:12px">{{ $assignments->fragment('tab-assignments')->links() }}</div>
      </div>
      @endif

      {{-- Tab: Application history --}}
      <div class="team-modal-panel" id="tab-history">
        @forelse($applicationHistory as $event)
        @php($details = json_decode($event->review_details ?? '{}', true))
        <div class="team-history-entry">
          <p><strong>{{ $event->occurred_at }}</strong> &middot; {{ ucfirst($event->role) }} &middot; <span class="stamp stamp-{{ $event->status }}" style="font-size:10px">{{ ucfirst($event->status) }}</span> &middot; Hub #{{ $event->hub_id }}@if($event->legacy_snapshot) <em style="color:var(--muted)">(legacy snapshot)</em>@endif</p>
          @if($event->reason)<p style="color:var(--muted)">{{ $event->reason }}</p>@endif
          @if(isset($details['from_status'],$details['to_status']))<p>{{ ucfirst($details['from_status']) }} &rarr; {{ ucfirst($details['to_status']) }}@if(!empty($details['action'])) &middot; {{ ucfirst(str_replace('_',' ',$details['action'])) }}@endif</p>@endif
          @if(!empty($details['interview_scheduled_at']))<p>Interview: {{ $details['interview_scheduled_at'] }}@if(!empty($details['interview_location'])) &middot; {{ $details['interview_location'] }}@endif</p>@endif
          @if(!empty($details['interview_completed']))<p>Interview completed &middot; attested by #{{ $details['interview_completed_attested_by'] }} at {{ $details['interview_completed_attested_at'] }}</p>@endif
          @if(!empty($details['document_changes']))<p style="color:var(--muted)">Documents updated: {{ implode(', ', array_keys($details['document_changes'])) }}</p>@endif
          <p style="color:var(--muted);font-size:11px">Actor: {{ $event->actor_id ?? 'Unknown / public application' }} &middot; Attempt: {{ $event->attempt_id }}</p>
        </div>
        @empty
        <p style="font-size:13px;color:var(--muted)">No recorded history.</p>
        @endforelse
        <div style="margin-top:12px">{{ $applicationHistory->fragment('tab-history')->links() }}</div>
      </div>

    </div>{{-- /modal-body --}}
  </div>{{-- /modal --}}
</dialog>{{-- /modal-overlay --}}

<script src="{{ asset('js/personnel-edit.js') }}?v={{ filemtime(public_path('js/personnel-edit.js')) }}" defer></script>
<script src="{{ asset('js/registration-unsaved.js') }}?v={{ filemtime(public_path('js/registration-unsaved.js')) }}"></script>
<script src="{{ asset('js/license-number-input.js') }}?v={{ filemtime(public_path('js/license-number-input.js')) }}"></script>
<script>
(function () {
  const tabs = document.querySelectorAll('#teamModalTabs .team-modal-tab[data-tab]');
  const editForm = document.querySelector('form[data-registration-form]');
  let formDirty = {{ $errors->any() ? 'true' : 'false' }};

  // Track dirtiness from the form itself
  if (editForm) {
    editForm.addEventListener('input', () => { formDirty = true; });
    editForm.addEventListener('change', () => { formDirty = true; });
    editForm.addEventListener('submit', () => { formDirty = false; });
  }

  // Discard dialog
  let discardDialog = null;
  let pendingTabId = null;

  function getOrCreateDiscardDialog() {
    if (discardDialog) return discardDialog;
    discardDialog = document.createElement('dialog');
    discardDialog.className = 'registration-leave-dialog';
    discardDialog.setAttribute('aria-labelledby', 'acfDiscardTitle');
    discardDialog.innerHTML = [
      '<h2 id="acfDiscardTitle">Discard changes?</h2>',
      '<p>You have unsaved changes. Leave this tab and discard them?</p>',
      '<div class="registration-leave-actions">',
        '<button type="button" data-stay autofocus>Keep editing</button>',
        '<button type="button" data-discard>Discard changes</button>',
      '</div>'
    ].join('');
    document.body.append(discardDialog);
    discardDialog.querySelector('[data-stay]').addEventListener('click', () => discardDialog.close());
    discardDialog.querySelector('[data-discard]').addEventListener('click', () => {
      formDirty = false;
      discardDialog.close();
      if (pendingTabId) { activate(pendingTabId); pendingTabId = null; }
    });
    discardDialog.addEventListener('click', e => {
      const r = discardDialog.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) discardDialog.close();
    });
    return discardDialog;
  }

  function activate(id) {
    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === id));
    document.querySelectorAll('.team-modal-panel').forEach(p => p.classList.toggle('active', p.id === id));
  }

  tabs.forEach(t => t.addEventListener('click', () => {
    if (t.dataset.tab === 'tab-edit') { activate('tab-edit'); return; }
    if (formDirty) {
      pendingTabId = t.dataset.tab;
      getOrCreateDiscardDialog().showModal();
    } else {
      activate(t.dataset.tab);
    }
  }));

  // open to edit tab if there are validation errors
  @if($errors->any()) activate('tab-edit'); @endif
  const hash = location.hash.slice(1);
  if (hash && document.getElementById(hash)) activate(hash);
}());
</script>
@endsection
