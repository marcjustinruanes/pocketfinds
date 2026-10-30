@extends($seller ? 'seller.layout' : 'logistics.layout')
@section('title','Shipping Payment')
@section('page-title','Shipping Payment')
@section('page-sub','Verified shipping fees, separate from buyer payment and parcel status')
@push('head')<link rel="stylesheet" href="{{ asset('css/logistics-cash-desk.css') }}?v={{ filemtime(public_path('css/logistics-cash-desk.css')) }}">@endpush
@section('content')
<div class="cash-desk">
  <nav aria-label="Payment navigation"><a class="btn" href="{{ $seller ? route('seller.shipping') : route('logistics.finance') }}">&larr; {{ $seller ? 'Shipping Payments' : 'Finance overview' }}</a></nav>
  @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
  @if($errors->any())<div class="cash-alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <section class="cash-card"><h2>{{ $ob->company_name }}</h2><p class="cash-reference">Order {{ $ob->order_id }} · {{ $shipment->tracking_number }}</p><div class="cash-stats"><div><span>Gross shipping obligation</span><strong>PHP {{ number_format($ob->amount_cents/100,2) }}</strong></div><div><span>Payment status</span><strong>{{ ucfirst(str_replace('_',' ',$ob->status)) }}</strong></div></div>
    <p>Approved rate: {{ $rate->region }} · Revision {{ $rate->revision }} · {{ $ob->chargeable_kg }} kg charged. First kg PHP {{ number_format($rate->first_kg_cents/100,2) }}, each additional kg PHP {{ number_format($rate->additional_kg_cents/100,2) }}.</p>
    @if($ob->collection_mode === 'cod')<p>COD shipping is tracked separately for reconciliation. Do not prepay this obligation through the GCash form.</p>@endif
    @foreach(json_decode($ob->voucher_funding,true) as $v)<p>Shipping voucher {{ $v['code'] }}: PHP {{ number_format($v['amount_cents']/100,2) }} funded by {{ $v['source'] }}.</p>@endforeach
    @if($ob->platform_claim_cents > 0)<p>Platform reimbursement claim: <strong>PHP {{ number_format($ob->platform_claim_cents/100,2) }}</strong> · {{ $ob->claim_status }}. This is not verified funds or an approved payout.</p>@endif
  </section>
  @if($seller && $ob->collection_mode === 'gcash' && in_array($ob->status,['pending','rejected']) && $shipment->shipping_status === 'ready_for_pickup')
  @php($instructions=json_decode($ob->instructions,true))
  <section class="cash-card"><h2>Pay the carrier shipping fee</h2><p>This payment is separate from the buyer’s GCash payment to your shop. Advance the full shipping fee, including any voucher-funded portion.</p>
    <p>GCash account: <strong>{{ $instructions['name'] }}</strong> · <strong>{{ $instructions['number'] }}</strong>. Pay exactly <strong>PHP {{ number_format($ob->amount_cents/100,2) }}</strong>.</p>
    <form method="POST" enctype="multipart/form-data" action="{{ route('seller.shipping.submit',$ob->id) }}">@csrf<input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="revision" value="{{ $ob->revision }}">
      <label for="shipping-paid">Amount paid (PHP)</label><input id="shipping-paid" name="amount" type="number" min="0.01" step="0.01" required>
      <label for="shipping-reference">GCash transaction reference</label><input id="shipping-reference" name="reference" minlength="6" maxlength="64" pattern="[A-Za-z0-9-]{6,64}" required>
      <label for="shipping-proof">Payment proof · JPG, PNG, WebP or PDF, up to 5 MB</label><input id="shipping-proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required><button class="btn btn-primary">Submit shipping payment</button>
    </form>
  </section>
  @endif
  <section class="cash-card"><h2>Payment history</h2><p>Uploading a receipt does not verify payment. The carrier’s Logistics Admin must confirm actual receipt.</p>
  @forelse($payments as $p)<article class="cash-handover"><h3>{{ ucfirst($p->status) }} · PHP {{ number_format($p->amount_cents/100,2) }}</h3><p>Reference: {{ $p->reference }}</p><a class="btn" href="{{ route('shipping.proof',$p->id) }}" target="_blank" rel="noopener">View private proof</a>@if($p->review_notes)<p>{{ $p->review_notes }}</p>@endif
    @if(!$seller && $p->status === 'submitted' && $ob->status === 'proof_submitted')<form method="POST" action="{{ route('logistics.finance.shipping.review',$ob->id) }}">@csrf<input type="hidden" name="payment_id" value="{{ $p->id }}"><input type="hidden" name="revision" value="{{ $ob->revision }}">
      <label for="review-amount">Actual amount received (PHP; required to verify)</label><input id="review-amount" name="amount" type="number" min="0" step="0.01">
      <label for="review-reference">Actual received transaction reference</label><input id="review-reference" name="reference" minlength="6" maxlength="64">
      <label for="review-notes">Verification evidence or rejection reason</label><textarea id="review-notes" name="notes" minlength="3" maxlength="2000" required></textarea>
      <button class="btn btn-primary" name="accept" value="1">Verify received payment</button><button class="btn" name="accept" value="0">Reject submission</button>
    </form>@endif
  </article>@empty<p>No shipping payments submitted.</p>@endforelse
  </section>
  <section class="cash-card"><h2>Audit history</h2>@foreach($history as $event)@php($details=json_decode($event->details,true))<p>{{ ucfirst(str_replace('_',' ',$event->event)) }} · {{ $event->created_at }} UTC @if(isset($details['notes']))<br>{{ $details['notes'] }}@endif</p>@endforeach</section>
</div>
@endsection
