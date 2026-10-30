@extends($rider ? 'rider.layout' : 'logistics.layout')
@section('title','Rider Earnings & Payouts')
@section('page-title',$rider ? 'Earnings & Payouts' : 'Rider Earnings & Payouts')
@section('page-sub','Completed assignments, verified funding and payment history')
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}?v={{ filemtime(public_path('css/logistics-cash-desk.css')) }}">@endpush
@section('content')
@if(auth()->user()->isLogisticsAdmin())
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-finance-navigation.css') }}?v={{ filemtime(public_path('css/logistics-finance-navigation.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/logistics-finance-feedback.js') }}" defer></script>@endpush
@endif
<div class="cash-desk settlement-workspace rider-finance-workspace">
  @if($rider && $errors->any() && !$errors->has('status'))<div class="cash-alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @unless(auth()->user()->isLogisticsAdmin())
  <nav class="cash-page-nav" aria-label="Rider finance sections"><a class="btn" href="#earnings">Earnings</a><a class="btn" href="#payments">Payout history</a><a class="btn" href="{{ $rider ? '#receiving' : '#funding' }}">{{ $rider ? 'Receiving accounts' : 'Payroll funding' }}</a></nav>
  @endunless
  <section class="cash-card" aria-label="Earnings summary"><div class="cash-stats">
    <div><span>Earned compensation</span><strong>PHP {{ number_format($totals['earned']/100,2) }}</strong></div>
    <div><span>Paid</span><strong>PHP {{ number_format($totals['paid']/100,2) }}</strong></div>
    <div><span>Unpaid earnings</span><strong>PHP {{ number_format(($totals['earned']-$totals['paid'])/100,2) }}</strong><small>PHP {{ number_format($totals['reserved']/100,2) }} reserved for prepared or verified payouts</small></div>
  </div><p>Rates are fixed when assigned. Shipping fees and COD cash are separate from rider income. Cash shortages do not reduce these earnings.</p></section>
  <section class="cash-card" id="earnings"><h2>Assignment earnings</h2><p>Pickup earns after two-sided handover and hub receipt. Delivery earns on successful recorded delivery. A hub-transfer batch earns once after every parcel is received.</p>
    <div class="cash-table"><table><thead><tr><th scope="col">Assignment</th>@unless($rider)<th scope="col">Rider</th>@endunless<th scope="col">Snapshot</th><th scope="col">Status</th><th scope="col">Completed</th>@unless($rider)<th scope="col">Review</th>@endunless</tr></thead><tbody>
    @forelse($earnings as $c)<tr><td>{{ ucfirst($c->leg) }}<small class="cash-reference">{{ $c->source_type }} {{ $c->source_id }}</small></td>@unless($rider)<td>{{ $people[$c->rider_id] ?? $c->rider_id }}</td>@endunless<td>PHP {{ number_format($c->amount_cents/100,2) }}<small>Rate revision {{ $c->settings_revision }}</small></td><td>{{ ucfirst($c->status) }}</td><td>{{ $c->earned_at ? $c->earned_at.' UTC' : 'Not yet earned' }}</td>
      @unless($rider)<td>@if($c->status==='pending')<form method="POST" action="{{ route('logistics.finance.riders.recognize',$c->id) }}">@csrf<button class="btn">Review completion evidence</button></form>
      @elseif($c->status==='earned' && $c->amount_cents>0 && (!isset($existing[$c->id]) || $existing[$c->id]->status==='cancelled'))<details><summary>Prepare payout</summary><form method="POST" action="{{ route('logistics.finance.riders.prepare') }}">@csrf<input type="hidden" name="compensation_id" value="{{ $c->id }}">
        <label for="payroll-account-{{ $c->id }}">Funded company account</label><select id="payroll-account-{{ $c->id }}" name="account_id" required><option value="">Choose account</option>@foreach($accounts->where('channel','gcash') as $a)<option value="{{ $a->id }}">{{ $a->institution }} / {{ $a->account_number }}</option>@endforeach</select>
        <label for="rider-destination-{{ $c->id }}">Rider-provided receiving account</label><select id="rider-destination-{{ $c->id }}" name="destination_id" required><option value="">Choose account</option>@foreach($destinations->where('rider_id',$c->rider_id)->where('channel','gcash') as $d)<option value="{{ $d->id }}">{{ $d->institution }} / {{ $d->account_name }} / {{ $d->account_number }}</option>@endforeach</select><button class="btn btn-primary">Prepare and reserve funds</button></form></details>
      @elseif(isset($existing[$c->id])){{ ucfirst($existing[$c->id]->status) }} payout @else No payout action @endif</td>@endunless
    </tr>@empty<tr><td colspan="{{ $rider ? 4 : 6 }}">No compensation snapshots available. Missing historical rates are not backfilled.</td></tr>@endforelse
    </tbody></table></div>{{ $earnings->links() }}
  </section>
  <section class="cash-card" id="payments"><h2>Payout history</h2><p>GCash is the only supported payment channel. Historical bank instructions require review; cancel only if never sent.</p><p>Preparation reserves payroll funds. Verification approves the instruction. Paid records an actual transfer; acknowledgement confirms the rider received it.</p>
    @forelse($payouts as $p)@php($destination=$destinations[$p->destination_id] ?? null)
