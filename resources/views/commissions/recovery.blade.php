@if($recoveries!==null)
<section id="commission-payment-recovery" class="card commission-panel"><div class="card-head"><h2>{{ $isSeller?'Already sent a payment?':'Payment recovery and unapplied receipts' }}</h2></div><div class="card-pad">
<p>Record an actual transfer even with no outstanding commission, or when another rejected claim used your reference. No commission is collected until Platform Admin checks the receipt and its rightful owner. Verified funds remain your credit until allocated or repaid.</p>
@if($isSeller && $accounts->isNotEmpty())
<form method="POST" action="{{ route('commissions.recoverySubmit') }}" enctype="multipart/form-data" data-confirm="Record an actual payment already sent for receipt verification?">@csrf
<input type="hidden" name="submission_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<p>For money already transferred only. These historical destinations are not instructions to send a new payment.</p>
<label>Receiving account already used<select name="receiving_account_id" required>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->account_name }} / {{ $a->account_number }} / {{ $a->created_at }}</option>@endforeach</select></label>
@include('commissions.receipt-fields')
<label>Payment or reference recovery explanation<textarea name="reason" minlength="3" maxlength="2000" required></textarea></label>
<button class="btn" type="submit">Submit already sent payment</button></form>
@endif
@include('commissions.history-filter',['history'=>'recovery','kind'=>'recoveries'])
@forelse($recoveries as $recovery)
<article id="commission-recovery-{{ $recovery->id }}" class="commission-record"><h3>Receipt {{ $recovery->reference }} &middot; Seller #{{ $recovery->seller_id }}</h3>
@include('commissions.message-link',['conversationKind'=>'recovery','conversationId'=>$recovery->id])
@php($recoveryAccount=$accounts->firstWhere('id',$recovery->receiving_account_id))
<p>Recorded receiving destination: {{ $recoveryAccount?->account_name }} / {{ $recoveryAccount?->account_number }}</p>
<p>{{ ucfirst($recovery->status) }} &middot; &#8369;{{ $money($recovery->amount_cents) }} &middot; Unapplied &#8369;{{ $money($recovery->unapplied_cents) }}<br>{{ $recovery->reason }}<br>{{ $recovery->review_reason }}</p>
<a href="{{ route('commissions.evidence',['kind'=>'recovery','id'=>$recovery->id]) }}">View private proof</a>
@if($actor->is_admin && isset($recoveryPriorPayments[$recovery->reference]))@php($prior=$recoveryPriorPayments[$recovery->reference])<p>Prior claim {{ $prior->id }} / Seller #{{ $prior->seller_id }} / {{ $prior->status }}. Only a rejected, entirely uncollected GCash claim can be resolved through recovery. <a href="{{ route('commissions.evidence',['kind'=>'payment','id'=>$prior->id]) }}">Review prior private evidence</a></p>@endif
@if($actor->is_admin && $recovery->status!=='verified')
<details><summary>Review receipt ownership</summary><form method="POST" action="{{ route('commissions.recoveryReview',$recovery->id) }}" data-confirm="Record this receipt review and ownership decision?">@csrf<input type="hidden" name="revision" value="{{ $recovery->revision }}">
<label>Decision<select name="decision"><option value="review">Needs review</option><option value="correction">Request corrections</option><option value="reject">Reject claim</option><option value="verify">Verify actual receipt</option></select></label>
<label>Exact received amount<input name="amount" type="number" step="0.01" min="0.01"></label><label>Verified reference<input name="reference" maxlength="64"></label>
<label><input name="received" type="checkbox" value="1">I verified actual funds in the recorded receiving account.</label><label><input name="ownership_checked" type="checkbox" value="1">I checked the rightful Seller, receiving destination and any prior rejected claim.</label>
<label>Review and ownership evidence<textarea name="reason" minlength="3" maxlength="2000" required></textarea></label><button class="btn">Record review</button></form></details>
@elseif($actor->is_admin && $recovery->unapplied_cents>0)
<details><summary>Allocate verified credit</summary><form method="POST" action="{{ route('commissions.recoveryAllocate',$recovery->id) }}" data-confirm="Allocate verified credit to this Seller's GCash commission?">@csrf<label>Own Seller GCash obligation ID<input name="obligation_id" required></label><label>Amount<input name="amount" type="number" step="0.01" min="0.01" required></label><button class="btn">Allocate credit</button></form></details>
<details><summary>Record actual credit repayment</summary><form method="POST" action="{{ route('commissions.recoveryRefund',$recovery->id) }}" enctype="multipart/form-data" data-confirm="Confirm this credit repayment was actually sent?">@csrf
@include('commissions.destination',['sellerId'=>$recovery->seller_id])
@include('commissions.receipt-fields')<label>Repayment reason<textarea name="reason" required minlength="3" maxlength="2000"></textarea></label><button class="btn">Record actual repayment</button></form></details>
@endif
<details><summary>Recovery activity and repayments</summary>@foreach($recoveryEvents[$recovery->id]??[] as $event)<p>{{ $event->created_at }} / {{ $event->kind }} / Actor #{{ $event->actor_id }}<br>{{ json_decode($event->details,true)['ownership_reason']??'' }}</p>@endforeach
@foreach($recoveryRefunds[$recovery->id]??[] as $refund)<p>Repaid &#8369;{{ $money($refund->amount_cents) }} / {{ $refund->reference }} / {{ $refund->paid_at }}<br>{{ $refund->destination }}<br>{{ $refund->reason }} <a href="{{ route('commissions.evidence',['kind'=>'recovery-refund','id'=>$refund->id]) }}">Private repayment proof</a></p>@endforeach</details>
</article>
@empty<p>No payment recovery receipts.</p>@endforelse{{ $recoveries->links() }}
</div></section>
@endif
