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
    <div class="card">
      <div class="card-head" id="account-security"><h2>Change password</h2></div>
      <div class="card-pad">
        @if(session('password_success'))
        <div class="auth-success" style="margin-bottom:16px">{{ session('password_success') }}</div>
        @endif
        @error('current_password')
        <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ $message }}</div>
        @enderror
        @php
          $passwordPending = session('buyer_password_change_pending', []);
          $showPasswordForm = (string)($passwordPending['user_id'] ?? '') === (string)auth()->id() && ($passwordPending['expires_at'] ?? 0) > now()->timestamp;
          $passwordAuthorized = $showPasswordForm && is_string(session('buyer_password_change_token')) && \Illuminate\Support\Facades\Password::tokenExists(auth()->user(), session('buyer_password_change_token'));
        @endphp
        @if($passwordAuthorized)
        <p class="account-password-instructions" role="status">Your email link is verified. Update your username and password below.</p>
        <form method="POST" action="{{ route('buyer.account.password-change') }}" class="account-password-change-form">
          @csrf
          <div class="form-row"><label for="buyerUsername">Username</label><input id="buyerUsername" name="username" value="{{ old('username',auth()->user()->username) }}" autocomplete="off" maxlength="50" readonly data-unlock-on-focus required></div>
          <div class="account-password-fields"><div>
            <div class="form-row"><label for="buyerNewPassword">New password</label><input id="buyerNewPassword" type="password" name="password" placeholder=" " minlength="8" maxlength="128" autocomplete="new-password" required></div>
            <div class="form-row"><label for="buyerConfirmPassword">Confirm password</label><input id="buyerConfirmPassword" type="password" name="password_confirmation" placeholder=" " maxlength="128" autocomplete="new-password" required></div>
          </div><div id="accountPasswordRequirements"></div></div>
          @error('username')<p class="profile-field-error">{{ $message }}</p>@enderror
          @error('password')<p class="profile-field-error">{{ $message }}</p>@enderror
          <button class="btn btn-primary" type="submit" @disabled(!$passwordAuthorized)>Save login details</button>
        </form>
        @else
        @if($showPasswordForm)
        <section class="account-password-link account-password-start"><div><p role="status">A password-change link has been sent to {{ auth()->user()->email }}. Check your inbox and spam folder, then open the link to choose a new password. Please wait a minute before requesting another link.</p></div><form method="POST" action="{{ route('buyer.account.password-link') }}">@csrf<button class="btn btn-outline" type="submit">Resend link</button></form></section>
        @else
        <section class="account-password-link account-password-start"><div><p>We'll email a secure link to {{ auth()->user()->email }} so you can choose a new password.</p></div><form method="POST" action="{{ route('buyer.account.password-link') }}">@csrf<button class="btn btn-primary" type="submit">Email change-password link</button></form></section>
        @endif
        @endif
        @error('password_link')<p class="profile-field-error" role="alert">{{ $message }}</p>@enderror
      </div>
    </div>
    <div class="card account-danger-zone" data-account-panel="account-security">
      <div class="card-head"><div><h2>Delete My Account</h2><p>Remove your profile, saved addresses, cart, and follows.</p></div><button type="button" class="btn btn-danger" data-modal-open="deleteAccountModal">Delete account</button></div>
      <div class="card-pad account-danger-zone-body">
        @error('delete_account')<p class="account-danger-error" role="alert">{{ $message }}</p>@enderror
        @error('confirmation')<p class="account-danger-error" role="alert">{{ $message }}</p>@enderror
        @error('password')<p class="account-danger-error" role="alert">{{ $message }}</p>@enderror
      </div>
    </div>
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

