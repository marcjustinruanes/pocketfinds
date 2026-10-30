@if($actor->isHubStaff() && \Illuminate\Support\Facades\Schema::hasColumn('logistics_cash_handovers','submitted_by'))
@foreach($recoveryRiders as $holderId=>$holderName)
<section class="cash-card"><h2>Suspended rider cash recovery</h2><p>Cash holder: {{ $holderName }}. Prepare a submission for existing collections at this hub. This does not count or verify cash.</p>
<details><summary>Prepare on-behalf submission</summary><form method="POST" action="{{ route('logistics.cash-desk.recovery') }}">@csrf
<input type="hidden" name="key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="rider_id" value="{{ $holderId }}">
@foreach($collections->where('actor_id',$holderId) as $c)
<label for="recovery-{{ $holderId }}-{{ $c->id }}">Collection {{ $c->id }} · available PHP {{ number_format($c->balance['available']/100,2) }}</label><input id="recovery-{{ $holderId }}-{{ $c->id }}" type="number" name="allocations[{{ $c->id }}]" value="0" min="0" step="0.01" max="{{ number_format($c->balance['available']/100,2,'.','') }}">
@endforeach
<label for="recovery-cash-reason-{{ $holderId }}">Recovery reason</label><textarea id="recovery-cash-reason-{{ $holderId }}" name="reason" minlength="3" maxlength="1000" required></textarea>
<label for="recovery-cash-ref-{{ $holderId }}">Custody reference</label><input id="recovery-cash-ref-{{ $holderId }}" name="reference" minlength="3" maxlength="255" required>
<button class="btn btn-primary">Prepare submission only</button></form></details></section>
@endforeach
@endif
