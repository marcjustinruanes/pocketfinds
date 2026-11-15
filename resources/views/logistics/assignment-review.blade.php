@extends($seller ? 'seller.layout' : 'logistics.layout')
@section('title','Assignment Review')
@section('page-title','Assignment Review')
@push('head')<link rel="stylesheet" href="{{ asset('css/assignment-review.css') }}?v={{ filemtime(public_path('css/assignment-review.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/assignment-review.js') }}?v={{ filemtime(public_path('js/assignment-review.js')) }}" defer></script>@endpush
@section('content')
@include('logistics.partials.assignment-review-content')
@endsection
