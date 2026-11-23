@extends('seller.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')

@include('account.partials.role-workspace-assets')
@push('account-head')<link rel="stylesheet" href="{{ asset('css/account-verification.css') }}?v={{ substr(hash_file('sha256', public_path('css/account-verification.css')), 0, 12) }}">@endpush
@push('scripts')<script src="{{ asset('js/account-verification.js') }}?v={{ substr(hash_file('sha256', public_path('js/account-verification.js')), 0, 12) }}" defer></script>@endpush

@section('content')
<x-account-workspace role="seller">

@include('commerce.messages')
@php $u = $seller; @endphp

@if($pendingRequest)
<div style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
  <span style="flex:none;margin-top:1px">@include('seller.partials.icon',['name'=>'clock','size'=>15])</span>
  <div>
    <div style="font-weight:700;margin-bottom:2px">Update Request Pending</div>
    <div>Your requested changes are awaiting admin review — the forms below are locked until this request is resolved.</div>
  </div>
</div>
@elseif($lastRequest && $lastRequest->status === 'rejected')
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
  <span style="flex:none;margin-top:1px">@include('seller.partials.icon',['name'=>'x','size'=>15])</span>
  <div>
    <div style="font-weight:700;margin-bottom:2px">Last Request Rejected</div>
    <div>{{ $lastRequest->note ?? 'Your previous update request was rejected. You may submit a new one.' }}</div>
  </div>
</div>
@endif

<div class="dash-grid role-account-content">
  <div class="stack">

    <x-account-profile-card role="seller" :action="route('seller.account.profile')" :pending="$pendingRequest !== null" />

    {{-- Address --}}
    <div class="card">
      <div class="card-head"><div><h2>Address Information</h2></div></div>
      <div class="card-pad">
        @if(session('address_success'))
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('address_success') }}</div>
        @endif
        <form method="POST" action="{{ route('seller.account.address') }}" id="addressForm" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf
