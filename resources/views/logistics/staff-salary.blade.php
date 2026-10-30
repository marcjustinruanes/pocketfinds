@extends('logistics.layout')
@section('title','Hub Staff Salaries')
@section('page-title','Hub Staff Salaries')
@section('page-sub','Review one payroll period, resolve blockers and prepare individual payments')
@include('logistics.partials.staff-payroll-assets')
@section('content')
<div class="cash-desk settlement-workspace staff-payroll">
  @include('commerce.messages')
  @if(session('payroll_results'))
  <section class="cash-card" aria-label="Bulk payroll results" role="status">
    <h2>Bulk action results</h2>
    <ul>@foreach(session('payroll_results') as $item)<li>{{ $item['name'] }} / #{{ $item['id'] }}: <strong>{{ ucfirst($item['state']) }}</strong> {{ $item['reason'] ?? '' }}</li>@endforeach</ul>
  </section>
  @endif
  <section class="cash-card">
    <h2>Payroll worklist</h2>
    <form method="GET" action="{{ route('logistics.finance.staff') }}" class="payroll-filters">
      <div><label for="payroll-q">Staff name or account ID</label><input id="payroll-q" name="q" value="{{ $filters['q'] }}" maxlength="100" type="search" placeholder="Search Staff"></div>
      <div><label for="payroll-hub">Assigned hub</label><select id="payroll-hub" name="hub"><option value="">All hubs</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected($filters['hub']==$hub->id)>{{ $hub->municipality }} / {{ $hub->province }} #{{ $hub->id }}</option>@endforeach</select></div>
      <div><label for="payroll-year">Year</label><input id="payroll-year" name="period_year" type="number" min="2020" max="2100" value="{{ $filters['period_year'] }}" required></div>
      <div><label for="payroll-month">Month</label><select id="payroll-month" name="period_month">@for($month=1;$month<=12;$month++)<option value="{{ $month }}" @selected($filters['period_month']==$month)>{{ date('F',mktime(0,0,0,$month,1)) }}</option>@endfor</select></div>
      <div><label for="payroll-half">Coverage</label><select id="payroll-half" name="period_half"><option value="1" @selected($filters['period_half']==1)>1st to 15th</option><option value="2" @selected($filters['period_half']==2)>16th to month end</option></select></div>
      <div><label for="payroll-status">Payment status</label><select id="payroll-status" name="status"><option value="">All statuses</option>@foreach(\App\Services\StaffPayrollWorkspace::STATUSES as $status)<option value="{{ $status }}" @selected($filters['status']==$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
      <div><label for="payroll-blocker">Blocker or attention</label><select id="payroll-blocker" name="blocker"><option value="">All Staff</option>@foreach(\App\Services\StaffPayrollWorkspace::BLOCKERS as $blocker)<option value="{{ $blocker }}" @selected($filters['blocker']==$blocker)>{{ ucfirst(str_replace('_',' ',$blocker)) }}</option>@endforeach</select></div>
      <div class="payroll-filter-actions"><button class="btn btn-primary">Apply filters</button><a class="btn" href="{{ route('logistics.finance.staff') }}">Reset</a></div>
    </form>
    <div class="cash-stats payroll-summary">
      <div><span>Matching Staff</span><strong>{{ $summary->staff_count }}</strong></div>
      <div><span>Unpaid generated obligations</span><strong>PHP {{ number_format(($summary->outstanding??0)/100,2) }}</strong></div>
      <div><span>Recorded paid</span><strong>PHP {{ number_format(($summary->paid??0)/100,2) }}</strong></div>
      <div><span>Not generated</span><strong>{{ $summary->ungenerated??0 }}</strong></div>
    </div>
    <p>Totals follow the selected period and filters. Not generated is not an established payable balance. Prepared reserves funds; Verified is still unpaid. Overdue means an unpaid generated obligation is past its due date in PHT.</p>
    <a class="btn" href="{{ route('logistics.finance.staff.export',$filters) }}">Export filtered payroll CSV</a>
  </section>

  @include('logistics.partials.hub-salary-settings')
  <section class="cash-card" id="salaries">
    <h2>Staff and salary obligations</h2>
    <p>Select up to 25 Staff on this page. Selection is explicit and resets when you change pages or filters. Generation does not require a GCash account; preparation does. Due dates are the 15th and 30th, or earlier month end.</p>
    <form method="POST" action="{{ route('logistics.finance.staff.bulk.preview') }}" id="payroll-selection" class="payroll-bulk">@csrf
      @include('logistics.partials.staff-payroll-filters-hidden')
      <div><label for="bulk-mode">Selected Staff action</label><select id="bulk-mode" name="mode"><option value="generate" @selected(old('mode','generate')==='generate')>Preview obligation generation</option><option value="prepare" @selected(old('mode')==='prepare')>Preview instruction preparation</option></select></div>
      <div><label for="bulk-account">Staff-funded company GCash account (preparation)</label><select id="bulk-account" name="account_id"><option value="">Choose for preparation</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('account_id')===$account->id)>{{ $account->account_name }} / ending {{ substr($account->account_number,-4) }} / PHP {{ number_format($funds[$account->id]['available']/100,2) }} available</option>@endforeach</select></div>
      <button class="btn btn-primary">Preview selected Staff</button>
    </form>
    <label class="payroll-checkbox"><input type="checkbox" id="select-payroll-page"><span>Select all Staff on this page only</span></label>
    <p id="payroll-selection-count" role="status" aria-live="polite">0 Staff selected on this page.</p>
    <div class="cash-table" tabindex="0" role="region" aria-label="Staff payroll worklist, scroll horizontally on small screens">
      <table><thead><tr><th>Select</th><th>Staff / hub</th><th>Monthly salary</th><th>Period installment / due</th><th>Status / attention</th><th>Actions</th></tr></thead><tbody>
      @forelse($staff as $member)
        <tr>
          <td><label class="payroll-checkbox"><input form="payroll-selection" class="payroll-row-checkbox" type="checkbox" name="ids[]" value="{{ $member->id }}" @checked(in_array($member->id,old('ids',[]))) aria-label="Select {{ $member->given_names }} {{ $member->last_name }}, account {{ $member->id }}"><span class="sr-only">Select #{{ $member->id }}</span></label></td>
          <td><strong>{{ $member->given_names }} {{ $member->last_name }}</strong><small>Account #{{ $member->id }}</small><small>{{ $member->hub_name ?? 'Unassigned hub' }} / #{{ $member->logistics_hub_id }}</small></td>
          <td>{{ $member->monthly_cents ? 'PHP '.number_format($member->monthly_cents/100,2) : 'Not configured' }}<small>{{ !$member->monthly_cents ? 'Missing Salary Configuration' : ($member->salary_source==='hub' ? 'Hub default / hub #'.$member->source_hub_id : 'Individual override') }}</small>@if($member->period_effective)<small>Effective {{ $member->period_effective }}</small>@endif @if($member->snapshot_monthly_cents!==null && $member->snapshot_monthly_cents!=$member->monthly_cents)<small>Period basis: PHP {{ number_format($member->snapshot_monthly_cents/100,2) }}</small>@endif</td>
          <td>{{ ($member->period_id || $member->snapshot_monthly_cents>0) ? 'PHP '.number_format($member->installment_cents/100,2) : 'No salary snapshot' }}<small>{{ $member->due_date }} PHT</small>@unless($member->period_id)<small>Estimate; not generated</small>@endunless</td>
          <td><strong>{{ $member->acknowledged_at ? 'Acknowledged' : ucfirst($member->period_status ?? 'Not generated') }}</strong>@foreach($member->labels as $label)<span class="payroll-attention">{{ $label }}</span>@endforeach</td>
          <td>
            @if($member->payout_id)
              <a class="btn" href="{{ route('logistics.finance.staff.show',array_merge($filters,['id'=>$member->payout_id])) }}">View payout</a>
            @endif
            @if($member->period_status==='pending' && (!$member->payout_id || $member->payout_status==='cancelled') && $member->staff_status==='approved')
              <details><summary>Prepare payout</summary><form method="POST" action="{{ route('logistics.finance.staff.prepare') }}">@csrf
                <input type="hidden" name="period_id" value="{{ $member->period_id }}">
                <label for="account-{{ $member->id }}">Company GCash account</label><select id="account-{{ $member->id }}" name="account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }} / PHP {{ number_format($funds[$account->id]['available']/100,2) }} available</option>@endforeach</select>
                @if($member->destination_id && $member->destination_ties==1 && $member->channel==='gcash')
                  <input type="hidden" name="destination_id" value="{{ $member->destination_id }}"><p>Latest Staff-provided GCash: {{ $member->account_name }} / ending {{ substr($member->account_number,-4) }}.</p><button class="btn btn-primary">Prepare and reserve funds</button>
                @else
                  <p>Staff must confirm unambiguous GCash details in My Account first.</p>
                @endif
              </form></details>
            @endif
            @if(isset($attendanceSummary[$member->id]))
            @php($att=$attendanceSummary[$member->id])
            <details><summary>Attendance review</summary><p>{{ $att['workdays'] }} recorded shift dates / {{ $att['absence'] }} confirmed absences / {{ $att['leave'] }} approved leave days / {{ $att['no_record'] }} elapsed calendar days with no record / {{ $att['open'] }} open shifts / {{ $att['missing_timeout'] }} flagged missing Time Out / {{ $att['pending'] }} pending corrections.</p>
            <p>Calendar gaps are not confirmed absences or scheduled days. Open intervals are not payable-hour evidence. Salary amounts and payment eligibility remain independent.</p>
            @if($att['open'] || $att['missing_timeout'] || $att['pending'])<span class="payroll-attention">Attendance requires review; salary is not blocked.</span>@endif
            <a class="btn" href="{{ route('logistics.attendance',['staff_id'=>$member->id,'from'=>$periodStart->toDateString(),'to'=>$periodEnd->toDateString()]) }}">Review attendance</a></details>
            @endif
            <details><summary>Salary settings &amp; history</summary>
              @if($member->staff_status==='approved')
              <form method="POST" action="{{ route('logistics.finance.staff.configure') }}">@csrf
                <input type="hidden" name="staff_id" value="{{ $member->id }}"><input type="hidden" name="revision" value="{{ $member->revision??0 }}">
                <label for="monthly-{{ $member->id }}">Monthly salary (PHP)</label><input id="monthly-{{ $member->id }}" name="monthly" type="number" min="0.01" step="0.01" required value="{{ old('staff_id')==$member->id ? old('monthly') : ($member->monthly_cents ? number_format($member->monthly_cents/100,2,'.','') : '') }}">
                <button class="btn btn-primary">Save salary rate</button>
              </form>
              @endif
              @if($hubSalaryInstalled && $member->staff_status==='approved')
              <form method="POST" action="{{ route('logistics.finance.staff.use-hub') }}">@csrf<input type="hidden" name="staff_id" value="{{ $member->id }}"><input type="hidden" name="revision" value="{{ $member->revision??0 }}">
              <label class="payroll-checkbox"><input name="confirmed" type="checkbox" value="1" required><span>Use the assigned hub default from the next period; a missing hub rate must remain unconfigured.</span></label><button class="btn">Use hub default</button></form>
              @endif
              <p>Revisions apply from the next period. Generated obligations keep their historical amount. Employment dates and proration are not supported.</p>
              <a class="btn" href="{{ route('logistics.finance.staff.history',array_merge($filters,['staff'=>$member->id])) }}">Salary history</a>
            </details>
          </td>
        </tr>
      @empty
        <tr><td colspan="6">No Staff match these filters. Try another hub, period or status.</td></tr>
      @endforelse
      </tbody></table>
    </div>
    {{ $staff->links() }}
  </section>
  <section class="cash-card" id="payouts">
    <h2>Payout history</h2><p>Instructions for the selected period and Staff filters. Open a payment to verify, hold, record an actual transfer or inspect its audit history.</p>
    @forelse($allPayouts as $p)
      <div class="payroll-history-row"><div><strong>{{ $p->given_names }} {{ $p->last_name }} / #{{ $p->staff_id }}</strong><p>PHP {{ number_format($p->amount_cents/100,2) }} / {{ ucfirst($p->status) }} / {{ $p->acknowledged_at ? 'Acknowledged' : 'Not acknowledged' }}</p></div><a class="btn" href="{{ route('logistics.finance.staff.show',array_merge($filters,['id'=>$p->id])) }}">View payout</a></div>
    @empty
      <p>No staff payouts recorded for this selection.</p>
    @endforelse
    {{ $allPayouts->links() }}
  </section>
  @include('logistics.partials.staff-payroll-funding')
</div>
@endsection
