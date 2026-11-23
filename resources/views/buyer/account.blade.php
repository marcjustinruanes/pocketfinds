@extends('buyer.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile and settings')
@section('hide-page-heading', true)
@section('body-class', 'buyer-account-page')
@push('head')
<link rel="stylesheet" href="{{ asset('css/buyer-account.css') }}?v={{ filemtime(public_path('css/buyer-account.css')) }}">
@endpush

@section('content')
@include('commerce.messages')

<div class="account-layout" id="accountLayout">
<aside class="account-sidebar" aria-label="Account navigation">
<div class="profile-summary">
  <x-user-avatar :user="auth()->user()" size="64" class="profile-summary-avatar" />
      <div class="profile-summary-info">
    <h1>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</h1>
    <p data-profile-username>{{ auth()->user()->username }}</p>
    <div class="profile-summary-badges"></div>
  </div>
</div>

<nav class="account-section-nav" aria-label="My account sections">
 @foreach(['profile-information'=>['user','My profile'],'account-security'=>['shield','Login & security'],'delivery-addresses'=>['home','My addresses'],'my-vouchers'=>['ticket','My vouchers'],'following-shops'=>['heart','Following shops']] as $anchor=>$item)
 <a href="#{{ $anchor }}" data-account-section="{{ $anchor }}">@include('buyer.partials.icon',['name'=>$item[0],'size'=>18])<span class="account-nav-label">{{ $item[1] }}</span>@if($anchor==='following-shops')<span class="account-count">{{ $followingShops->count() }}</span>@endif</a>
 @endforeach
