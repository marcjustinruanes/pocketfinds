@extends('logistics.layout')
@section('title','My Salary')
@section('page-title','My Salary')
@section('page-sub','Your salary rate, payroll periods and payment history')
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}?v={{ filemtime(public_path('css/logistics-cash-desk.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/logistics-finance-feedback.js') }}" defer></script>@endpush
@section('content')
<div class="cash-desk settlement-workspace">
  @if(session('success'))<div class="cash-alert cash-alert--success" role="alert">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="cash-alert" role="alert"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <nav class="cash-page-nav" aria-label="My salary sections">
    <a class="btn" href="#rate">My Rate</a>
    <a class="btn" href="#periods">Payroll Periods</a>
    <a class="btn" href="#history">Payment History</a>
    <a class="btn" href="#receiving">Receiving Account</a>
  </nav>

  @include('logistics.partials.staff-salary-summary')
  {{-- ── Current rate ── --}}
  <section class="cash-card" id="rate" aria-label="Configured salary rate">
    <h2>My Salary Rate</h2>
    @if($salary && $salary->monthly_cents>0)
    <p>Next scheduled pay date: <strong>{{ $upcomingPayDate->toDateString() }} PHT</strong>. A date is a schedule, not confirmation of payment.</p>
    <div class="cash-stats">
      <div><span>Configured monthly salary</span><strong>PHP {{ number_format($salary->monthly_cents/100,2) }}</strong></div>
      <div><span>First / second installment</span><strong>PHP {{ number_format(intdiv($salary->monthly_cents,2)/100,2) }} / PHP {{ number_format((intdiv($salary->monthly_cents,2)+$salary->monthly_cents%2)/100,2) }}</strong></div>
      <div><span>Effective period / initial configuration</span><strong>{{ $salary->period_effective ?? \Carbon\CarbonImmutable::parse($salary->effective_from,'UTC')->setTimezone('Asia/Manila')->format('M j, Y') }}</strong></div>
    </div>
    <p>{{ ($salary->salary_source??'individual')==='hub' ? 'Hub default / hub #'.$salary->source_hub_id : 'Individual salary override' }}. Period obligations retain their own historical salary snapshots.</p>
    <p>The 1st-15th installment is payable on the 15th; the 16th-end of month installment is payable on the 30th or earlier month end. The second installment receives an odd remaining centavo.</p>
    @else
    <p><strong>Missing Salary Configuration</strong>. Contact your Logistics Admin. No salary amount is assumed for an unconfigured hub.</p>
    @endif
    @if($salaryHistory->isNotEmpty())
    <details><summary>Salary history</summary>
      @foreach($salaryHistory as $h)
      <p>{{ $h->monthly_cents ? 'PHP '.number_format($h->monthly_cents/100,2).'/month' : 'Missing Salary Configuration' }} / Revision {{ $h->revision }}
        / {{ ($h->salary_source??'individual')==='hub' ? 'Hub default / hub #'.$h->source_hub_id : 'Individual override' }}
        / Effective period or initial configuration {{ $h->period_effective ?? \Carbon\CarbonImmutable::parse($h->effective_from,'UTC')->setTimezone('Asia/Manila')->format('M j, Y') }}
      </p>
      @endforeach
    </details>
    @endif
  </section>

  {{-- ── Payroll periods ── --}}
  <section class="cash-card" id="periods" aria-label="Payroll periods">
    <h2>Payroll Periods</h2>
    <p>Each row is one semi-monthly installment. Your Logistics Admin generates and pays these. Acknowledge receipt once you have confirmed the actual GCash transfer in your account.</p>
    <div class="cash-table">
      <table><thead><tr>
        <th>Period</th><th>Pay date</th><th>Amount</th><th>Status</th><th></th>
      </tr></thead><tbody>
      @forelse($periods as $period)
      @php($payout = $periodPayouts[$period->id] ?? null)
      <tr>
        <td>{{ $period->period_year }}-{{ str_pad($period->period_month,2,'0',STR_PAD_LEFT) }}
          Half {{ $period->period_half }}
          <small class="cash-reference">{{ $period->period_half === 1 ? '1st–15th' : '16th–end of month' }}</small>
        </td>
        <td class="mono">{{ $period->pay_date }}</td>
        <td>PHP {{ number_format($period->amount_cents/100,2) }}</td>
        <td><span class="stamp stamp-{{ $period->status }}">{{ ucfirst($period->status) }}</span></td>
        <td>
          @if($payout && $payout->status === 'paid' && !$payout->acknowledged_at)
          <form method="POST" action="{{ route('logistics.my-salary.acknowledge',$payout->id) }}">@csrf
            <label class="cash-confirmation" for="ack-{{ $payout->id }}">
              <input id="ack-{{ $payout->id }}" name="received" type="checkbox" value="1" required>
              I received this payment in my recorded account.
            </label>
            <button class="btn btn-primary">Acknowledge receipt</button>
          </form>
          @elseif($payout && $payout->acknowledged_at)
          <span style="color:var(--success)">✓ Acknowledged</span>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="5">No payroll periods generated yet. Contact your Logistics Admin.</td></tr>
      @endforelse
      </tbody></table>
    </div>
    {{ $periods->links() }}
  </section>

  {{-- ── Payment history ── --}}
  <section class="cash-card" id="history" aria-label="Payment history">
    <h2>Payment History</h2>
    @forelse($payouts as $p)
    @php($destination = $destinations->firstWhere('id',$p->destination_id))
    <article class="cash-handover">
      <header>
        <div>
          <h3 class="settlement-amount">PHP {{ number_format($p->amount_cents/100,2) }}</h3>
          <span class="settlement-status">{{ ucfirst($p->status) }}</span>
        </div>
        <span class="settlement-acknowledgement">
          {{ $p->acknowledged_at ? 'Acknowledged '.\Carbon\CarbonImmutable::parse($p->acknowledged_at,'UTC')->setTimezone('Asia/Manila')->format('M j, Y') : ($p->status==='paid' ? 'Acknowledgement pending' : 'Not yet sent') }}
        </span>
      </header>
      @if($destination)<p>To: {{ $destination->institution }} / {{ $destination->account_name }} / {{ '*******'.substr($destination->account_number,-4) }}</p>@endif
      @if($p->reference)<p>Transfer reference: {{ $p->reference }} / Transfer date {{ $p->payment_date ?? 'Not recorded' }} PHT / Recorded {{ \Carbon\CarbonImmutable::parse($p->paid_at,'UTC')->setTimezone('Asia/Manila')->format('M j, Y g:i A') }} PHT.</p>@endif
      @if(isset($evidence[$p->id]))<a class="btn" href="{{ route('finance.evidence',$evidence[$p->id]->id) }}" target="_blank" rel="noopener">View payment evidence</a>@endif
      @if($p->status === 'paid' && !$p->acknowledged_at)
      <form method="POST" action="{{ route('logistics.my-salary.acknowledge',$p->id) }}">@csrf
        <label class="cash-confirmation" for="hist-ack-{{ $p->id }}">
          <input id="hist-ack-{{ $p->id }}" name="received" type="checkbox" value="1" required>
          I received this payout in my recorded account.
        </label>
        <button class="btn btn-primary">Acknowledge receipt</button>
      </form>
      @endif
    </article>
    @empty
    <p>No payments recorded yet.</p>
    @endforelse
    {{ $payouts->links() }}
  </section>

  {{-- ── Receiving account ── --}}
  <section class="cash-card" id="receiving" aria-label="GCash receiving account">
    <h2>My Receiving Account</h2>
    <p>Only you can add a receiving account. Your Logistics Admin will use this to send salary payments. Existing payouts retain their original instructions.</p>
    @forelse($destinations as $d)
    <div class="settlement-account">
      <strong>{{ $d->institution }}</strong>
      <span>{{ $d->account_name }}</span>
      <span class="cash-reference">{{ '*******'.substr($d->account_number,-4) }}</span>
      <small>Added {{ \Carbon\CarbonImmutable::parse($d->created_at,'UTC')->setTimezone('Asia/Manila')->format('M j, Y') }}</small>
    </div>
    @empty
    <p>No receiving account added yet. Add one below so your Admin can prepare your salary payment.</p>
    @endforelse
    <a class="btn" href="{{ route('logistics.account') }}#salary-receiving">Manage GCash details in My Account</a>
  </section>
</div>
@endsection
