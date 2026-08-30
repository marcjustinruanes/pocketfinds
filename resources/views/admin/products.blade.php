@extends('admin.layout')
@section('title', 'Product Reviews')
@section('page-title', 'Product Reviews')
@section('page-sub', 'Review and manage seller product submissions')
@php use Illuminate\Support\Facades\Storage; @endphp

@section('content')
@if(session('success'))
  <div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>
@endif

@php
  $pendingProducts   = $products->where('status', 'pending')->count();
  $activeProducts    = $products->where('status', 'active')->count();
  $rejectedProducts  = $products->where('status', 'rejected')->count();
  $outOfStockProducts = $products->filter(fn($p) => $p->total_stock <= 0)->count();
@endphp
<div class="kpi-grid">
  <button type="button" class="kpi kpi-filter active" data-status-kpi="">
    <div class="label">Total Submissions</div>
    <div class="value">{{ $products->count() }}</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="pending">
    <div class="label">Pending Review</div>
    <div class="value">{{ $pendingProducts }}</div>
    <div class="delta {{ $pendingProducts > 0 ? 'down' : 'up' }}">Needs attention</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="active">
    <div class="label">Approved</div>
    <div class="value">{{ $activeProducts }}</div>
    <div class="delta up">Live in shop</div>
  </button>
  <button type="button" class="kpi kpi-filter" data-status-kpi="rejected">
    <div class="label">Rejected</div>
    <div class="value">{{ $rejectedProducts }}</div>
  </button>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Product Submissions</h2><p>{{ $products->count() }} total</p></div>
  </div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search product, seller or SKU..." data-table-search="productsTable">
      </div>
      <select class="select" id="statusFilter">
        <option value="">All Status</option>
        <option value="pending">Pending Review</option>
        <option value="active">Approved</option>
        <option value="rejected">Rejected / Declined</option>
        <option value="outofstock">Out of Stock</option>
      </select>
    </div>

    <div class="table-wrap">
    <table class="dtable" id="productsTable">
      <thead>
        <tr><th>Product</th><th>Seller</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Submitted</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($products as $product)
        <tr class="rail-row rail-{{ $product->status }}" data-status="{{ $product->status }}" data-stock="{{ $product->total_stock <= 0 ? 'out' : 'in' }}">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              @if($product->image)
                <img src="{{ Storage::url($product->image) }}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid var(--border)">
              @else
                <div style="width:44px;height:44px;background:var(--paper);border-radius:8px;display:grid;place-items:center;flex-shrink:0;color:var(--muted)">
                  <x-admin-icon name="bag" />
                </div>
              @endif
              <div style="min-width:0">
                <div style="font-weight:650;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px">{{ $product->name }}</div>
                @if($product->sku)
                  <div style="font-size:11px;color:var(--muted)" class="mono">SKU: {{ $product->sku }}</div>
                @endif
              </div>
            </div>
          </td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr($product->seller->business_name ?? $product->seller->given_names, 0, 1)) }}</div>
              <div>
                <strong>{{ $product->seller->business_name ?? ($product->seller->given_names.' '.$product->seller->last_name) }}</strong>
                <span>{{ $product->seller->given_names }} {{ $product->seller->last_name }}</span>
              </div>
            </div>
          </td>
          <td style="font-size:12px">{{ $product->category->name ?? '—' }}</td>
          <td class="mono">₱{{ number_format($product->price, 2) }}</td>
          <td class="mono" style="{{ $product->total_stock <= 0 ? 'color:var(--danger);font-weight:700' : '' }}">
            {{ $product->total_stock }}{{ $product->total_stock <= 0 ? ' · Out' : '' }}
          </td>
          <td>
            @if($product->status === 'pending')
              <span class="stamp stamp-pending">Pending</span>
            @elseif($product->status === 'active')
              <span class="stamp stamp-active">Approved</span>
            @elseif($product->status === 'rejected')
              <span class="stamp stamp-rejected">Rejected</span>
            @endif
          </td>
          <td class="mono" style="font-size:12px">{{ $product->created_at->format('M d, Y') }}</td>
          <td>
            <div class="row-actions">
              <button class="btn btn-sm btn-outline" data-modal-open="productModal-{{ $product->id }}"><x-admin-icon name="eye" /> Review</button>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="8"><div class="empty"><div class="ic"><x-admin-icon name="bag" /></div><h3>No product submissions yet</h3><p>Products sellers submit for review will appear here.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>
