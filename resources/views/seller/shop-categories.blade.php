@extends('seller.layout')
@section('title','Shop categories')
@section('page-title','Shop categories')
@section('content')
@include('commerce.messages')
<p>Organize products into categories and subcategories. Each product can appear in up to three shop categories.</p>
<section class="card"><div class="card-pad">
 <h2>Add category</h2>
 <form method="POST" action="{{ route('seller.shop-categories.save') }}">
  @csrf <div class="form-grid-2">
   <label>Name<input name="name" required maxlength="50"></label>
   <label>Parent category<select name="parent_id"><option value="">None — top-level category</option>@foreach($categories->whereNull('parent_id') as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
  </div><button class="btn btn-primary">Add category</button>
 </form>
 @foreach($categories as $category)
 <div style="padding:14px 0;border-top:1px solid var(--border)">
  <form method="POST" action="{{ route('seller.shop-categories.save') }}" style="display:flex;gap:8px;flex-wrap:wrap">
   @csrf <input type="hidden" name="id" value="{{ $category->id }}">
   <input name="name" value="{{ $category->name }}" required maxlength="50" aria-label="Category name">
   <select name="parent_id" aria-label="Parent category"><option value="">Top-level category</option>@foreach($categories->whereNull('parent_id')->where('id','!=',$category->id) as $parent)<option value="{{ $parent->id }}" @selected($category->parent_id===$parent->id)>{{ $parent->name }}</option>@endforeach</select>
   <button class="btn btn-outline">Save</button>
  </form>
  <details><summary>Remove category</summary><p>Removing a parent also removes its subcategories. Products are kept.</p><form method="POST" action="{{ route('seller.shop-categories.delete',$category->id) }}">@csrf @method('DELETE')<button class="btn btn-danger">Remove category</button></form></details>
 </div>
 @endforeach
</div></section>
<section class="card" style="margin-top:18px"><div class="card-pad"><h2>Product categories</h2>
@forelse($products as $product)
 <form method="POST" action="{{ route('seller.shop-categories.assign',$product) }}" style="padding:14px 0;border-top:1px solid var(--border)">
  @csrf <strong>{{ $product->name }}</strong>
  <div style="display:flex;gap:12px;flex-wrap:wrap;margin:10px 0">
   @foreach($categories as $category)<label><input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(($assignments->get($product->id,collect()))->contains('shop_category_id',$category->id))> {{ $category->parent_id ? $categories->firstWhere('id',$category->parent_id)?->name.' / ' : '' }}{{ $category->name }}</label>@endforeach
  </div><button class="btn btn-outline">Save categories</button>
 </form>
@empty<p>Add products to assign them to shop categories.</p>@endforelse
</div></section>
@endsection
