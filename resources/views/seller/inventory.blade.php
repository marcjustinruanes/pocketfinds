@extends('seller.layout')
@section('title', 'Inventory')
@section('page-title', 'Inventory')
@section('page-sub', 'Manage your product listings')
@php use Illuminate\Support\Facades\Storage; @endphp

@section('content')

@if(!$seller->category_id)
  <div style="background:var(--warning-soft);border:1px solid var(--warning-line);color:var(--warning);padding:12px 16px;border-radius:9px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:10px">
    @include('seller.partials.icon',['name'=>'bell','size'=>15])
    <span>You haven't set a shop category yet. <a href="{{ route('seller.account') }}" style="font-weight:700;color:var(--warning);text-decoration:underline">Set it in Account → Shop Information</a> before adding products.</span>
  </div>
@endif

@include('seller.partials.product-result-modal')

<div class="filter-bar">
  <div class="search-mini">
    <span class="ic">@include('seller.partials.icon',['name'=>'search','size'=>13])</span>
    <input type="text" placeholder="Search products…" id="productSearch">
  </div>
  <select class="select" id="statusFilter">
    <option value="">All Status</option>
    <option value="pending">Pending</option>
    <option value="active">Active</option>
    <option value="rejected">Rejected</option>
    <option value="archived">Archived</option>
  </select>
  <button class="btn btn-primary" data-modal="addProductModal" @disabled(!$seller->category_id) title="{{ !$seller->category_id ? 'Set your shop category first' : '' }}">
    @include('seller.partials.icon',['name'=>'plus','size'=>14]) Add Product
  </button>
</div>

