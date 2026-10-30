    <article class="cash-handover" id="payout-{{ $p->id }}">
      <header>
        <div>
          <h3 class="settlement-amount">PHP {{ number_format($p->amount_cents/100,2) }}</h3>
          <span class="settlement-status">{{ ucfirst($p->status) }}</span>
          <span class="cash-reference">Staff account #{{ $p->staff_id }} / Period {{ $p->period_id }}</span>
        </div>
        <span class="settlement-acknowledgement">
          {{ $p->acknowledged_at ? 'Staff acknowledged' : ($p->status==='paid' ? 'Acknowledgement pending' : 'Not yet sent') }}
        </span>
      </header>
      @if($destination)<p>Payee: {{ $destination->institution }} / {{ $destination->account_name }} / {{ $destination->account_number }}</p>@endif
      @if($p->hold_reason)<p class="cash-warning">Hold: {{ $p->hold_reason }}</p>@endif
      @if($p->reference)<p>Transfer reference: {{ $p->reference }} / Transfer date {{ $p->payment_date ?? 'Not recorded' }} PHT / Recorded {{ $p->paid_at }} UTC.</p>@endif
      @if($evidence)<a class="btn" href="{{ route('finance.evidence',$evidence->id) }}" target="_blank" rel="noopener">View private payment evidence</a>@endif
      @if(in_array($p->status,['prepared','verified']))
      <details><summary>Review, hold or cancel</summary>
        <form method="POST" action="{{ route('logistics.finance.staff.action',$p->id) }}">@csrf
          <input type="hidden" name="revision" value="{{ $p->revision }}">
          <label for="action-{{ $p->id }}">Action</label>
          <select id="action-{{ $p->id }}" name="action" required>
            @if($p->status==='prepared' && $member->status==='approved' && ($destination?->channel==='gcash' && $accounts->firstWhere('id',$p->treasury_account_id)?->channel==='gcash'))<option value="verify">Verify payout instruction</option>@endif
            <option value="hold">Place documented hold</option>
            <option value="release">Release manual hold</option>
            <option value="cancel">Cancel unsent payout and release reservation</option>
          </select>
          <label for="action-notes-{{ $p->id }}">Review / hold reason</label>
          <textarea id="action-notes-{{ $p->id }}" name="notes" minlength="3" maxlength="2000" required></textarea>
          <button class="btn btn-primary">Record review</button>
        </form>
      </details>
      @if($p->status==='verified' && $member->status==='approved' && !$p->hold_reason && ($destination?->channel==='gcash' && $accounts->firstWhere('id',$p->treasury_account_id)?->channel==='gcash'))
      <details><summary>Record actual staff payment</summary>
        <form method="POST" enctype="multipart/form-data" action="{{ route('logistics.finance.staff.pay',$p->id) }}">@csrf
          <input type="hidden" name="revision" value="{{ $p->revision }}">
          <label for="payment-date-{{ $p->id }}">Actual GCash transfer date (PHT)</label>
          <input id="payment-date-{{ $p->id }}" type="date" name="payment_date" value="{{ old('payment_date') }}" max="{{ now()->setTimezone('Asia/Manila')->toDateString() }}" required>
          @include('logistics.partials.finance-receipt-fields',['prefix'=>'staff-pay-'.$p->id,'confirmationField'=>'sent','confirmationDescription'=>'salary transfer to the recorded Staff GCash account','retainInput'=>true])
          <button class="btn btn-primary">Record actual payment</button>
        </form>
      </details>
      @endif
      @endif
      <details><summary>Audit history</summary>
        @foreach($history as $h)
        @php $detail=json_decode($h->details,true); @endphp
        <p>{{ str_replace('_',' ',ucfirst($h->event)) }} / Actor #{{ $h->actor_id }} / {{ $h->created_at }} UTC
          @if(isset($detail['notes']))<br>{{ $detail['notes'] }}@endif</p>
        @endforeach
      </details>
    </article>