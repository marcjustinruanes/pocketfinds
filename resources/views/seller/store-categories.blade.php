@extends('seller.layout')
@section('title', 'Store Categories')
@section('page-title', 'Store Categories')
@section('page-sub', 'Organize products within your store')
@section('content')
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<p><a href="{{ route('seller.inventory') }}">Back to inventory</a>. Products can belong to up to 3 store categories. Platform categories stay unchanged.</p>
<div class="card"><div class="card-pad">
  <h2>Create category</h2>
  <form method="POST" action="{{ route('seller.store-categories.store') }}">
    @csrf
    <div class="form-row"><label for="new-category-name">Name (1–50 characters)</label><input type="text" id="new-category-name" name="name" value="{{ old('name') }}" maxlength="50" required></div>
    <div class="form-row"><label for="new-category-parent">Parent (optional; maximum 2 levels)</label><select name="parent_id" id="new-category-parent"><option value="">None</option>@foreach($categories->whereNull('parent_id') as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}</option>@endforeach</select></div>
    <button class="btn btn-primary" type="submit">Create category</button>
  </form>
</div></div>
<div id="storeCategoryList">
@foreach($categories as $category)
  <section class="card" data-category-id="{{ $category->id }}" style="margin-top:16px"><div class="card-pad">
    <h2>{{ $category->name }} @if(!$category->is_visible)<small>(Hidden)</small>@endif</h2>
    <p>{{ $category->products->count() }} product(s) @if($category->parent_id) · Subcategory @endif</p>
    <form method="POST" action="{{ route('seller.store-categories.update', $category) }}">
      @csrf @method('PATCH')
      <div class="form-row"><label>Name<input type="text" name="name" value="{{ $category->name }}" maxlength="50" required></label></div>
      <div class="form-row"><label>Parent<select name="parent_id"><option value="">None</option>@foreach($categories->whereNull('parent_id')->where('id', '!=', $category->id) as $parent)<option value="{{ $parent->id }}" @selected($parent->id === $category->parent_id)>{{ $parent->name }}</option>@endforeach</select></label></div>
      <input type="hidden" name="is_visible" value="0"><label><input type="checkbox" name="is_visible" value="1" @checked($category->is_visible)> Show on store page</label>
      <button class="btn btn-outline" type="submit">Save category</button>
    </form>
    <form method="POST" action="{{ route('seller.store-categories.products', $category) }}" style="margin-top:12px">
      @csrf
      <div class="form-row"><label>Products<select name="product_ids[]" multiple size="{{ min(6, max(2, $products->count())) }}">@foreach($products as $product)<option value="{{ $product->id }}" @selected($category->products->contains('id', $product->id))>{{ $product->name }}</option>@endforeach</select></label></div>
      <input type="hidden" name="empty_selection" value="1">
      <button class="btn btn-outline" type="submit">Save products</button>
    </form>
    <div style="display:flex;gap:8px;margin-top:12px">
      <button class="btn btn-outline" type="button" data-move-category="up">Move up</button><button class="btn btn-outline" type="button" data-move-category="down">Move down</button>
      @php
        $affected = $category->children->pluck('id')->push($category->id);
        $productCount = \Illuminate\Support\Facades\DB::table('store_category_products')->whereIn('store_category_id', $affected)->distinct()->count('product_id');
        $warning = 'Delete '.$category->name.'? '.($category->children->isEmpty() ? '' : 'Also deletes: '.$category->children->pluck('name')->join(', ').'. ').$productCount.' product(s) affected. Products are kept and move to Uncategorized if they have no other category.';
      @endphp
      <form method="POST" action="{{ route('seller.store-categories.destroy', $category) }}" data-delete-category data-warning="{{ $warning }}">@csrf @method('DELETE')<input type="hidden" name="confirm" value="1"><button class="btn btn-danger" type="submit">Delete category</button></form>
    </div>
  </div></section>
@endforeach
</div>
<form method="POST" action="{{ route('seller.store-categories.reorder') }}" id="categoryReorderForm">@csrf</form>
<script>
document.querySelectorAll('[data-delete-category]').forEach(form => form.addEventListener('submit', event => { if (!confirm(form.dataset.warning)) event.preventDefault(); }));
document.querySelectorAll('form:has([name="empty_selection"])').forEach(form => form.addEventListener('submit', () => {
  if (!form.querySelector('option:checked')) { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'product_ids'; input.value = ''; form.appendChild(input); }
}));
document.querySelectorAll('[data-move-category]').forEach(button => button.addEventListener('click', () => {
  const card = button.closest('[data-category-id]'), list = card.parentElement;
  if (button.dataset.moveCategory === 'up' && card.previousElementSibling) list.insertBefore(card, card.previousElementSibling);
  else if (button.dataset.moveCategory === 'down' && card.nextElementSibling) list.insertBefore(card.nextElementSibling, card);
  else return;
  const form = document.getElementById('categoryReorderForm');
  [...list.children].forEach(child => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'ids[]'; input.value = child.dataset.categoryId; form.appendChild(input); });
  form.submit();
}));
</script>
@endsection
