@props(['title', 'subtitle' => null])
@php
    $showcaseProduct = \App\Models\Product::where('status', 'active')
        ->whereNotNull('image')
        ->inRandomOrder()
        ->first();
@endphp
<section class="auth-brand-panel">
    <div class="auth-brand-top">
        <a class="auth-logo" href="{{ url('/') }}">
            <span class="auth-logo-mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></span>
            <span>PocketFinds</span>
        </a>
    </div>

    <div class="auth-brand-showcase">
        <div class="auth-showcase-card">
            @if($showcaseProduct)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($showcaseProduct->image) }}" alt="{{ $showcaseProduct->name }}">
            @else
                <svg class="auth-showcase-icon" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            @endif
        </div>
        <span class="auth-showcase-caption">Good finds, brighter days</span>
    </div>

    <div class="auth-brand-content">
        <h1 class="auth-brand-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="auth-brand-text">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="auth-brand-footer">© {{ date('Y') }} PocketFinds. All rights reserved.</div>
</section>
