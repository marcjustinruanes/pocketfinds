@extends('logistics.layout')
@section('title','Staff salary history')
@section('page-title','Staff salary history')
@section('page-sub','Historical monthly salary revisions')
@include('logistics.partials.staff-payroll-assets')
@section('content')
<div class="cash-desk staff-payroll"><a class="btn" href="{{ route('logistics.finance.staff',$filters) }}">Back to payroll worklist</a>
<section class="cash-card"><h2>{{ ($hubHistory??false) ? 'Hub: ' : 'Staff: ' }}{{ $member->given_names }} {{ $member->last_name }} / #{{ $member->id }}</h2>
<p>Saved timestamps show when Admin configured a rate. Changes apply from the next period; an initial individual rate may enable the current period. Generated obligations are never rewritten.</p>
@forelse($history as $rate)<p>Revision {{ $rate->revision }} / {{ $rate->monthly_cents ? 'PHP '.number_format($rate->monthly_cents/100,2).' monthly' : 'Missing Salary Configuration' }} @if(isset($rate->salary_source)) / {{ $rate->salary_source==='hub' ? 'Hub default / hub #'.$rate->source_hub_id : 'Individual override' }} @endif / Saved {{ \Carbon\CarbonImmutable::parse($rate->effective_from,config('app.timezone'))->setTimezone('Asia/Manila')->format('M j, Y g:i A') }} PHT @if($rate->period_effective ?? null) / Effective period {{ $rate->period_effective }} @endif / Actor #{{ $rate->set_by }}</p>@empty<p>No salary rate configured.</p>@endforelse
{{ $history->links() }}
</section></div>
@endsection
