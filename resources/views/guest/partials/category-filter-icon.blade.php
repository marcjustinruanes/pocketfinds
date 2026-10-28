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
@include('buyer.partials.icon', ['name' => $categoryIcon, 'size' => 20])
