@extends($actor->is_admin ? 'admin.layout' : ($actor->account_type === 'seller' ? 'seller.layout' : 'logistics.layout'))
@section('title', $actor->is_admin ? 'Commission Management' : ($actor->account_type==='seller' ? 'Commission Statements' : 'Seller Commissions'))
@section('page-title', $actor->is_admin ? 'Commission Management' : ($actor->account_type==='seller' ? 'Commission Statements' : 'Seller Commissions'))
@section('page-sub', $actor->account_type==='seller' && !$actor->is_admin ? 'Review your commission, submit GCash payments and track credits' : 'Review commission balances, verify receipts and track settlements')
@push('head')<link rel="stylesheet" href="{{ asset('css/commissions.css') }}?v={{ filemtime(public_path('css/commissions.css')) }}">@if(!$actor->is_admin && $actor->account_type==='seller')<link rel="stylesheet" href="{{ asset('css/seller-finance.css') }}?v={{ filemtime(public_path('css/seller-finance.css')) }}">@endif @endpush
@section('content')
@php($money=fn($v)=>number_format($v/100,2))
@php($isSeller=!$actor->is_admin && $actor->account_type==='seller')
@php($isLogistics=!$actor->is_admin && $actor->account_type==='logistics')
@php($logisticsMetricIcons=['provisional'=>'clock','earned_net'=>'peso','reversed'=>'edit','seller_due'=>'users','logistics_due'=>'box','overdue'=>'info','held'=>'shield'])
@if($isSeller)
    @include('commissions.seller-workspace')
