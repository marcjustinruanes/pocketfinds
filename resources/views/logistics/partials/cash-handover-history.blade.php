  <section class="cash-card cash-history-card" id="cash-history"><h2 class="cash-section-title"><span class="cash-section-icon"><x-admin-icon name="clock" /></span>Handover history</h2><p>Counts are final. Corrections require financial review; resolving a discrepancy documents the decision without changing cash or forgiving a balance.</p>
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
  @empty<div class="cash-empty cash-history-empty"><span class="cash-empty-icon"><x-admin-icon name="file" /></span><strong>{{ request('queue') || request('handover') ? 'No handovers match this selection.' : 'No cash handovers yet.' }}</strong><p>Use All handovers to review other records. Submitted remittances and authorized transfers appear here; authorization alone does not move cash.</p></div>@endforelse
  {{ $handovers->links() }}
  </section>
