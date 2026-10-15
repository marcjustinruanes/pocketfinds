@props(['id', 'label', 'active' => false])
<div class="navigation-group">
    <button type="button" class="navigation-group-toggle" data-navigation-group aria-expanded="{{ $active ? 'true' : 'false' }}" aria-controls="{{ $id }}">
        <span>{{ $label }}</span><span class="navigation-chevron" aria-hidden="true">⌄</span>
    </button>
    <div id="{{ $id }}" class="navigation-group-items" @if(!$active) hidden @endif>
        {{ $slot }}
    </div>
</div>
