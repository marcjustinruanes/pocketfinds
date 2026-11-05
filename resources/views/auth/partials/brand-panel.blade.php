@php
    $signInBrand = $signInBrand ?? request()->routeIs('login');
    $collageCrops = $signInBrand && \Illuminate\Support\Facades\Schema::hasColumn('settings', 'auth_collage_crops')
        ? (json_decode(\Illuminate\Support\Facades\DB::table('settings')->where('id', 1)->value('auth_collage_crops') ?? '{}', true) ?: []) : [];
@endphp
<section class="auth-brand-panel" aria-label="PocketFinds">
    <a class="auth-logo" href="{{ url('/') }}" aria-label="PocketFinds home">
        <span class="auth-logo-mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="" class="brand-logo-img"></span>
        @unless($signInBrand)
            <span><span class="auth-logo-pocket">Pocket</span><span class="auth-logo-finds">Finds</span></span>
        @endunless
    </a>
    <div class="auth-brand-content">
        @if($signInBrand)
            <div class="auth-shopping-collage" aria-hidden="true">
                <div class="auth-collage-card auth-collage-card-main"><img src="{{ route('auth.collage-image') }}" alt="" style="{{ \App\Support\AuthCollage::style($collageCrops, 'main') }}"></div>
                <div class="auth-collage-sale-photo">
                    <div class="auth-collage-card auth-collage-card-back"><img src="{{ route('auth.collage-slot', 'back') }}" alt="" style="{{ \App\Support\AuthCollage::style($collageCrops, 'back') }}"></div>
                    <span class="auth-collage-deal"><svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 13-7 1L2.5 36a1.5 1.5 0 0 0 .8 2l15 7a1.5 1.5 0 0 0 2-.8l1.2-3Z" fill="currentColor"/><path d="m27 10-11 6v25a1.5 1.5 0 0 0 1.5 1.5h21A1.5 1.5 0 0 0 40 41V16l-8-5" fill="#faf5f8"/><path d="M31 6c-1-3-4-4-6-2-3 3-2 10 1 13 1.5 1.5 4 2 5 .5 1-1.5-.5-3-2-3"/><path d="m22 35 12-12"/><circle cx="23" cy="24" r="2.5"/><circle cx="33" cy="34" r="2.5"/></svg></span>
                </div>
                <div class="auth-collage-card auth-collage-card-front">
                    <img src="{{ route('auth.collage-slot', 'front') }}" alt="" style="{{ \App\Support\AuthCollage::style($collageCrops, 'front') }}">
                </div>
                <span class="auth-collage-cart" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" data-collage-icon="shopping-cart"><path d="M2 3h3l3 12h11l2-9H6"/><path d="M8 15l-1 3h12M10 6v6M15 6v6M7 10h13"/><circle cx="9" cy="21" r="1.4" fill="currentColor" stroke="none"/><circle cx="18" cy="21" r="1.4" fill="currentColor" stroke="none"/></svg></span>
            </div>
        @endif
        <h1 class="auth-brand-title">Find It.<br> <span class="auth-tagline-love">Love It.</span><br> <span class="auth-tagline-pocket">Pocket It.</span></h1>
    </div>
</section>