<div class="modal-overlay profile-photo-crop-overlay" id="profilePhotoCropModal" aria-hidden="true">
  <section class="profile-photo-crop-modal" role="dialog" aria-modal="true" aria-labelledby="profilePhotoCropTitle">
    <header class="profile-photo-crop-header"><div><h2 id="profilePhotoCropTitle">Profile photo</h2><p>View your current photo or choose a new one to crop.</p></div><button type="button" data-photo-modal-close aria-label="Close photo manager">&times;</button></header>
    <form id="buyerProfilePhotoForm" method="POST" action="{{ route('buyer.account.photo') }}" enctype="multipart/form-data">
      @csrf
      <input class="buyer-profile-photo-input" type="file" id="buyerProfilePhotoInput" name="profile_picture" accept="image/jpeg,image/png,image/webp" aria-label="Choose profile photo">
      <div class="profile-photo-manager-current" id="profilePhotoManagerCurrent">
        <span class="buyer-profile-photo-preview profile-photo-manager-avatar">@if(auth()->user()->profile_picture)<img src="{{ auth()->user()->shopPhotoUrl() }}" alt="Current profile photo">@else<x-user-avatar :user="auth()->user()" size="100" />@endif</span>
        <div class="profile-photo-manager-actions">
          <button class="btn btn-primary" type="button" id="chooseBuyerProfilePhoto">Choose photo</button>
          @if(auth()->user()->profile_picture)<button class="btn btn-outline" type="button" id="enlargeBuyerProfilePhoto">View image</button>@endif
        </div>
        <small>JPG, PNG or WEBP · maximum 5 MB</small>
      </div>
      <div class="profile-photo-crop-editor" id="profilePhotoCropEditor" hidden>
        <div class="profile-photo-crop-stage"><canvas id="profilePhotoCropCanvas" width="600" height="600" aria-label="Circular profile photo crop area"></canvas></div>
        <div class="profile-photo-crop-tools"><label for="profilePhotoZoom">Zoom</label><input type="range" id="profilePhotoZoom" min="1" max="3" step="0.01" value="1"><span class="profile-photo-crop-shape">Circular crop</span></div>
        <div class="profile-photo-crop-footer"><button type="button" class="btn btn-outline" id="cancelProfilePhotoCrop">Cancel crop</button><button type="button" class="btn btn-primary" id="applyProfilePhotoCrop">Apply crop</button></div>
      </div>
      <div class="profile-photo-ready" id="profilePhotoReady" hidden>
        <span class="buyer-profile-photo-preview profile-photo-manager-avatar"><img id="profilePhotoReadyImage" alt="Cropped profile photo preview"></span>
        <p>Preview your cropped profile photo before saving.</p>
        <button class="btn btn-outline" type="button" id="chooseAnotherBuyerPhoto">Choose another photo</button>
      </div>
      <small class="profile-field-error" id="buyerProfilePhotoError" role="alert" hidden>@error('profile_picture'){{ $message }}@enderror</small>
      <footer class="profile-photo-crop-footer profile-photo-manager-footer"><button class="btn btn-primary" type="submit" id="saveBuyerProfilePhoto" hidden disabled>Save photo</button></footer>
    </form>
  </section>
</div><div class="modal-overlay" id="buyerProfilePhotoEnlargeModal" aria-hidden="true"><div class="buyer-profile-photo-enlarge" role="dialog" aria-modal="true" aria-label="Profile photo preview"><button type="button" data-photo-enlarge-close aria-label="Close preview">&times;</button><img id="buyerProfilePhotoEnlargeImage" alt="Enlarged profile photo" hidden><x-user-avatar :user="auth()->user()" size="300" id="buyerProfilePhotoEnlargeAvatar" /></div></div>

<div class="modal-overlay" id="accountDocumentModal" aria-hidden="true"><section class="account-document-modal" role="dialog" aria-modal="true" aria-label="Document preview"><button type="button" data-document-close aria-label="Close document preview">&times;</button><img id="accountDocumentImage" alt="Current identity document" hidden><iframe id="accountDocumentPdf" title="Current identity document PDF" hidden></iframe></section></div>

