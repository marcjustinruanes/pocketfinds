<div data-settings-panel="collage" style="display:none">
    <h2 class="settings-section-title">Account Photos</h2>
    <p class="settings-section-sub">Change the collage photos on sign-in, registration, and password recovery. Adjust each crop, then save.</p>
    <div class="auth-collage-editors">
        @foreach(\App\Support\AuthCollage::SLOTS as $slot => $label)
            @php
                $crop = \App\Support\AuthCollage::crop($setting->auth_collage_crops ?? [], $slot);
                $retry = session('collage_slot') === $slot;
            @endphp
            <form class="card auth-collage-editor" data-auth-collage-editor method="POST" action="{{ route('admin.settings.auth-collage.update', $slot) }}" enctype="multipart/form-data">
                @csrf
                <div class="card-pad">
                    <h3>{{ $label }}</h3>
                    <div class="auth-collage-crop-frame" style="aspect-ratio:{{ $slot === 'back' ? '1.31' : '1' }}">
                        <img data-collage-preview src="{{ route('auth.collage-slot', $slot) }}" alt="{{ $label }} crop preview" style="{{ \App\Support\AuthCollage::style($setting->auth_collage_crops ?? [], $slot) }}">
                    </div>
                    <div class="form-row">
                        <label for="collage-upload-{{ $slot }}">Replace photo</label>
                        <input id="collage-upload-{{ $slot }}" name="collage_image" type="file" accept="image/jpeg,image/png,image/webp" data-collage-upload>
                        <span class="hint">JPG, PNG or WebP, up to 5 MB.</span>
                    </div>
                    @if($retry && $errors->any())<p class="auth-collage-editor-error" role="alert">{{ $errors->first() }}</p>@endif
                    <p data-collage-upload-error class="auth-collage-editor-error" role="alert" hidden></p>
                    @foreach(['x' => ['Horizontal position', 0, 100], 'y' => ['Vertical position', 0, 100], 'zoom' => ['Zoom', 100, 200]] as $axis => [$axisLabel, $min, $max])
                        <div class="auth-collage-slider">
                            <label for="collage-{{ $slot }}-{{ $axis }}">{{ $axisLabel }} <output data-collage-output="{{ $axis }}"></output></label>
                            <input id="collage-{{ $slot }}-{{ $axis }}" name="crop_{{ $axis }}" data-collage-axis="{{ $axis }}" type="range" min="{{ $min }}" max="{{ $max }}" step="1" value="{{ $retry ? old('crop_'.$axis, $crop[$axis]) : $crop[$axis] }}">
                        </div>
                    @endforeach
                    <div class="auth-collage-editor-actions">
                        <button class="btn" type="button" data-collage-reset>Reset crop</button>
                        <button class="btn btn-primary" type="submit">Save photo</button>
                    </div>
                </div>
            </form>
        @endforeach
    </div>
</div>
@push('head')
<link rel="stylesheet" href="{{ asset('css/auth-collage-editor.css') }}?v={{ filemtime(public_path('css/auth-collage-editor.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/auth-collage-editor.js') }}?v={{ filemtime(public_path('js/auth-collage-editor.js')) }}" defer></script>
@endpush
