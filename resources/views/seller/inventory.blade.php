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

@if(session('product_success'))
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 16px;border-radius:9px;font-size:13px;margin-bottom:18px">{{ session('product_success') }}</div>
@endif
@error('image')
  <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 16px;border-radius:9px;font-size:13px;margin-bottom:18px">{{ $message }}</div>
@enderror

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
  </select>
  <button class="btn btn-primary" data-modal="addProductModal" {{ !$seller->category_id ? 'disabled title=Set shop category first' : '' }}>
    @include('seller.partials.icon',['name'=>'plus','size'=>14]) Add Product
  </button>
</div>

<div class="card">
  <div class="card-pad" style="padding:0">
    <table class="tbl" id="productTable">
      <thead>
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($products as $product)
        <tr data-status="{{ $product->status }}">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="inv-img">
                @if($product->image)
                  <img src="{{ Storage::url($product->image) }}" style="width:40px;height:40px;object-fit:cover;border-radius:7px">
                @else
                  @include('seller.partials.icon',['name'=>'bag','size'=>20])
                @endif
              </div>
              <div>
                <div style="font-weight:650;font-size:13px">{{ $product->name }}</div>
                @if($product->sku)
                  <div style="font-size:11px;color:var(--muted)">{{ $product->sku }}</div>
                @endif
                @if($product->description)
                  <div style="font-size:11px;color:var(--muted);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $product->description }}</div>
                @endif
              </div>
            </div>
          </td>
          <td style="font-size:12px;color:var(--muted)">{{ $product->category->name ?? '—' }}</td>
          <td class="mono">₱{{ number_format($product->price, 2) }}</td>
          <td>
            @php $stock = $product->total_stock; @endphp
            <span style="font-size:13px;font-weight:600">{{ $stock }}</span>
            @if(!empty($product->variations))
              <div style="font-size:11px;color:var(--muted);margin-top:2px">
                {{ collect($product->variations)->pluck('name')->join(', ') }}
              </div>
            @endif
          </td>
          <td>
            @if($product->status === 'pending')
              <span class="stamp stamp-pending" style="display:inline-flex;align-items:center;gap:5px">
                @include('seller.partials.icon',['name'=>'clock','size'=>11]) Pending Review
              </span>
            @elseif($product->status === 'active')
              <span class="stamp stamp-active" style="display:inline-flex;align-items:center;gap:5px">
                @include('seller.partials.icon',['name'=>'check-circle','size'=>11]) Active
              </span>
            @elseif($product->status === 'rejected')
              <div>
                <span class="stamp stamp-rejected" style="display:inline-flex;align-items:center;gap:5px">
                  @include('seller.partials.icon',['name'=>'x','size'=>11]) Rejected
                </span>
                @if($product->rejection_note)
                  <button type="button" class="btn btn-sm btn-outline rejection-note-btn"
                    data-note="{{ $product->rejection_note }}"
                    style="margin-top:4px;display:inline-flex;align-items:center;gap:5px;font-size:11px">
                    @include('seller.partials.icon',['name'=>'file','size'=>11]) See Reason
                  </button>
                @endif
              </div>
            @else
              <span class="stamp stamp-pending">{{ ucfirst($product->status) }}</span>
            @endif
          </td>
          <td>
            @if(in_array($product->status, ['pending','rejected']))
              <form method="POST" action="{{ route('seller.inventory.destroy', $product->id) }}" onsubmit="return confirm('Remove this product?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-danger" type="submit" title="Remove">
                  @include('seller.partials.icon',['name'=>'x','size'=>13])
                </button>
              </form>
            @endif
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
