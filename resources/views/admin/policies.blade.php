@extends('admin.layout')
@section('title', 'Policies')
@section('page-title', 'Policies')
@section('page-sub', 'Terms & Conditions for every role, and every logistics company\'s own')

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif

@php($tcRoles = ['buyer' => 'Buyer', 'seller' => 'Seller', 'rider' => 'Rider / Courier', 'logistics' => 'Logistics'])
<div class="card" style="margin-bottom:18px">
  <div class="card-head">
    <div><h2>Platform Terms &amp; Conditions</h2><p>Each role sees its own document during registration — editing one here updates it everywhere immediately, without touching the others</p></div>
  </div>
  <div class="card-pad" style="display:flex;flex-direction:column;gap:22px">
    @foreach($tcRoles as $accountType => $label)
    @php($doc = $terms->get($accountType))
    <div @if(!$loop->first) style="border-top:1px solid var(--border);padding-top:22px" @endif>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <h3 style="margin:0;font-size:14px">{{ $label }}</h3>
        @if($doc?->editor)
          <span style="font-size:11.5px;color:var(--muted)">Last updated by {{ $doc->editor->given_names }} {{ $doc->editor->last_name }} · {{ $doc->updated_at->diffForHumans() }}</span>
        @endif
      </div>
      @if($doc)
      <form method="POST" action="{{ route('admin.policies.update', $accountType) }}">
        @csrf
        <div class="form-row">
          <textarea name="content" rows="10" required maxlength="20000" style="font-family:var(--font-mono, monospace);font-size:12.5px;line-height:1.6;width:100%;padding:12px;border:1px solid var(--border);border-radius:9px;resize:vertical">{{ old('content', $doc->content) }}</textarea>
        </div>
        <button class="btn btn-primary" type="submit">Save {{ $label }} Terms &amp; Conditions</button>
      </form>

      @php($history = $termsHistory->get($accountType, collect()))
      @if($history->isNotEmpty())
      <details style="margin-top:12px">
        <summary style="cursor:pointer;font-size:12.5px;font-weight:700;color:var(--muted)">Revision history ({{ $history->count() }})</summary>
        <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px">
          @foreach($history as $rev)
          <div style="border:1px solid var(--border);border-radius:9px;padding:10px 12px">
            <div style="font-size:11px;color:var(--muted);margin-bottom:6px">
              {{ $rev['editor'] ? $rev['editor']->given_names . ' ' . $rev['editor']->last_name : 'System' }} · replaced {{ \Illuminate\Support\Carbon::parse($rev['created_at'])->format('M d, Y g:i A') }}
            </div>
            <pre style="white-space:pre-wrap;font-size:11.5px;margin:0;max-height:120px;overflow-y:auto;color:var(--text)">{{ $rev['content'] }}</pre>
          </div>
          @endforeach
        </div>
      </details>
      @endif
      @else
      <p style="color:var(--muted);font-size:13px">No {{ $label }} Terms &amp; Conditions document found — run the database migration to create the default one.</p>
      @endif
    </div>
    @endforeach
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Logistics Company Terms &amp; Conditions</h2><p>Each company writes its own — a submission only goes live once you approve it here</p></div>
  </div>
  <div class="card-pad" style="display:flex;flex-direction:column;gap:18px">
    @forelse($companies as $company)
    <div @if(!$loop->first) style="border-top:1px solid var(--border);padding-top:18px" @endif>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        @if($company->logo_path)
          <img src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}" alt="{{ $company->name }}" style="width:34px;height:34px;border-radius:8px;object-fit:cover;flex:none">
        @else
          <span class="modal-icon"><x-admin-icon name="truck" /></span>
        @endif
        <div>
          <h3 style="margin:0;font-size:14px">{{ $company->name }}</h3>
          <span style="font-size:11.5px;color:var(--muted)">{{ $company->staff_count }} staff account{{ $company->staff_count === 1 ? '' : 's' }}</span>
        </div>
      </div>

      @php($policy = $company->policy)

      @if($policy?->hasPending())
      <div style="background:var(--warning-soft, #fff7ed);border:1px solid var(--warning-line, #fed7aa);border-radius:9px;padding:12px 14px;margin-bottom:12px">
        <div style="font-size:11.5px;color:var(--muted);margin-bottom:6px">
          Pending review — submitted by {{ $policy->submittedBy?->given_names }} {{ $policy->submittedBy?->last_name }} · {{ $policy->pending_submitted_at?->diffForHumans() }}
        </div>
        <pre style="white-space:pre-wrap;font-size:12.5px;margin:0 0 10px;max-height:160px;overflow-y:auto;color:var(--text)">{{ $policy->pending_content }}</pre>
        <div style="display:flex;gap:8px">
          <form method="POST" action="{{ route('admin.policies.company.approve', $company->name) }}">
            @csrf @method('PATCH')
            <button class="btn btn-sm btn-primary" type="submit">Approve &amp; Publish</button>
          </form>
          <button type="button" class="btn btn-sm btn-danger" onclick="openRevisionsModal('{{ route('admin.policies.company.revisions', $company->name) }}', {{ Illuminate\Support\Js::from($company->name) }})">Request Revisions</button>
        </div>
      </div>
      @elseif($policy?->rejection_reason)
      <div style="background:var(--danger-soft, #fef2f2);border:1px solid var(--danger-line, #fecaca);border-radius:9px;padding:12px 14px;margin-bottom:12px">
        <div style="font-size:11.5px;font-weight:700;color:#991b1b;margin-bottom:4px">Revisions requested</div>
        <p style="margin:0;font-size:12.5px;color:#374151;white-space:pre-wrap">{{ $policy->rejection_reason }}</p>
      </div>
      @endif

      @if($policy?->content)
        <p style="font-size:11.5px;color:var(--muted);margin:0 0 6px">Currently live — shown to new registrants choosing this company</p>
        <pre style="white-space:pre-wrap;font-size:12.5px;margin:0;max-height:160px;overflow-y:auto;color:var(--text);background:var(--surface-soft, #f8fafc);border:1px solid var(--border);border-radius:9px;padding:12px">{{ $policy->content }}</pre>
      @elseif(!$policy?->hasPending())
        <p style="color:var(--muted);font-size:13px">No Terms &amp; Conditions submitted yet by this company — new registrants will see a placeholder until they do.</p>
      @endif

      @if($policy)
      @php($history = $policy->historyForDisplay()->take(10))
      @if($history->isNotEmpty())
      <details style="margin-top:12px">
        <summary style="cursor:pointer;font-size:12.5px;font-weight:700;color:var(--muted)">Revision history ({{ $history->count() }})</summary>
        <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px">
          @foreach($history as $rev)
          <div style="border:1px solid var(--border);border-radius:9px;padding:10px 12px">
            <div style="font-size:11px;color:var(--muted);margin-bottom:6px">
              Approved {{ \Illuminate\Support\Carbon::parse($rev['created_at'])->format('M d, Y g:i A') }}
            </div>
            <pre style="white-space:pre-wrap;font-size:11.5px;margin:0;max-height:120px;overflow-y:auto;color:var(--text)">{{ $rev['content'] }}</pre>
          </div>
          @endforeach
        </div>
      </details>
      @endif
      @endif
    </div>
    @empty
    <p style="color:var(--muted);font-size:13px">No logistics companies yet — one appears here as soon as a registrant creates it and gets approved.</p>
    @endforelse
  </div>
