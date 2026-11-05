@extends('seller.layout')
@section('title','Shop categories')
@section('page-title','Shop categories')
@section('content')
<div class="shop-categories-page">
  @include('commerce.messages')
  <div class="shop-categories-intro">
    <div><p>Organize your products into categories and subcategories.</p><span>Each product can appear in up to three shop categories.</span></div>
    <span class="shop-categories-count">{{ $categories->count() }} {{ \Illuminate\Support\Str::plural('category', $categories->count()) }}</span>
  </div>

  <section class="card shop-category-panel" aria-labelledby="shop-category-title">
    <div class="card-head"><div><h2 id="shop-category-title">Manage categories</h2></div></div>
    <div class="card-pad">
      <form class="shop-category-create" method="POST" action="{{ route('seller.shop-categories.save') }}">
        @csrf
        <label class="form-row">Category name<input name="name" required maxlength="50" placeholder="e.g. Home and Living"></label>
        <label class="form-row">Parent category<select name="parent_id"><option value="">None — top-level category</option>@foreach($categories->whereNull('parent_id') as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
        <button class="btn btn-primary">Add category</button>
      </form>

      @if($categories->isNotEmpty())
      <div class="shop-category-list">
        @foreach($categories as $category)
        <article class="shop-category-row">
          <span class="shop-category-icon">@include('seller.partials.icon',['name'=>'tag','size'=>16])</span>
          <form class="shop-category-edit" method="POST" action="{{ route('seller.shop-categories.save') }}">
            @csrf <input type="hidden" name="id" value="{{ $category->id }}">
            <label class="sr-only" for="category-name-{{ $category->id }}">Category name</label>
            <input id="category-name-{{ $category->id }}" name="name" value="{{ $category->name }}" required maxlength="50" aria-label="Category name">
            <select name="parent_id" aria-label="Parent category"><option value="">Top-level category</option>@foreach($categories->whereNull('parent_id')->where('id','!=',$category->id) as $parent)<option value="{{ $parent->id }}" @selected($category->parent_id===$parent->id)>{{ $parent->name }}</option>@endforeach</select>
            <button class="btn btn-outline">Save</button>
          </form>
          <details class="shop-category-remove"><summary>Remove</summary><div><p>Removing a parent also removes its subcategories. Products are kept.</p><form method="POST" action="{{ route('seller.shop-categories.delete',$category->id) }}">@csrf @method('DELETE')<button class="btn btn-danger">Remove category</button></form></div></details>
        </article>
        @endforeach
      </div>
      @else
      <div class="shop-category-empty"><span class="shop-category-icon">@include('seller.partials.icon',['name'=>'tag','size'=>16])</span><strong>No shop categories yet</strong><p>Create your first category above to organize products in your store.</p></div>
      @endif
    </div>
  </section>

  <section class="card shop-category-panel shop-product-assignment" aria-labelledby="product-category-title">
    <div class="card-head"><div><h2 id="product-category-title">Product categories</h2><p>Choose up to three shop categories for each product.</p></div></div>
    <div class="card-pad">
      @forelse($products as $product)
      <form class="shop-product-category-row" method="POST" action="{{ route('seller.shop-categories.assign',$product) }}">
        @csrf
        <div class="shop-product-category-heading"><div><strong>{{ $product->name }}</strong><span>Assign this product to relevant categories.</span></div><button class="btn btn-outline">Save categories</button></div>
        <div class="shop-product-category-options">
          @foreach($categories as $category)<label><input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(($assignments->get($product->id,collect()))->contains('shop_category_id',$category->id))><span>{{ $category->parent_id ? $categories->firstWhere('id',$category->parent_id)?->name.' / ' : '' }}{{ $category->name }}</span></label>@endforeach
        </div>
      </form>
      @empty
      <div class="shop-category-empty"><span class="shop-category-icon">@include('seller.partials.icon',['name'=>'bag','size'=>16])</span><strong>No products to categorize yet</strong><p>Your products will appear here so you can assign shop categories.</p></div>
      @endforelse
    </div>
  </section>
</div>
@endsection