@php($gcashAvailable=($destination?->channel==='gcash' && $accounts->firstWhere('id',$p->treasury_account_id)?->channel==='gcash'))
      <article class="cash-handover"><header><div><h3 class="settlement-amount">PHP {{ number_format($p->amount_cents/100,2) }}</h3><span class="settlement-status">{{ ucfirst($p->status) }}</span><span class="cash-reference">{{ $people[$p->rider_id] ?? $p->rider_id }} / {{ $p->company_name }}</span></div><span class="settlement-acknowledgement">{{ $p->acknowledged_at ? 'Rider acknowledged' : ($p->status==='paid' ? 'Acknowledgement pending' : 'Not yet sent') }}</span></header>
      <p class="cash-reference">Earning {{ $p->compensation_id }}</p>
      @if($destination)<p>Payee: {{ $destination->institution }} / {{ $destination->account_name }} / {{ $destination->account_number }}</p>@endif
      @if($p->hold_reason)<p class="cash-warning">Hold: {{ $p->hold_reason }}</p>@endif
      @if($p->reference)<p>Transfer reference: {{ $p->reference }} / Paid {{ $p->paid_at }} UTC.</p>@endif
      @if(isset($evidence[$p->id]))<a class="btn" href="{{ route('finance.evidence',$evidence[$p->id]->id) }}" target="_blank" rel="noopener">View private payment evidence</a>@endif
      @if($rider && $p->status==='paid' && !$p->acknowledged_at)<form method="POST" action="{{ route('rider.earnings.acknowledge',$p->id) }}">@csrf<label class="cash-confirmation" for="rider-ack-{{ $p->id }}"><input id="rider-ack-{{ $p->id }}" name="received" type="checkbox" value="1" required> I received this payout in my recorded account.</label><button class="btn btn-primary">Acknowledge receipt</button></form>@endif
      @if(!$rider && in_array($p->status,['prepared','verified']))
        <details><summary>Review, hold or cancel</summary><form method="POST" action="{{ route('logistics.finance.riders.action',$p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}"><label for="rider-action-{{ $p->id }}">Action</label><select id="rider-action-{{ $p->id }}" name="action" required>@if($p->status==='prepared' && $gcashAvailable)<option value="verify">Verify payout instruction</option>@endif<option value="hold">Place documented hold</option><option value="release">Release manual hold</option><option value="cancel">Cancel unsent payout and release reservation</option></select><label for="rider-notes-{{ $p->id }}">Review / hold reason</label><textarea id="rider-notes-{{ $p->id }}" name="notes" minlength="3" maxlength="2000" required></textarea><button class="btn btn-primary">Record review</button></form></details>
        @if($p->status==='verified' && !$p->hold_reason && $gcashAvailable)<details><summary>Record actual rider payment</summary><form method="POST" enctype="multipart/form-data" action="{{ route('logistics.finance.riders.pay',$p->id) }}">@csrf<input type="hidden" name="revision" value="{{ $p->revision }}">
