<!DOCTYPE html><html lang="en"><head>
    @include('partials.favicon')<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Activate Rider Account — PocketFinds</title><link rel="stylesheet" href="{{ asset('css/style.css') }}"><link rel="stylesheet" href="{{ asset('css/seller-application.css') }}"></head>
<body class="seller-application-page"><main class="seller-application" style="max-width:440px">
<a class="application-brand" href="{{ url('/') }}">PocketFinds</a>
@if($invitation)
<h1>Set your rider password</h1><p>Your rider account with {{ $invitation->company_name }} will be activated when you set your password.</p>
@if($errors->any())<div class="application-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ route('rider.activation.store', $token) }}">@csrf
<label>Password<input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="128" required></label>
<small>At least 8 characters with uppercase, lowercase, a number and a symbol.</small>
<label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="128" required></label>
<div class="application-actions"><button type="submit">Set password and activate</button></div>
</form>
@else
<h1>Activation link unavailable</h1><p>This link has expired, has been replaced, or has already been used. Ask your logistics admin to resend the activation email.</p><a href="{{ route('login') }}">Back to sign in</a>
@endif
</main></body></html>
