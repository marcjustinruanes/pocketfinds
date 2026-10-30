@extends('logistics.layout')
@section('title', $admin ? 'Hub Staff Attendance' : 'My Attendance')
@section('page-title', $admin ? 'Hub Staff Attendance' : 'My Attendance')
@section('page-sub','Recorded attendance and reviewed corrections; fixed salary remains independent')
@include('logistics.partials.staff-payroll-assets')
@push('scripts')<script src="{{ asset('js/staff-attendance.js') }}" defer></script>@endpush
@section('content')
@php($pht=fn($v)=>$v ? \Carbon\CarbonImmutable::parse($v,config('app.timezone'))->setTimezone('Asia/Manila')->format('Y-m-d g:i A') : 'Not recorded')
<div class="cash-desk staff-payroll attendance-workspace">
@include('commerce.messages')
@if(!$admin)
<section class="cash-card"><h2>Today / {{ $flow->now()->toDateString() }} PHT</h2>
<p>Assigned hub: {{ $hub->municipality ?? 'Unassigned' }} / #{{ $actor->logistics_hub_id }}. Timestamps are recorded by the server.</p>
@if($active)
<p><strong>{{ $active->needs_review ? 'Missing Time Out - review required' : 'Working' }}</strong> / Shift started {{ $pht($active->time_in) }} PHT. Shift hub #{{ $active->hub_id }}.</p>
<p>An open shift is not proof of continuous work or payable hours. Overnight shifts remain attached to their original Time In date.</p>
<form method="POST" action="{{ route('logistics.my-attendance.clock',['action'=>'out']) }}" data-attendance-confirm="Record Time Out now using the server time?">@csrf<button class="btn btn-primary">Time Out</button></form>
@else
<p><strong>{{ $today->isEmpty() ? 'No Record' : $flow->label($today->last()) }}</strong></p>
<form method="POST" action="{{ route('logistics.my-attendance.clock',['action'=>'in']) }}" data-attendance-confirm="Record Time In now at your assigned hub using the server time?">@csrf<button class="btn btn-primary">Time In</button></form>
@endif
<p>No Record does not mean Absent. No work schedule, deductions or automatic penalties are applied.</p>
</section>
@endif
<section class="cash-card"><h2>Attendance history</h2>
<form method="GET" class="payroll-filters">
<div><label for="att-from">From (Time In / business date)</label><input id="att-from" name="from" type="date" value="{{ $filters['from'] }}" required></div>
<div><label for="att-to">Through</label><input id="att-to" name="to" type="date" value="{{ $filters['to'] }}" required></div>
@if($admin)
<div><label for="att-q">Staff name or account ID</label><input id="att-q" type="search" name="q" value="{{ $filters['q'] }}"></div>
<div><label for="att-hub">Recorded hub</label><select id="att-hub" name="hub"><option value="">All hubs</option>@foreach($hubs as $item)<option value="{{ $item->id }}" @selected($filters['hub']==$item->id)>{{ $item->municipality }} #{{ $item->id }}</option>@endforeach</select></div>
<div><label for="att-member">Exact Staff account ID</label><input id="att-member" type="number" min="1" name="staff_id" value="{{ $filters['staff_id'] }}"></div>
@endif
<div><label for="att-status">Attendance status</label><select id="att-status" name="status"><option value="">All</option>@foreach(['working'=>'Working','completed'=>'Completed','missing_timeout'=>'Missing Time Out','leave'=>'On Leave','absence'=>'Confirmed absence','no_record'=>'No Record in selected range','void'=>'Voided day marks'] as $value=>$label)<option value="{{ $value }}" @selected($filters['status']===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="att-corrections">Correction queue</label><select id="att-corrections" name="corrections">@foreach(['pending','approved','rejected'] as $value)<option value="{{ $value }}" @selected($filters['corrections']===$value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
<button class="btn btn-primary">Apply filters</button>
</form>
<p>Date ranges use shift start dates in PHT. No Record means no entry in the selected range; it does not establish a scheduled absence. Missing Time Out is an explicit Admin review flag, not an automatic overnight rule.</p>
@if($admin)<a class="btn" href="{{ route('logistics.attendance.export',$filters) }}">Export filtered attendance CSV</a>@endif
<div class="cash-table" tabindex="0" role="region" aria-label="Attendance records"><table><thead><tr><th scope="col">Staff / recorded hub</th><th scope="col">Date / status</th><th scope="col">Time In / Time Out PHT</th><th scope="col">Recorded duration</th><th scope="col">Details / next action</th></tr></thead><tbody>
@forelse($records as $row)
<tr><td>{{ $row->given_names }} {{ $row->last_name }} / #{{ $row->member_id }}<small>{{ $row->hub_name }}</small></td>
<td>{{ $row->business_date ?? 'Selected range' }}<small>{{ $row->id ? $flow->label($row) : 'No Record' }}</small>@if($row->corrected)<small>Reviewed correction applied</small>@endif</td>
<td>{{ $pht($row->time_in) }}<br>{{ $pht($row->time_out) }}</td>
<td>@if($row->time_in && $row->time_out){{ (int)\Carbon\CarbonImmutable::parse($row->time_in)->diffInMinutes(\Carbon\CarbonImmutable::parse($row->time_out)) }} minutes<small>Recorded interval; not payable hours</small>@else Not verified @endif</td>
<td>@if($row->id)<a class="btn" href="{{ route($admin?'logistics.attendance.show':'logistics.my-attendance.show',$row->id) }}">Record &amp; audit</a>@endif
@if($admin && $row->id && (($row->kind==='shift' && !$row->time_out) || in_array($row->kind,['leave','absence'])))
<details><summary>Document review</summary><form method="POST" action="{{ route('logistics.attendance.review',$row->id) }}" data-attendance-confirm="Save this documented attendance review? No salary amount will change.">@csrf
<input type="hidden" name="revision" value="{{ $row->revision }}"><input type="hidden" name="action" value="{{ $row->kind==='shift'?'missing_timeout':'void' }}">
<label for="review-reason-{{ $row->id }}">Required review reason</label><textarea id="review-reason-{{ $row->id }}" name="reason" minlength="5" maxlength="2000" required></textarea>
<button class="btn">{{ $row->kind==='shift'?'Flag Missing Time Out':'Void documented leave/absence' }}</button></form></details>
@endif</td></tr>
@empty<tr><td colspan="5">No matching records. Adjust your filters.</td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}
</section>
@if(!$admin)
<section class="cash-card" id="attendance-correction"><h2>Request attendance correction</h2><p>Submit proposed PHT times and a reason. Admin must review them. Original clock timestamps remain preserved. For an overnight shift, the affected date is its Time In date.</p>
<form method="POST" action="{{ route('logistics.my-attendance.correction') }}" data-attendance-confirm="Submit these proposed times for Admin review? This does not change recorded attendance or salary yet.">@csrf
<label for="correction-record">Affected shift</label><select id="correction-record" name="attendance_id"><option value="">Forgot Time In / no existing record</option>
@if($active && !$records->contains('id',$active->id))<option value="{{ $active->id }}">Active shift / {{ $active->business_date }}</option>@endif
@foreach($records as $row)@if($row->id && $row->kind==='shift')<option value="{{ $row->id }}" @selected(old('attendance_id')===$row->id)>{{ $row->business_date }} / {{ $pht($row->time_in) }} / {{ substr($row->id,0,8) }}</option>@endif @endforeach</select>
<label for="correction-date">Affected date (PHT)</label><input id="correction-date" name="business_date" type="date" value="{{ old('business_date') }}" required>
<label for="requested-in">Requested Time In (PHT)</label><input id="requested-in" name="requested_in" type="datetime-local" value="{{ old('requested_in') }}" required>
<label for="requested-out">Requested Time Out (PHT, leave blank only for an open shift)</label><input id="requested-out" name="requested_out" type="datetime-local" value="{{ old('requested_out') }}">
<label for="correction-reason">Reason</label><textarea id="correction-reason" name="reason" minlength="5" maxlength="2000" required>{{ old('reason') }}</textarea><button class="btn btn-primary">Request correction</button>
</form></section>
@else
<section class="cash-card"><h2>Document approved leave or confirmed absence</h2><p>There is no work-schedule engine. Confirm an absence only after verifying an actual scheduled workday, not a rest day or approved leave. These records never deduct salary.</p>
<form method="POST" action="{{ route('logistics.attendance.mark') }}" data-attendance-confirm="Record this documented day status for the selected Staff member? Salary will remain unchanged.">@csrf
<label for="mark-staff">Staff account ID</label><input id="mark-staff" name="staff_id" type="number" min="1" required value="{{ old('staff_id') }}">
<label for="mark-date">Business date (PHT)</label><input id="mark-date" name="business_date" type="date" required value="{{ old('business_date') }}">
<label for="mark-kind">Status</label><select id="mark-kind" name="kind"><option value="leave" @selected(old('kind')==='leave')>Approved leave</option><option value="absence" @selected(old('kind')==='absence')>Confirmed absence</option></select>
<label class="payroll-checkbox"><input type="checkbox" name="scheduled_confirmed" value="1"><span>For absence: I verified this was a scheduled workday, not a rest day or approved leave.</span></label>
<label for="mark-reason">Required approval / scheduling evidence and reason</label><textarea id="mark-reason" name="reason" minlength="5" maxlength="2000" required>{{ old('reason') }}</textarea><button class="btn btn-primary">Record documented day</button>
</form></section>
@endif
<section class="cash-card"><h2>Attendance corrections / {{ ucfirst($filters['corrections']) }}</h2><p>Queue follows hub, Staff search and decision status; older pending issues remain visible outside the history date range.</p>
@forelse($corrections as $item)
<article class="cash-handover"><h3>{{ $item->given_names }} {{ $item->last_name }} / #{{ $item->staff_id }} / {{ $item->business_date }} / Request hub #{{ $item->hub_id }}</h3>
<p>Status: {{ ucfirst($item->status) }}. Reason: {{ $item->reason }}</p>
<p>Original clock: {{ $pht($item->original_in) }} / {{ $pht($item->original_out) }}.<br>Current record: {{ $pht($item->current_in) }} / {{ $pht($item->current_out) }}.<br>Requested: {{ $pht($item->requested_in) }} / {{ $pht($item->requested_out) }} PHT.</p>
@if($item->decision_reason)<p>Decision: {{ $item->decision_reason }} / Actor #{{ $item->decided_by }} / {{ $pht($item->decided_at) }} PHT</p>@endif
@if($admin && $item->status==='pending')
<form method="POST" action="{{ route('logistics.attendance.decide',$item->id) }}" data-attendance-confirm="Apply this correction decision with its recorded reason? Approved times update attendance evidence only, never salary.">@csrf
<label for="decision-{{ $item->id }}">Decision</label><select id="decision-{{ $item->id }}" name="decision"><option value="approve">Approve proposed times</option><option value="reject">Reject correction</option></select>
<label for="decision-reason-{{ $item->id }}">Required decision reason</label><textarea id="decision-reason-{{ $item->id }}" name="reason" minlength="5" maxlength="2000" required></textarea><button class="btn btn-primary">Record decision</button></form>
@endif</article>
@empty<p>No correction requests match these filters.</p>@endforelse
{{ $corrections->links() }}
</section></div>
@endsection
