@extends('rider.layout')
@section('title', 'Delivery Detail')
@section('page-title', 'Delivery Detail')
@section('page-sub', $shipment->tracking_number ?? substr($shipment->id, 0, 8))

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
<div class="card">
  <div class="card-head">
    <div><h2>{{ $info['title'] }}</h2><p>{{ $info['hint'] }}</p></div>
    <span class="stamp stamp-{{ $shipment->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $shipment->shipping_status)) }}</span>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Seller — Origin</h2></div>
      <div class="card-pad">
        <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
          <div><strong>{{ $seller?->business_name ?? trim(($seller?->given_names ?? '') . ' ' . ($seller?->last_name ?? '')) ?: 'Unknown seller' }}</strong></div>
          <div style="color:var(--muted)">{{ collect([$seller?->house_no, $seller?->street, $seller?->barangay, $seller?->municipality, $seller?->province])->filter()->implode(', ') ?: 'No address on file' }}</div>
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
          <div style="color:var(--muted)">{{ implode(', ', array_filter([$addr['house_no'] ?? null, $addr['street'] ?? null, $addr['barangay'] ?? null, $addr['municipality'] ?? null, $addr['province'] ?? null])) ?: 'No address on file' }}</div>
          <div style="color:var(--muted)">{{ $buyer?->contact_no ?: 'No contact number on file' }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Actions</h2></div>
      <div class="card-pad">
        @if($info['button'])
        <form method="POST" action="{{ route('rider.deliveries.advance', $shipment->id) }}">
          @csrf @method('PATCH')
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
