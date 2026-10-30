@extends('logistics.layout')
@section('title','Payroll preview')
@section('page-title','Payroll preview')
@section('page-sub','Review every selected Staff member before creating records')
@include('logistics.partials.staff-payroll-assets')
@section('content')
<div class="cash-desk settlement-workspace staff-payroll">
@include('commerce.messages')
<a class="btn" href="{{ route('logistics.finance.staff',$preview['filters']) }}">Back to selection</a>
<section class="cash-card"><h2>{{ $preview['mode']==='generate' ? 'Generate obligations' : 'Prepare instructions' }}</h2>
<p>Coverage: {{ $preview['filters']['period_year'] }}-{{ $preview['filters']['period_month'] }}, half {{ $preview['filters']['period_half'] }}. Preview expires after 15 minutes. Existing and skipped rows will not be changed.</p>
@if($preview['account_label'])<p>Funding account: <strong>{{ $preview['account_label'] }}</strong></p>@endif
<div class="cash-stats"><div><span>Eligible selected total</span><strong>PHP {{ number_format($preview['total']/100,2) }}</strong></div>
@if($preview['mode']==='prepare')<div><span>Verified Staff funds available</span><strong>PHP {{ number_format($preview['available']/100,2) }}</strong></div><div><span>Funding shortfall</span><strong>PHP {{ number_format($preview['shortfall']/100,2) }}</strong></div>@endif
</div>
@if($preview['shortfall'])<p role="alert" class="payroll-attention">No instructions can be prepared for this group. Add separately verified Staff funds or return and select a smaller group. No employees will be silently prioritized.</p>@endif
<div class="cash-table" tabindex="0" role="region" aria-label="Selected Staff preview"><table><thead><tr><th>Staff / hub</th><th>Installment</th><th>GCash destination</th><th>Result if confirmed</th></tr></thead><tbody>
@foreach($preview['items'] as $item)<tr><td>{{ $item['name'] }} / #{{ $item['id'] }}<small>{{ $item['hub'] }}</small></td><td>PHP {{ number_format($item['amount']/100,2) }}</td><td>{{ $item['destination_name'] ?? '' }} / {{ $item['masked'] ?? 'Not registered' }}</td><td>{{ ucfirst($item['state']) }}<small>{{ $item['reason'] }}</small></td></tr>@endforeach
</tbody></table></div>
<form method="POST" action="{{ route('logistics.finance.staff.bulk.execute') }}">@csrf
<input type="hidden" name="token" value="{{ $preview['token'] }}">
<label class="payroll-checkbox"><input type="checkbox" name="confirmed" value="1" required><span>I reviewed the selected Staff, amounts and exclusions. This action does not send money.</span></label>
<button class="btn btn-primary" @disabled($preview['shortfall']>0 || !collect($preview['items'])->contains('state','ready'))>Confirm {{ $preview['mode']==='generate' ? 'generation' : 'preparation' }}</button>
</form></section></div>
@endsection
