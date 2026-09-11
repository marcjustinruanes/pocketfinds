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
        // profile_picture is stored inconsistently across upload paths: some
        // rows hold a relative storage path (this branch's own convention,
        // needs Storage::url() to resolve), others already hold a full
        // absolute URL (another code path stores it pre-resolved). Use it
        // as-is when it's already absolute.
        $picUrl = str_starts_with($user->profile_picture, 'http')
            ? $user->profile_picture
            : \Illuminate\Support\Facades\Storage::url($user->profile_picture);
    @endphp
    <img
        {{ $attributes->except('style')->merge(['class' => 'user-avatar']) }}
        src="{{ $picUrl }}"
        alt="{{ $name ?: 'User profile picture' }}"
        style="{{ $baseStyle }};object-fit:cover;display:block;{{ $customStyle }}"
    >
@else
    <span
        {{ $attributes->except('style')->merge(['class' => 'user-avatar']) }}
        style="{{ $baseStyle }};display:grid;place-items:center;background:var(--pink-soft, #fdf2f8);color:var(--pink-dark, #be3a7d);font-weight:700;font-size:{{ round($size * 0.38) }}px;{{ $customStyle }}"
    >{{ $initials }}</span>
@endif