<div class="card">
  <div class="card-pad" style="padding:0">
    <table class="tbl" id="productTable" style="table-layout:fixed">
      <thead>
        <tr style="text-align:center">
          <th style="text-align:center;width:34%">Product</th>
          <th style="text-align:center;width:13%">Category</th>
          <th style="text-align:center;width:17%">Price</th>
          <th style="text-align:center;width:9%">Stock</th>
          <th style="text-align:center;width:15%">Status</th>
          <th style="text-align:center;width:12%">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($products as $product)
        @php
          $allOptions   = collect($product->variations ?? [])->flatMap(fn ($v) => $v['options'] ?? []);
          $optionPrices = $allOptions->pluck('price')->filter()->values();
          $minPrice     = $optionPrices->push($product->price)->min();
          $maxPrice     = $optionPrices->max();
        @endphp
        <tr data-status="{{ $product->status }}" style="white-space:nowrap">
          <td style="overflow:hidden">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <div class="inv-img" style="flex:none">
                @if($product->image)
                  <img src="{{ Storage::url($product->image) }}" style="width:40px;height:40px;object-fit:cover;border-radius:7px">
                @else
                  @include('seller.partials.icon',['name'=>'bag','size'=>20])
                @endif
              </div>
              <div style="font-weight:650;font-size:13px;overflow:hidden;text-overflow:ellipsis">{{ $product->name }}</div>
            </div>
          </td>
          <td style="font-size:12px;color:var(--muted);text-align:center;overflow:hidden;text-overflow:ellipsis">{{ $product->category->name ?? '—' }}</td>
          <td class="mono" style="text-align:center;overflow:hidden;text-overflow:ellipsis">
            @if($product->discount_price && $product->discount_price < $product->price)
              <span style="color:var(--pink-dark);font-weight:700">₱{{ number_format($product->discount_price, 2) }}</span>
              <span style="text-decoration:line-through;color:var(--muted);font-size:11px;display:block">₱{{ number_format($product->price, 2) }}</span>
            @elseif($maxPrice > $minPrice)
              ₱{{ number_format($minPrice, 2) }}–₱{{ number_format($maxPrice, 2) }}
            @else
              ₱{{ number_format($product->price, 2) }}
            @endif
          </td>
          <td style="text-align:center">
            @php $stock = $product->total_stock; @endphp
            <span style="font-size:13px;font-weight:600;{{ $stock <= 0 ? 'color:var(--danger)' : '' }}">{{ $stock }}</span>
          </td>
          <td style="text-align:center">
            @if($product->status === 'pending')
              <span class="stamp stamp-pending" style="display:inline-flex;align-items:center;gap:5px">
                @include('seller.partials.icon',['name'=>'clock','size'=>11]) Pending
              </span>
            @elseif($product->status === 'active')
              <span class="stamp stamp-active" style="display:inline-flex;align-items:center;gap:5px">
                @include('seller.partials.icon',['name'=>'check-circle','size'=>11]) Active
              </span>
            @elseif($product->status === 'rejected')
              <span class="stamp stamp-rejected" style="display:inline-flex;align-items:center;gap:5px">
                @include('seller.partials.icon',['name'=>'x','size'=>11]) Rejected
              </span>
              @if($product->rejection_note)
                <button type="button" class="rejection-note-btn" data-note="{{ $product->rejection_note }}"
                  style="border:0;background:none;cursor:pointer;color:var(--danger);padding:0 0 0 4px;vertical-align:middle" title="See reason">
                  @include('seller.partials.icon',['name'=>'file','size'=>12])
                </button>
              @endif
            @elseif($product->status === 'archived')
              <span class="stamp" style="display:inline-flex;align-items:center;gap:5px;background:var(--neutral-soft);color:var(--neutral);border-color:var(--neutral-line)">
                @include('seller.partials.icon',['name'=>'inventory','size'=>11]) Archived
              </span>
            @else
              <span class="stamp stamp-pending">{{ ucfirst($product->status) }}</span>
            @endif
          </td>
          <td style="text-align:center">
            <div class="tbl-actions" style="justify-content:center">
              <button type="button" class="btn btn-sm btn-outline icon-only" data-modal="viewProductModal-{{ $product->id }}" title="View" aria-label="View">
                @include('seller.partials.icon',['name'=>'eye','size'=>13])
              </button>
              @if($product->status === 'active')
                <form method="POST" action="{{ route('seller.inventory.archive', $product->id) }}" id="archiveForm-{{ $product->id }}">
                  @csrf @method('PATCH')
                  <input type="hidden" name="archived" value="1">
                </form>
                <button type="submit" form="archiveForm-{{ $product->id }}" class="btn btn-sm btn-outline icon-only" title="Archive" aria-label="Archive">
                  @include('seller.partials.icon',['name'=>'inventory','size'=>13])
                </button>
              @elseif($product->status === 'archived')
                <form method="POST" action="{{ route('seller.inventory.archive', $product->id) }}" id="unarchiveForm-{{ $product->id }}">
                  @csrf @method('PATCH')
                  <input type="hidden" name="archived" value="0">
                </form>
                <button type="submit" form="unarchiveForm-{{ $product->id }}" class="btn btn-sm btn-outline icon-only" title="Unarchive" aria-label="Unarchive">
                  @include('seller.partials.icon',['name'=>'check-circle','size'=>13])
                </button>
              @endif
              @if(in_array($product->status, ['pending','rejected','archived']))
                <form method="POST" action="{{ route('seller.inventory.destroy', $product->id) }}" id="removeForm-{{ $product->id }}">
                  @csrf @method('DELETE')
                </form>
                <button type="button" class="btn btn-sm btn-danger icon-only" title="Remove" aria-label="Remove"
                  onclick="confirmRemoveProduct('removeForm-{{ $product->id }}', '{{ addslashes($product->name) }}')">
                  @include('seller.partials.icon',['name'=>'x','size'=>13])
                </button>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6">
            <div class="empty" style="padding:40px 20px">
              @include('seller.partials.icon',['name'=>'bag','size'=>28,'class'=>'ic'])
              <h3>No products yet</h3>
              <p>Add your first product to get started.</p>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@include('seller.partials.add-product-modal')

{{-- View & Edit product modals --}}
@foreach($products as $product)
@include('seller.partials.view-product-modal', ['product' => $product])
@include('seller.partials.edit-product-modal', ['product' => $product])
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
// Search filter
document.getElementById('productSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#productTable tbody tr[data-status]').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Status filter
document.getElementById('statusFilter').addEventListener('change', function () {
  const val = this.value;
  document.querySelectorAll('#productTable tbody tr[data-status]').forEach(row => {
    row.style.display = (!val || row.dataset.status === val) ? '' : 'none';
  });
});

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
