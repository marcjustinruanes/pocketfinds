@extends('logistics.layout')
@section('title','Staff salary payment')
@section('page-title','Staff salary payment')
@section('page-sub','Individual payment instruction and evidence')
@include('logistics.partials.staff-payroll-assets')
@section('content')
<div class="cash-desk settlement-workspace staff-payroll">
@include('commerce.messages')
<a class="btn" href="{{ route('logistics.finance.staff',$filters) }}">Back to payroll worklist</a>
<section class="cash-card"><h2>{{ $member->given_names }} {{ $member->last_name }} / #{{ $member->id }}</h2>
<p>Coverage: {{ $period->period_year }}-{{ $period->period_month }}, half {{ $period->period_half }}. Due {{ $period->pay_date }} PHT. Staff status: {{ ucfirst($member->status) }}.</p>
<p>Preparation reserves funds. Verification does not send money. Record an actual payment only after sending GCash externally.</p>
@include('logistics.partials.staff-salary-payment')
{{ $history->withQueryString()->links() }}
</section></div>
@endsection