<div class="modal-overlay" id="deleteAccountModal" aria-hidden="true">
  <div class="modal pf-confirm account-delete-modal" role="dialog" aria-modal="true" aria-labelledby="deleteAccountTitle">
    <div class="modal-head">
      <div><h3 id="deleteAccountTitle">Delete your account?</h3><p>This permanently removes your buyer profile and signs you out.</p></div>
    </div>
    <form method="POST" action="{{ route('buyer.account.destroy') }}">
      @csrf @method('DELETE')
      <div class="modal-body"><p>Completed order records remain for fulfilment, refunds, and accounting. This action signs you out and cannot be undone.</p>
        <div class="form-row"><label for="deleteAccountPassword">Current password</label><input id="deleteAccountPassword" type="password" name="password" autocomplete="current-password" required></div>
        <div class="form-row"><label for="deleteAccountConfirmation">Type “yes i confirm” to continue</label><input id="deleteAccountConfirmation" type="text" name="confirmation" autocomplete="off" required></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger">Delete account</button></div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-upload-input]').forEach(input => input.addEventListener('change', () => {
  const preview = document.getElementById(input.dataset.uploadInput);
  const empty = input.closest('.profile-identity-upload')?.querySelector('.profile-upload-empty');
  const file = input.files?.[0];
  if (!preview || !empty) return;
  if (!file) { preview.hidden = true; preview.replaceChildren(); empty.hidden = false; return; }
  if (file.size > 5 * 1024 * 1024) { input.value = ''; preview.hidden = true; preview.replaceChildren(); empty.hidden = false; return; }
  preview.replaceChildren();
  if (file.type.startsWith('image/')) {
    const image = document.createElement('img');
    image.alt = file.name;
    image.src = URL.createObjectURL(file);
    preview.append(image);
  } else {
    const label = document.createElement('span');
    label.className = 'profile-upload-file';
    label.textContent = `PDF · ${file.name}`;
    preview.append(label);
  }
  preview.hidden = false;
  if (empty) empty.hidden = true;
}));

