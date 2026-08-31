<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Waybill — {{ $order->order_number }}</title>
<style>
  @page { size: A5; margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1b1620; margin: 0; padding: 24px; background: #f4f1f2; }
  .label { max-width: 480px; margin: 0 auto; background: #fff; border: 2px solid #1b1620; border-radius: 10px; overflow: hidden; }
  .label-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 2px dashed #1b1620; }
  .label-head h1 { font-size: 18px; margin: 0; }
  .label-head span { font-size: 11px; color: #79737f; }
  .tracking { padding: 16px 20px; text-align: center; border-bottom: 1px solid #e9e4ea; }
  .tracking .code { font-family: 'Courier New', monospace; font-size: 22px; font-weight: 700; letter-spacing: 2px; }
  .tracking .barcode { margin-top: 8px; height: 40px; background: repeating-linear-gradient(90deg, #1b1620 0 2px, transparent 2px 5px); }
  .section { padding: 14px 20px; border-bottom: 1px solid #e9e4ea; }
  .section:last-child { border-bottom: 0; }
  .section .label-title { font-size: 10px; letter-spacing: .08em; text-transform: uppercase; color: #79737f; margin-bottom: 6px; }
  .section .value { font-size: 14px; font-weight: 600; }
  .section .sub { font-size: 12.5px; color: #444; margin-top: 2px; }
  .items { font-size: 12.5px; }
  .items div { padding: 2px 0; }
  .print-bar { max-width: 480px; margin: 16px auto 0; text-align: right; }
  .print-bar button { background: #d9468f; color: #fff; border: 0; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
  @media print { body { background: #fff; padding: 0; } .print-bar { display: none; } .label { border-color: #000; box-shadow: none; } }
</style>
</head>
<body>
  <div class="label">
    <div class="label-head">
      <h1>PocketFinds</h1>
      <span>Shipping Label</span>
    </div>
    <div class="tracking">
      <div class="label-title" style="color:#79737f;font-size:10px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px">Tracking Number</div>
      <div class="code">{{ $order->shipment->tracking_number }}</div>
      <div class="barcode"></div>
    </div>
    <div class="section">
      <div class="label-title">From</div>
      <div class="value">{{ $seller->business_name ?: ($seller->given_names . ' ' . $seller->last_name) }}</div>
      <div class="sub">{{ trim(($seller->house_no ? $seller->house_no . ', ' : '') . ($seller->street ? $seller->street . ', ' : '') . $seller->barangay . ', ' . $seller->municipality . ', ' . $seller->province) }}</div>
      <div class="sub">{{ $seller->contact_no }}</div>
    </div>
    <div class="section">
      <div class="label-title">To</div>
      <div class="value">{{ $order->buyer?->given_names }} {{ $order->buyer?->last_name }}</div>
      @php($addr = $order->shipping_address ?? [])
      <div class="sub">{{ trim((($addr['house_no'] ?? null) ? $addr['house_no'] . ', ' : '') . (($addr['street'] ?? null) ? $addr['street'] . ', ' : '') . ($addr['barangay'] ?? '') . ', ' . ($addr['municipality'] ?? '') . ', ' . ($addr['province'] ?? '')) }}</div>
      <div class="sub">{{ $order->buyer?->contact_no }}</div>
    </div>
    <div class="section">
      <div class="label-title">Order {{ $order->order_number }}</div>
      <div class="items">
        @forelse($order->items ?? [] as $item)
          <div>{{ $item['name'] ?? 'Item' }} × {{ $item['qty'] ?? 1 }}</div>
        @empty
          <div>Items not provided</div>
        @endforelse
      </div>
      <div class="sub" style="margin-top:8px">Total: ₱{{ number_format($order->total, 2) }} · {{ $order->payment_method ?: 'Payment not provided' }}</div>
      @if($order->buyer_note)
        <div class="sub" style="margin-top:4px"><strong>Note:</strong> {{ $order->buyer_note }}</div>
      @endif
    </div>
  </div>
  @unless(request()->boolean('embedded'))
    <div class="print-bar">
      <button onclick="window.print()">Print Waybill</button>
    </div>
  @endunless
</body>
</html>
