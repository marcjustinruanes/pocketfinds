@extends($rider ? 'rider.layout' : 'logistics.layout')
@section('title', $rider ? 'Cash Remittances' : 'Cash Desk')
@section('page-title', $rider ? 'Cash Remittances' : 'Cash Desk')
@section('page-sub', 'COD cash handovers, verified allocations and hub custody')
@push('head')
<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}">
@endpush
@section('content')
@if(auth()->user()->isLogisticsAdmin())
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-finance-navigation.css') }}?v={{ filemtime(public_path('css/logistics-finance-navigation.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/logistics-finance-feedback.js') }}" defer></script>@endpush
@endif
@php
$localTime = fn($date) => \Carbon\CarbonImmutable::parse($date, config('app.timezone'))->setTimezone('Asia/Manila')->format('M j, Y H:i').' PHT';
  $hubLabels = $hubs->mapWithKeys(fn($hub) => [$hub->id => $hub->municipality.' - '.$hub->province.' (#'.$hub->id.')']);
  $statusLabels = ['submitted'=>'Awaiting Hub Verification', 'authorized'=>'Awaiting origin release', 'in_transit'=>'Released - awaiting receipt', 'verified'=>'Count verified', 'received'=>'Receipt recorded', 'discrepancy'=>'Count difference - review needed', 'withdrawn'=>'Withdrawn - not counted'];
