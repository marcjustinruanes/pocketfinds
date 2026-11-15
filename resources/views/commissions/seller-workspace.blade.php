@php
 $payable=$obligations->filter(fn($o)=>$o->method==='gcash' && $o->due_cents>$o->reserved_cents);
 $orderLabels=\Illuminate\Support\Facades\DB::table('orders')->whereIn('id',$obligations->pluck('order_id')->filter())->pluck('order_number','id');
 $filterCount=count(array_filter(request()->only('method','date_basis','from','to','balance')));
@endphp
<div class="commission-workspace seller-finance-workspace" data-seller-finance data-error-modal="{{ $errors->any() ? old('_finance_modal','finance-pay') : '' }}">
 <div class="finance-heading"><div class="finance-tools">
  @if(\App\Services\BuyerCommerce::installed())<button type="button" class="btn btn-outline" data-finance-open="finance-shipping-rates">Shipping regions & rates</button>@endif
  <button type="button" class="btn btn-outline" data-finance-open="finance-history-modal">Payment history</button>
  <button type="button" class="btn btn-outline" data-finance-open="finance-credit-modal">Refund credits</button>
  @if($payable->isNotEmpty())<button type="button" class="btn btn-primary" data-finance-open="finance-pay">Record payment</button>@endif
 </div></div>
 @if(session('success'))<p class="commission-feedback is-success" role="status">{{ session('success') }}</p>@endif
 @if($errors->any())<div class="commission-feedback is-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
 <div class="finance-balances">
  <article><span>Separate commission due</span><strong>&#8369;{{ $money($totals['seller_due']) }}</strong><small>Filtered statements @if($totals['overdue']>0)&middot; &#8369;{{ $money($totals['overdue']) }} overdue @endif</small></article>
  <article><span>Available receipt credit</span><strong>&#8369;{{ $money($unapplied) }}</strong><small>All verified payment history</small></article>
 </div>
 <div class="finance-context"><span>10% product commission &middot; Shipping excluded</span><button type="button" data-finance-open="finance-policy">How it works</button></div>
 <section class="finance-statements" aria-labelledby="finance-statement-title">
  <div class="finance-statement-toolbar"><h2 id="finance-statement-title">Commission obligations <span>{{ $obligations->total() }}</span></h2><div class="finance-tools"><button type="button" class="btn btn-outline" data-finance-open="finance-filters">Filters @if($filterCount)({{ $filterCount }})@endif</button><a class="btn btn-outline" href="{{ route('commissions.export',request()->only('method','date_basis','from','to','balance')) }}">Export CSV</a></div></div>
  <div class="finance-table-scroll"><table class="finance-statement-table"><thead><tr><th>Order</th><th>Net commission</th><th>Separate payment due</th><th><span class="finance-visually-hidden">Statement details</span></th></tr></thead><tbody>
   @forelse($obligations as $o)
   <tr><td><strong>{{ $orderLabels[$o->order_id] ?? 'Reserved purchase' }}</strong><small>{{ $o->platform_received ? 'Platform-received GCash' : ($o->method==='cod' ? 'COD withholding' : 'Seller-received GCash') }}</small></td><td>&#8369;{{ $money(max(0,$o->commission_cents-$o->reversed_cents)) }}</td><td><strong>&#8369;{{ $money($o->due_cents) }}</strong><small>{{ $o->cancelled_at ? 'Cancelled' : ($o->held ? 'On review hold' : (!$o->earned_at ? 'Awaiting completion' : ($o->platform_received ? 'No separate payment' : ($o->due_cents>0 ? 'Payment due' : ($o->method==='cod' && $o->withheld_cents<$o->commission_cents-$o->reversed_cents ? 'Awaiting withholding' : 'Settled'))))) }}</small></td><td><button type="button" class="finance-detail-button" data-finance-open="finance-statement-{{ $o->id }}" aria-label="View statement for {{ $orderLabels[$o->order_id] ?? 'reserved purchase' }}">View</button></td></tr>
   @empty<tr><td colspan="4"><div class="commission-empty"><strong>No commission obligations match these filters.</strong><p>Try another date range or reset your filters.</p></div></td></tr>@endforelse
  </tbody></table></div>
  {{ $obligations->links() }}
 </section>
 @if($totals['seller_due']===0)<p class="finance-footer-note">No separate commission payment due on this page. Platform-received GCash requires no additional seller payment.</p>@endif
 @if($recoveries!==null)<button type="button" class="finance-recovery-link" data-finance-open="finance-recovery">Already sent money? Track or recover a payment</button>@endif

 @if(\App\Services\BuyerCommerce::installed())
 <x-seller-finance-modal id="finance-shipping-rates" title="Shipping regions & rates">
  @include('account.partials.commerce-shipping',['financeModal'=>'finance-shipping-rates'])
 </x-seller-finance-modal>
 @once @push('scripts')<script src="{{ asset('js/commerce.js') }}?v={{ filemtime(public_path('js/commerce.js')) }}" defer></script>@endpush @endonce
 @endif
 <x-seller-finance-modal id="finance-filters" title="Filter statements">
  <form method="GET" class="finance-filter-form">
   <label>Payment method<select name="method"><option value="">All methods</option><option value="gcash" @selected(request('method')==='gcash')>GCash</option><option value="cod" @selected(request('method')==='cod')>COD withholding</option></select></label>
   <label>Date basis<select name="date_basis"><option value="created" @selected(request('date_basis')!=='earned')>Transaction creation</option><option value="earned" @selected(request('date_basis')==='earned')>Commission earning</option></select></label>
   <label>From<input name="from" type="date" value="{{ request('from') }}"></label><label>To<input name="to" type="date" value="{{ request('to') }}"></label>
   <label>Balance<select name="balance"><option value="">All balances</option><option value="due" @selected(request('balance')==='due')>Outstanding</option><option value="overdue" @selected(request('balance')==='overdue')>Overdue</option></select></label>
   <div class="finance-form-actions"><a class="btn btn-outline" href="{{ route('commissions.index') }}">Reset</a><button class="btn btn-primary" type="submit">Apply filters</button></div>
  </form>
 </x-seller-finance-modal>
 <x-seller-finance-modal id="finance-pay" title="Record commission payment">
  @if($payable->isEmpty())<div class="commission-empty"><strong>{{ $totals['seller_due']>0 ? 'No unreserved balance on this page' : 'No separate commission payment due on this page' }}</strong><p>Only older seller-received GCash balances need a separate payment. Submitted proofs await verification; change statement filters or pages to find other balances.</p></div>
  @elseif(!$activeAccount)<p>No active GCash receiving account. New payments are unavailable until the platform activates its official account.</p>
  @else
  <p>Use the official receiving account below. Submit proof after sending your payment.</p>
  <form method="POST" action="{{ route('commissions.submit') }}" enctype="multipart/form-data" data-confirm="Submit this actual commission payment for verification?">@csrf
   <input type="hidden" name="_finance_modal" value="finance-pay"><input type="hidden" name="submission_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="receiving_account_id" value="{{ $activeAccount->id }}">
   <label class="finance-wide">Platform GCash receiving account<input readonly value="{{ $activeAccount->account_name }} / {{ $activeAccount->account_number }}"></label>
   @foreach($payable as $o)<label>Allocate to {{ $orderLabels[$o->order_id] ?? 'purchase' }}<small>Available &#8369;{{ $money($o->due_cents-$o->reserved_cents) }}</small><input name="allocations[{{ $o->id }}]" type="number" min="0" max="{{ ($o->due_cents-$o->reserved_cents)/100 }}" step="0.01" value="{{ old('allocations.'.$o->id,0) }}"></label>@endforeach
   @include('commissions.receipt-fields')
   @include('commissions.optional-message')
   <button class="btn btn-primary" type="submit">Submit actual payment for verification</button>
  </form>
  @endif
 </x-seller-finance-modal>
 <x-seller-finance-modal id="finance-history-modal" title="Payment history">
  <div class="finance-history-totals"><span>Verified payments <strong>&#8369;{{ $money($verifiedReceipts) }}</strong></span><span>Repaid <strong>&#8369;{{ $money($repayments) }}</strong></span></div>
  @include('commissions.history-filter',['history'=>'payment','kind'=>'payments'])
  @forelse($payments as $p)@include('commissions.payment-record')@empty<div class="commission-empty"><strong>No commission payments submitted.</strong><p>Your receipts and verification updates will appear here.</p></div>@endforelse
  {{ $payments->links() }}
 </x-seller-finance-modal>
 <x-seller-finance-modal id="finance-credit-modal" title="Commission refund credits">
  <p>Repayments for reversed commissions. Buyer product refunds are managed in Orders.</p>
  @include('commissions.history-filter',['history'=>'credit','kind'=>'credits'])
  @forelse($credits as $credit)@include('commissions.credit-record')@empty<div class="commission-empty"><strong>No commission refund credits.</strong></div>@endforelse
  {{ $credits->links() }}
 </x-seller-finance-modal>
 @if($recoveries!==null)<x-seller-finance-modal id="finance-recovery" title="Track an already sent payment">@include('commissions.recovery')</x-seller-finance-modal>@endif
 <x-seller-finance-modal id="finance-policy" title="How commissions work">
  <div class="finance-policy-copy"><h3>Platform-received GCash</h3><p>The platform holds these buyer payments. Their commission requires no second seller payment.</p><h3>Older seller-received GCash</h3><p>Commission becomes payable after the order is eligible. Submitted proof counts as payment only after admin verification.</p><h3>COD orders</h3><p>Logistics withholds and remits commission. You are not billed for it again.</p><h3>Calculation</h3><p>10% of the snapshotted product subtotal before voucher discounts. Shipping is excluded; cancellations and refunds can reverse the commission.</p></div>
 </x-seller-finance-modal>
 @foreach($obligations as $o)
 <x-seller-finance-modal :id="'finance-statement-'.$o->id" title="Statement details">
  <div class="finance-statement-summary"><strong>{{ $orderLabels[$o->order_id] ?? 'Reserved purchase' }}</strong><p>{{ $o->platform_received ? 'Platform-received GCash · No separate seller payment' : strtoupper($o->method) }}</p></div>
  <dl class="finance-calculation"><div><dt>Product basis</dt><dd>&#8369;{{ $money($o->basis_cents) }}</dd></div><div><dt>Original commission</dt><dd>&#8369;{{ $money($o->commission_cents) }}</dd></div><div><dt>Reversed</dt><dd>&#8369;{{ $money($o->reversed_cents) }}</dd></div><div><dt>Withheld</dt><dd>&#8369;{{ $money($o->withheld_cents) }}</dd></div><div><dt>Verified collection</dt><dd>&#8369;{{ $money($o->collected_cents) }}</dd></div><div><dt>Pending proof</dt><dd>&#8369;{{ $money($o->reserved_cents) }}</dd></div><div><dt>Separate payment due</dt><dd>&#8369;{{ $money($o->due_cents) }}</dd></div></dl>
  @if(!$o->platform_received && $o->due_at && $o->due_cents>0)<p>Due {{ $o->due_at }}</p>@endif
  <h3 class="finance-modal-subtitle">Product calculation</h3>
  @foreach($lines[$o->id] ?? [] as $line)<div class="finance-line-item"><strong>{{ $line->label }} &times; {{ $line->quantity }}</strong><p>Selling &#8369;{{ $money($line->selling_cents) }} &middot; Seller discount &#8369;{{ $money($line->seller_discount_cents) }} &middot; Commission &#8369;{{ $money($line->commission_cents) }}</p></div>@endforeach
  <details><summary>Activity history</summary>@foreach($events[$o->id] ?? [] as $event)<p><strong>{{ ucfirst(str_replace('_',' ',$event->kind)) }}</strong><br>{{ $event->created_at }} &middot; &#8369;{{ $money($event->amount_cents) }}<br>{{ json_decode($event->details,true)['reason'] ?? '' }}</p>@endforeach</details>
 </x-seller-finance-modal>
 @endforeach
</div>
