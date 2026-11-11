@extends('seller.layout')
@section('title','Promotions · Flash sales')
@section('page-title','Promotions')
@push('head')
<link rel="stylesheet" href="{{ asset('css/seller-promotions.css') }}?v={{ filemtime(public_path('css/seller-promotions.css')) }}">
@endpush
@section('content')
@if(\App\Services\BuyerCommerce::installed())
@include('seller.partials.promotions-workspace',['promotionType'=>'flash'])
@endif
@endsection
@push('scripts')<script src="{{ asset('js/seller-promotions.js') }}?v={{ filemtime(public_path('js/seller-promotions.js')) }}"></script>@endpush
