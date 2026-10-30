@extends('rider.layout')
@section('title', 'Delivery Detail')
@section('page-title', 'Delivery Detail')
@section('page-sub', $shipment->tracking_number ?? substr($shipment->id, 0, 8))
@push('head')
<style>
.cod-collection{min-width:0;border:1px solid var(--border);border-radius:16px;padding:20px;margin:0 0 20px;background:var(--surface);font-size:14px;line-height:1.6}.cod-collection legend{font-size:15px;font-weight:600;padding:0 6px}.cod-collection input[type=number]{min-height:44px;width:100%;font-size:14px;border-radius:10px}.cod-collection input:focus-visible{outline:2px solid var(--pink);outline-offset:3px}.cod-collection .form-row{margin-bottom:16px}.cod-collection .form-row label{font-size:12px}.cod-collection p{overflow-wrap:anywhere}
.rider-delivery-detail{min-width:0;grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.rider-delivery-detail>.stack{min-width:0}.rider-delivery-detail .card{min-width:0}.rider-delivery-detail .table-wrap{max-width:100%;overflow-x:auto}@media(max-width:900px){.rider-delivery-detail{grid-template-columns:minmax(0,1fr)}}
</style>
@endpush

@php
  $order  = $shipment->order;
  $seller = $order?->seller;
  $buyer  = $order?->buyer;
  $addr   = $order?->shipping_address ?? [];

  $stageInfo = [
    'assigned_to_rider' => ['title' => 'Pick Up From Sorting Center', 'hint' => 'Collect this parcel from the sorting center, then mark it out for delivery.', 'button' => 'Mark Out for Delivery'],
    'out_for_delivery'  => ['title' => 'On the Way', 'hint' => 'Deliver to the buyer\'s address below, then complete the delivery.', 'button' => 'Complete Delivery'],
    'delivered'         => ['title' => 'Delivered', 'hint' => 'This delivery is complete — awaiting the buyer\'s confirmation.', 'button' => null],
  ];
  $info = $stageInfo[$shipment->shipping_status] ?? ['title' => ucfirst(str_replace('_',' ',$shipment->shipping_status)), 'hint' => '', 'button' => null];
@endphp

@section('content')
@include('logistics.partials.rts-custody')
<div class="card">
  <div class="card-head">
    <div><h2>{{ $info['title'] }}</h2><p>{{ $info['hint'] }}</p></div>
    <span class="stamp stamp-{{ $shipment->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $shipment->shipping_status)) }}</span>
  </div>
</div>

<div class="dash-grid rider-delivery-detail">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Seller — Origin</h2></div>
      <div class="card-pad">
        <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
          <div><strong>{{ $seller?->business_name ?? trim(($seller?->given_names ?? '') . ' ' . ($seller?->last_name ?? '')) ?: 'Unknown seller' }}</strong></div>
          <div style="color:var(--muted)">{{ collect([$seller?->house_no, $seller?->street, $seller?->barangay, $seller?->municipality, $seller?->province, $seller?->region, $seller?->postal_code])->filter()->implode(', ') ?: 'No address on file' }}</div>
          <div style="color:var(--muted)">{{ $seller?->contact_no ?: 'No contact number on file' }}</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Order Contents</h2></div>
      <div class="table-wrap">
        <table class="dtable">
          <thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead>
          <tbody>
            @forelse(($order->items ?? []) as $item)
            <tr>
              <td>{{ $item['name'] ?? '—' }}</td>
              <td class="mono">{{ $item['qty'] ?? 1 }}</td>
              <td class="mono">₱{{ number_format($item['price'] ?? 0, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="3"><div class="empty"><h3>No items on file</h3></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-pad" style="padding-top:0">
        <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:700;padding-top:10px;border-top:1px solid var(--border)">
          <span>Order Total</span><span>₱{{ number_format($order->total ?? 0, 2) }}</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Buyer — Delivery Address</h2></div>
      <div class="card-pad">
        <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
          <div><strong>{{ trim(($buyer?->given_names ?? '') . ' ' . ($buyer?->last_name ?? '')) ?: 'Unknown buyer' }}</strong></div>
          <div style="color:var(--muted)">{{ implode(', ', array_filter([$addr['house_no'] ?? null, $addr['street'] ?? null, $addr['barangay'] ?? null, $addr['municipality'] ?? null, $addr['province'] ?? null, $addr['region'] ?? null, $addr['postal_code'] ?? null])) ?: 'No address on file' }}</div>
          <div style="color:var(--muted)">{{ $buyer?->contact_no ?: 'No contact number on file' }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Actions</h2></div>
      <div class="card-pad">
        @if($codCollection ?? null)
        <div role="status" style="padding:14px;border:1px solid var(--border);border-radius:12px;margin-bottom:16px;font-size:13px">COD collection recorded: <strong>?{{ number_format($codCollection->amount_cents / 100, 2) }}</strong><br>Hub cash verification: {{ ucfirst($codCollection->verification_state) }}.</div>
        @elseif(($codRequired ?? false) && in_array($shipment->shipping_status, ['delivered','completed']))
        <p class="hint">Legacy delivery: no cash collection record is available. Payment or remittance has not been inferred.</p>
        @endif
        @if($info['button'])
        <form method="POST" action="{{ route('rider.deliveries.advance', $shipment->id) }}">
          @csrf @method('PATCH')
          <input type="hidden" name="from" value="{{ $shipment->shipping_status }}">
          @if(($codRequired ?? false) && $shipment->shipping_status === 'out_for_delivery')
          @if($errors->any())<div role="alert" style="padding:12px;border:1px solid var(--danger-line);border-radius:10px;color:var(--danger);margin-bottom:16px;font-size:13px"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
          <fieldset class="cod-collection">
            <legend>Cash on delivery</legend>
            <p style="font-size:14px">Collect <strong>?{{ number_format($order->total, 2) }}</strong> from the buyer.</p>
            <input type="hidden" name="collection_key" value="{{ old('collection_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="form-row"><label for="cod-amount">Amount collected (PHP)</label><input id="cod-amount" name="cod_amount" type="number" step="0.01" min="0" value="{{ old('cod_amount') }}" required inputmode="decimal" aria-describedby="cod-help"></div>
            <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px"><input type="checkbox" name="cod_confirmed" value="1" required @checked(old('cod_confirmed')) style="width:18px;height:18px;flex:none"> I received the full cash amount and handed the parcel to the buyer.</label>
            <p id="cod-help" style="font-size:12px;color:var(--muted);margin-bottom:0">Collection is recorded with delivery. Hub Staff must separately verify your cash remittance. Do not deduct rider earnings.</p>
          </fieldset>
          @endif
          <button class="btn btn-primary" style="width:100%">{{ $info['button'] }}</button>
        </form>
        @else
        <p style="font-size:13px;color:var(--muted)">No further action needed for this delivery.</p>
        @endif
        @if($shipment->shipping_status === 'out_for_delivery')
        <button type="button" class="btn btn-outline" style="width:100%;margin-top:10px;color:var(--danger)" onclick="document.getElementById('failedForm').hidden = !document.getElementById('failedForm').hidden">Delivery Failed?</button>
        <form id="failedForm" method="POST" action="{{ route('rider.deliveries.failed', $shipment->id) }}" hidden style="margin-top:10px">
          @csrf @method('PATCH')
          <textarea name="reason" rows="2" maxlength="500" placeholder="What went wrong? (e.g. buyer unreachable, wrong address)" required style="width:100%;border:1px solid var(--border);border-radius:9px;padding:8px 10px;font-size:12.5px;font-family:var(--font-body)"></textarea>
          <button type="submit" class="btn btn-danger" style="width:100%;margin-top:8px">Report Failed Delivery</button>
        </form>
        @endif
        <a href="{{ route('rider.messages.thread', $seller?->id) }}" class="btn btn-outline" style="width:100%;margin-top:10px;{{ $seller ? '' : 'pointer-events:none;opacity:.5' }}">Message Seller</a>
        <a href="{{ route('rider.messages.thread', $buyer?->id) }}" class="btn btn-outline" style="width:100%;margin-top:10px;{{ $buyer ? '' : 'pointer-events:none;opacity:.5' }}">Message Buyer</a>
        <a href="{{ route('rider.deliveries') }}" class="btn btn-outline" style="width:100%;margin-top:10px">← Back to My Deliveries</a>
      </div>
    </div>
  </div>
</div>
@endsection
