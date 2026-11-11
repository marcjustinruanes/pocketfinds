@extends('seller.layout')
@section('title', 'Inventory')
@section('page-title', 'Inventory')
@section('body-class', 'seller-products-page')
@push('head')
<link rel="stylesheet" href="{{ asset('css/seller-products.css') }}?v={{ filemtime(public_path('css/seller-products.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/product-color-picker.js') }}?v={{ filemtime(public_path('js/product-color-picker.js')) }}" defer></script>
<script src="{{ asset('js/seller-products.js') }}?v={{ filemtime(public_path('js/seller-products.js')) }}" defer></script>
@endpush

@section('content')
@if($errors->has('product'))<div role="alert" class="product-inventory-notice is-danger">@include('buyer.partials.icon',['name'=>'x','size'=>17])<span>{{ $errors->first('product') }}</span></div>@endif

@if(!$seller->category_id)
  <div class="product-inventory-notice is-warning">
    @include('seller.partials.icon',['name'=>'bell','size'=>15])
    <span>Choose your shop category before adding products. <a href="{{ route('seller.account') }}">Open shop settings</a></span>
  </div>
@endif

@include('seller.partials.product-result-modal')

@include('commerce.messages')
@include('seller.partials.product-list')
@include('seller.partials.inventory-categories')

@include('seller.partials.add-product-modal')
@include('seller.partials.discard-product-modal')

{{-- View & Edit product modals --}}
@foreach($products as $product)
@include('seller.partials.view-product-modal', ['product' => $product])
@include('seller.partials.edit-product-modal', ['product' => $product])
@if(in_array($product->status, ['active', 'archived']))
@include('seller.partials.add-stock-modal', ['product' => $product])
@endif
@endforeach

{{-- Remove confirmation modal --}}
<div class="modal-overlay" id="removeConfirmModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-head">
      <div style="display:flex;align-items:center;gap:10px">
        <span style="width:36px;height:36px;border-radius:50%;background:var(--danger-soft);color:var(--danger);display:flex;align-items:center;justify-content:center;flex:none">
          @include('seller.partials.icon',['name'=>'x','size'=>16])
        </span>
        <div><h3>Remove Product?</h3><p>This can't be undone.</p></div>
      </div>
      <button class="modal-close" data-modal-close>@include('seller.partials.icon',['name'=>'x','size'=>14])</button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--text);margin:0">Remove <strong id="removeConfirmName"></strong> from your inventory? This cannot be undone.</p>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
      <button class="btn btn-danger" type="button" id="removeConfirmBtn">@include('seller.partials.icon',['name'=>'x','size'=>13]) Remove</button>
    </div>
  </div>
</div>

{{-- Rejection note modal --}}
<div id="rejectionModal" style="display:none;position:fixed;inset:0;background:rgba(27,22,32,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;width:min(480px,100%);box-shadow:0 24px 60px rgba(27,22,32,.3)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:8px;color:var(--danger)">
        @include('seller.partials.icon',['name'=>'x','size'=>16])
        <span style="font-weight:700;font-size:14px">Product Rejected</span>
      </div>
      <button id="rejectionModalClose" style="border:0;background:var(--paper);width:30px;height:30px;border-radius:50%;cursor:pointer;display:grid;place-items:center">
        @include('seller.partials.icon',['name'=>'x','size'=>14])
      </button>
    </div>
    <div style="padding:20px">
      <p style="font-size:12px;color:var(--muted);margin:0 0 8px">Reason from admin:</p>
      <div id="rejectionNoteText" style="background:var(--danger-soft);border:1px solid var(--danger-line);border-radius:9px;padding:12px 14px;font-size:13px;color:var(--danger)"></div>
      <p style="font-size:12px;color:var(--muted);margin:12px 0 0">You may remove this product and resubmit with corrections.</p>
    </div>
  </div>
</div>



<script>
// Remove confirmation modal (replaces the native browser confirm())
const removeConfirmModal = document.getElementById('removeConfirmModal');
const removeConfirmName  = document.getElementById('removeConfirmName');
let pendingRemoveFormId  = null;

function confirmRemoveProduct(formId, productName) {
  pendingRemoveFormId = formId;
  removeConfirmName.textContent = productName;
  removeConfirmModal.classList.add('open');
}

document.getElementById('removeConfirmBtn').addEventListener('click', () => {
  if (pendingRemoveFormId) document.getElementById(pendingRemoveFormId).submit();
});

// Rejection note modal
const rejModal      = document.getElementById('rejectionModal');
const rejNoteText   = document.getElementById('rejectionNoteText');
document.getElementById('rejectionModalClose').addEventListener('click', () => rejModal.style.display = 'none');
rejModal.addEventListener('click', e => { if (e.target === rejModal) rejModal.style.display = 'none'; });
document.querySelectorAll('.rejection-note-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    rejNoteText.textContent = btn.dataset.note;
    rejModal.style.display = 'flex';
  });
});


</script>
@endsection
