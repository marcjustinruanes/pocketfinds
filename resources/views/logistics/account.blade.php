@extends('logistics.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile, address, and password')

@push('head')
<style>
  .addr-combo{position:relative}
  .addr-combo input{width:100%}
  .addr-combo-list{position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:220px;overflow-y:auto;background:var(--surface,#fff);border:1px solid var(--border);border-radius:9px;box-shadow:0 8px 24px rgba(27,22,32,.12);z-index:20}
  .addr-combo-option{padding:9px 12px;font-size:14px;cursor:pointer}
  .addr-combo-option:hover,.addr-combo-option.active{background:var(--paper)}
  .addr-combo-empty{padding:9px 12px;font-size:14px;color:var(--muted)}
</style>
@endpush

@include('account.partials.role-workspace-assets')

@section('content')
<x-account-workspace role="logistics" :salary-available="($salaryDestinations ?? null) !== null" :company-available="(bool) ($company ?? null)">
<div class="role-account-content">
@include('commerce.messages')
@php($user = auth()->user())



@if($salaryDestinations !== null)
@include('logistics.partials.staff-salary-account')
@endif

@if($pendingRequest)
<div style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);padding:12px 14px;border-radius:9px;font-size:14px;margin-bottom:16px">
  <div style="font-weight:700;margin-bottom:2px">Update Request Pending</div>
  <div>Your requested changes are awaiting admin review — the forms below are locked until this request is resolved.</div>
</div>
@elseif($lastRequest && $lastRequest->status === 'rejected')
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:12px 14px;border-radius:9px;font-size:14px;margin-bottom:16px">
  <div style="font-weight:700;margin-bottom:2px">Last Request Rejected</div>
  <div>{{ $lastRequest->note ?? 'Your previous update request was rejected. You may submit a new one.' }}</div>
</div>
@endif

<div class="account-settings-grid">
  <section class="account-settings-main" aria-label="Personal account settings">

    <x-account-profile-card role="logistics" :action="route('logistics.account.update')" :pending="$pendingRequest !== null" />

    {{-- Documents --}}
    @php($docs = app(\App\Services\SensitiveDocuments::class))
    @php($docRole = $user->account_type === 'rider' ? 'rider' : 'hub_staff')
    @php($idType = $user->id_type_id ? \Illuminate\Support\Facades\DB::table('id_types')->where('id',$user->id_type_id)->value('name') : null)
    @if($docs->url($user->id_file) || $docs->url($user->selfie_file) || ($docRole === 'rider' && ($docs->url($user->license_file) || $docs->url($user->nbi_clearance_file) || $docs->url($user->or_file) || $docs->url($user->cr_file))) || $docs->url($user->resume_file))
    <div class="card" id="account-documents">
      <div class="card-head"><div><h2>Application Documents</h2><p>Documents submitted during registration. Contact your admin to replace any document.</p></div></div>
      <div class="card-pad">
        <div class="form-grid-2">
          @if($docs->url($user->id_file))
          <div class="form-row">
            <label>{{ $idType ? $idType.' (ID Photo)' : 'ID Photo' }}</label>
            <a href="{{ $docs->url($user->id_file) }}" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:12px">View ID document</a>
          </div>
          @endif
          @if($docs->url($user->selfie_file))
          <div class="form-row">
            <label>Selfie</label>
            <a href="{{ $docs->url($user->selfie_file) }}" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:12px">View selfie</a>
          </div>
          @endif
          @if($docRole === 'rider' && $docs->url($user->license_file))
          <div class="form-row">
            <label>Driver's License</label>
            <a href="{{ $docs->url($user->license_file) }}" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:12px">View license</a>
          </div>
          @endif
          @if($docRole === 'rider' && $docs->url($user->nbi_clearance_file))
          <div class="form-row">
            <label>NBI Clearance</label>
            <a href="{{ $docs->url($user->nbi_clearance_file) }}" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:12px">View NBI clearance</a>
          </div>
          @endif
          @if($docRole === 'rider' && $docs->url($user->or_file))
          <div class="form-row">
            <label>OR (Vehicle)</label>
            <a href="{{ $docs->url($user->or_file) }}" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:12px">View OR</a>
          </div>
          @endif
          @if($docRole === 'rider' && $docs->url($user->cr_file))
          <div class="form-row">
            <label>CR (Vehicle)</label>
            <a href="{{ $docs->url($user->cr_file) }}" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:12px">View CR</a>
          </div>
          @endif
          @if($docs->url($user->resume_file))
          <div class="form-row">
            <label>Resume / CV</label>
            <a href="{{ $docs->url($user->resume_file) }}" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:12px">View resume</a>
          </div>
          @endif
        </div>
      </div>
    </div>
    @endif

    {{-- Address --}}
    <div class="card" id="account-address" role="region" aria-labelledby="account-address-title">
      <div class="card-head"><div><h2 id="account-address-title">Address Information</h2><p>Where you're based</p></div></div>
      <div class="card-pad">
        @if(session('address_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:14px;margin-bottom:14px">{{ session('address_success') }}</div>
        @endif
        @error('province')
        <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:14px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('logistics.account.address') }}" id="addressForm" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf
@include('account.partials.address-region', ['addressApi' => url('/logistics/address-locations')])
          <div class="form-row">
            <label for="province_input">Province</label>
            <div class="addr-combo">
              <input type="text" name="province" id="province_input" autocomplete="off" required
                     value="{{ old('province', $user->province) }}" placeholder="Type to search a province…">
              <div class="addr-combo-list" id="province_list" hidden></div>
            </div>
          </div>
          <div class="form-row">
            <label for="municipality_input">Municipality / City</label>
            <div class="addr-combo">
              <input type="text" name="municipality" id="municipality_input" autocomplete="off" required
                     value="{{ old('municipality', $user->municipality) }}" placeholder="Type to search a city / municipality…">
              <div class="addr-combo-list" id="municipality_list" hidden></div>
            </div>
          </div>
          <div class="form-row">
            <label for="barangay_input">Barangay</label>
            <div class="addr-combo">
              <input type="text" name="barangay" id="barangay_input" autocomplete="off" required
                     value="{{ old('barangay', $user->barangay) }}" placeholder="Type to search a barangay…">
              <div class="addr-combo-list" id="barangay_list" hidden></div>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label for="account-house-no">House No. / Unit</label><input type="text" id="account-house-no" name="house_no" value="{{ old('house_no', $user->house_no) }}" placeholder="e.g. 123"></div>
            <div class="form-row"><label for="account-street">Street</label><input type="text" id="account-street" name="street" value="{{ old('street', $user->street) }}" placeholder="e.g. Rizal St."></div>
          </div>
          <button class="btn btn-primary" type="submit" id="addrSubmitBtn" disabled>Update Address</button>
        </form>
      </div>
    </div>

    {{-- Password --}}
    @include('buyer.partials.email-change', ['emailRole'=>'logistics'])
    @include('account.partials.login-security', ['securityRole'=>'logistics'])

  </section>

  <aside class="account-settings-sidebar" aria-label="Account overview and company terms">
    

    {{-- Company Terms & Conditions — separate from PocketFinds' own platform-wide
         T&C: this is what shows to new registrants choosing {{ $company ?? 'this company' }}
         at registration, and only goes live once an admin approves it. --}}
    @if($company ?? null)
    <div class="card" id="account-terms">
      <div class="card-head"><div><h2>{{ $company }} — Terms &amp; Conditions</h2><p>Shown to new staff and riders registering under your company. Edits need admin approval before they go live.</p></div></div>
      <div class="card-pad">
        @if(session('policy_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:14px;margin-bottom:14px">{{ session('policy_success') }}</div>
        @endif

        @if($companyPolicy?->hasPending())
        <div style="background:var(--warning-soft,#fff7ed);border:1px solid var(--warning-line,#fed7aa);border-radius:9px;padding:10px 14px;font-size:14px;margin-bottom:14px">
          Your last edit is awaiting admin review — submitted {{ $companyPolicy->pending_submitted_at?->diffForHumans() }}. The live version below is still what registrants see until it's approved.
        </div>
        @elseif($companyPolicy?->rejection_reason)
        <div style="background:var(--danger-soft,#fef2f2);border:1px solid var(--danger-line,#fecaca);border-radius:9px;padding:10px 14px;font-size:14px;margin-bottom:14px">
          <strong>Revisions requested:</strong> {{ $companyPolicy->rejection_reason }} Please make these changes and resubmit below.
        </div>
        @endif

        <form method="POST" action="{{ route('logistics.company-policy.update') }}">
          @csrf
          <div class="form-row">
            <label for="account-company-terms" class="account-settings-sr-only">Terms &amp; Conditions</label>
            <textarea id="account-company-terms" name="content" rows="10" required maxlength="20000" class="account-settings-terms" placeholder="Write your company's Terms & Conditions for new staff registering here...">{{ old('content', $companyPolicy?->hasPending() ? $companyPolicy->pending_content : $companyPolicy?->content) }}</textarea>
          </div>
          <button class="btn btn-primary" type="submit">Submit for Approval</button>
        </form>

        @if($companyPolicy?->content)
        <details style="margin-top:14px">
          <summary style="cursor:pointer;font-size:14px;font-weight:700;color:var(--muted)">Currently live version</summary>
          <pre style="white-space:pre-wrap;font-size:14px;margin-top:8px;max-height:160px;overflow-y:auto;color:var(--text);background:var(--paper);border:1px solid var(--border);border-radius:9px;padding:12px">{{ $companyPolicy->content }}</pre>
        </details>
        @endif
      </div>
    </div>
    @endif
  </aside>
</div>

</div>
@include('account.partials.delete-account')
</x-account-workspace>
@include('account.partials.profile-photo-manager')
@endsection

@push('scripts')
<script src="{{ asset('js/role-account-details.js') }}?v={{ filemtime(public_path('js/role-account-details.js')) }}"></script>
@endpush
