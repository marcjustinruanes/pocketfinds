@if(\Illuminate\Support\Facades\Schema::hasColumn('logistics_issues','recovery'))
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}">@endpush
<div class="cash-desk">
<section class="cash-card recovery-form-card"><h2 class="issue-card-title"><span class="issue-heading-icon"><x-admin-icon name="box" /></span>Supervised parcel recovery</h2><p>Use normal hub receipt whenever its evidence is complete. Recovery records actual custody only; it does not complete delivery, return stock, earn compensation or authorize redispatch.</p>
@if(auth()->user()->isHubStaff())
<details class="recovery-form-details"><summary>Record physical recovery</summary><form class="recovery-form" method="POST" enctype="multipart/form-data" action="{{ route('logistics.issues.recovery') }}">@csrf
<input type="hidden" name="key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label class="recovery-field" for="recovery-rider">Suspended rider<select id="recovery-rider" name="rider_id" required><option value="">Choose the recorded rider</option>@foreach($recoveryRiders as $r)<option value="{{ $r->id }}">{{ $r->username }}</option>@endforeach</select></label>
<label class="recovery-field" for="recovery-leg">Assignment leg<select id="recovery-leg" name="leg" required><option value="pickup">Seller pickup</option><option value="delivery">Final delivery</option><option value="hub_transfer">Hub transfer</option></select></label>
<label class="recovery-field recovery-field--wide" for="recovery-tracking">Tracking numbers actually scanned / received<textarea id="recovery-tracking" name="tracking_numbers" maxlength="10000" required placeholder="One tracking number per line"></textarea></label>
<label class="recovery-field" for="recovery-time">Observed at (Asia/Manila)<input id="recovery-time" name="observed_at" type="datetime-local" required></label>
<label class="recovery-field" for="recovery-condition">Observed condition<textarea id="recovery-condition" name="condition" minlength="3" maxlength="1000" required></textarea></label>
<label class="recovery-field" for="recovery-reason">Recovery reason<textarea id="recovery-reason" name="reason" minlength="3" maxlength="1000" required></textarea></label>
<label class="recovery-field" for="recovery-reference">Custody / evidence reference<input id="recovery-reference" name="reference" minlength="3" maxlength="255" required></label>
<label class="recovery-field recovery-field--wide" for="recovery-proof">Optional private evidence (JPG, PNG, WebP or PDF, up to 5 MB)<input id="recovery-proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
<label class="cash-check recovery-confirmation" for="recovery-physical"><input id="recovery-physical" name="confirmed" type="checkbox" value="1" required><span>I actually received these parcels from the suspended rider after physical collection, at my assigned hub. This is not an uncollected reassignment or a substitute normal handover.</span></label>
<button class="btn btn-primary">Record recovered custody</button></form></details>
@else<p>Assigned Hub Staff must attest physical recovery. Admin can monitor and review the resulting issues.</p>@endif
</section>
@foreach($issues as $issue)@if($issue->recovery)
@php($recovery=$issue->recovery)
@if(!is_array($recovery) || !Illuminate\Support\Arr::has($recovery,['rider_id','leg','hub_id','received_by','observed_at','recorded_at','condition','reference','shipment_ids']) || !is_array($recovery['shipment_ids']))
<section class="cash-card"><h2>Recovery custody needs evidence review · issue #{{ $issue->id }}</h2><p>The historical custody record is incomplete. No holder, location or receipt time will be inferred. Redispatch remains held.</p></section>
@else
<section class="cash-card"><h2>Recovered custody · issue #{{ $issue->id }}</h2><p>Rider #{{ $recovery['rider_id'] }} · {{ $recovery['leg'] }} · actual hub #{{ $recovery['hub_id'] }} · receiver #{{ $recovery['received_by'] }}</p>
<p>Observed {{ $recovery['observed_at'] }} · recorded {{ $recovery['recorded_at'] }}</p><p>{{ $recovery['condition'] }} · {{ $recovery['reference'] }}</p><p>{{ count($recovery['shipment_ids']) }} parcels. Redispatch remains held even if the issue is marked resolved.</p>
@php($proof=\Illuminate\Support\Facades\DB::table('logistics_finance_evidence')->where('source_type','parcel_recovery')->where('source_id',(string)$issue->id)->first())
@if($proof)<a class="btn" href="{{ route('finance.evidence',$proof->id) }}">View private evidence</a>@endif
</section>
@endif
@endif @endforeach
</div>
@endif
