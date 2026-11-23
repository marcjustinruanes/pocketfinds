@extends('seller.layout')
@section('title','Promotions · Vouchers')
@section('page-title','Promotions')
@push('head')
<link rel="stylesheet" href="{{ asset('css/seller-promotions.css') }}?v={{ substr(hash_file('sha256', public_path('css/seller-promotions.css')), 0, 12) }}">
@endpush
@section('content')
@include('seller.partials.promotions-workspace',['promotionType'=>'voucher'])
@endsection
@push('scripts')<script src="{{ asset('js/seller-promotions.js') }}?v={{ substr(hash_file('sha256', public_path('js/seller-promotions.js')), 0, 12) }}"></script>@endpush
