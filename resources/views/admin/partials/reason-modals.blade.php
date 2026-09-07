{{-- Shared "why" modals for Suspend and Reject — one instance per page, reused across every
     row via JS (openReasonModal sets the form's target URL before opening). Each offers a
     preset radio list plus an "Other" option; the details textarea stays enabled regardless
     of which radio is picked, so an admin can always add context — it's only required when
     "Other" is the one selected. The chosen reason is emailed to the account and stored on it.
     Pass $reasonModalTypes (e.g. ['suspend'] or ['reject']) to render only the ones a page
     actually uses — defaults to both. --}}
@php
    $reasonModalTypes = $reasonModalTypes ?? ['suspend', 'reject'];
    $suspendReasons = [
        'Violation of platform policies',
        'Fraudulent or suspicious activity',
        'Multiple complaints from other users',
        'Failure to fulfill orders or deliveries',
        'Non-compliance with verification requirements',
    ];
    $rejectReasons = [
        'Incomplete or unclear documents',
        'Document details don\'t match provided information',
        'Suspicious or potentially fraudulent submission',
        'Duplicate application',
        'Doesn\'t meet role requirements',
    ];
@endphp

@foreach(array_intersect_key([
          'suspend' => ['label' => 'Suspend Account', 'reasons' => $suspendReasons, 'btn' => 'btn-outline', 'submitLabel' => 'Suspend Account'],
          'reject'  => ['label' => 'Reject Application', 'reasons' => $rejectReasons, 'btn' => 'btn-danger', 'submitLabel' => 'Reject Application'],
        ], array_flip($reasonModalTypes)) as $type => $cfg)
<div class="modal-overlay" id="{{ $type }}ReasonModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="flag" /></span>
        <div class="modal-head-copy"><h3>{{ $cfg['label'] }}</h3><p>The reason you pick here is emailed to the account holder</p></div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>
    <form method="POST" id="{{ $type }}ReasonForm" onsubmit="return validateReasonForm('{{ $type }}')">
      @csrf @method('PATCH')
      <div class="modal-body">
        <div style="display:flex;flex-direction:column;gap:10px">
          @foreach($cfg['reasons'] as $reason)
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
            <input type="radio" name="reason_preset" value="{{ $reason }}" required>
            {{ $reason }}
          </label>
          @endforeach
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
            <input type="radio" name="reason_preset" value="other" required>
            Other
          </label>
        </div>
        <div class="form-row" style="margin-top:14px">
          <label>Additional details</label>
          <textarea name="reason_details" id="{{ $type }}ReasonDetails" rows="3" placeholder="Add any extra context for the account holder — required if you selected &quot;Other&quot;" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-size:13px;resize:vertical;font-family:inherit"></textarea>
          <span id="{{ $type }}ReasonError" style="display:none;color:var(--danger);font-size:11.5px;margin-top:4px">Please select a reason, and add details for "Other".</span>
        </div>
      </div>
      <div class="modal-foot">
        {{-- The suspend modal skips this — its X icon above already closes it, and a
             footer Cancel next to "Suspend Account" was redundant. --}}
        @if($type !== 'suspend')
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        @endif
        <button type="submit" class="{{ $cfg['btn'] }}">{{ $cfg['submitLabel'] }}</button>
      </div>
    </form>
  </div>
</div>
@endforeach

<script>
// Opens a reason modal (suspend|reject), pointing its form at the specific account's
// action URL. Closes whatever detail modal was open behind it first, so only one shows.
function openReasonModal(type, formAction) {
  document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  const form = document.getElementById(type + 'ReasonForm');
  form.action = formAction;
  form.reset();
  document.getElementById(type + 'ReasonError').style.display = 'none';
  document.getElementById(type + 'ReasonModal').classList.add('open');
}

function validateReasonForm(type) {
  const checked = document.querySelector(`#${type}ReasonForm input[name="reason_preset"]:checked`);
  const details = document.getElementById(type + 'ReasonDetails');
  const errEl = document.getElementById(type + 'ReasonError');
  if (!checked || (checked.value === 'other' && !details.value.trim())) {
    errEl.style.display = 'block';
    return false;
  }
  errEl.style.display = 'none';
  return true;
}
</script>
