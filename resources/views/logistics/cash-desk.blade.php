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
$localTime = fn($date) => \Carbon\CarbonImmutable::parse($date, config('app.timezone'))->setTimezone('Asia/Manila')->format('M j, Y g:i A').'';
  $hubLabels = $hubs->mapWithKeys(fn($hub) => [$hub->id => $hub->municipality.' - '.$hub->province.' (#'.$hub->id.')']);
  $statusLabels = ['submitted'=>'Awaiting Hub Verification', 'authorized'=>'Awaiting origin release', 'in_transit'=>'Released - awaiting receipt', 'verified'=>'Count verified', 'received'=>'Receipt recorded', 'discrepancy'=>'Count difference - review needed', 'withdrawn'=>'Withdrawn - not counted'];
@endphp
<div class="cash-desk cash-workspace">
  @unless(auth()->user()->isLogisticsAdmin())
  <nav class="cash-page-nav" aria-label="Cash Desk sections"><a href="#cash-overview">Overview</a><a href="#cash-outstanding">Outstanding collections</a>@if($settings)<a href="#cash-settings">Cutoff & transfers</a>@endif<a href="#cash-history">Handover history</a></nav>
  @endunless
  @if($actor->isHubStaff())
  <section class="cash-card cash-actions-card"><h2 class="cash-section-title"><span class="cash-section-icon"><x-admin-icon name="layers" /></span>Your hub cash actions</h2><p>Count cash received from Riders, then release treasury cash only after Admin authorization. Admin verifies actual company GCash receipt in Seller Settlements; you do not need access to that Admin page.</p>
    <nav class="cash-page-nav" aria-label="Hub cash work queues">
      @foreach(['counts'=>'Awaiting Hub Verification','treasury'=>'Authorized treasury releases','discrepancies'=>'Differences awaiting Admin review'] as $queue=>$label)<a href="{{ route('logistics.cash-desk',['queue'=>$queue]) }}#cash-history" @if(request('queue')===$queue) aria-current="page" @endif>{{ $label }} ({{ $workCounts[$queue] }})</a>@endforeach
      <a href="{{ route('logistics.cash-desk') }}#cash-history">All handovers</a>
    </nav>
    <p>On-hand cash below is physically counted hub custody. It is not an available Seller payout or payroll balance.</p>
  </section>
  @endif
  @if($errors->any())<div role="alert" class="cash-alert"><strong>Please check your submission.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <section class="cash-card cash-overview-card" id="cash-overview"><h2 class="cash-section-title"><span class="cash-section-icon"><x-admin-icon name="box" /></span>{{ $rider ? 'Your COD cash' : 'Cash custody' }}</h2>
    <p>Pending Rider Remittance plus Awaiting Hub Verification equals total unverified COD. Overdue is a separate flag that can apply to either amount; it is not an additional balance. Submission records a declaration, not verified physical receipt. No automatic penalties or Rider blocks.</p>
    <div class="cash-stats">
      <div class="cash-stat-card cash-stat-pending">@unless($rider)<span class="cash-stat-icon"><x-admin-icon name="clock" /></span>@endunless<span>Pending Rider Remittance</span><strong>PHP {{ number_format($collections->sum(fn($c) => $c->balance['available']) / 100, 2) }}</strong><small>Not currently submitted for counting</small></div>
      <div class="cash-stat-card cash-stat-total">@unless($rider)<span class="cash-stat-icon"><x-admin-icon name="peso" /></span>@endunless<span>Total unverified COD</span><strong>PHP {{ number_format($collections->sum(fn($c) => $c->balance['outstanding']) / 100, 2) }}</strong></div>
      <div class="cash-stat-card cash-stat-verification">@unless($rider)<span class="cash-stat-icon"><x-admin-icon name="check-circle" /></span>@endunless<span>Awaiting Hub Verification</span><strong>PHP {{ number_format($collections->sum(fn($c) => $c->balance['reserved']) / 100, 2) }}</strong><small>Submitted for counting; physical receipt is not yet verified</small></div>
      <div class="cash-stat-card cash-stat-overdue">@unless($rider)<span class="cash-stat-icon"><x-admin-icon name="info" /></span>@endunless<span>Overdue</span><strong>PHP {{ number_format($collections->where('overdue', true)->sum(fn($c) => $c->balance['outstanding']) / 100, 2) }}</strong><small>Part of outstanding cash</small></div>
      @unless($rider)<div class="cash-stat-card cash-stat-transit"><span class="cash-stat-icon"><x-admin-icon name="layers" /></span><span>Cash in transit</span><strong>PHP {{ number_format($transit / 100, 2) }}</strong></div>@endunless
    </div>
    @unless($rider)
    <details class="cash-hub-balances">
      <summary><span>Hub cash balances</span><span class="cash-hub-count">{{ $hubs->count() }} {{ \Illuminate\Support\Str::plural('hub', $hubs->count()) }}</span></summary>
      <div class="cash-grid cash-hub-balances-grid">@foreach($hubs as $hub)<article class="cash-hub-balance-card">
        <header class="cash-hub-balance-head"><span class="cash-hub-balance-pin"><x-admin-icon name="pin" /></span><div><strong>{{ $hub->municipality }}</strong><span>{{ $hub->province }}</span></div><span class="cash-hub-id">#{{ $hub->id }}</span></header>
        <div class="cash-hub-balance-amount"><span>Cash on hand</span><strong>PHP {{ number_format($custody[$hub->id] / 100, 2) }}</strong></div>
        <p>Includes unallocated overages. Not a Seller payout balance.</p>
        @unless($hub->is_operational)<span class="cash-hub-inactive"><x-admin-icon name="info" />Inactive hub · transfers unavailable</span>@endunless
      </article>@endforeach @if($hubs->isEmpty())<p>No company hubs are available in your scope.</p>@endif</div>
    </details>
    @endunless
  </section>

  @include('logistics.partials.recovery-cash')
  <section class="cash-card cash-outstanding-card" id="cash-outstanding"><h2 class="cash-section-title"><span class="cash-section-icon"><x-admin-icon name="clock" /></span>Outstanding collections</h2><p>Amounts are in PHP. Cash is due at the hub recorded on the collection, including after a rider reassignment. @if($rider)A zero amount leaves that collection unselected.@endif</p>
  @forelse($collections->groupBy('hub_id') as $hubId => $group)
    @if($rider)<form method="POST" action="{{ route('rider.remittances.submit') }}">@csrf<input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">@endif
    <h3>{{ $hubLabels[$hubId] ?? "Recorded hub #".$hubId }}</h3><div class="cash-table" tabindex="0" role="region" aria-label="Cash amounts table (scroll horizontally)"><table><thead><tr><th>Order / rider</th><th>Outstanding</th><th>Awaiting Hub Verification</th><th>Pending Rider Remittance<small>Available to submit</small></th><th>Due &middot; Asia/Manila</th>@if($rider)<th>Submit amount</th>@endif</tr></thead><tbody>
    @foreach($group as $c)<tr><td><span class="cash-reference">{{ $c->order_id ?? $c->id }}</span><small>{{ $people[$c->actor_id] ?? 'Rider' }}</small></td><td>{{ number_format($c->balance['outstanding'] / 100, 2) }}</td><td>{{ number_format($c->balance['reserved'] / 100, 2) }}</td><td>{{ number_format($c->balance['available'] / 100, 2) }}</td><td>{{ $c->due->format('M j, Y g:i A') }} @if($c->overdue)<strong class="cash-warning">Overdue</strong>@endif</td>
      @if($rider)<td><label for="collection-{{ $c->id }}">Amount to hand over</label><input id="collection-{{ $c->id }}" name="allocations[{{ $c->id }}]" type="number" min="0" max="{{ number_format($c->balance['available'] / 100, 2, '.', '') }}" step="0.01" value="0" required></td>@endif</tr>@endforeach
    </tbody></table></div>
    @if($rider)<button class="btn btn-primary">Submit selected amounts</button></form>@endif
  @empty<div class="cash-empty cash-collections-empty">@unless($rider)<span class="cash-empty-icon"><x-admin-icon name="box" /></span>@endunless<strong>No outstanding COD collections.</strong><p>Rider collections awaiting remittance or counting will appear here.</p></div>@endforelse
  </section>

  @if($settings)
  <div class="cash-grid cash-settings-grid" id="cash-settings">
    <section class="cash-card"><h2>Remittance cutoff</h2><p>Company working-day cutoff in Asia/Manila. Flags only; no automatic deductions or rider blocks.</p><form method="POST" action="{{ route('logistics.cash-desk.cutoff') }}">@csrf<input type="hidden" name="revision" value="{{ $settings->revision }}"><label for="cash-cutoff">End of working day</label><input id="cash-cutoff" name="cutoff" type="time" value="{{ old('cutoff', substr($settings->remittance_cutoff, 0, 5)) }}" step="60" aria-describedby="cutoff-help" required><small id="cutoff-help">Minute precision, Philippine time. Applies to overdue flags; no automatic penalties.</small><button class="btn btn-primary">Save cutoff</button></form></section>
    @include('logistics.partials.cash-handover-history')
    <section class="cash-card"><h2>Authorize hub cash transfer</h2>@if($hubs->where('is_operational', true)->count() < 2)<div class="cash-unavailable"><strong>Transfers unavailable</strong><p>Two operational company hubs are required for a hub transfer. Your current hub can still receive Rider remittances through assigned Hub Staff.</p></div>@else<p>Origin Hub Staff releases cash; destination Hub Staff counts and confirms receipt. Authorization does not move cash.</p><form method="POST" action="{{ route('logistics.cash-desk.authorize') }}" data-cash-transfer>@csrf<input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
      @foreach(['hub_id'=>'Origin hub', 'destination_hub_id'=>'Destination hub'] as $field=>$label)<label for="cash-{{ $field }}">{{ $label }}</label><select id="cash-{{ $field }}" name="{{ $field }}" required><option value="">Choose {{ strtolower($label) }}</option>@foreach($hubs->where('is_operational', true) as $hub)<option value="{{ $hub->id }}" data-on-hand="{{ $custody[$hub->id] }}" @selected((string)old($field) === (string)$hub->id)>{{ $hub->municipality }} &middot; {{ $hub->province }} (#{{ $hub->id }})</option>@endforeach</select>@endforeach
      <label for="transfer-amount">Authorized amount (PHP)</label><input id="transfer-amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" aria-describedby="transfer-balance" required>
      <p id="transfer-balance" role="status" aria-live="polite">Select an origin to see recorded on-hand cash.</p><small>Authorization does not reserve funds. Staff release rechecks the actual available balance.</small><label for="transfer-notes">Transfer instructions / custody reference</label><textarea id="transfer-notes" name="notes" maxlength="2000" required>{{ old('notes') }}</textarea><button class="btn btn-primary" @disabled($hubs->where('is_operational', true)->count() < 2)>Authorize transfer</button>@if($hubs->where('is_operational', true)->count() < 2)<p>Two operational company hubs are required for a hub transfer.</p>@endif</form>@endif</section>
  </div>
  @endif

  @unless($settings)
  @include('logistics.partials.cash-handover-history')
  @endunless
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics-cash-desk.js') }}" defer></script>
@endpush
