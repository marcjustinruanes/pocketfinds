@once
@push('head')
<link rel="stylesheet" href="{{ asset('css/gcash.css') }}?v={{ filemtime(public_path('css/gcash.css')) }}">
@endpush
@endonce