(() => {
  const input=document.getElementById('buyerProfilePhotoInput');
  const choose=document.getElementById('chooseBuyerProfilePhoto');
  const chooseAnother=document.getElementById('chooseAnotherBuyerPhoto');
  const save=document.getElementById('saveBuyerProfilePhoto');
  const error=document.getElementById('buyerProfilePhotoError');
  const preview=document.getElementById('buyerProfilePhotoPreview');
  const cropModal=document.getElementById('profilePhotoCropModal');
  const currentPanel=document.getElementById('profilePhotoManagerCurrent');
  const cropPanel=document.getElementById('profilePhotoCropEditor');
  const readyPanel=document.getElementById('profilePhotoReady');
  const readyImage=document.getElementById('profilePhotoReadyImage');
  const enlargeModal=document.getElementById('buyerProfilePhotoEnlargeModal');
  const enlargeImage=document.getElementById('buyerProfilePhotoEnlargeImage');
  const canvas=document.getElementById('profilePhotoCropCanvas');
  if(!input||!canvas||!cropModal)return;
  const ctx=canvas.getContext('2d');
  const zoomInput=document.getElementById('profilePhotoZoom');
  const crop={image:null,zoom:1,dx:0,dy:0,ratio:'circle',dragging:false,lastX:0,lastY:0,rect:null};
  const cw=canvas.width,ch=canvas.height;
  let acceptedPhoto=null;
  function cropRect(){
    return{x:50,y:50,w:500,h:500,circle:true};
  }
  function draw(){
    if(!crop.image)return;
    crop.rect=cropRect();const r=crop.rect;
    const scale=Math.max(r.w/crop.image.naturalWidth,r.h/crop.image.naturalHeight)*crop.zoom;
    const iw=crop.image.naturalWidth*scale,ih=crop.image.naturalHeight*scale;
    const ix=(cw-iw)/2+crop.dx,iy=(ch-ih)/2+crop.dy;
    ctx.clearRect(0,0,cw,ch);ctx.fillStyle='#252129';ctx.fillRect(0,0,cw,ch);
    ctx.drawImage(crop.image,ix,iy,iw,ih);
    ctx.fillStyle='rgba(14,12,16,.58)';ctx.fillRect(0,0,cw,ch);
    ctx.save();
    if(r.circle){ctx.beginPath();ctx.ellipse(r.x+r.w/2,r.y+r.h/2,r.w/2,r.h/2,0,0,Math.PI*2);ctx.clip();}
    else ctx.beginPath(),ctx.rect(r.x,r.y,r.w,r.h),ctx.clip();
    ctx.drawImage(crop.image,ix,iy,iw,ih);ctx.restore();
    ctx.save();ctx.strokeStyle='#fff';ctx.lineWidth=3;ctx.setLineDash([9,6]);
    if(r.circle){ctx.beginPath();ctx.arc(r.x+r.w/2,r.y+r.h/2,r.w/2,0,Math.PI*2);ctx.stroke();}
    else ctx.strokeRect(r.x,r.y,r.w,r.h);
    ctx.restore();
  }
  function clampPhoto(){
    if(!crop.image||!crop.rect)return;
    const r=crop.rect,scale=Math.max(r.w/crop.image.naturalWidth,r.h/crop.image.naturalHeight)*crop.zoom;
    const iw=crop.image.naturalWidth*scale,ih=crop.image.naturalHeight*scale;
    const baseX=(cw-iw)/2,baseY=(ch-ih)/2;
    crop.dx=Math.max(r.x+r.w-(baseX+iw),Math.min(r.x-baseX,crop.dx));
    crop.dy=Math.max(r.y+r.h-(baseY+ih),Math.min(r.y-baseY,crop.dy));
  }
  function openCrop(file){
    if(file.size>5*1024*1024){error.textContent='Choose an image no larger than 5 MB.';error.hidden=false;restoreAcceptedPhoto();return;}
    error.hidden=true;
    const image=new Image();image.onload=()=>{crop.image=image;crop.zoom=1;crop.dx=0;crop.dy=0;zoomInput.value='1';currentPanel.hidden=true;readyPanel.hidden=true;cropPanel.hidden=false;save.hidden=true;draw();};image.onerror=()=>{error.textContent='This image could not be opened. Choose another JPG, PNG, or WEBP photo.';error.hidden=false;restoreAcceptedPhoto();};
    image.src=URL.createObjectURL(file);
  }
  function restoreAcceptedPhoto(){const transfer=new DataTransfer();if(acceptedPhoto)transfer.items.add(acceptedPhoto);input.files=transfer.files;save.disabled=!acceptedPhoto;save.hidden=!acceptedPhoto;cropPanel.hidden=true;readyPanel.hidden=!acceptedPhoto;currentPanel.hidden=!!acceptedPhoto;if(acceptedPhoto){readyImage.src=URL.createObjectURL(acceptedPhoto);}}
  function closePhotoModal(){cropModal.classList.remove('open');cropModal.setAttribute('aria-hidden','true');acceptedPhoto=null;restoreAcceptedPhoto();error.hidden=true;}
  document.getElementById('openBuyerProfilePhotoModal')?.addEventListener('click',()=>{restoreAcceptedPhoto();cropModal.classList.add('open');cropModal.setAttribute('aria-hidden','false');});
  choose?.addEventListener('click',()=>input.click());
  chooseAnother?.addEventListener('click',()=>input.click());
  input.addEventListener('change',()=>{const file=input.files?.[0];if(file)openCrop(file);});
  document.getElementById('cancelProfilePhotoCrop').textContent='Choose another photo';
  document.getElementById('cancelProfilePhotoCrop')?.addEventListener('click',()=>input.click());
  document.getElementById('chooseAnotherPhotoFooter')?.addEventListener('click',()=>input.click());
  document.querySelectorAll('[data-photo-modal-close]').forEach(button=>button.addEventListener('click',closePhotoModal));
  zoomInput.addEventListener('input',()=>{crop.zoom=Number(zoomInput.value);clampPhoto();draw();});
  canvas.addEventListener('pointerdown',event=>{crop.dragging=true;crop.lastX=event.offsetX*cw/canvas.clientWidth;crop.lastY=event.offsetY*ch/canvas.clientHeight;canvas.setPointerCapture(event.pointerId);});
  canvas.addEventListener('pointermove',event=>{if(!crop.dragging)return;const x=event.offsetX*cw/canvas.clientWidth,y=event.offsetY*ch/canvas.clientHeight;crop.dx+=x-crop.lastX;crop.dy+=y-crop.lastY;crop.lastX=x;crop.lastY=y;clampPhoto();draw();});
  const finishCropDrag=()=>{crop.dragging=false;};
  canvas.addEventListener('pointerup',finishCropDrag);canvas.addEventListener('pointercancel',finishCropDrag);
  document.getElementById('applyProfilePhotoCrop')?.addEventListener('click',()=>{
    if(!crop.image||!crop.rect)return;
    const r=crop.rect,scale=Math.max(r.w/crop.image.naturalWidth,r.h/crop.image.naturalHeight)*crop.zoom;
    const iw=crop.image.naturalWidth*scale,ih=crop.image.naturalHeight*scale,ix=(cw-iw)/2+crop.dx,iy=(ch-ih)/2+crop.dy;
    const sx=Math.max(0,(r.x-ix)/scale),sy=Math.max(0,(r.y-iy)/scale),sw=Math.min(crop.image.naturalWidth-sx,r.w/scale),sh=Math.min(crop.image.naturalHeight-sy,r.h/scale);
    const out=document.createElement('canvas'),factor=Math.min(1000/Math.max(r.w,r.h),1.5);out.width=Math.round(r.w*factor);out.height=Math.round(r.h*factor);
    const outCtx=out.getContext('2d');outCtx.drawImage(crop.image,sx,sy,sw,sh,0,0,out.width,out.height);
    out.toBlob(blob=>{if(!blob)return;const cropped=new File([blob],'profile-photo.jpg',{type:'image/jpeg'});acceptedPhoto=cropped;const transfer=new DataTransfer();transfer.items.add(cropped);input.files=transfer.files;
      readyImage.src=URL.createObjectURL(cropped);cropPanel.hidden=true;currentPanel.hidden=true;readyPanel.hidden=false;save.hidden=false;save.disabled=false;
    },'image/jpeg',.92);
  });
  document.getElementById('enlargeBuyerProfilePhoto')?.addEventListener('click',()=>{
    const image=preview.querySelector('img'),avatar=document.getElementById('buyerProfilePhotoEnlargeAvatar');if(image){enlargeImage.src=image.src;enlargeImage.hidden=false;if(avatar)avatar.hidden=true;}else{enlargeImage.removeAttribute('src');enlargeImage.hidden=true;if(avatar)avatar.hidden=false;}
    enlargeModal.classList.add('open');enlargeModal.setAttribute('aria-hidden','false');
  });
  document.querySelectorAll('[data-photo-enlarge-close]').forEach(button=>button.addEventListener('click',()=>{enlargeModal.classList.remove('open');enlargeModal.setAttribute('aria-hidden','true');}));
})();
(() => {
  const birthday = document.querySelector('.profile-birthday-row input[name="birthday"]');
  const age = document.querySelector('[data-buyer-age]');
  if (!birthday || !age) return;
  birthday.addEventListener('change', () => {
    if (!birthday.value) { age.textContent = '—'; return; }
    const birth = new Date(`${birthday.value}T00:00:00`);
    if (Number.isNaN(birth.getTime())) { age.textContent = '—'; return; }
    const today = new Date();
    let years = today.getFullYear() - birth.getFullYear();
    if (today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) years--;
    age.textContent = years >= 0 ? `${years} years old` : '—';
  });
})();
</script>
<script src="{{ asset('js/buyer-account-form.js') }}?v={{ filemtime(public_path('js/buyer-account-form.js')) }}" defer></script>
@endpush
