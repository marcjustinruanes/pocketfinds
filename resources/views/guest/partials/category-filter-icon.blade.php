@php
  $categoryName = strtolower($category->name ?? '');
  $categoryIcon = match (true) {
      $categoryName === '' => 'category-all',
      str_contains($categoryName, 'women') => 'category-women',
      str_contains($categoryName, 'men') => 'category-men',
      str_contains($categoryName, 'apparel'), str_contains($categoryName, 'fashion') => 'shirt',
      str_contains($categoryName, 'electronic'), str_contains($categoryName, 'gadget') => 'category-electronics',
      str_contains($categoryName, 'beauty'), str_contains($categoryName, 'health') => 'category-bottles',
      str_contains($categoryName, 'home'), str_contains($categoryName, 'garden') => 'home',
      str_contains($categoryName, 'food') => 'food',
      str_contains($categoryName, 'sport'), str_contains($categoryName, 'outdoor') => 'category-camping',
      str_contains($categoryName, 'pet') => 'category-paw',
      str_contains($categoryName, 'kid'), str_contains($categoryName, 'baby') => 'category-teddy',
      default => 'tag',
  };
@endphp
@if(in_array($categoryIcon, ['category-all', 'category-men', 'category-electronics'], true))
<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
  @if($categoryIcon === 'category-all')
    <rect x="3" y="2" width="7" height="11" rx="1.5"/>
    <rect x="14" y="2" width="7" height="5" rx="1.5"/>
    <rect x="3" y="17" width="7" height="5" rx="1.5"/>
    <rect x="14" y="11" width="7" height="11" rx="1.5"/>
  @elseif($categoryIcon === 'category-electronics')
    <path d="M12 16H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1M2 12h10M9 16v5M5 21h8"/>
    <rect x="15" y="9" width="7" height="13" rx="1.75"/>
    <path d="M17.5 12h2"/>
    <circle cx="18.5" cy="19" r=".75" fill="currentColor" stroke="none"/>
  @else
    <path d="m8 3-4 3-1 15h18L20 6l-4-3H8Z"/>
    <path d="m8 3 1 6 3-3 3 3 1-6M7 14v7m10-7v7"/>
    <path d="m10 4 2 2 2-2h-4Zm1 4h2l1.5 7-2.5 3-2.5-3L11 8Z" fill="currentColor" stroke="none"/>
  @endif
</svg>
@else
  @include('buyer.partials.icon', ['name' => $categoryIcon, 'size' => 20])
@endif
