<div class="modal-overlay profile-photo-crop-overlay" id="profilePhotoCropModal" aria-hidden="true">
  <section class="profile-photo-crop-modal" role="dialog" aria-modal="true" aria-labelledby="profilePhotoCropTitle">
    <header class="profile-photo-crop-header"><div><h2 id="profilePhotoCropTitle">Profile photo</h2><p>View your current photo or choose a new one to crop.</p></div><button type="button" data-photo-modal-close aria-label="Close photo manager">&times;</button></header>
    <form id="buyerProfilePhotoForm" method="POST" action="{{ $photoAction ?? route('account.photo') }}" enctype="multipart/form-data">
      @csrf
      <input class="buyer-profile-photo-input" type="file" id="buyerProfilePhotoInput" name="profile_picture" accept="image/jpeg,image/png,image/webp" aria-label="Choose profile photo">
      <div class="profile-photo-manager-current" id="profilePhotoManagerCurrent">
        <span class="buyer-profile-photo-preview profile-photo-manager-avatar">@if(auth()->user()->profile_picture)<img src="{{ auth()->user()->shopPhotoUrl() }}" alt="Current profile photo">@else<x-user-avatar :user="auth()->user()" size="100" />@endif</span>
        <div class="profile-photo-manager-actions">
          @if(auth()->user()->account_type==='seller')
          <fieldset class="seller-upload-box account-photo-upload"><legend>Profile photo</legend><button type="button" id="chooseBuyerProfilePhoto"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M3 15v5h18v-5"/></svg>Choose photo</button></fieldset>
          @else<button class="btn btn-primary" type="button" id="chooseBuyerProfilePhoto">Choose photo</button>@endif
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

<div class="modal-overlay" id="accountDocumentModal" aria-hidden="true"><section class="account-document-modal" role="dialog" aria-modal="true" aria-label="Document preview"><button type="button" data-document-close aria-label="Close document preview">&times;</button><img id="accountDocumentImage" alt="Current identity document" hidden><div id="accountDocumentPdf" class="account-pdf-paper" aria-label="Current identity document PDF" hidden></div></section></div>