</nav>
</aside>
<div class="account-main">
<div class="dash-grid">
  <div class="stack">

    @if($pendingRequest)
    <div class="card">
      <div class="card-pad" style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);border-radius:9px;font-size:13px;display:flex;align-items:flex-start;gap:10px">
        <div>
          <div style="font-weight:700;margin-bottom:2px">Update Request Pending</div>
          <div>Your requested changes are awaiting admin review and won't appear on your account until approved.</div>
        </div>
      </div>
    </div>
    @endif

    <div class="card">
      <div class="card-pad">
        @if(session('profile_photo_success'))<div class="auth-success" style="margin-bottom:16px">{{ session('profile_photo_success') }}</div>@endif
        @if(session('profile_success'))
        <div class="auth-success" style="margin-bottom:16px">{{ session('profile_success') }}</div>
        @endif
        <div class="profile-personal-heading-row" id="profile-information">
          <div class="profile-form-section-heading"><h3>Personal details</h3><p>If these details differ from your submitted ID, an admin will review them before approval.</p></div>
          <button class="buyer-profile-photo-trigger" type="button" id="openBuyerProfilePhotoModal" aria-label="Manage profile photo">
            <span class="buyer-profile-photo-preview" id="buyerProfilePhotoPreview">@if(auth()->user()->profile_picture)<img src="{{ auth()->user()->shopPhotoUrl() }}" alt="Profile photo">@else<x-user-avatar :user="auth()->user()" size="82" />@endif</span>
          </button>
        </div>
        <form method="POST" action="{{ route('buyer.account.profile') }}" enctype="multipart/form-data" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf
          <section class="profile-form-section">
          <div class="profile-name-grid">
            <div class="form-row profile-name-given">
              <label>Given Names</label>
              <input type="text" name="given_names" placeholder=" " value="{{ old('given_names', auth()->user()->given_names) }}" minlength="2" maxlength="50" pattern="[\p{L}'\- ]+" required>
            </div>
            <div class="form-row profile-name-username">
              <label for="profileUsername">Username</label>
              <input id="profileUsername" type="text" value="{{ auth()->user()->username }}" placeholder=" " readonly autocomplete="off">
            </div>
            <div class="form-row">
              <label>Middle Name</label>
              <input type="text" name="middle_name" placeholder=" " value="{{ old('middle_name', auth()->user()->middle_name) }}" minlength="2" maxlength="50" pattern="[\p{L}'\- ]+">
            </div>
            <div class="form-row">
              <label>Last Name</label>
              <input type="text" name="last_name" placeholder=" " value="{{ old('last_name', auth()->user()->last_name) }}" minlength="2" maxlength="50" pattern="[\p{L}'\- ]+" required>
            </div>
            <div class="form-row">
              <label>Suffix (optional)</label>
              <select name="suffix">
                <option value="">None</option>
                @foreach(\App\Enums\Suffix::cases() as $suffix)
                  <option value="{{ $suffix->value }}" @selected(old('suffix', auth()->user()->suffix?->value) === $suffix->value)>{{ $suffix->value }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-grid-2 profile-birthday-row">
            <div class="form-row">
              <label>Sex</label>
              <select name="sex" required>
                @foreach(\App\Enums\Sex::cases() as $sex)
                  <option value="{{ $sex->value }}" @selected(old('sex', auth()->user()->sex) === $sex->value)>{{ $sex->label() }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-row">
              <label>Birthday</label>
              <input type="date" name="birthday" value="{{ old('birthday', auth()->user()->birthday?->format('Y-m-d')) }}" max="{{ now()->subYears(18)->toDateString() }}" required>
            </div>
            <div class="form-row profile-age-field">
              <div class="account-floating-field always-floating"><output class="account-age-output" data-buyer-age>{{ auth()->user()->birthday ? auth()->user()->birthday?->age . ' years old' : '—' }}</output><label>Age</label><fieldset aria-hidden="true"><legend><span>Age</span></legend></fieldset></div>
            </div>
          </div>
          </section>
          <section class="profile-form-section identity-verification-section">
            <div class="profile-form-section-heading"><h3>Identity Verification</h3><p>When changing your name, suffix, sex, or birthday, submit a replacement government ID and current selfie for review.</p></div>
            <div class="form-row profile-id-type">
              <label for="profileIdType">ID Type</label>
              <select id="profileIdType" name="id_type_id">
                <option value="">Select ID type</option>
                @foreach($idTypes as $idType)
                  <option value="{{ $idType->id }}" @selected((string) old('id_type_id', auth()->user()->id_type_id) === (string) $idType->id)>{{ $idType->name }}</option>
                @endforeach
              </select>
              @error('id_type_id')<small class="profile-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="profile-identity-upload-grid">
              <div class="profile-identity-document-field"><label class="profile-identity-upload" for="profileIdFile">
                <input id="profileIdFile" type="file" name="id_file" accept="image/jpeg,image/png,application/pdf" data-upload-input="profileIdPreview">
                <span class="profile-upload-empty">@include('buyer.partials.icon', ['name' => 'file', 'size' => 26])<strong>Government ID</strong><small>JPG, PNG, or PDF · maximum 5 MB</small></span>
                <span class="profile-upload-preview" id="profileIdPreview" hidden></span>
              </label>@if(auth()->user()->id_file)<button class="profile-document-view" type="button" data-document-view data-document-kind="{{ str_ends_with(strtolower(auth()->user()->id_file), '.pdf') ? 'pdf' : 'image' }}" data-document-src="{{ route('sensitive-documents.show', ['token' => \Illuminate\Support\Facades\Crypt::encryptString(auth()->user()->id_file)]) }}">View current ID</button>@endif</div>
              <div class="profile-identity-document-field">
                <div class="profile-selfie-box" id="profileSelfieBox">
                  <video id="profileSelfieVideo" playsinline muted hidden></video>
                  <canvas id="profileSelfieCanvas" hidden></canvas>
                  <button type="button" class="profile-selfie-idle" id="startProfileSelfie" aria-label="Open selfie camera" title="Take selfie">@include('buyer.partials.icon', ['name' => 'camera', 'size' => 32])</button>
                  <img id="profileSelfiePreviewImage" alt="Selfie preview" hidden>
                  <input id="profileSelfieFile" type="file" name="selfie_file" accept="image/jpeg,image/png" hidden>
                  <div class="profile-selfie-actions"><button type="button" class="btn btn-primary" id="captureProfileSelfie" aria-label="Take selfie" title="Take selfie" disabled hidden>@include('buyer.partials.icon',['name'=>'camera','size'=>20])</button><button type="button" class="profile-selfie-retake" id="retakeProfileSelfie" hidden>↶ Retake</button></div>
                </div>
                <small class="profile-field-error" id="profileSelfieError" role="alert" hidden></small>
                @if(auth()->user()->selfie_file)<button class="profile-document-view" type="button" data-document-view data-document-kind="image" data-document-src="{{ route('sensitive-documents.show', ['token' => \Illuminate\Support\Facades\Crypt::encryptString(auth()->user()->selfie_file)]) }}">View current selfie</button>@endif
              </div>
            </div>
            <div class="profile-upload-errors">
              @error('id_file')<small class="profile-field-error">{{ $message }}</small>@enderror
              @error('selfie_file')<small class="profile-field-error">{{ $message }}</small>@enderror
            </div>
          </section>
          <section class="profile-form-section">
            <div class="profile-form-section-heading"><h3>Contact details</h3><p>Your email is managed separately for account security.</p></div>
          <div class="form-grid-2">
            <div class="form-row">
              <label>Email</label>
              <input type="email" value="{{ auth()->user()->email }}" disabled style="background:var(--paper)">
            </div>
            <div class="form-row">
              <label>Contact No.</label>
              <span style="display:flex;gap:8px;align-items:center"><span>+63</span><input type="tel" name="contact_no" data-national-mobile value="{{ substr(\App\Support\PhilippineMobile::normalize((string)old('contact_no', auth()->user()->contact_no)),1) }}" placeholder="9XXXXXXXXX" minlength="10" maxlength="10" pattern="9[0-9]{9}" inputmode="numeric"></span>
            </div>
          </div>
          </section>
          <div class="profile-submit-row">
            <button class="btn btn-primary" type="submit">Submit profile update for review</button>
            <div class="account-details-grid profile-account-details" id="account-details" aria-label="Account details">
              <div class="account-detail-item"><span class="field-label">Account Type</span><span class="field-value"><span class="account-detail-badge">Buyer</span></span></div>
              <div class="account-detail-item"><span class="field-label">Status</span><span class="field-value"><span class="account-detail-badge account-status-badge">{{ ucfirst(auth()->user()->status) }} on {{ auth()->user()->created_at?->format('M d, Y') ?? '—' }}</span></span></div>
            </div>
          </div>
        </form>
      </div>
    </div>

@if(\App\Services\BuyerCommerce::installed())@include('buyer.partials.email-change')@endif
    @include('account.partials.login-security')
    @include('account.partials.delete-account')
  </div>

  <div class="stack">
@if(\App\Services\BuyerCommerce::installed())@include('buyer.partials.voucher-wallet')@endif


    <div class="card">
      <div class="card-head" id="delivery-addresses">
        <div><h2>My Addresses</h2><p>Manage saved delivery addresses and choose a default for checkout.</p></div>
        <button type="button" class="btn btn-outline" data-modal-open="addAddressModal">Add New Address</button>
      </div>
      <div class="card-pad">
        @include('buyer.partials.delivery-addresses', ['addresses' => $addresses, 'selectable' => false])
      </div>
    </div>

  </div>
</div>

@include('buyer.partials.following-shops', ['shops'=>$followingShops])
</div>
</div>
<script src="{{ asset('js/buyer-account-panels.js') }}?v={{ filemtime(public_path('js/buyer-account-panels.js')) }}" defer></script>

@include('account.partials.profile-photo-manager', ['photoAction'=>route('buyer.account.photo')])


@endsection

@push('scripts')
<script src="{{ asset('js/account-profile-photo.js') }}?v={{ filemtime(public_path('js/account-profile-photo.js')) }}" defer></script>
<script src="{{ asset('js/buyer-account-form.js') }}?v={{ filemtime(public_path('js/buyer-account-form.js')) }}" defer></script>
@endpush