@endphp
<div class="cash-desk cash-workspace">
  @unless(auth()->user()->isLogisticsAdmin())
  <nav class="cash-page-nav" aria-label="Cash Desk sections"><a href="#cash-overview">Overview</a><a href="#cash-outstanding">Outstanding collections</a>@if($settings)<a href="#cash-settings">Cutoff & transfers</a>@endif<a href="#cash-history">Handover history</a></nav>
  @endunless
  @if($actor->isHubStaff())
  <section class="cash-card"><h2>Your hub cash actions</h2><p>Count cash received from Riders, then release treasury cash only after Admin authorization. Admin verifies actual company GCash receipt in Seller Settlements; you do not need access to that Admin page.</p>
    <nav class="cash-page-nav" aria-label="Hub cash work queues">
      @foreach(['counts'=>'Awaiting Hub Verification','treasury'=>'Authorized treasury releases','discrepancies'=>'Differences awaiting Admin review'] as $queue=>$label)<a href="{{ route('logistics.cash-desk',['queue'=>$queue]) }}#cash-history" @if(request('queue')===$queue) aria-current="page" @endif>{{ $label }} ({{ $workCounts[$queue] }})</a>@endforeach
      <a href="{{ route('logistics.cash-desk') }}#cash-history">All handovers</a>
    </nav>
    <p>On-hand cash below is physically counted hub custody. It is not an available Seller payout or payroll balance.</p>
  </section>
  @endif
  @if($errors->any())<div role="alert" class="cash-alert"><strong>Please check your submission.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <section class="cash-card" id="cash-overview"><h2>{{ $rider ? 'Your COD cash' : 'Cash custody' }}</h2>
    <p>Pending Rider Remittance plus Awaiting Hub Verification equals total unverified COD. Overdue is a separate flag that can apply to either amount; it is not an additional balance. Submission records a declaration, not verified physical receipt. No automatic penalties or Rider blocks.</p>
    <div class="cash-stats">
      <div><span>Pending Rider Remittance</span><strong>PHP {{ number_format($collections->sum(fn($c) => $c->balance['available']) / 100, 2) }}</strong><small>Not currently submitted for counting</small></div>
      <div><span>Total unverified COD</span><strong>PHP {{ number_format($collections->sum(fn($c) => $c->balance['outstanding']) / 100, 2) }}</strong></div>
      <div><span>Awaiting Hub Verification</span><strong>PHP {{ number_format($collections->sum(fn($c) => $c->balance['reserved']) / 100, 2) }}</strong><small>Submitted for counting; physical receipt is not yet verified</small></div>
      <div><span>Overdue</span><strong>PHP {{ number_format($collections->where('overdue', true)->sum(fn($c) => $c->balance['outstanding']) / 100, 2) }}</strong><small>Part of outstanding cash</small></div>
      @unless($rider)<div><span>Cash in transit</span><strong>PHP {{ number_format($transit / 100, 2) }}</strong></div>@endunless
    </div>
    @unless($rider)<div class="cash-grid">@foreach($hubs as $hub)<div><span>{{ $hub->municipality }} &middot; {{ $hub->province }} (#{{ $hub->id }})</span><strong class="cash-balance">PHP {{ number_format($custody[$hub->id] / 100, 2) }}</strong><small>On hand, including unallocated overages. Not a seller payout balance.</small>@unless($hub->is_operational)<small>Inactive hub - transfers unavailable</small>@endunless</div>@endforeach @if($hubs->isEmpty())<p>No company hubs are available in your scope.</p>@endif</div>@endunless
  </section>

  @include('logistics.partials.recovery-cash')
  <section class="cash-card" id="cash-outstanding"><h2>Outstanding collections</h2><p>Amounts are in PHP. Cash is due at the hub recorded on the collection, including after a rider reassignment. @if($rider)A zero amount leaves that collection unselected.@endif</p>
  @forelse($collections->groupBy('hub_id') as $hubId => $group)
    @if($rider)<form method="POST" action="{{ route('rider.remittances.submit') }}">@csrf<input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">@endif
    <h3>{{ $hubLabels[$hubId] ?? "Recorded hub #".$hubId }}</h3><div class="cash-table" tabindex="0" role="region" aria-label="Cash amounts table (scroll horizontally)"><table><thead><tr><th>Order / rider</th><th>Outstanding</th><th>Awaiting Hub Verification</th><th>Pending Rider Remittance<small>Available to submit</small></th><th>Due &middot; Asia/Manila</th>@if($rider)<th>Submit amount</th>@endif</tr></thead><tbody>
    @foreach($group as $c)<tr><td><span class="cash-reference">{{ $c->order_id ?? $c->id }}</span><small>{{ $people[$c->actor_id] ?? 'Rider' }}</small></td><td>{{ number_format($c->balance['outstanding'] / 100, 2) }}</td><td>{{ number_format($c->balance['reserved'] / 100, 2) }}</td><td>{{ number_format($c->balance['available'] / 100, 2) }}</td><td>{{ $c->due->format('M j, Y H:i') }} @if($c->overdue)<strong class="cash-warning">Overdue</strong>@endif</td>
      @if($rider)<td><label for="collection-{{ $c->id }}">Amount to hand over</label><input id="collection-{{ $c->id }}" name="allocations[{{ $c->id }}]" type="number" min="0" max="{{ number_format($c->balance['available'] / 100, 2, '.', '') }}" step="0.01" value="0" required></td>@endif</tr>@endforeach
    </tbody></table></div>
    @if($rider)<button class="btn btn-primary">Submit selected amounts</button></form>@endif
  @empty<div class="cash-empty"><strong>No outstanding COD collections.</strong><p>Rider collections awaiting remittance or counting will appear here.</p></div>@endforelse
  </section>

  @if($settings)
  <div class="cash-grid cash-settings-grid" id="cash-settings">
    <section class="cash-card"><h2>Remittance cutoff</h2><p>Company working-day cutoff in Asia/Manila. Flags only; no automatic deductions or rider blocks.</p><form method="POST" action="{{ route('logistics.cash-desk.cutoff') }}">@csrf<input type="hidden" name="revision" value="{{ $settings->revision }}"><label for="cash-cutoff">End of working day</label><input id="cash-cutoff" name="cutoff" type="time" value="{{ old('cutoff', substr($settings->remittance_cutoff, 0, 5)) }}" step="60" aria-describedby="cutoff-help" required><small id="cutoff-help">Minute precision, Philippine time (PHT). Applies to overdue flags; no automatic penalties.</small><button class="btn btn-primary">Save cutoff</button></form></section>
    <section class="cash-card"><h2>Authorize hub cash transfer</h2>@if($hubs->where('is_operational', true)->count() < 2)<div class="cash-unavailable"><strong>Transfers unavailable</strong><p>Two operational company hubs are required for a hub transfer. Your current hub can still receive Rider remittances through assigned Hub Staff.</p></div>@else<p>Origin Hub Staff releases cash; destination Hub Staff counts and confirms receipt. Authorization does not move cash.</p><form method="POST" action="{{ route('logistics.cash-desk.authorize') }}" data-cash-transfer>@csrf<input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
      @foreach(['hub_id'=>'Origin hub', 'destination_hub_id'=>'Destination hub'] as $field=>$label)<label for="cash-{{ $field }}">{{ $label }}</label><select id="cash-{{ $field }}" name="{{ $field }}" required><option value="">Choose {{ strtolower($label) }}</option>@foreach($hubs->where('is_operational', true) as $hub)<option value="{{ $hub->id }}" data-on-hand="{{ $custody[$hub->id] }}" @selected((string)old($field) === (string)$hub->id)>{{ $hub->municipality }} &middot; {{ $hub->province }} (#{{ $hub->id }})</option>@endforeach</select>@endforeach
      <label for="transfer-amount">Authorized amount (PHP)</label><input id="transfer-amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" aria-describedby="transfer-balance" required>
      <p id="transfer-balance" role="status" aria-live="polite">Select an origin to see recorded on-hand cash.</p><small>Authorization does not reserve funds. Staff release rechecks the actual available balance.</small><label for="transfer-notes">Transfer instructions / custody reference</label><textarea id="transfer-notes" name="notes" maxlength="2000" required>{{ old('notes') }}</textarea><button class="btn btn-primary" @disabled($hubs->where('is_operational', true)->count() < 2)>Authorize transfer</button>@if($hubs->where('is_operational', true)->count() < 2)<p>Two operational company hubs are required for a hub transfer.</p>@endif</form>@endif</section>
  </div>
  @endif

  <section class="cash-card" id="cash-history"><h2>Handover history</h2><p>Counts are final. Corrections require financial review; resolving a discrepancy documents the decision without changing cash or forgiving a balance.</p>
  @if(request('queue') || request('handover'))<p role="status">Showing a selected handover or work queue. <a href="{{ route($rider ? 'rider.remittances' : 'logistics.cash-desk') }}#cash-history">View all handovers</a></p>@endif
  @forelse($handovers as $h)
    @if(!empty($h->recovery_reason))<section class="cash-card"><p>Cash holder: {{ $people[$h->actor_id] ?? '#'.$h->actor_id }} / Submitted by: {{ $people[$h->submitted_by] ?? '#'.$h->submitted_by }}</p><p>{{ $h->recovery_reason }}</p></section>@endif
    <article class="cash-handover"><header><div><h3>{{ ucfirst($h->kind) }} &middot; PHP {{ number_format($h->expected_cents / 100, 2) }}</h3><span class="cash-reference">{{ $h->id }}</span></div><strong class="cash-status" data-status="{{ $h->status }}">{{ $h->resolved_at ? 'Difference documented - amounts unchanged' : ($statusLabels[$h->status] ?? str_replace('_', ' ', ucfirst($h->status))) }}</strong></header>
      <p><strong>Next responsibility:</strong>
      @if($h->status==='submitted')Assigned origin Hub Staff must physically count and allocate this remittance.
      @elseif($h->status==='authorized')Assigned origin Hub Staff must record physical release after following the authorization.
      @elseif($h->status==='in_transit'){{ $h->kind==='treasury' ? 'Logistics Admin must verify actual company-account receipt in Seller Settlements.' : 'Destination Hub Staff must physically count receipt.' }}
      @elseif($h->status==='discrepancy'){{ $h->resolved_at ? 'Review is documented; counted amounts and liabilities remain unchanged.' : 'Logistics Admin must investigate and document the difference. Staff must not edit the count.' }}
      @else
      No further cash movement is implied by this record. Review its count and audit history.
      @endif</p>
      <p>{{ $people[$h->actor_id] ?? 'Account' }} &middot; {{ $hubLabels[$h->hub_id] ?? 'Hub #'.$h->hub_id }} @if($h->destination_hub_id) → {{ $hubLabels[$h->destination_hub_id] ?? 'Hub #'.$h->destination_hub_id }} @endif &middot; {{ $localTime($h->created_at) }}</p>
      @if($h->kind==='remittance')<p><strong>{{ empty($h->recovery_reason) ? 'Cash from Rider:' : 'Recorded Rider cash holder:' }}</strong> {{ $people[$h->actor_id] ?? 'Rider' }} (#{{ $h->actor_id }}). Submitted amount: <strong>PHP {{ number_format($h->expected_cents / 100, 2) }}</strong>.</p>@endif
      @if($h->counted_at)
      <dl class="cash-receipt"><div><dt>Received &amp; counted</dt><dd>PHP {{ number_format($h->counted_cents / 100, 2) }}</dd></div><div><dt>Received by</dt><dd>{{ $people[$h->counted_by] ?? 'Recorded receiver' }} (#{{ $h->counted_by }})</dd></div><div><dt>Recorded receipt time</dt><dd>{{ $localTime($h->counted_at) }}</dd></div><div><dt>{{ $h->counted_cents < $h->expected_cents ? 'Shortage' : ($h->counted_cents > $h->expected_cents ? 'Overage' : 'Difference') }}</dt><dd>PHP {{ number_format(abs($h->counted_cents - $h->expected_cents) / 100, 2) }}{{ $h->counted_cents == $h->expected_cents ? ' (exact count)' : '' }}</dd></div></dl>
      @endif
      @if($h->notes)<p>{{ $h->notes }}</p>@endif
      @if($h->resolved_at)<p><strong>Resolution:</strong> {{ $h->resolution }} &middot; {{ $people[$h->resolved_by] ?? 'Logistics Admin' }} &middot; {{ $localTime($h->resolved_at) }}</p>@endif
      @if($rider && $h->status === 'submitted' && empty($h->recovery_reason))<form method="POST" action="{{ route('rider.remittances.withdraw', $h->id) }}">@csrf<button class="btn">Withdraw uncounted submission</button></form>@endif
      @if($h->kind === 'remittance')
        @php($canCount = $actor->isHubStaff() && (int)$actor->logistics_hub_id === (int)$h->hub_id && $h->status === 'submitted')
        @if($canCount)
        @php($restoreCount = (string)old('count_handover') === (string)$h->id)
        <h3>Receive &amp; Count Cash</h3><p>Count cash for <strong>{{ $people[$h->actor_id] ?? 'Rider' }} (#{{ $h->actor_id }})</strong>. Recording Staff: <strong>{{ $people[$actor->id] ?? $actor->username }} (#{{ $actor->id }})</strong>. The server records the receipt time when saved.</p>
        <form method="POST" data-remittance-rider="{{ $people[$h->actor_id] ?? 'Rider' }} (#{{ $h->actor_id }})" data-receiving-staff="{{ $people[$actor->id] ?? $actor->username }} (#{{ $actor->id }})" data-expected-cents="{{ $h->expected_cents }}" action="{{ route('logistics.cash-desk.count', $h->id) }}">@csrf<input type="hidden" name="count_handover" value="{{ $h->id }}"><label for="count-{{ $h->id }}">Amount physically received and counted (PHP)</label><input id="count-{{ $h->id }}" name="counted" type="number" min="0" step="0.01" value="{{ $restoreCount ? old('counted') : '' }}" aria-describedby="count-preview-{{ $h->id }}" required><p id="count-preview-{{ $h->id }}" data-count-preview role="status" aria-live="polite">Enter the actual cash counted, then check the collection allocations below.</p>@endif
        <div class="cash-table" tabindex="0" role="region" aria-label="Cash amounts table (scroll horizontally)"><table><thead><tr><th>Collection</th><th>Expected</th><th>{{ $canCount ? 'Allocate counted cash' : 'Verified cash' }}</th></tr></thead><tbody>
        @foreach($allocations[$h->id] ?? [] as $a)<tr><td class="cash-reference">{{ $a->collection_id }}</td><td>{{ number_format($a->expected_cents / 100, 2) }}</td><td>@if($canCount)<label for="allocation-{{ $a->id }}">Verified amount (PHP)</label><input id="allocation-{{ $a->id }}" name="allocations[{{ $a->collection_id }}]" type="number" min="0" max="{{ number_format($a->expected_cents / 100, 2, '.', '') }}" step="0.01" value="{{ $restoreCount ? old('allocations.'.$a->collection_id, number_format($a->expected_cents / 100, 2, '.', '')) : number_format($a->expected_cents / 100, 2, '.', '') }}" required>@else{{ $a->verified_cents === null ? 'Pending count' : number_format($a->verified_cents / 100, 2) }}@endif</td></tr>@endforeach
        </tbody></table></div>
        @if($canCount)<label for="notes-{{ $h->id }}">Count notes (required for a shortage or overage)</label><textarea id="notes-{{ $h->id }}" name="notes" maxlength="2000">{{ $restoreCount ? old('notes') : '' }}</textarea><p>Allocate the counted amount up to the expected total. Leave any overage unallocated. This single action records receipt, counting and verification together.</p><button class="btn btn-primary">Receive &amp; Count Cash</button></form>@endif
      @elseif($h->kind === 'treasury')
        <p>Company treasury transfer. Only verified COD cash can fund this handover.</p>
        @if(isset($treasuryAccounts[$h->treasury_account_id]))@php($treasuryDestination=$treasuryAccounts[$h->treasury_account_id])<p>Destination: {{ $treasuryDestination->institution }} &middot; {{ $treasuryDestination->account_name }} &middot; {{ $treasuryDestination->account_number }}.</p>@endif
        @if($actor->isHubStaff() && $h->status === 'authorized')<form method="POST" action="{{ route('logistics.cash-desk.treasury.release',$h->id) }}">@csrf<label for="treasury-release-{{ $h->id }}">Physical COD cash released (PHP)</label><input id="treasury-release-{{ $h->id }}" name="amount" type="number" min="0.01" step="0.01" value="{{ number_format($h->expected_cents / 100, 2, '.', '') }}" required><button class="btn btn-primary">Confirm treasury cash release</button></form>@endif
        @if($settings)<a class="btn" href="{{ route('logistics.finance.payouts') }}#treasury">View treasury receipts</a>@endif
      @elseif($actor->isHubStaff())
        @php($action = $h->status === 'authorized' && (int)$actor->logistics_hub_id === (int)$h->hub_id ? 'dispatch' : ($h->status === 'in_transit' && (int)$actor->logistics_hub_id === (int)$h->destination_hub_id ? 'receive' : null))
        @if($action)<form method="POST" action="{{ route('logistics.cash-desk.transfer', [$h->id, $action]) }}">@csrf<label for="transfer-count-{{ $h->id }}">Physical cash {{ $action === 'dispatch' ? 'released' : 'received' }} (PHP)</label><input id="transfer-count-{{ $h->id }}" name="counted" type="number" min="0" step="0.01" required>
        @if(app(\App\Services\LogisticsTreasury::class)->installed())<label for="transfer-cod-{{ $h->id }}">Verified COD portion {{ $action === 'dispatch' ? 'released' : 'received' }} (PHP)</label><input id="transfer-cod-{{ $h->id }}" name="cod_amount" type="number" min="0" step="0.01" value="0" required><p>Count this portion separately. Exclude overages and unverified cash; only this verified portion can later fund seller payouts.</p>@endif
        <label for="transfer-note-{{ $h->id }}">Handover evidence / reference and notes</label><textarea id="transfer-note-{{ $h->id }}" name="notes" maxlength="2000" required></textarea><button class="btn btn-primary">Confirm {{ $action === 'dispatch' ? 'cash release' : 'cash receipt' }}</button></form>@endif
      @endif
      @if($settings && $h->status === 'discrepancy' && !$h->resolved_at)<form method="POST" action="{{ route('logistics.cash-desk.resolve', $h->id) }}">@csrf<label for="resolution-{{ $h->id }}">Admin investigation and resolution</label><textarea id="resolution-{{ $h->id }}" name="notes" minlength="3" maxlength="2000" required></textarea><button class="btn btn-primary">Document resolution</button></form>@endif
      <details><summary>Audit history</summary>@forelse($events[$h->id] ?? [] as $event)
        @php($detail = json_decode($event->details, true) ?? [])
        <p><strong>{{ str_replace('_', ' ', ucfirst($event->event)) }}</strong> &middot; {{ $people[$event->actor_id] ?? 'Account' }} &middot; {{ $localTime($event->created_at) }}
          @if(isset($detail['counted_cents']))<br>Cash counted: PHP {{ number_format($detail['counted_cents'] / 100, 2) }}@endif
          @if(isset($detail['notes']))<br>{{ $detail['notes'] }}@endif
        </p>
      @empty<p>No audit entries are recorded for this handover.</p>@endforelse</details>
    </article>
  @empty<div class="cash-empty"><strong>{{ request('queue') || request('handover') ? 'No handovers match this selection.' : 'No cash handovers yet.' }}</strong><p>Use All handovers to review other records. Submitted remittances and authorized transfers appear here; authorization alone does not move cash.</p></div>@endforelse
  {{ $handovers->links() }}
  </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics-cash-desk.js') }}" defer></script>
@endpush
