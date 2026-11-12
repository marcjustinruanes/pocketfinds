@extends('logistics.layout')
@section('title','Manage Account')
@section('page-title','Manage Account')
@section('page-sub','Profile, hub assignment, account status, and delivery history')
@section('content')
@if($account->account_type === 'rider' && app(\App\Services\RiderBarangayCoverage::class)->installed())<a class="btn btn-outline" href="{{ route('logistics.riders.coverage.edit', $account->id) }}">Manage barangay routes</a>@endif
@if(app(\App\Services\AssignmentReassignment::class)->installed())<div class="card card-pad"><a class="btn btn-primary" href="{{ route('logistics.assignments.review') }}">Assignment evidence & reassignment</a></div>@endif
<a class="btn btn-outline" href="{{ route($account->account_type === 'rider' ? 'logistics.riders' : 'logistics.staff') }}">Back to personnel</a>
@if($account->account_type === 'rider' && ($account->status !== 'rejected' || $account->can_resubmit) && app(\App\Services\RiderActivation::class)->pending($account))
@php($activationInvitation = app(\App\Services\RiderActivation::class)->invitation($account))
<div class="card card-pad" style="margin-top:16px"><h2>{{ $account->status === 'rejected' ? 'Application correction' : 'Pending activation' }}</h2>
<p>{{ $activationInvitation->delivery_status === 'failed' ? 'The email could not be sent. Please resend it.' : ($account->status === 'rejected' ? 'The rider can use the emailed link to correct the rejected application.' : 'The rider must set a password through the activation email before signing in.') }}</p>
<p>Sent to {{ $activationInvitation->email }} · Expires {{ $activationInvitation->expires_at }}</p>
<form method="POST" action="{{ route('logistics.riders.activation.resend', $account->id) }}">@csrf<button class="btn btn-primary" type="submit">{{ $account->status === 'rejected' ? 'Resend correction link' : 'Resend activation email' }}</button></form></div>
@endif
<div class="card card-pad" style="margin-top:16px"><h2>{{ $account->given_names }} {{ $account->last_name }}</h2><p>{{ $account->account_type === 'rider' ? 'Rider' : 'Hub staff' }} · {{ $account->business_name }}</p>
<form data-registration-form data-registration-dirty="{{ $errors->any() ? 'true' : 'false' }}" method="POST" enctype="multipart/form-data" action="{{ route('logistics.accounts.update',$account->id) }}" onsubmit="return confirm('Save these account changes?')">@csrf @method('PATCH') @include('logistics.partials.account-form')</form></div>
<div class="card"><div class="card-head"><h2>Delivery assignments and history</h2></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Tracking</th><th>Leg</th><th>Assignment status</th><th>Parcel status</th><th>Assigned at</th></tr></thead><tbody>
@forelse($assignments as $assignment)<tr><td>{{ $assignment->shipment?->tracking_number ?? $assignment->shipment_id }}</td><td>{{ ucfirst(str_replace('_',' ',$assignment->leg)) }}</td><td>{{ $assignment->status }}</td><td>{{ $assignment->shipment?->shipping_status }}</td><td>{{ $assignment->accepted_at?->format('M d, Y g:i A') }}</td></tr>@empty<tr><td colspan="5"><div class="empty"><h3>No rider assignments</h3></div></td></tr>@endforelse
</tbody></table></div></div><div class="logistics-pagination">{{ $assignments->links() }}</div>
<div class="card card-pad"><h2>Application history</h2>
@forelse($applicationHistory as $event)<p>{{ $event->occurred_at }} ? {{ $event->role }} ? {{ $event->status }} ? Hub #{{ $event->hub_id }}@if($event->legacy_snapshot) (legacy snapshot; earlier decisions unknown)@endif</p>@if($event->reason)<p>{{ $event->reason }}</p>@endif
@php($details = json_decode($event->review_details ?? '{}', true))
@if(isset($details['from_status'],$details['to_status']))<p>{{ ucfirst($details['from_status']) }} &rarr; {{ ucfirst($details['to_status']) }}@if(!empty($details['action'])) &middot; {{ ucfirst(str_replace('_',' ',$details['action'])) }}@endif</p>@endif
@if(!empty($details['interview_completed']))<p>Interview completion attested by #{{ $details['interview_completed_attested_by'] }} at {{ $details['interview_completed_attested_at'] }}.</p>@endif
@if(!empty($details['document_changes']))<p>Document references updated: {{ implode(', ',array_keys($details['document_changes'])) }}. Private reference hashes retained in audit history.</p>@endif
@if(!empty($details['interview_scheduled_at']))<p>Interview: {{ $details['interview_scheduled_at'] }} · {{ $details['interview_location'] ?? '' }}</p>@endif
<p>Actor: {{ $event->actor_id ?? 'Unknown / public application' }} · Attempt: {{ $event->attempt_id }}</p>
@empty<p>No recorded history.</p>@endforelse
{{ $applicationHistory->links() }}</div>
<script src="{{ asset('js/registration-unsaved.js') }}?v={{ filemtime(public_path('js/registration-unsaved.js')) }}"></script>
<script src="{{ asset('js/license-number-input.js') }}?v={{ filemtime(public_path('js/license-number-input.js')) }}"></script>
@endsection