</div>

{{-- Per-product review modal --}}
@foreach($products as $product)
@php
  $seller = $product->seller;
  $hasVariations = !empty($product->variations);
  $hasDetails = !empty($product->details);
  $galleryImages = collect();
  if ($product->image) $galleryImages->push(Storage::url($product->image));
  foreach ($product->images as $img) { $galleryImages->push(Storage::url($img->image_url)); }
  $galleryImages = $galleryImages->unique()->values();
@endphp
<div class="modal-overlay" id="productModal-{{ $product->id }}">
  <div class="modal modal-xl">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="bag" /></span>
        <div class="modal-head-copy">
          <h3>{{ $product->name }}
            @if($product->status === 'pending')<span class="stamp stamp-pending">Pending</span>
            @elseif($product->status === 'active')<span class="stamp stamp-active">Approved</span>
            @elseif($product->status === 'rejected')<span class="stamp stamp-rejected">Rejected</span>@endif
          </h3>
          <p>{{ $seller->business_name ?? ($seller->given_names.' '.$seller->last_name) }} · Submitted {{ $product->created_at->format('M d, Y g:i A') }}</p>
        </div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>

    <div class="modal-body">
      <div class="modal-tabs" data-modal-tabs>
        <button type="button" class="tab active" data-tab-target="overview-{{ $product->id }}">Overview</button>
        <button type="button" class="tab" data-tab-target="description-{{ $product->id }}">Description</button>
        @if($hasVariations)
        <button type="button" class="tab" data-tab-target="variations-{{ $product->id }}">Variations</button>
        @endif
        <button type="button" class="tab" data-tab-target="seller-{{ $product->id }}">Seller</button>
        <button type="button" class="tab" data-tab-target="metadata-{{ $product->id }}">Metadata</button>
      </div>

      {{-- Overview --}}
      <div data-tab-panel="overview-{{ $product->id }}" class="active">
        <div class="dash-grid" style="grid-template-columns:minmax(0,1fr) minmax(240px,1fr)">
          <div class="pv-gallery">
            <button type="button" class="pv-main-img" data-lightbox-trigger data-src="{{ $galleryImages->first() ?? '' }}">
              @if($galleryImages->isNotEmpty())
                <img src="{{ $galleryImages->first() }}" alt="{{ $product->name }}" id="pvMainImg-{{ $product->id }}">
              @else
                <span style="color:var(--muted)"><x-admin-icon name="bag" /></span>
              @endif
            </button>
            @if($galleryImages->count() > 1)
            <div class="pv-thumbs">
              @foreach($galleryImages as $i => $src)
              <button type="button" class="pv-thumb {{ $i === 0 ? 'active' : '' }}" data-pv-thumb data-src="{{ $src }}" data-target="pvMainImg-{{ $product->id }}">
                <img src="{{ $src }}" alt="Image {{ $i + 1 }}">
              </button>
              @endforeach
            </div>
            @endif
          </div>
          <div>
            <div class="detail-grid" style="grid-template-columns:1fr">
              <div><div class="field-label">Category</div><div class="field-value">{{ $product->category->name ?? '—' }}</div></div>
              <div><div class="field-label">Price</div><div class="field-value mono" style="font-size:16px;font-weight:700;color:var(--pink-dark)">₱{{ number_format($product->price, 2) }}</div></div>
              <div>
                <div class="field-label">Stock</div>
                <div class="field-value {{ $product->total_stock <= 0 ? 'mono' : 'mono' }}" style="{{ $product->total_stock <= 0 ? 'color:var(--danger);font-weight:700' : '' }}">
                  {{ $product->total_stock }} {{ $hasVariations ? '(across all variations)' : 'units' }}
                  @if($product->total_stock <= 0) — Out of Stock @endif
                </div>
              </div>
              @if($product->sku)
              <div><div class="field-label">SKU</div><div class="field-value mono">{{ $product->sku }}</div></div>
              @endif
            </div>
          </div>
        </div>
      </div>

      {{-- Description --}}
      <div data-tab-panel="description-{{ $product->id }}">
        <p class="crumb" style="margin-bottom:8px">Full Description</p>
        <p style="font-size:13.5px;line-height:1.7;color:var(--text);white-space:pre-line;margin:0 0 18px">{{ $product->description ?: 'No description provided.' }}</p>
        @if($hasDetails)
        <p class="crumb" style="margin-bottom:8px">Specifications</p>
        <div class="kv-table">
          @foreach($product->details as $row)
          <div class="kv-row"><div class="kv-key">{{ $row['label'] ?? '—' }}</div><div class="kv-val">{{ $row['value'] ?? '—' }}</div></div>
          @endforeach
        </div>
        @endif
      </div>

      {{-- Variations --}}
      @if($hasVariations)
      <div data-tab-panel="variations-{{ $product->id }}">
        @foreach($product->variations as $group)
        <div class="variation-group">
          <div class="variation-group-head">{{ $group['name'] ?? 'Option' }}</div>
          <div class="variation-options">
            @forelse(($group['options'] ?? []) as $opt)
            <div class="variation-option">
              <span class="opt-name">{{ $opt['value'] ?? '—' }}</span>
              <span class="opt-stock {{ ($opt['stock'] ?? 0) <= 0 ? 'zero' : '' }}">{{ $opt['stock'] ?? 0 }} in stock</span>
            </div>
            @empty
            <span style="font-size:12px;color:var(--muted)">No options listed.</span>
            @endforelse
          </div>
        </div>
        @endforeach
      </div>
      @endif

      {{-- Seller --}}
      <div data-tab-panel="seller-{{ $product->id }}">
        <div class="seller-card">
          <div class="avatar-lg">{{ strtoupper(substr($seller->business_name ?? $seller->given_names, 0, 1)) }}</div>
          <div>
            <div class="seller-card-name">{{ $seller->business_name ?? ($seller->given_names.' '.$seller->last_name) }}</div>
            <div class="seller-card-sub">{{ $seller->given_names }} {{ $seller->last_name }} · <span class="stamp stamp-{{ $seller->status }}" style="vertical-align:middle">{{ ucfirst($seller->status) }}</span></div>
          </div>
        </div>
        <div class="detail-grid">
          <div><div class="field-label">Email</div><div class="field-value">{{ $seller->email }}</div></div>
          <div><div class="field-label">Contact No.</div><div class="field-value mono">{{ $seller->contact_no ?? '—' }}</div></div>
          <div class="full"><div class="field-label">Shop Categories</div><div class="field-value">{{ $seller->categories->pluck('name')->push($seller->category_other)->filter()->implode(', ') ?: '—' }}</div></div>
          <div><div class="field-label">Seller Since</div><div class="field-value mono">{{ $seller->created_at?->format('M d, Y') ?? '—' }}</div></div>
          <div><div class="field-label">Account Status</div><div class="field-value"><span class="stamp stamp-{{ $seller->status }}">{{ ucfirst($seller->status) }}</span></div></div>
        </div>
      </div>

      {{-- Metadata --}}
      <div data-tab-panel="metadata-{{ $product->id }}">
        <div class="kv-table">
          <div class="kv-row"><div class="kv-key">Product ID</div><div class="kv-val mono">{{ $product->id }}</div></div>
          <div class="kv-row"><div class="kv-key">Current Status</div><div class="kv-val"><span class="stamp stamp-{{ $product->status }}">{{ ucfirst($product->status === 'active' ? 'approved' : $product->status) }}</span></div></div>
          <div class="kv-row"><div class="kv-key">Date Submitted</div><div class="kv-val mono">{{ $product->created_at->format('M d, Y g:i A') }}</div></div>
          <div class="kv-row"><div class="kv-key">Last Updated</div><div class="kv-val mono">{{ $product->updated_at->format('M d, Y g:i A') }}</div></div>
          @if($product->status === 'rejected')
          <div class="kv-row"><div class="kv-key">Rejection Reason</div><div class="kv-val">{{ $product->rejection_note ?: 'No reason was recorded.' }}</div></div>
          @endif
        </div>
      </div>
    </div>

    <div class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Close</button>
      @if($product->status === 'pending')
        <button type="button" class="btn btn-outline-danger" onclick="openReject('{{ $product->id }}', '{{ addslashes($product->name) }}')"><x-admin-icon name="close" /> Reject</button>
        <form method="POST" action="{{ route('admin.products.approve', $product->id) }}" style="display:inline">
          @csrf @method('PATCH')
          <button class="btn btn-success" type="submit"><x-admin-icon name="edit" /> Approve Product</button>
        </form>
      @elseif($product->status === 'active')
        <button type="button" class="btn btn-danger" onclick="openReject('{{ $product->id }}', '{{ addslashes($product->name) }}')"><x-admin-icon name="close" /> Reject &amp; Take Down</button>
      @elseif($product->status === 'rejected')
        <form method="POST" action="{{ route('admin.products.approve', $product->id) }}" style="display:inline">
          @csrf @method('PATCH')
          <button class="btn btn-success" type="submit"><x-admin-icon name="edit" /> Approve Product</button>
        </form>
      @endif
    </div>
  </div>
