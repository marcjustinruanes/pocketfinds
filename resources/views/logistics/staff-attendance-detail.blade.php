@extends('logistics.layout')
@section('title','Attendance details')
@section('page-title','Attendance details')
@section('page-sub','Shift times and activity history')
@include('logistics.partials.staff-payroll-assets')
@section('content')
@php
    $date=fn($v)=>$v ? \Carbon\CarbonImmutable::parse($v,config('app.timezone'))->setTimezone('Asia/Manila') : null;
    $pht=fn($v)=>$date($v)?->format('M j, Y / g:i:s A').' PHT';
    $seconds=$record->time_in && $record->time_out ? (int)$date($record->time_in)->diffInSeconds($date($record->time_out)) : null;
    $duration=$seconds===null ? 'Not available' : ($seconds>=3600 ? intdiv($seconds,3600).'h ' : '').($seconds>=60 ? intdiv($seconds%3600,60).'m ' : '').($seconds%60).'s';
    $name=trim(($member->given_names??'').' '.($member->last_name??''));
    $titles=['time_in'=>'Timed in','time_out'=>'Timed out','correction_applied'=>'Attendance correction applied','missing_timeout'=>'Missing Time Out flagged','void'=>'Leave or absence voided','day_leave'=>'Approved leave recorded','day_absence'=>'Confirmed absence recorded','correction_requested'=>'Correction requested','correction_approve'=>'Correction approved','correction_reject'=>'Correction rejected'];
@endphp
<div class="cash-desk staff-payroll attendance-detail">
<div class="attendance-detail-toolbar"><a class="btn" href="{{ route($admin?'logistics.attendance':'logistics.my-attendance') }}">&larr; Back to attendance</a><span>All times in Philippine time (PHT)</span></div>
<section class="cash-card attendance-overview" aria-labelledby="shift-heading">
    <header class="attendance-detail-heading"><div><p class="attendance-eyebrow">{{ \Carbon\CarbonImmutable::parse($record->business_date)->format('l, F j, Y') }}</p><h2 id="shift-heading">{{ $name ?: 'Staff account #'.$record->staff_id }}</h2><p>Account #{{ $record->staff_id }} <span aria-hidden="true">&middot;</span> {{ $hub ? $hub->municipality.', '.$hub->province : 'Hub' }} / Hub #{{ $record->hub_id }}</p></div><div class="attendance-state"><span class="attendance-status">{{ $flow->label($record) }}</span><small>{{ $record->corrected ? 'Reviewed correction' : 'Original record' }}</small></div></header>
    <dl class="attendance-times">
        <div><dt>Time In</dt><dd>{{ $record->time_in ? $date($record->time_in)->format('g:i:s A') : 'Not recorded' }}</dd><small>{{ $record->time_in ? $date($record->time_in)->format('M j, Y').' PHT' : 'No clock time on this record' }}</small></div>
        <div><dt>Time Out</dt><dd>{{ $record->time_out ? $date($record->time_out)->format('g:i:s A') : 'Not recorded' }}</dd><small>{{ $record->time_out ? $date($record->time_out)->format('M j, Y').' PHT' : ($record->kind==='shift' ? 'Shift has no recorded Time Out' : 'Not applicable') }}</small></div>
        <div><dt>Recorded duration</dt><dd>{{ $duration }}</dd><small>{{ $seconds===null ? 'Requires both Time In and Time Out' : 'Recorded interval; not payable hours' }}</small></div>
    </dl>
    @if($record->reason)<div class="attendance-note"><strong>Recorded reason</strong><p>{{ $record->reason }}</p></div>@endif
    @if($record->corrected)
    <details class="attendance-original"><summary>View original clock times</summary><dl class="attendance-original-times"><div><dt>Original Time In</dt><dd>{{ $record->original_in ? $pht($record->original_in) : 'Not recorded' }}</dd></div><div><dt>Original Time Out</dt><dd>{{ $record->original_out ? $pht($record->original_out) : 'Not recorded' }}</dd></div></dl><p>Original clock times remain preserved. The summary above shows the current reviewed times.</p></details>
    @endif
</section>
<section class="cash-card" aria-labelledby="activity-heading"><div class="attendance-activity-heading"><h2 id="activity-heading">Activity history</h2><span>Most recent first</span></div>
<ol class="attendance-timeline">
@forelse($events as $event)
    @php
        $details=json_decode($event->details,true)??[];
        $actorName=trim(($event->actor_given_names??'').' '.($event->actor_last_name??''));
    @endphp
    <li><span class="attendance-event-dot" aria-hidden="true"></span><article><div class="attendance-event-heading"><h3>{{ $titles[$event->event]??'Attendance record updated' }}</h3><time datetime="{{ $date($event->created_at)->toIso8601String() }}">{{ $pht($event->created_at) }}</time></div><p class="attendance-event-person">{{ $actorName ?: 'Account #'.$event->actor_id }}@if($actorName) <span>/ Account #{{ $event->actor_id }}</span>@endif</p>
    @if(in_array($event->event,['time_in','time_out']))<p>{{ $event->event==='time_in' ? 'Shift started using the server-recorded time.' : 'Shift ended using the server-recorded time.' }}</p>@endif
    @if(!empty($details['reason']))<div class="attendance-note"><strong>Reason</strong><p>{{ $details['reason'] }}</p></div>@endif
    @if($event->event==='correction_applied')
    <dl class="attendance-change"><div><dt>Previous Time In</dt><dd>{{ !empty($details['before_in']) ? $pht($details['before_in']) : 'Not recorded' }}</dd></div><div><dt>Reviewed Time In</dt><dd>{{ !empty($details['corrected_in']) ? $pht($details['corrected_in']) : 'Not recorded' }}</dd></div><div><dt>Previous Time Out</dt><dd>{{ !empty($details['before_out']) ? $pht($details['before_out']) : 'Not recorded' }}</dd></div><div><dt>Reviewed Time Out</dt><dd>{{ !empty($details['corrected_out']) ? $pht($details['corrected_out']) : 'Not recorded' }}</dd></div></dl>
    @endif
    </article></li>
@empty<li><p>No activity has been recorded for this entry.</p></li>@endforelse
</ol>
{{ $events->links() }}
</section></div>
@endsection
