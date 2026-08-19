@extends('seller.layout')
@section('title', 'Inventory')
@section('page-title', 'Inventory')
@section('page-sub', 'Manage your products, prices, discounts and stock')

@section('content')
<div class="filter-bar">
  <div class="search-mini">
    <span class="ic">@include('seller.partials.icon', ['name' => 'search', 'size' => 13])</span>
    <input type="text" placeholder="Search products…">
  </div>
  <select class="select">
    <option>All Categories</option>
    <option>Food & Drinks</option><option>Clothing</option><option>Beauty</option>
    <option>Electronics</option><option>Home & Living</option><option>Hobbies</option>
  </select>
  <select class="select">
    <option>All Stock</option><option>In Stock</option><option>Low Stock</option><option>Out of Stock</option>
  </select>
  <button class="btn btn-primary" data-modal="addProductModal">
    @include('seller.partials.icon', ['name' => 'plus', 'size' => 14]) Add Product
  </button>
</div>

<div class="card">
  <div class="tabs" style="padding:0 20px;margin-bottom:0" data-tabs>
    <button class="tab active" data-tab="active">Active</button>
    <button class="tab" data-tab="archived">Archived</button>
  </div>

  <div data-panel="active">
    <table class="tbl">
      <thead><tr>
        <th>Product</th><th>Category</th><th>Price</th><th>Discount</th><th>Stock</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="inv-img">@include('seller.partials.icon', ['name' => 'bag', 'size' => 20])</div>
              <div>
                <div style="font-weight:650;font-size:13px">Sample Product</div>
                <div style="font-size:11px;color:var(--muted)">SKU-001</div>
              </div>
            </div>
          </td>
          <td style="font-size:12px;color:var(--muted)">General</td>
          <td class="mono">₱299.00</td>
          <td><span class="stamp stamp-pending">10% OFF</span></td>
          <td>
            <div style="font-size:13px;font-weight:600">10 units</div>
            <div class="stock-bar" style="width:80px"><div class="stock-fill" style="width:60%"></div></div>
          </td>
          <td><span class="stamp stamp-active">Active</span></td>
          <td>
            <div class="tbl-actions">
              <button class="btn btn-sm btn-outline" data-modal="editProductModal" title="Edit">@include('seller.partials.icon', ['name' => 'edit', 'size' => 13])</button>
              <button class="btn btn-sm btn-outline" title="Archive">@include('seller.partials.icon', ['name' => 'archive', 'size' => 13])</button>
            </div>
          </td>
        </tr>
        <tr><td colspan="7"><div class="empty" style="padding:30px 20px"><h3>No more products</h3><p>Add your first product to get started.</p></div></td></tr>
      </tbody>
    </table>
  </div>

  <div data-panel="archived" style="display:none">
    <div class="empty" style="padding:40px 20px">
      <div class="ic">@include('seller.partials.icon', ['name' => 'archive', 'size' => 28])</div>
      <h3>No archived products</h3><p>Archived items will appear here.</p>
    </div>
  </div>
</div>

@include('seller.partials.add-product-modal')

{{-- Edit product modal --}}
<div class="modal-overlay" id="editProductModal">
  <div class="modal">
    <div class="modal-head">
      <div><h3>Edit Product</h3><p>Update product details</p></div>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <div class="modal-body">
      <div class="form-row"><label>Product Name</label><input type="text" value="Sample Product"></div>
      <div class="form-grid-2">
        <div class="form-row"><label>Price (₱)</label><input type="number" value="299.00"></div>
        <div class="form-row"><label>Stock Qty</label><input type="number" value="10"></div>
      </div>
      <div class="form-row"><label>Category</label>
        <select><option>Food & Drinks</option><option>Clothing</option><option>Beauty</option><option selected>General</option></select>
      </div>
      <div class="form-row"><label>Description</label><textarea rows="3">Sample product description.</textarea></div>
      <div class="form-grid-2">
        <div class="form-row"><label>Discount (%)</label><input type="number" value="10"></div>
        <div class="form-row"><label>Voucher Code</label><input type="text" value="SAVE10"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Cancel</button>
      <button class="btn btn-primary">@include('seller.partials.icon', ['name' => 'edit', 'size' => 14]) Save Changes</button>
    </div>
  </div>
</div>
@endsection
