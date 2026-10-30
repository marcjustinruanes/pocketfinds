@extends('logistics.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile, address, and password')

@push('head')
<link rel="stylesheet" href="{{ asset('css/logistics-account.css') }}?v={{ filemtime(public_path('css/logistics-account.css')) }}">
<style>
  .addr-combo{position:relative}
  .addr-combo input{width:100%}
  .addr-combo-list{position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:220px;overflow-y:auto;background:var(--surface,#fff);border:1px solid var(--border);border-radius:9px;box-shadow:0 8px 24px rgba(27,22,32,.12);z-index:20}
  .addr-combo-option{padding:9px 12px;font-size:14px;cursor:pointer}
  .addr-combo-option:hover,.addr-combo-option.active{background:var(--paper)}
  .addr-combo-empty{padding:9px 12px;font-size:14px;color:var(--muted)}
</style>
@endpush

@section('content')
<div class="logistics-account">
@include('commerce.messages')
@php($user = auth()->user())
<header class="account-settings-intro">
  <div><p class="account-settings-eyebrow">Your workspace</p><h1>Profile &amp; account</h1><p>Manage your personal information, contact details, and account security.</p></div>
</header>
<nav class="account-settings-nav" aria-label="Account settings sections">
  <a href="#account-profile">Profile</a><a href="#account-address">Address</a><a href="#account-security">Password</a><a href="#change-email">Email</a>
  @if($salaryDestinations !== null)<a href="#salary-receiving">Salary GCash</a>@endif
  @if($company ?? null)<a href="#account-terms">Company terms</a>@endif
</nav>


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

    {{-- Profile --}}
    <div class="card" id="account-profile" role="region" aria-labelledby="account-profile-title">
      <div class="card-head"><div><h2 id="account-profile-title">Profile Information</h2><p>Your personal details on file</p></div></div>
      <div class="card-pad">
        <div class="account-settings-identity">
          @if($user->profile_picture)
          <img src="{{ \Illuminate\Support\Facades\Storage::disk('profile_images')->url($user->profile_picture) }}" alt="Profile picture"
               style="width:64px;height:64px;border-radius:50%;object-fit:cover;flex:none">
          @else
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:22px;flex:none">
            {{ strtoupper(substr($user->given_names, 0, 1)) }}
          </div>
          @endif
          <div>
            <div style="font-size:16px;font-weight:700">{{ $user->given_names }} {{ $user->last_name }}</div>
            <div class="account-settings-help">{{ $user->email }}</div>
            <span class="stamp stamp-approved" style="margin-top:4px">Logistics</span>
          </div>
        </div>
        @if(session('profile_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:14px;margin-bottom:14px">{{ session('profile_success') }}</div>
        @endif
        <form method="POST" action="{{ route('logistics.account.update') }}" enctype="multipart/form-data" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf
          <div class="form-row">
            <label for="account-profile-picture">Profile Picture</label>
            <input type="file" id="account-profile-picture" name="profile_picture" aria-describedby="account-picture-help" accept="image/png,image/jpeg,image/webp" style="padding:6px">
            <span id="account-picture-help" class="account-settings-help">JPG, PNG, or WEBP. Max 2MB.</span>
            @error('profile_picture')<div style="color:var(--danger);font-size:14px;margin-top:4px">{{ $message }}</div>@enderror
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label for="account-given-names">Given Names (First Name)</label><input type="text" id="account-given-names" name="given_names" value="{{ old('given_names', $user->given_names) }}" required></div>
            <div class="form-row"><label for="account-last-name">Last Name</label><input type="text" id="account-last-name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required></div>
          </div>
          <div class="form-grid-2">
            <div class="form-row">
              <label for="account-middle-name">Middle Name <span style="color:var(--muted);font-weight:400">(optional)</span></label>
              <input type="text" id="account-middle-name" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" maxlength="1" pattern="[A-Za-z]" placeholder="e.g. D" style="text-transform:uppercase">
              @error('middle_name')<div style="color:var(--danger);font-size:14px;margin-top:4px">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
              <label for="account-contact">Contact No.</label>
              <input type="tel" id="account-contact" name="contact_no" value="{{ old('contact_no', $user->contact_no) }}" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX">
              @error('contact_no')<div style="color:var(--danger);font-size:14px;margin-top:4px">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="form-row">
            <label for="account-sex">Sex</label>
            <select id="account-sex" name="sex">
              <option value="" {{ blank(old('sex', $user->sex)) ? 'selected' : '' }}>Select sex</option>
              <option value="male" {{ old('sex', $user->sex) === 'male' ? 'selected' : '' }}>Male</option>
              <option value="female" {{ old('sex', $user->sex) === 'female' ? 'selected' : '' }}>Female</option>
              <option value="other" {{ old('sex', $user->sex) === 'other' ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="form-row"><label for="account-email">Email Address</label><input id="account-email" type="email" value="{{ $user->email }}" disabled style="background:var(--paper);color:var(--muted);cursor:not-allowed"></div>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </div>

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
    <div class="card" id="account-security" role="region" aria-labelledby="account-security-title">
      <div class="card-head"><h2 id="account-security-title">Change Password</h2></div>
      <div class="card-pad">
        @if(session('password_success'))
        <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:14px;margin-bottom:14px">{{ session('password_success') }}</div>
        @endif
        @error('current_password')
        <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:14px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('logistics.account.password') }}">
          @csrf
          <div class="form-row"><label for="account-current-password">Current Password</label><input type="password" id="account-current-password" name="current_password" required></div>
          <div class="form-row"><label for="account-new-password">New Password</label><input type="password" id="account-new-password" name="password" required></div>
          <div class="form-row"><label for="account-confirm-password">Confirm New Password</label><input type="password" id="account-confirm-password" name="password_confirmation" required></div>
          <button class="btn btn-primary" type="submit">Update Password</button>
        </form>
      </div>
    </div>

  </section>

  <aside class="account-settings-sidebar" aria-label="Account overview and company terms">
    <div class="card">
      <div class="card-head"><h2>Account Overview</h2></div>
      <div class="card-pad account-settings-overview">
        @foreach([
          ['Account Type', 'Logistics'],
          ['Status',       filled($user->status) ? ucfirst($user->status) : 'Not provided'],
          ['Member Since', $user->created_at?->format('M d, Y') ?? 'Not provided'],
          ['Email',        $user->email ?: 'Not provided'],
          ['Contact No.',  $user->contact_no ?: 'Not provided'],
          ['Full Address', collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])->filter()->implode(', ') ?: 'Not provided'],
        ] as [$label, $val])
        <div class="account-settings-overview-row">
          <span style="color:var(--muted);flex:none">{{ $label }}</span>
          <span class="account-settings-overview-value">{{ $val }}</span>
        </div>
        @endforeach
      </div>
    </div>

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
@include('account.partials.email-change')
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/role-account-details.js') }}?v={{ filemtime(public_path('js/role-account-details.js')) }}"></script>
@endpush
