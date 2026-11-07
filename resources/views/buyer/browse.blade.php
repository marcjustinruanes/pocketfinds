@extends('buyer.layout')
@section('title', 'Products')
@section('hide-page-heading', true)

@push('head')
<link rel="stylesheet" href="{{ asset('css/guest-catalog.css') }}?v={{ filemtime(public_path('css/guest-catalog.css')) }}">
@endpush

@section('content')
<section id="products" aria-label="Products">
  <div class="pf-catalog-layout">
    <div class="pf-catalog-sidebar-stack">
      <aside class="pf-catalog-sidebar-shell" aria-label="Product categories">
        <span class="pf-catalog-filter-label">Category</span>
        <nav class="pf-catalog-icon-rail" aria-label="Filter products by category">
          <a href="{{ route('buyer.browse', request()->except('category', 'page')) }}" aria-label="All categories" @if(!request('category')) aria-current="true" @endif>
            @include('guest.partials.category-filter-icon', ['category' => null])<span>All categories</span>
          </a>
          @foreach($categories as $category)
            <a href="{{ route('buyer.browse', array_merge(request()->only('q','min_price','max_price','sort'), ['category' => $category->id])) }}" aria-label="{{ $category->name }}" @if((string)request('category') === (string)$category->id) aria-current="true" @endif>
              @include('guest.partials.category-filter-icon', ['category' => $category])<span>{{ $category->name }}</span>
            </a>
          @endforeach
        </nav>
      </aside>
      <aside class="pf-catalog-price-sidebar" aria-label="Price range">
        <form class="pf-product-filters" action="{{ route('buyer.browse') }}" method="GET" id="browsePriceFilter">
          @foreach(request()->only('q','category','sort') as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
          @endforeach
          <div class="pf-price-filter">
            <label class="pf-number-field"><span>Min price (₱)</span><input type="number" name="min_price" min="0" max="1000000000" step="0.01" placeholder="0" aria-label="Minimum price in pesos" value="{{ request('min_price') }}"></label>
            <span class="pf-catalog-price-divider" aria-hidden="true">|</span>
            <label class="pf-number-field"><span>Max price (₱)</span><input type="number" name="max_price" min="0" max="1000000000" step="0.01" placeholder="0" aria-label="Maximum price in pesos" value="{{ request('max_price') }}"></label>
            <a class="pf-catalog-price-reset" href="{{ route('buyer.browse', request()->only('q','category','sort')) }}">Reset</a>
          </div>
          @error('min_price')<p class="pf-filter-error" role="alert">{{ $message }}</p>@enderror
          @error('max_price')<p class="pf-filter-error" role="alert">{{ $message }}</p>@enderror
        </form>
      </aside>
    </div>
  <div class="pf-catalog-results">
    <div class="pf-product-grid" id="browseGrid">
      @forelse($products as $p)
        @include('guest.partials.pf-product-card', ['p' => $p, 'productRoute' => 'buyer.product', 'catalogCard' => true])
      @empty
        <div class="empty" style="grid-column:1/-1">
          @include('buyer.partials.icon', ['name' => 'bag', 'size' => 28])
          <h3>No products found</h3>
          <p>Try another search using the navigation bar.</p>
        </div>
      @endforelse
    </div>
  </div>
  </div>
</section>
<script>
(() => {
 const form=document.getElementById('browsePriceFilter');
 form.addEventListener('change',()=>{if(form.reportValidity())form.requestSubmit();});
 form.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();if(form.reportValidity())form.requestSubmit();}});
})();
</script>
@endsection
