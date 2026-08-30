@props([
    'user' => auth()->user(),
    'size' => 40,
])

@php
    $name = trim(($user->given_names ?? '').' '.($user->last_name ?? ''));
    $baseStyle = "width:{$size}px;height:{$size}px;border-radius:50%;display:grid;place-items:center;object-fit:cover;flex:none;overflow:hidden";
    $customStyle = $attributes->get('style');
@endphp

@if($user && $user->profile_picture)
    <img
        {{ $attributes->except('style')->merge(['class' => 'user-avatar']) }}
        src="{{ asset('storage/'.$user->profile_picture) }}"
        alt="{{ $name ?: 'User profile picture' }}"
        style="{{ $baseStyle }};{{ $customStyle }}"
    >
@else
    <img
        {{ $attributes->except('style')->merge(['class' => 'user-avatar']) }}
        src="{{ asset('images/default-avatar.png') }}"
        alt="{{ $name ?: 'Default profile picture' }}"
        style="{{ $baseStyle }};{{ $customStyle }}"
    >
@endif
