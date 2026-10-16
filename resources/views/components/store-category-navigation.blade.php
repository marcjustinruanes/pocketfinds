<nav aria-label="Store categories" style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0">
  <a href="{{ request()->url() }}">All products</a>
  @foreach($storeCategories->whereNull('parent_id') as $category)
    <a href="{{ request()->url() }}?store_category={{ $category->id }}" @if((string)request('store_category') === (string)$category->id) aria-current="page" @endif>{{ $category->name }}</a>
    @foreach($storeCategories->where('parent_id', $category->id) as $child)
      <a href="{{ request()->url() }}?store_category={{ $child->id }}" @if((string)request('store_category') === (string)$child->id) aria-current="page" @endif>{{ $category->name }} / {{ $child->name }}</a>
    @endforeach
  @endforeach
  <a href="{{ request()->url() }}?store_category=uncategorized">Uncategorized</a>
</nav>
