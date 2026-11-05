@php
    $isColor = preg_match('/^colou?rs?$/i', $groupName);
    $isSize = preg_match('/^sizes?$/i', $groupName);
    $value = trim($option['value']);
    $sizes = ['extra extra small' => 'XXS', 'extra small' => 'XS', 'small' => 'S', 'medium' => 'M', 'large' => 'L', 'extra large' => 'XL', 'extra extra large' => 'XXL', '2xl' => 'XXL', '3xl' => '3XL', '4xl' => '4XL'];
    $color = \App\Support\ProductColor::resolve($option);
    $validColor = $color !== null;
@endphp
@if($isColor && $validColor)
    <span class="pd-option-swatch" style="background-color:{{ $color }}" aria-hidden="true"></span>
@elseif($isColor && !empty($option['image']))
    <img class="pd-option-image" src="{{ $option['image'] }}" alt="{{ $value }}" loading="lazy">
@endif
<span class="pd-option-value">{{ $isSize ? ($sizes[strtolower($value)] ?? $value) : $value }}</span>