@else
<div class="commission-workspace">
    @if($isSeller)
    <nav class="finance-section-links" aria-label="Finance sections"><a href="#finance-statements">Statements</a><a href="#finance-payment">Make a payment</a><a href="#finance-history">Payment history</a><a href="#finance-credits">Refund credits</a></nav>
    @endif
    <div class="commission-policy" role="note">
        <span class="commission-policy-icon" aria-hidden="true"><x-admin-icon name="peso" /></span>
        <div><strong>10% product commission</strong><p>Based on the product subtotal before voucher discounts. Shipping excluded.</p>@if($isSeller)<p>Platform-received GCash requires no separate commission payment. Older seller-received GCash balances remain payable; COD commission is withheld by Logistics.</p>@endif @if(config('commission.enabled') && config('commission.activation_at'))<p>Applies to new transactions from {{ config('commission.activation_at') }}.</p>@endif</div>
        <span class="commission-badge {{ config('commission.enabled') && config('commission.activation_at') ? 'is-success' : 'is-muted' }}">{{ config('commission.enabled') && config('commission.activation_at') ? 'New enrollment configured' : 'New enrollment disabled' }}</span>
    </div>
    @if(session('success'))<p class="commission-feedback is-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="commission-feedback is-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="commission-metrics">
        @foreach(($isSeller ? ['seller_due'=>'Your commission due','earned_net'=>'Net commission','provisional'=>'Awaiting completion','held'=>'On review hold'] : ['provisional'=>'Provisional','earned_net'=>'Net earned','reversed'=>'Reversed','seller_due'=>'Due from Sellers','logistics_due'=>'Due from Logistics','overdue'=>'Overdue','held'=>'On review hold']) as $key=>$label)
        <article class="{{ $isLogistics ? 'commission-metric-card' : '' }}">@if($isLogistics)<span class="commission-metric-icon"><x-admin-icon :name="$logisticsMetricIcons[$key]" /></span>@endif<span>{{ $isSeller ? (['earned_net'=>'Net commission','seller_due'=>'Separate commission due','logistics_due'=>'COD remittance pending'][$key] ?? $label) : $label }}</span><strong>&#8369;{{ $money($totals[$key]) }}</strong>@if($isSeller && $key==='seller_due' && $totals['overdue']>0)<small>Overdue &#8369;{{ $money($totals['overdue']) }}</small>@endif @if($isSeller && $key==='earned_net' && $totals['reversed']>0)<small>Reversed &#8369;{{ $money($totals['reversed']) }}</small>@endif</article>
        @endforeach
    </div>
    <div class="commission-receipts" aria-label="All-time payment activity">
        @foreach([($isSeller ? 'Your verified payments' : 'Verified receipts')=>$verifiedReceipts,'Unapplied receipt credit'=>$unapplied,'Commission repayments'=>$repayments] as $label=>$value)<div><span>{{ $label }}</span><strong>&#8369;{{ $money($value) }}</strong></div>@endforeach
    </div>
    <p class="commission-note">Receipt totals cover your full accessible history. Filters apply to the balances above and obligations below. Revenue is not profit.</p>

    <section class="card commission-panel" id="finance-statements">
        <div class="card-head"><h2>Statement filters</h2><a class="btn btn-outline" href="{{ route('commissions.export',request()->only('seller_id','company','method','date_basis','from','to','balance')) }}">Export statement CSV</a></div>
        <div class="card-pad"><form method="GET" class="commission-filter">
            @unless($isSeller)<label>Seller account ID<input name="seller_id" type="number" min="1" value="{{ request('seller_id') }}" placeholder="All sellers"></label>@endunless
            <label>Collection method<select name="method"><option value="">All methods</option>@foreach(['cod'=>'COD withholding','gcash'=>'GCash'] as $v=>$label)<option value="{{ $v }}" @selected(request('method')===$v)>{{ $label }}</option>@endforeach</select></label>
            @unless($isSeller)<label>Company name<input name="company" maxlength="190" value="{{ request('company') }}" placeholder="All companies"></label>@endunless
            <label>Date basis<select name="date_basis"><option value="created" @selected(request('date_basis')!=='earned')>Transaction creation</option><option value="earned" @selected(request('date_basis')==='earned')>Commission earning</option></select></label>
            <label>From<input name="from" type="date" value="{{ request('from') }}"></label><label>To<input name="to" type="date" value="{{ request('to') }}"></label>
            <label>Balance<select name="balance"><option value="">All balances</option><option value="due" @selected(request('balance')==='due')>Outstanding</option><option value="overdue" @selected(request('balance')==='overdue')>Overdue</option></select></label>
            <div class="commission-filter-actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline" href="{{ route('commissions.index') }}">Reset</a></div>
        </form></div>
    </section>
    <section class="card commission-panel">
        <div class="card-head"><h2>Commission obligations</h2><span class="commission-badge">{{ $obligations->total() }} records</span></div>
        <div class="card-pad"><p>COD withholding is owed by Logistics, not billed to the Seller again. Pending proof does not count as collected.</p>
            <div class="commission-scroll" role="region" tabindex="0" aria-label="Commission obligations, scroll horizontally for more columns"><table>
                <thead><tr><th>Order / Seller</th><th>Product basis</th><th>Commission</th><th>Settlement</th><th>Outstanding</th><th>Details</th></tr></thead><tbody>
                @forelse($obligations as $o)
                    <tr><td><strong>{{ $o->order_id ?? 'Reserved purchase' }}</strong><br>Seller #{{ $o->seller_id }} &middot; {{ strtoupper($o->method) }}<br>{{ $o->company_name }}<br><small>Obligation {{ $o->id }}</small></td>
                        <td>&#8369;{{ $money($o->basis_cents) }}<br><small>10% rate</small></td>
                        <td><strong>&#8369;{{ $money($o->commission_cents) }}</strong><br><small>Reversed &#8369;{{ $money($o->reversed_cents) }}</small></td>
                        <td>@if($o->platform_received)<span class="commission-badge">Platform-received GCash</span><br><small>{{ $o->earned_at ? 'Commission included in platform-held funds.' : 'Awaiting payment verification or order completion.' }} No separate seller payment.</small>@else Withheld &#8369;{{ $money($o->withheld_cents) }}<br>Collected &#8369;{{ $money($o->collected_cents) }}@endif</td>
                        <td><strong>&#8369;{{ $money($o->due_cents) }}</strong><br><small>{{ $o->platform_received ? 'No separate payment' : ($o->due_at ?? ($o->cancelled_at ? 'Cancelled' : 'Provisional')) }}</small><br><small>Pending &#8369;{{ $money($o->reserved_cents) }}</small>@if($o->held)<br><span class="commission-badge status-review">On review hold</span>@endif</td>
                        <td><details><summary>Calculation and activity</summary>
                            @foreach($lines[$o->id] ?? [] as $line)<p><strong>{{ $line->label }} &times; {{ $line->quantity }}</strong><br>Selling &#8369;{{ $money($line->selling_cents) }}<br>Seller discount &#8369;{{ $money($line->seller_discount_cents) }}<br>Commission &#8369;{{ $money($line->commission_cents) }}</p>@endforeach
                            @foreach($events[$o->id] ?? [] as $event)@php($eventDetails=json_decode($event->details,true))<p><strong>{{ ucfirst(str_replace('_',' ',$event->kind)) }}</strong><br>{{ $event->created_at }} &middot; &#8369;{{ $money($event->amount_cents) }}<br>Actor #{{ $event->actor_id ?? 'system' }} @if(!empty($eventDetails['reason']))<br>{{ $eventDetails['reason'] }}@endif @if(!empty($eventDetails['reference']))<br>Reference: {{ $eventDetails['reference'] }}@endif</p>@endforeach
                        </details></td>
                    </tr>
                @empty<tr><td colspan="6"><div class="commission-empty"><x-admin-icon name="file" /><strong>No commission obligations match these filters.</strong><p>Try another date range or reset your filters.</p></div></td></tr>@endforelse
                </tbody></table></div>{{ $obligations->links() }}
        </div>
    </section>

    <section class="card commission-panel" id="finance-payment">
        <div class="card-head"><h2>{{ $actor->is_admin ? 'Platform receiving instructions' : ($actor->account_type==='seller' ? 'Submit commission payment' : 'Remit withheld COD commissions') }}</h2></div>
        <div class="card-pad">
        @if($isSeller && $recoveries!==null)<p>Already sent money, or your reference was claimed by another account? <a href="#commission-payment-recovery">Submit payment proof for ownership review</a>, even with no balance due.</p>@endif
        @if($actor->is_admin)
            @include('commissions.account-settings')
        @elseif($isSeller && $obligations->every(fn($o)=>$o->method!=='gcash' || $o->due_cents<=$o->reserved_cents))
            <div class="commission-empty"><x-admin-icon name="peso" /><strong>No separate commission payment due on this page</strong><p>Platform-received GCash requires no additional payment. Check other statement pages or filters for older seller-received balances.</p></div>
        @elseif($activeAccount)
            @php($payableOnPage=$obligations->filter(fn($o)=>$o->method==='gcash' && $o->due_cents>$o->reserved_cents)->isNotEmpty())
            @if($isSeller && !$payableOnPage)
                <div class="commission-empty"><x-admin-icon name="peso" /><strong>No separate commission payment due on this page</strong><p>Platform-received GCash is already held by the platform. Only older seller-received GCash balances need a separate payment. COD commission is withheld and remitted by Logistics.</p></div>
            @else
            <p>@if($actor->account_type==='seller')Send your commission through GCash, then submit proof. You may pay part of the available commission balance. Excess verified funds become your credit.@elseReserve a remittance before sending money. Select obligations from one treasury account. You may remit part of the available balance; the remainder stays outstanding. Sent funds stay in transit until Platform Admin verifies actual receipt.@endif</p>
            <form method="POST" action="{{ route($actor->account_type==='seller' ? 'commissions.submit' : 'commissions.prepare-remittance') }}" enctype="multipart/form-data" data-confirm="Confirm this commission instruction?">@csrf<input type="hidden" name="submission_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
                <label>Active Platform GCash receiving account<input readonly value="{{ $activeAccount->account_name }} / {{ $activeAccount->account_number }}"></label><input type="hidden" name="receiving_account_id" value="{{ $activeAccount->id }}">
                @foreach($obligations as $o)@if($o->due_cents>$o->reserved_cents && ($actor->account_type==='seller' ? $o->method==='gcash' : $o->method==='cod'))<label>Allocate to {{ $o->order_id }} (available &#8369;{{ $money($o->due_cents-$o->reserved_cents) }})<input name="allocations[{{ $o->id }}]" type="number" min="0" max="{{ ($o->due_cents-$o->reserved_cents)/100 }}" step="0.01" value="0"></label>@endif @endforeach
                @if($actor->account_type==='seller')@include('commissions.receipt-fields')@endif
                @if($actor->account_type==='seller')@include('commissions.optional-message')@endif
                <button class="btn" type="submit">{{ $actor->account_type==='seller' ? 'Submit actual payment for verification' : 'Prepare and reserve remittance' }}</button>
            </form>
            @endif
        @else<div class="commission-empty"><x-admin-icon name="peso" /><strong>No active GCash receiving account</strong><p>New commission payments are unavailable. Do not send money until Platform Admin activates the official GCash account. If money was already sent, use payment recovery below.</p></div>@endif
        </div>
    </section>
    <section class="card commission-panel" id="finance-history"><div class="card-head"><h2>Payment and remittance history</h2><a class="btn btn-outline" href="{{ route('commissions.money-export','payments') }}">Export payments CSV</a></div><div class="card-pad">
        @include('commissions.history-filter',['history'=>'payment','kind'=>'payments'])
        @forelse($payments as $p)@include('commissions.payment-record')@empty<div class="commission-empty"><x-admin-icon name="peso" /><strong>No commission payments submitted.</strong><p>Payment receipts and remittance batches will appear here for review.</p></div>@endforelse{{ $payments->links() }}
    </div></section>
    <section class="card commission-panel" id="finance-credits"><div class="card-head"><h2>Commission refund credits</h2><a class="btn btn-outline" href="{{ route('commissions.money-export','credits') }}">Export credits CSV</a></div><div class="card-pad"><p>Commission repayments are separate from buyer product refunds.</p>
        @include('commissions.history-filter',['history'=>'credit','kind'=>'credits'])
        @forelse($credits as $credit)@include('commissions.credit-record')@empty<div class="commission-empty"><x-admin-icon name="file" /><strong>No commission refund credits.</strong><p>Reversal credits will appear here with their repayment status.</p></div>@endforelse{{ $credits->links() }}
    </div></section>
    @include('commissions.recovery')
    @include('commissions.cod-refunds')
</div>
@endif
@endsection
@push('scripts')<script src="{{ asset('js/commissions.js') }}?v={{ filemtime(public_path('js/commissions.js')) }}" defer></script>@endpush
@if(!$actor->is_admin && $actor->account_type==='seller')
@push('scripts')<script src="{{ asset('js/seller-finance.js') }}?v={{ filemtime(public_path('js/seller-finance.js')) }}" defer></script>@endpush
@endif