</div>
@endforeach

{{-- Reject / Decline modal (shared) --}}
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon" style="background:var(--danger-soft);color:var(--danger)"><x-admin-icon name="flag" /></span>
        <div class="modal-head-copy">
          <h3>Reject Product</h3>
          <p id="rejectModalSub">Tell the seller why this product is being rejected.</p>
        </div>
      </div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>
    <form id="rejectForm" method="POST">
      @csrf @method('PATCH')
      <div class="modal-body">
        <div class="form-row">
          <label>Reason for rejection <span style="color:var(--danger)">*</span></label>
          <textarea name="note" rows="4" placeholder="e.g. This product does not match your shop category. Please only submit related products." required></textarea>
          <span class="hint" style="margin-top:4px;display:block">This message will be shown to the seller.</span>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Reject &amp; Notify Seller</button>
      </div>
    </form>
  </div>
</div>

{{-- Image lightbox --}}
<div class="modal-overlay" id="mediaLightbox">
  <div class="modal" style="width:min(720px,100%);max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-head">
      <div><h3>Image Preview</h3></div>
      <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </div>
    <div style="flex:1;overflow:auto;padding:20px;display:flex;align-items:center;justify-content:center;min-height:300px">
      <img id="mediaLightboxImg" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain" alt="Product preview">
    </div>
  </div>
