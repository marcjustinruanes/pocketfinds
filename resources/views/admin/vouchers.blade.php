@extends('admin.layout')
@section('title','Vouchers')
@section('page-title','Vouchers')
@section('body-class','platform-accounts-page platform-resources-page')
@push('account-head')
<link rel="stylesheet" href="{{ asset('css/platform-accounts.css') }}?v={{ filemtime(public_path('css/platform-accounts.css')) }}">
@endpush
@section('workspace-search')
<div class="platform-account-toolbar"><div class="filter-bar"><div class="search-mini"><span class="ic"><x-admin-icon name="search" /></span><input type="search" placeholder="Search voucher code" aria-label="Search vouchers" data-table-search="vouchersTable"></div></div></div>
@endsection
@section('content')
@include('commerce.messages')
<div class="platform-voucher-toolbar"><button type="button" class="btn btn-primary" data-modal-open="createPlatformVoucher">Create voucher</button></div>
<div class="card platform-accounts-card"><div class="card-pad"><div class="table-wrap">
<table class="dtable" id="vouchersTable" data-platform-filter-table>
<thead><tr><th>Voucher</th><th>@include('admin.partials.column-filter',['filterTable'=>'vouchersTable','filterLabel'=>'Type','filterField'=>'kind','filterOptions'=>['all'=>'All types','platform'=>'Platform discount','shipping'=>'Shipping discount']])</th><th>Minimum spend</th><th>Schedule</th><th>@include('admin.partials.column-filter',['filterTable'=>'vouchersTable','filterLabel'=>'Status','filterField'=>'status','filterOptions'=>['all'=>'All statuses','scheduled'=>'Scheduled','active'=>'Live','expired'=>'Expired','ended'=>'Ended']])</th><th class="platform-manage-heading">Manage</th></tr></thead>
<tbody>
@forelse($vouchers as $voucher)
@php
 $starts=\Illuminate\Support\Carbon::parse($voucher->starts_at);
 $ends=\Illuminate\Support\Carbon::parse($voucher->ends_at);
 $voucherStatus=!$voucher->is_active?'ended':($ends->isPast()?'expired':($starts->isFuture()?'scheduled':'active'));
@endphp
<tr data-kind="{{ $voucher->kind }}" data-status="{{ $voucherStatus }}">
<td><div class="platform-voucher-table-code"><strong>{{ $voucher->code }}</strong><span>{{ $voucher->discount_type==='percent' ? number_format($voucher->amount,0).'%' : 'PHP '.number_format($voucher->amount,2) }} off</span></div></td>
<td>{{ ucfirst($voucher->kind) }}</td><td>PHP {{ number_format($voucher->minimum_spend,2) }}</td>
<td><div class="platform-voucher-dates"><span>{{ $starts->format('M j, Y � g:i A') }}</span><span>{{ $ends->format('M j, Y � g:i A') }}</span></div></td>
<td><span class="stamp stamp-{{ $voucherStatus==='active'?'approved':'pending' }}">{{ $voucherStatus==='active'?'Live':ucfirst($voucherStatus) }}</span></td>
<td><button type="button" class="btn platform-detail-button" data-modal-open="voucherDetails-{{ $voucher->id }}" aria-label="Manage voucher {{ $voucher->code }}"><span aria-hidden="true">&#8599;</span></button></td>
</tr>
@push('voucher-details')
<div class="modal-overlay" id="voucherDetails-{{ $voucher->id }}"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="voucherTitle-{{ $voucher->id }}"><header class="modal-head"><h3 id="voucherTitle-{{ $voucher->id }}">{{ $voucher->code }}</h3><button type="button" class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button></header><div class="modal-body"><dl class="platform-profile-fields"><div><dt>Total uses allowed</dt><dd>{{ $voucher->usage_limit }}</dd></div><div><dt>Uses per buyer</dt><dd>{{ $voucher->per_buyer_limit }}</dd></div><div><dt>Maximum discount</dt><dd>{{ $voucher->cap ? 'PHP '.number_format($voucher->cap,2) : 'No cap' }}</dd></div><div><dt>Status</dt><dd>{{ ucfirst($voucherStatus) }}</dd></div></dl></div><footer class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close>Close</button>@if($voucher->is_active)<form method="POST" action="{{ route('admin.commerce.end',['voucher',$voucher->id]) }}" data-confirm data-confirm-title="End this voucher?" data-confirm-message="Buyers will no longer be able to use this voucher." data-confirm-action="End voucher">@csrf<button type="submit" class="btn btn-outline-danger">End voucher</button></form>@endif</footer></section></div>
@endpush
@empty
<tr><td colspan="6"><div class="empty"><h3>No vouchers yet</h3><p>Create a platform or shipping discount to get started.</p></div></td></tr>
@endforelse
</tbody></table></div></div></div>
@stack('voucher-details')
<div class="modal-overlay {{ $errors->any() ? 'open' : '' }}" id="createPlatformVoucher"><section class="modal modal-lg" role="dialog" aria-modal="true" aria-labelledby="createPlatformVoucherTitle"><header class="modal-head"><h3 id="createPlatformVoucherTitle">Create voucher</h3><button type="button" class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button></header><div class="modal-body">
    @if($errors->any())<div role="alert" class="platform-voucher-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="platform-voucher-form" method="POST" action="{{ route('admin.commerce.voucher') }}">
      @csrf
      <div class="platform-voucher-fields">
        <label class="form-row">Code (4–12 letters/digits)<input name="code" pattern="[A-Za-z0-9]{4,12}" minlength="4" maxlength="12" required value="{{ old('code', '') }}"></label>
        <label class="form-row">Type<select class="select" name="kind"><option value="platform">Platform discount</option><option value="shipping">Shipping discount</option></select></label>
        <label class="form-row">Discount<select class="select" name="discount_type"><option value="fixed">Fixed PHP amount</option><option value="percent">Percentage</option></select></label>
        <label class="form-row">Amount / percent<input name="amount" type="number" min="0.01" step="0.01" required value="{{ old('amount', '') }}"></label>
        <label class="form-row">Maximum discount (optional PHP)<input name="cap" type="number" min="0.01" step="0.01" value="{{ old('cap', '') }}"></label>
        <label class="form-row">Minimum spend (PHP)<input name="minimum_spend" type="number" min="0" step="0.01" required value="{{ old('minimum_spend', '0') }}"></label>
        <label class="form-row">Starts<input name="starts_at" type="datetime-local" required value="{{ old('starts_at', '') }}"></label>
        <label class="form-row">Ends<input name="ends_at" type="datetime-local" required value="{{ old('ends_at', '') }}"></label>
        <label class="form-row">Total uses<input name="usage_limit" type="number" min="1" required value="{{ old('usage_limit', '') }}"></label>
        <label class="form-row">Uses per buyer<input name="per_buyer_limit" type="number" min="1" required value="{{ old('per_buyer_limit', '1') }}"></label>
      </div>
      <button class="btn btn-primary" type="submit">Create voucher</button>
    </form>
</div></section></div>
@endsection
