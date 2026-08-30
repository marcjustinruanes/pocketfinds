@props(['path' => null, 'label'])
@php
    $isPdf = $path && str_ends_with(strtolower($path), '.pdf');
    $url = $path ? \Illuminate\Support\Facades\Storage::url($path) : null;
    $type = $isPdf ? 'pdf' : 'image';
@endphp
@if($path)
<button type="button" class="doc-thumb" data-doc-trigger data-src="{{ $url }}" data-type="{{ $type }}" data-title="{{ $label }}">
    <span class="doc-thumb-img">
        @if($isPdf)
            <x-admin-icon name="file" />
        @else
            <img src="{{ $url }}" alt="{{ $label }}" loading="lazy">
        @endif
    </span>
    <span class="doc-thumb-label"><x-admin-icon name="eye" /> {{ $label }}</span>
</button>
@else
<div class="doc-thumb missing">
    <span class="doc-thumb-img"><x-admin-icon name="close" /></span>
    <span class="doc-thumb-label">{{ $label }} — not submitted</span>
</div>
@endif
