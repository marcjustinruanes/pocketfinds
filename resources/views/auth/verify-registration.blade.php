@extends('layouts.auth')
@section('title', 'Verify your email — PocketFinds')
@section('content')
<div class="auth-card" style="max-width:480px;margin:48px auto;padding:28px">
  <h1 class="auth-title">Verify your email</h1>
  <p>Enter the six-digit code sent to {{ $email }}. It expires after five minutes.</p>
  @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
  @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
  <form method="POST" action="{{ route('register.verify-otp') }}">
    @csrf
    <label class="auth-label" for="otp">Verification code</label>
    <input class="auth-input" id="otp" name="otp" required pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code">
    <button class="auth-btn" type="submit">Verify</button>
  </form>
  <form method="POST" action="{{ route('register.send-otp') }}" style="margin-top:16px">@csrf<button type="submit" class="auth-btn">Resend code</button></form>
</div>
@endsection
