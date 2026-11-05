@extends('seller.layout')
@section('title','Vouchers')
@section('page-title','Vouchers')
@section('content')
<div class="seller-vouchers-page">
@include('commerce.messages')
@include('account.partials.commerce-vouchers')
</div>
@endsection
