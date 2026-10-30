@if($hubSalaryInstalled)
<section class="cash-card"><details><summary>Hub salary defaults</summary><p>Defaults apply from the next payroll period. Existing individual overrides stay in place. Approval inherits the applicable hub rate; transfers change inherited rates prospectively while retaining earlier salary records. No rate is assumed for an unconfigured hub.</p>
<div class="cash-table" tabindex="0" role="region" aria-label="Hub salary configurations"><table><thead><tr><th scope="col">Hub</th><th scope="col">Approved Staff</th><th scope="col">Configured monthly salary</th><th scope="col">Change default</th></tr></thead><tbody>
@foreach($hubs as $hub)
@php($rate=$hubRates[$hub->id]??null)
<tr><td>{{ $hub->municipality }} / {{ $hub->province }} / #{{ $hub->id }}</td><td>{{ $hubStaffCounts[$hub->id]??0 }}</td><td>{{ $rate ? 'PHP '.number_format($rate->monthly_cents/100,2) : 'Missing Salary Configuration' }}@if($rate)<small>Effective {{ $rate->effective_period }}</small>@endif</td><td>
<form method="POST" action="{{ route('logistics.finance.staff.hub') }}">@csrf<input type="hidden" name="hub_id" value="{{ $hub->id }}"><input type="hidden" name="revision" value="{{ $rate->revision??0 }}">
<label for="hub-monthly-{{ $hub->id }}">Monthly default (PHP)</label><input id="hub-monthly-{{ $hub->id }}" name="monthly" type="number" min="0.01" step="0.01" required value="{{ old('hub_id')==$hub->id ? old('monthly') : ($rate ? number_format($rate->monthly_cents/100,2,'.','') : '') }}"><button class="btn btn-primary">Save hub default</button></form>
<a class="btn" href="{{ route('logistics.finance.staff.hub-history',['hub'=>$hub->id]) }}">Hub salary history</a>
</td></tr>@endforeach
</tbody></table></div></details></section>
@endif
