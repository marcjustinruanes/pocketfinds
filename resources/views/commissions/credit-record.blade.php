<article class="commission-record">
    <h3>&#8369;{{ $money($credit->amount_cents) }} <span class="commission-badge status-{{ $credit->status }}">{{ ucfirst($credit->status) }}</span></h3>
    <p>Seller #{{ $credit->seller_id }} &middot; {{ $credit->order_id }}<br>Payer: {{ ucfirst($credit->payer) }} &middot; {{ $credit->reason }}</p>
    @if($credit->proof_path)<a href="{{ route('commissions.evidence',['credit',$credit->id]) }}">View repayment proof &rarr;</a>@endif
    @if($credit->status==='pending' && ($credit->payer==='platform' ? $actor->is_admin : (!$actor->is_admin && $actor->account_type==='logistics')))
        <details class="commission-action"><summary>Record credit repayment</summary><form method="POST" enctype="multipart/form-data" action="{{ route('commissions.repay',$credit->id) }}" data-confirm="Confirm this commission credit was actually repaid to the Seller?">@csrf @include('commissions.receipt-fields') @include('commissions.destination',['sellerId'=>$credit->seller_id])<label>Repayment reason<textarea name="reason" minlength="3" maxlength="2000" required></textarea></label><p>Repay Seller #{{ $credit->seller_id }} using their recorded GCash receiving details.</p><button class="btn">Record actual credit repayment</button></form></details>
    @endif
</article>