@include('logistics.partials.finance-receipt-fields',['prefix'=>'rider-pay-'.$p->id,'confirmationField'=>'sent'])<button class="btn btn-primary">Record actual payout</button></form></details>@endif
      @endif
      <details><summary>Audit history</summary>@foreach($history[$p->id] ?? [] as $h)@php($detail=json_decode($h->details,true))<p>{{ str_replace('_',' ',ucfirst($h->event)) }} / Actor #{{ $h->actor_id }} / {{ $h->created_at }} UTC @if(isset($detail['notes']))<br>{{ $detail['notes'] }}@endif</p>@endforeach</details>
      </article>
    @empty<p>No rider payouts recorded.</p>@endforelse{{ $payouts->links() }}
  </section>
  @if($rider)<section class="cash-card" id="receiving"><h2>Your receiving accounts</h2><p>Only you can add a receiving account. Existing payouts retain their original instructions.</p>
    @forelse($destinations->where('rider_id',$actor->id) as $d)<div class="settlement-account"><strong>{{ $d->institution }}</strong><span>{{ $d->account_name }}</span><span class="cash-reference">{{ $d->account_number }}</span></div>@empty<p>No receiving accounts added yet.</p>@endforelse
    <details><summary>Add a receiving account</summary><form method="POST" action="{{ route('rider.earnings.destination') }}">@csrf
@include('logistics.partials.finance-account-fields',['prefix'=>'rider-destination'])<button class="btn btn-primary">Save receiving account</button></form></details>
  </section>@else<section class="cash-card" id="funding"><h2>Rider payroll funding</h2><p>Use separately verified company contributions only. COD funds, seller adjustments, shipping receipts and overages cannot fund rider payouts here.</p>
    <div class="cash-grid">@forelse($accounts as $a)<div><h3>{{ $a->institution }} / {{ $a->account_name }}</h3><p>{{ $a->account_number }}</p><span>Available payroll funds</span><strong class="cash-balance">PHP {{ number_format($balances[$a->id]['available']/100,2) }}</strong><small>Verified balance PHP {{ number_format($balances[$a->id]['balance']/100,2) }} before reservations</small></div>@empty<p>Authorize a company receiving account in <a href="{{ route('logistics.finance.payouts') }}#treasury">Seller Settlements / Company treasury</a> first.</p>@endforelse</div>
    <details><summary>Record verified payroll contribution</summary><p>Confirm an actual additional contribution with its own reference and private proof. Do not re-enter a receipt already recorded in another financial bucket.</p><form method="POST" enctype="multipart/form-data" action="{{ route('logistics.finance.riders.funding') }}">@csrf<input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}"><label for="payroll-funding-account">Company receiving account</label><select id="payroll-funding-account" name="account_id" required>@foreach($accounts->where('channel','gcash') as $a)<option value="{{ $a->id }}">{{ $a->institution }} / {{ $a->account_number }}</option>@endforeach</select>
@include('logistics.partials.finance-receipt-fields',['prefix'=>'payroll-funding','confirmationField'=>'received'])<button class="btn btn-primary">Record verified contribution</button></form></details>
    <details><summary>Verified funding history</summary>@forelse($funding as $m)<p>PHP {{ number_format($m->delta_cents/100,2) }} / {{ $m->reference }} / Actor #{{ $m->actor_id }} / {{ $m->created_at }} UTC</p>@if(isset($fundingEvidence[$m->source_id]))<a class="btn" href="{{ route('finance.evidence',$fundingEvidence[$m->source_id]->id) }}" target="_blank" rel="noopener">View private funding evidence</a>@endif @empty<p>No verified payroll contributions.</p>@endforelse</details>
  </section>@endif
</div>
@endsection
