@props([
    'user' => auth()->user(),
    'size' => 40,
])

@php
    $given = $user->given_names ?? $user->first_name ?? '';
    $last  = $user->last_name ?? '';
    $name  = trim($given.' '.$last);
    $initials = strtoupper(substr($given, 0, 1).substr($last, 0, 1)) ?: 'U';
    $baseStyle = "width:{$size}px;height:{$size}px;border-radius:50%;flex:none;overflow:hidden";
    $customStyle = $attributes->get('style');
@endphp

@if($user && $user->profile_picture)
    @php
        // Keep resolved URLs as-is; relative upload paths use the profile disk.
        $picUrl = str_starts_with($user->profile_picture, 'http') || str_starts_with($user->profile_picture, '/storage/')
            ? $user->profile_picture
            : $user->shopPhotoUrl();
    @endphp
    <img
        {{ $attributes->except('style')->merge(['class' => 'user-avatar']) }}
        src="{{ $picUrl }}"
        onerror="this.style.display='none';this.nextElementSibling.style.display='grid'"
        alt="{{ $name ?: 'User profile picture' }}"
        style="{{ $baseStyle }};object-fit:cover;display:block;{{ $customStyle }}"
    >
    <span aria-hidden="true" style="{{ $baseStyle }};display:none;place-items:center;background:#f5edf2;color:#a52d6e;font-weight:700;font-size:{{ round($size * 0.38) }}px">{{ $initials }}</span>
@else
    <span
        {{ $attributes->except('style')->merge(['class' => 'user-avatar']) }}
        style="{{ $baseStyle }};display:grid;place-items:center;background:var(--pink-soft, #fdf2f8);color:var(--pink-dark, #be3a7d);font-weight:700;font-size:{{ round($size * 0.38) }}px;{{ $customStyle }}"
    >{{ $initials }}</span>
@endif