</div>

<script>
function applyProductFilter(val) {
  document.querySelectorAll('#productsTable tbody tr[data-status]').forEach(row => {
    let show;
    if (!val) show = true;
    else if (val === 'outofstock') show = row.dataset.stock === 'out';
    else show = row.dataset.status === val;
    row.style.display = show ? '' : 'none';
  });
  document.querySelectorAll('.kpi-filter').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.statusKpi === val);
  });
}

document.getElementById('statusFilter').addEventListener('change', function () {
  applyProductFilter(this.value);
});

document.querySelectorAll('.kpi-filter').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('statusFilter').value = btn.dataset.statusKpi;
    applyProductFilter(btn.dataset.statusKpi);
  });
});

function openReject(id, name) {
  document.getElementById('rejectForm').action = `/admin/products/${id}/reject`;
  document.getElementById('rejectModalSub').textContent = `Tell the seller why "${name}" is being rejected.`;
  document.getElementById('rejectModal').classList.add('open');
}

// In-modal section tabs (Overview / Description / Variations / Seller / Metadata)
document.addEventListener('click', (e) => {
  const tabBtn = e.target.closest('[data-modal-tabs] .tab');
  if (tabBtn) {
    const scope = tabBtn.closest('.modal');
    scope.querySelectorAll('[data-modal-tabs] .tab').forEach(t => t.classList.remove('active'));
    tabBtn.classList.add('active');
    scope.querySelectorAll('[data-tab-panel]').forEach(p => p.classList.toggle('active', p.dataset.tabPanel === tabBtn.dataset.tabTarget));
    return;
  }
  const thumb = e.target.closest('[data-pv-thumb]');
  if (thumb) {
    const wrap = thumb.closest('.pv-gallery');
    wrap.querySelectorAll('.pv-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
    const mainImg = document.getElementById(thumb.dataset.target);
    if (mainImg) mainImg.src = thumb.dataset.src;
    wrap.querySelector('.pv-main-img').dataset.src = thumb.dataset.src;
    return;
  }
  const lightboxTrigger = e.target.closest('[data-lightbox-trigger]');
  if (lightboxTrigger && lightboxTrigger.dataset.src) {
    document.getElementById('mediaLightboxImg').src = lightboxTrigger.dataset.src;
    document.getElementById('mediaLightbox').classList.add('open');
  }
});
</script>
@endsection
