  <section class="cash-card" id="funding" aria-label="Payroll funding">
    <h2>Staff Payroll Funding</h2>
    <p>Use separately verified company contributions only. COD funds, seller adjustments, shipping receipts and rider payroll cannot fund staff salary payments here.</p>
    <div class="cash-grid">
      @forelse($accounts as $a)
      <div>
        <h3>{{ $a->institution }} / {{ $a->account_name }}</h3>
        <p>{{ $a->account_number }}</p>
        <span>Available staff-payroll funds</span>
        <strong class="cash-balance">PHP {{ number_format($funds[$a->id]['available']/100,2) }}</strong>
        <small>Verified balance PHP {{ number_format($funds[$a->id]['balance']/100,2) }} before reservations</small>
      </div>
      @empty
      <p>Authorize a company receiving account in <a href="{{ route('logistics.finance.payouts') }}#treasury">Seller Settlements / Company treasury</a> first.</p>
      @endforelse
    </div>
    <details><summary>Record verified payroll contribution</summary>
      <p>Confirm an actual additional contribution with its own reference and private proof. Do not re-enter a receipt already recorded in another financial bucket.</p>
      <form method="POST" enctype="multipart/form-data" action="{{ route('logistics.finance.staff.funding') }}">@csrf
        <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <label for="staff-funding-account">Company receiving account</label>
        <select id="staff-funding-account" name="account_id" required>
          @foreach($accounts->where('channel','gcash') as $a)
          <option value="{{ $a->id }}">{{ $a->institution }} / {{ $a->account_number }}</option>
          @endforeach
        </select>
        @include('logistics.partials.finance-receipt-fields',['prefix'=>'staff-funding','confirmationField'=>'received'])
        <button class="btn btn-primary">Record verified contribution</button>
      </form>
    </details>
    <details><summary>Verified funding history</summary>
      @forelse($funding as $m)
      <p>PHP {{ number_format($m->delta_cents/100,2) }} / {{ $m->reference }} / Actor #{{ $m->actor_id }} / {{ $m->created_at }} UTC</p>
      @if(isset($fundingEvidence[$m->source_id]))<a class="btn" href="{{ route('finance.evidence',$fundingEvidence[$m->source_id]->id) }}" target="_blank" rel="noopener">View private funding evidence</a>@endif
      @empty
      <p>No verified staff payroll contributions.</p>
      @endforelse
    </details>
    {{ $funding->links() }}
  </section>