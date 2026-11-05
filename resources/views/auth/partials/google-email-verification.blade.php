@if(!($isGoogleSignup ?? false))
@php
    $verifiedEmail = session('registration_verified_email', []);
    $representativeReturn=request('email_target')==='representative';
    $representativeProof=session('registration_verified_representative_email',[]);
    $googleVerifiedEmail = !$representativeReturn && request()->boolean('google_return') && ($verifiedEmail['method'] ?? '') === 'google' && ($verifiedEmail['expires_at'] ?? 0) > now()->timestamp
        ? ($verifiedEmail['email'] ?? null) : null;
    $googleResult=['email'=>$googleVerifiedEmail,'returning'=>request()->boolean('google_return'),
        'target'=>$representativeReturn?'representative':'company',
        'representative_email'=>$representativeReturn&&request()->boolean('google_return')&&($representativeProof['method']??'')==='google'&&($representativeProof['expires_at']??0)>now()->timestamp?($representativeProof['email']??null):null,
        'representative_error'=>$representativeReturn?session('google_email_error'):null,
        'error'=>!$representativeReturn&&request()->boolean('google_return')?session('google_email_error'):null];
@endphp
<script type="application/json" id="registrationGoogleResult">@json($googleResult)</script>
<script defer src="{{ asset('js/registration-draft.js') }}?v={{ filemtime(public_path('js/registration-draft.js')) }}"></script>
<div class="registration-google-email">
    <span class="registration-email-or">or</span>
    <button type="button" class="registration-google-button" id="registrationGoogleEmail" data-type="{{ ($regType ?? '') === 'hub_staff' ? 'logistics-staff' : ($regType ?? request('type', 'buyer')) }}" data-start-url="{{ route('register.google.start') }}" data-reset-url="{{ route('register.google.reset') }}" data-complete-url="{{ route('register.google.complete') }}">
        <svg width="19" height="19" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.9 14.9 0 0 1 0-9.18l-7.98-6.19a24 24 0 0 0 0 21.56z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6C30 37.67 27.48 38.5 24 38.5c-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
        Continue with Google
    </button>
    <span class="field-hint" id="googleEmailHint" role="status">Verify your email with Google instead of a code.</span>
</div>
@endif
