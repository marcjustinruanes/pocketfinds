@extends('layouts.auth')
@section('title', 'Activate rider account — PocketFinds')
@section('content')
<main class="auth-card" style="max-width:480px;margin:48px auto;padding:28px">
  <h1>Activate your rider account</h1><p>Welcome, {{ $user->given_names }}. Set a password for your account with {{ $user->business_name }}.</p>
  @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
  <form method="POST" action="{{ route('rider.activation.submit', ['user' => $user->id, 'token' => $token]) }}">
    @csrf
    <label class="auth-label" for="password">Password</label><input class="auth-input" type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
    <p class="field-hint">Use uppercase and lowercase letters, a number, a special character, and at least 8 characters.</p>
    <label class="auth-label" for="password_confirmation">Confirm password</label><input class="auth-input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    <button class="auth-btn" type="submit">Activate account</button>
  </form>
</main>
@endsection