</div>

{{-- Request Revisions modal — shared across every company; the button that opens it
     points the form at that specific company's own revisions endpoint first. --}}
<div class="modal-overlay" id="revisionsModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="flag" /></span>
        <div class="modal-head-copy"><h3>Request Revisions</h3><p id="revisionsCompanyName">Tell the company what needs to change before it can be approved</p></div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>
    <form method="POST" id="revisionsForm" onsubmit="return validateRevisionsForm()">
      @csrf
      <div class="modal-body">
        <div class="form-row">
          <label>What should be revised?</label>
          <textarea name="rejection_reason" id="revisionsNotes" rows="5" required placeholder="e.g. Clarify the cancellation window in section 2, remove the blanket liability waiver in section 4, add a data-privacy clause..." style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-size:13px;resize:vertical;font-family:inherit"></textarea>
          <span id="revisionsError" style="display:none;color:var(--danger);font-size:11.5px;margin-top:4px">Please describe what needs to be revised.</span>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Send for Revision</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRevisionsModal(formAction, companyName) {
  document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  const form = document.getElementById('revisionsForm');
  form.action = formAction;
  form.reset();
  document.getElementById('revisionsCompanyName').textContent = `What should ${companyName} revise before it can be approved?`;
  document.getElementById('revisionsError').style.display = 'none';
  document.getElementById('revisionsModal').classList.add('open');
}

function validateRevisionsForm() {
  const notes = document.getElementById('revisionsNotes');
  const errEl = document.getElementById('revisionsError');
  if (!notes.value.trim()) {
    errEl.style.display = 'block';
    return false;
  }
  errEl.style.display = 'none';
  return true;
}
</script>
@endsection
