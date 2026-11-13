<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $detail['title'] }} — {{ $order->order_number }} — PocketFinds</title>
  <link rel="stylesheet" href="{{ asset('css/order-receipt.css') }}">
</head>
<body>
<main class="receipt">
  <header><div><p class="brand">PocketFinds</p><h1>{{ $detail['title'] }}</h1><p>Order {{ $order->order_number }}<br>Placed {{ $order->created_at?->format('M j, Y g:i A') }}</p></div><button type="button" class="print-button" id="print-receipt">Print / save PDF</button></header>
  <section class="order-detail-info-grid" aria-label="Order information">
    <div><span>Shop</span><strong>{{ $order->seller?->business_name ?: $order->seller?->username ?: 'Shop not available' }}</strong></div>
    <div><span>Payment method</span><strong>{{ $order->paymentMethod?->name ?: $order->payment_method ?: 'Not recorded' }}</strong></div>
    <div><span>Recipient</span><strong>{{ $order->shipping_address['recipient_name'] ?? trim(($order->buyer?->given_names ?? '').' '.($order->buyer?->last_name ?? '')) }}</strong></div>
    <div><span>Contact number</span><strong>{{ $order->shipping_address['contact_no'] ?? $order->buyer?->contact_no ?? 'Not recorded' }}</strong></div>
    <div class="full"><span>Delivery address</span><address>{{ collect($order->shipping_address ?? [])->only(['house_no','street','barangay','municipality','province','region','postal_code'])->filter(fn($part) => filled($part) && $part !== 'Not provided')->join(', ') ?: 'Not recorded' }}</address></div>
    @if($order->buyer_note)<div class="full"><span>Delivery / seller instructions</span><strong>{{ $order->buyer_note }}</strong></div>@endif
  </section>
  <div class="table-wrap"><table><thead><tr><th>Product / variation</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead><tbody>
    @foreach($order->items ?? [] as $item)
    <tr><td>{{ $item['name'] ?? 'Product' }}<small>{{ collect([$item['variation_group'] ?? null, $item['variation_value'] ?? $item['color'] ?? null, $item['size'] ?? null])->filter()->unique()->join(' / ') }}</small></td><td>{{ $item['qty'] }}</td><td>PHP {{ number_format($item['price'], 2) }}</td><td>PHP {{ number_format($item['price'] * $item['qty'], 2) }}</td></tr>
    @endforeach
  </tbody></table></div>
  <section aria-label="Order totals">@include('orders.partials.costs')</section>
  @include('orders.partials.payment', ['printView' => true])
  @if(in_array($role, ['seller', 'admin', 'logistics']))@include('orders.partials.settlement')@endif
  @if(in_array($role, ['admin', 'logistics']))@include('orders.partials.logistics')@endif
  <section><h2>Delivery progress</h2>
    @if($shipment = $order->shipment)
    <div class="order-detail-info-grid">
      <div><span>Tracking number</span><strong>{{ $shipment->tracking_number ?: 'Not assigned' }}</strong></div>
      <div><span>Logistics company</span><strong>{{ $shipment->logistics_company ?: 'Not assigned' }}</strong></div>
      <div><span>Shipment status</span><strong>{{ str($shipment->shipping_status)->replace('_', ' ')->title() }}</strong></div>
      @if($shipment->courier)<div><span>Delivery rider</span><strong>{{ trim($shipment->courier->given_names.' '.$shipment->courier->last_name) }}</strong></div>@endif
    </div>
    @else<p>Shipment has not been assigned.</p>@endif
    @if($order->delivery_guaranteed_by)<p>Delivery window: {{ \App\Services\DeliveryGuarantee::label($order->delivery_guaranteed_from?->toDateTimeString(), $order->delivery_guaranteed_by->toDateTimeString()) }}</p>@endif
    <ol class="events">@foreach($timeline['rows'] as $step)@if($step['timestamp'])<li><strong>{{ $step['title'] }}</strong> — {{ $step['timestamp']->format('M j, Y g:i A') }}</li>@endif @endforeach</ol>
    @if($role === 'buyer' && $order->shipment?->delivery_proof_file)<p><a href="{{ route('buyer.orders.delivery-proof', $order) }}" target="_blank" rel="noopener">View delivery photo</a></p>@endif
  </section>
  @if($order->status === 'cancelled')<section><h2>Cancellation</h2><p>{{ $order->cancellation_reason ?: 'Reason not recorded' }}</p>@if($order->cancellation_note)<p>{{ $order->cancellation_note }}</p>@endif</section>@endif
  @include('orders.partials.refund')
  <footer>Generated {{ now()->format('M j, Y g:i A') }} ({{ config('app.timezone') }}). This record reflects the payment and delivery evidence currently recorded for this order.</footer>
</main>
<script src="{{ asset('js/order-receipt.js') }}" defer></script>
</body>
</html>
