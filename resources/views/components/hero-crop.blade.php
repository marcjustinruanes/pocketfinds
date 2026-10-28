@props(['setting', 'slide' => 1, 'src'])
@php
  $prefix = $slide === 2 ? 'hero_2_crop_' : 'hero_crop_';
  $upload = $slide === 2 ? 'hero_image_2' : 'hero_image';
@endphp
<fieldset data-hero-crop data-upload="{{ $upload }}" style="border:1px solid var(--border);border-radius:12px;padding:16px;margin:16px 0">
  <legend style="padding:0 6px;font-size:13px;font-weight:600">Banner {{ $slide }} crop</legend>
  <div data-crop-frame style="position:relative;overflow:hidden;border-radius:8px;background:var(--border)">
    <iframe data-crop-preview title="Banner {{ $slide }} at storefront size" style="display:block;border:0;transform-origin:top left;pointer-events:none" tabindex="-1"></iframe>
  </div>
  <template data-crop-template>
    <!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8">
      <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Allura&family=Manrope:wght@400&display=swap">
      <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
      <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
      <link rel="stylesheet" href="{{ asset('css/guest-responsive.css') }}">
      <style>html,body{margin:0!important;padding:0!important;overflow:hidden!important}.pf-hero-banner{overflow:hidden!important}</style>
    </head><body class="marketplace">
      <section class="pf-hero-banner pf-overlay-{{ $setting->hero_overlay ?: 'dark' }}">
        <div id="heroPhotoFrame"><img data-crop-image class="hero-slide active" src="{{ $src }}" alt="Banner crop preview"></div>
        <div class="pf-hero-overlay"></div>
        <div class="pf-hero-content container">
          <h1 class="pf-hero-h1" data-crop-headline>{{ $setting->hero_tagline ?: 'Find It. Love It. Pocket It.' }}</h1>
          <p class="pf-hero-p" data-crop-subtitle>{{ $setting->hero_subtitle }}</p>
          <span class="pf-hero-cta" data-crop-cta>{{ $setting->hero_cta_text ?: 'Browse Products' }}</span>
        </div>
      </section>
    </body></html>
  </template>
  <p style="font-size:12px;color:var(--muted);margin:10px 0">Everything inside this frame appears in the banner at the selected size. Adjust the sliders to move the image inside the frame, then save.</p>
  <p data-crop-dimensions style="font-size:12px;color:var(--muted)" aria-live="polite"></p>
  <label style="display:block;font-size:13px;margin-bottom:12px">Preview size
    <select data-crop-size aria-label="Banner {{ $slide }} preview size">
      <option value="desktop">Desktop banner</option>
      <option value="mobile">Mobile banner</option>
      <option value="intro">Desktop opening banner</option>
    </select>
  </label>
  @foreach(['x' => ['Horizontal position', 0, 100, 50], 'y' => ['Vertical position', 0, 100, 50], 'zoom' => ['Zoom', 100, 200, 100]] as $axis => [$label, $min, $max, $default])
    <label for="{{ $prefix.$axis }}" style="display:block;font-size:13px;margin-top:10px">{{ $label }} <output data-crop-output="{{ $axis }}"></output></label>
    <input id="{{ $prefix.$axis }}" name="{{ $prefix.$axis }}" data-crop-axis="{{ $axis }}" type="range" min="{{ $min }}" max="{{ $max }}" step="1" value="{{ old($prefix.$axis, $setting->{$prefix.$axis} ?? $default) }}" style="width:100%">
    @error($prefix.$axis)<span style="color:var(--danger)">{{ $message }}</span>@enderror
  @endforeach
  <button type="button" data-crop-reset class="btn" style="margin-top:12px">Reset crop</button>
</fieldset>