@include('account.partials.address-region')
          <div class="form-row">
            <label>Province <span style="color:var(--danger)">*</span></label>
            <select name="province" id="addr-province" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:#fff">
              <option value="" disabled {{ !$u->province ? 'selected' : '' }}>Loading provinces…</option>
              @if($u->province)
                <option value="{{ $u->province }}" selected>{{ $u->province }}</option>
              @endif
            </select>
          </div>
          <div class="form-row">
            <label>Municipality / City <span style="color:var(--danger)">*</span></label>
            <select name="municipality" id="addr-municipality" required disabled style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:#fff">
              <option value="" disabled {{ !$u->municipality ? 'selected' : '' }}>Select province first</option>
              @if($u->municipality)
                <option value="{{ $u->municipality }}" selected>{{ $u->municipality }}</option>
              @endif
            </select>
          </div>
          <div class="form-row">
            <label>Barangay <span style="color:var(--danger)">*</span></label>
            <select name="barangay" id="addr-barangay" required disabled style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:#fff">
              <option value="" disabled {{ !$u->barangay ? 'selected' : '' }}>Select municipality first</option>
              @if($u->barangay)
                <option value="{{ $u->barangay }}" selected>{{ $u->barangay }}</option>
              @endif
            </select>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>House No. / Unit</label><input type="text" id="addr-house-no" name="house_no" value="{{ old('house_no', $u->house_no) }}" placeholder="e.g. 123"></div>
            <div class="form-row"><label>Street</label><input type="text" id="addr-street" name="street" value="{{ old('street', $u->street) }}" placeholder="e.g. Rizal St."></div>
          </div>
          <button type="submit" id="addrSubmitBtn" class="btn btn-primary" disabled style="opacity:.45;cursor:not-allowed">Update Address</button>
        </form>
      </div>
    </div>

    {{-- Shop --}}
    <div class="card">
      <div class="card-head"><div><h2>Shop Information</h2></div></div>
      <div class="card-pad">
        @if(session('shop_photo_success'))<p role="status" style="color:var(--success)">{{ session('shop_photo_success') }}</p>@endif
        <form method="POST" action="{{ route('seller.account.shop-photo') }}" enctype="multipart/form-data" class="seller-shop-photo-form">
          @csrf
          <div class="seller-shop-photo-row">
            <div style="width:72px;height:72px;border-radius:50%;background:var(--pink-soft);display:grid;place-items:center;overflow:hidden;color:var(--pink);font-size:24px;font-weight:700">
              @if($u->profile_picture)<img src="{{ $u->shopPhotoUrl() }}" alt="Your shop picture" style="width:100%;height:100%;object-fit:contain;background:#fff">@else{{ strtoupper(substr($u->business_name ?: $u->given_names, 0, 1)) }}@endif
            </div>
            <div class="form-row seller-shop-photo-upload">

              <x-account-upload name="profile_picture" label="Shop picture" accept="image/jpeg,image/png" action-label="Choose photo" />

              @error('profile_picture')<p role="alert" style="color:var(--danger)">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-primary seller-shop-photo-save">Save picture</button>
          </div>
        </form>
        @if(session('shop_success'))
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('shop_success') }}</div>
        @endif
        <form method="POST" action="{{ route('seller.account.shop') }}" enctype="multipart/form-data">
          @csrf
          <div class="form-grid-2">
            <div class="form-row"><label>Business Name</label><input type="text" name="business_name" value="{{ old('business_name', $u->business_name) }}" placeholder="Your Shop Name"></div>
            <div class="form-row"><label>Username</label><input type="text" name="username" value="{{ old('username', $u->username) }}" placeholder="@yourshop"></div>
          </div>
          <div class="form-row">
            <label>Shop Category</label>
            <select name="category_id">
              <option value="">— Select category —</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ old('category_id', $u->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-row"><label>Store description</label><textarea name="store_description" maxlength="500" rows="4" placeholder="Tell buyers about your shop">{{ old('store_description', $u->store_description) }}</textarea></div>
          <x-account-upload name="store_banner" label="Store banner (optional)" accept="image/jpeg,image/png" />
          @if(!\App\Services\BuyerCommerce::installed())<div class="form-row">
            <label>Shipping Fee (₱)</label>
            <input type="number" name="shipping_fee" readonly value="50.00">
            <span style="font-size:11px;color:var(--muted)">Charged once per order from your shop, shown to buyers at checkout.</span>
          </div>@endif
          <button type="submit" class="btn btn-primary">Update Shop</button>
        </form>
      </div>
    </div>

    {{-- Documents --}}
    <div class="card">
      <div class="card-head"><div><h2>Documents & Verification</h2></div></div>
      <div class="card-pad">

        {{-- Pending document previews (profile/address/shop banners live at the top of the page) --}}
        @if($pendingRequest && ($pendingRequest->requested_documents['id_file'] ?? $pendingRequest->requested_documents['business_permit_file'] ?? null))
          <div style="margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px">
            @if($idFile = $pendingRequest->requested_documents['id_file'] ?? null)
              <button type="button" class="doc-preview-btn btn btn-sm btn-outline"
                data-src="{{ app(\App\Services\SensitiveDocuments::class)->url($idFile) }}"
                data-type="{{ in_array(strtolower(pathinfo($idFile,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf' }}">
                @include('seller.partials.icon',['name'=>'file','size'=>13]) Submitted ID (pending)
              </button>
            @endif
            @if($permitFile = $pendingRequest->requested_documents['business_permit_file'] ?? null)
              <button type="button" class="doc-preview-btn btn btn-sm btn-outline"
                data-src="{{ asset('storage/'.$permitFile) }}"
                data-type="{{ in_array(strtolower(pathinfo($permitFile,PATHINFO_EXTENSION)),['jpg','jpeg','png']) ? 'image' : 'pdf' }}">
                @include('seller.partials.icon',['name'=>'file','size'=>13]) Submitted Permit (pending)
              </button>
            @endif
          </div>
        @endif

        @if(session('docs_success'))
          <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px">{{ session('docs_success') }}</div>
        @endif

        <form method="POST" action="{{ route('seller.account.documents') }}" enctype="multipart/form-data" {{ $pendingRequest ? 'style=opacity:.5;pointer-events:none' : '' }}>
          @csrf

          {{-- Valid ID --}}
          <div class="form-row">
            <label>ID Type</label>
            <select name="id_type_id" id="id_type_id">
              <option value="">— Select ID type —</option>
              @foreach($idTypes as $idType)
                <option value="{{ $idType->id }}" {{ old('id_type_id', $u->id_type_id) == $idType->id ? 'selected' : '' }}>{{ $idType->name }}</option>
              @endforeach
            </select>
          </div>
          @if($u->id_file)
            @php $ext = strtolower(pathinfo($u->id_file, PATHINFO_EXTENSION)); $isImg = in_array($ext, ['jpg','jpeg','png']); @endphp
            <div style="margin-bottom:12px">
              <div style="font-size:12px;font-weight:650;margin-bottom:6px">Current ID on File</div>
              <button type="button" class="doc-preview-btn" data-src="{{ app(\App\Services\SensitiveDocuments::class)->url($u->id_file) }}" data-type="{{ $isImg ? 'image' : 'pdf' }}"
                style="background:none;border:1px solid var(--border);border-radius:9px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                @include('seller.partials.icon',['name'=>'file','size'=>14]) View Current ID
              </button>
            </div>
          @endif
          @include('account.partials.id-selfie-capture')

          {{-- Business Permit --}}
          <x-account-upload name="business_permit_file" label="Business permit (optional)" />
          @if($u->business_permit_file)
            @php $bext = strtolower(pathinfo($u->business_permit_file, PATHINFO_EXTENSION)); $bIsImg = in_array($bext, ['jpg','jpeg','png']); @endphp
            <div style="margin-bottom:12px">
              <div style="font-size:12px;font-weight:650;margin-bottom:6px">Current Business Permit</div>
              <button type="button" class="doc-preview-btn" data-src="{{ asset('storage/'.$u->business_permit_file) }}" data-type="{{ $bIsImg ? 'image' : 'pdf' }}"
                style="background:none;border:1px solid var(--border);border-radius:9px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                @include('seller.partials.icon',['name'=>'file','size'=>14]) View Current Permit
              </button>
            </div>
          @endif
          @foreach((\App\Support\SellerDocuments::required($u->seller_type) ?: array_keys(\App\Support\SellerDocuments::LABELS)) as $documentField)
          @php($documentLabel = \App\Support\SellerDocuments::LABELS[$documentField])
          @if($u->{$documentField})
          <button type="button" class="doc-preview-btn" data-src="{{ app(\App\Services\SensitiveDocuments::class)->url($u->{$documentField}) }}" data-type="{{ str_ends_with(strtolower($u->{$documentField}), '.pdf') ? 'pdf' : 'image' }}">View {{ $documentLabel }}</button>
          @endif
          <x-account-upload :name="$documentField" :label="$documentLabel" />
          @endforeach

          <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:7px">
            @include('seller.partials.icon',['name'=>'send','size'=>14]) Submit for Review
          </button>
        </form>
      </div>
    </div>

  </div>

  <div class="stack">

    @include('buyer.partials.email-change', ['emailRole'=>'seller'])
    @include('account.partials.login-security', ['securityRole'=>'seller'])

    {{-- Account overview --}}



  </div>
</div>

{{-- Document lightbox modal --}}
<div id="docModal" style="display:none;position:fixed;inset:0;background:rgba(27,22,32,.7);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;width:min(780px,100%);max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(27,22,32,.3);overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <span id="docModalTitle" style="font-weight:700;font-size:14px">Document Preview</span>
      <button id="docModalClose" style="border:0;background:var(--paper);width:30px;height:30px;border-radius:50%;font-size:16px;cursor:pointer;display:grid;place-items:center">✕</button>
    </div>
    <div id="docModalBody" style="flex:1;overflow:auto;padding:20px;display:flex;align-items:center;justify-content:center;min-height:300px"></div>
  </div>
</div>

<script>
// Document lightbox
const docModal      = document.getElementById('docModal');
const docModalBody  = document.getElementById('docModalBody');
const docModalTitle = document.getElementById('docModalTitle');

document.getElementById('docModalClose').addEventListener('click', closeDocModal);
docModal.addEventListener('click', e => { if (e.target === docModal) closeDocModal(); });

function closeDocModal() {
  docModal.style.display = 'none';
  docModalBody.innerHTML = '';
}

document.querySelectorAll('.doc-preview-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const src  = btn.dataset.src;
    const type = btn.dataset.type;
    docModalTitle.textContent = type === 'pdf' ? 'Document Preview' : 'Image Preview';
    docModalBody.innerHTML = type === 'image'
      ? `<img src="${src}" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain">`
      : `<iframe src="${src}" style="width:100%;height:70vh;border:0;border-radius:8px"></iframe>`;
    docModal.style.display = 'flex';
  });
});
</script>

<script src="{{ asset('js/role-account-details.js') }}?v={{ substr(hash_file('sha256', public_path('js/role-account-details.js')), 0, 12) }}"></script>
@if(\App\Services\BuyerCommerce::installed())
@include('account.partials.commerce-vacation')


@endif

@include('account.partials.delete-account')
</x-account-workspace>
@include('account.partials.profile-photo-manager')
@endsection

@push('scripts')<script src="{{ asset('js/commerce.js') }}?v={{ substr(hash_file('sha256', public_path('js/commerce.js')), 0, 12) }}"></script>@endpush
