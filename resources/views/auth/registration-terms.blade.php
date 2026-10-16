@extends('layouts.auth')
@section('title', 'Terms and Privacy Policy — PocketFinds')
@section('content')
<main class="auth-card" style="max-width:760px;margin:32px auto;padding:28px">
  <h1>Terms and Privacy Policy</h1>
  @forelse($policies as $policy)
    <h2>{{ $policy->title }}</h2>
    <div style="white-space:pre-wrap">{{ $policy->content }}</div>
  @empty
    <p>The administrator has not published these policies yet.</p>
  @endforelse
</main>
@endsection
