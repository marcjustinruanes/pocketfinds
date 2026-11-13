@extends('logistics.layout')
@section('title', 'Scan Parcel')
@section('page-title', 'Scan Parcel')
@section('page-sub', 'USB, workstation camera, or PocketFinds Scanner for Android')

@push('head')
<link rel="stylesheet" href="{{ asset('css/phone-scanner.css') }}?v={{ filemtime(public_path('css/phone-scanner.css')) }}">
<style>
  .scan-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}
  @media (max-width:900px){.scan-grid{grid-template-columns:1fr}}
  .reader{width:100%;min-height:220px;border:1px dashed var(--border);border-radius:9px;background:var(--paper);overflow:hidden;margin-bottom:14px}
  .reader video{transform:scaleX(-1)}
  .reader:empty:before{content:"Camera preview appears here";display:flex;height:220px;align-items:center;justify-content:center;color:var(--muted);font-size:12.5px}
  .scan-input-row{display:flex;gap:8px}
  .scan-input-row input{flex:1;height:38px;border:1px solid var(--border);border-radius:9px;padding:0 12px;font-size:13.5px;font-family:var(--font-mono)}
  .scan-input-row input:focus{border-color:var(--pink)}
  .scan-msg{padding:10px 14px;border-radius:9px;font-size:13px;margin-top:12px}
  .scan-msg.info{background:var(--info-soft);border:1px solid var(--info-line);color:var(--info)}
  .scan-msg.success{background:var(--success-soft);border:1px solid var(--success-line);color:var(--success)}
  .scan-msg.danger{background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger)}
  .scan-result-empty{padding:50px 20px;text-align:center;color:var(--muted);font-size:13px}
  .scan-actions{display:flex;gap:10px;margin-top:16px}
  .logistics-ui .scan-grid{grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);gap:12px;align-items:stretch}
  .logistics-ui .scan-grid>.card{min-width:0;overflow:hidden;border:1px solid var(--border);border-radius:13px;background:#fff;box-shadow:0 2px 8px rgba(35,24,33,.025)}
  .logistics-ui .scan-grid>.card>.card-head{min-height:44px;padding:11px 13px;border-bottom:1px solid var(--border)}
  .logistics-ui .scan-grid>.card>.card-head h2{font-size:12.5px;font-weight:750}
  .logistics-ui .scan-grid>.card>.card-pad{padding:13px}
  .logistics-ui .scan-grid h3{margin:0 0 5px;font-size:10.5px;font-weight:750}
  .logistics-ui .scan-grid p.hint{margin:4px 0 9px;font-size:9px;line-height:1.55}
  .logistics-ui .scan-grid .reader{min-height:180px;margin:8px 0 10px;border:1px dashed var(--border);border-radius:10px;background:#faf9fa}
  .logistics-ui .scan-grid .reader:empty:before{height:180px;font-size:9px}
  .logistics-ui .scan-grid .scan-actions{gap:6px;margin-top:10px}
  .logistics-ui .scan-grid .scan-actions .btn,
  .logistics-ui .scan-grid .btn{min-height:33px;padding:7px 10px;border-radius:8px;font-size:9px;font-weight:700}
  .logistics-ui .scan-grid .scan-input-row{gap:6px}
  .logistics-ui .scan-grid .scan-input-row input,
  .logistics-ui .scan-grid input:not([type=hidden]):not([type=file]){min-height:34px;padding:7px 9px;border:1px solid var(--border);border-radius:8px;background:#fff;font-size:10px}
  .logistics-ui .scan-grid .phone-connection{margin-top:14px;padding-top:12px}
  .logistics-ui .scan-grid .phone-connection p{font-size:9px;line-height:1.5}
  .logistics-ui .scan-grid .phone-connection #phonePairPanel{padding:10px;border:1px solid var(--border);border-radius:9px;background:#faf9fa}
  .logistics-ui .scan-grid .phone-connection #phonePairQr{max-width:100%;margin-bottom:8px;padding:8px;border:1px solid var(--border);border-radius:8px}
  .logistics-ui .scan-grid .scan-result-empty{padding:38px 14px;font-size:10px}
  .logistics-ui .scan-grid .detail-grid{gap:8px}
  .logistics-ui .scan-grid .detail-grid>div{min-width:0;padding:9px;border:1px solid var(--border);border-radius:9px;background:#fff}
  .logistics-ui .scan-grid .field-label{font-size:8px;font-weight:700}
  .logistics-ui .scan-grid .field-value{font-size:9.5px;overflow-wrap:anywhere}
  .logistics-ui .scan-grid #scanMessage:empty{display:none}
  @media(max-width:900px){.logistics-ui .scan-grid{grid-template-columns:minmax(0,1fr)}}
  @media(max-width:640px){.logistics-ui .scan-grid>.card>.card-pad{padding:11px}.logistics-ui .scan-grid .scan-actions{flex-wrap:wrap}.logistics-ui .scan-grid .scan-actions .btn{flex:1 1 130px}}
</style>
@endpush

@section('content')
<div class="scan-grid">
  @php($scannerPref = auth()->user()->preferred_scanner ?? 'both')
  <div class="card scan-workspace-card">
    <div class="card-head"><h2><span class="scan-card-icon"><x-admin-icon name="scan" /></span>Universal Scanner</h2></div>
    <div class="card-pad">
      <h3>Workstation scanner</h3>
      <p class="hint">Use a USB scanner, this computer's camera, or enter a parcel code. Phone scanning is available in the PocketFinds Scanner Android app.</p>
      <div id="reader" class="reader" @if($scannerPref === 'usb') hidden @endif></div>
      <div class="scan-actions" style="margin-top:0;margin-bottom:14px">
        <button type="button" class="btn btn-outline" id="startCamera">Use this device’s camera</button>
        <button type="button" class="btn btn-outline" id="stopCamera">Stop Camera</button>
      </div>

      <form id="trackingForm" class="form-row" style="margin-bottom:0">
        <label>Tracking Number / Order Number</label>
        <div class="scan-input-row">
          <input id="trackingInput" type="text" autocomplete="off" autofocus placeholder="e.g. PF-SHIP-260831-AB12CD">
          <button type="submit" class="btn btn-primary">Find</button>
        </div>
        <p class="hint" style="margin-top:6px">
          @if($scannerPref === 'usb')
            This workstation is set to USB scanner only — scan directly into this field.
          @else
            A USB barcode scanner types directly into this field and submits automatically.
          @endif
        </p>
      </form>

      <section id="phoneScannerConnection" class="phone-connection" data-login-url="{{ route('login') }}" data-create-url="{{ route('logistics.scan.phone-sessions') }}" data-lookup-url="{{ route('logistics.scan.lookup') }}" data-status-base="{{ url('/logistics/status') }}" data-receive-base="{{ url('/logistics/scan') }}" data-assign-base="{{ url('/logistics/assignments') }}">
        <h3>Pair with PocketFinds Scanner</h3>
        <p>In the Android app, sign into the same account and choose Pair with Computer to scan this connection QR.</p>
        <button type="button" class="btn btn-outline" id="connectPhoneScanner">Connect phone scanner</button>
        <div id="phonePairPanel" hidden>
          <div id="phonePairQr" aria-label="Phone connection QR code"></div>
          <label for="phonePairLink">Phone connection link</label>
          <input id="phonePairLink" type="text" readonly autocomplete="off" onclick="this.select()">
          <p id="phoneAddressNote"></p>
          <button type="button" id="phoneDisconnect" class="btn btn-outline" hidden>Disconnect phone</button>
          <button type="button" id="phoneNextScan" class="btn btn-primary" hidden>Next phone scan</button>
        </div>
        <p id="phonePairStatus" role="status" aria-live="polite"></p>
      </section>
      <div id="scanMessage" role="status" aria-live="polite"></div>
    </div>
  </div>

  <div class="card scan-shipment-card">
    <div class="card-head"><h2><span class="scan-card-icon"><x-admin-icon name="box" /></span>Shipment details</h2></div>
    <div class="card-pad">
      <div id="emptyResult" class="scan-result-empty" role="status" aria-live="polite">Scan or enter a tracking number to view shipment details.</div>

      <div id="shipmentResult" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h2 class="mono" id="rTracking" style="margin:0;font-size:18px"></h2>
          <span class="stamp" id="rStatus"></span><p id="rCustody" role="status" style="font-size:14px;line-height:1.6;overflow-wrap:anywhere"></p>
        </div>
        <div class="detail-grid">
          <div><div class="field-label">Order #</div><div class="field-value mono" id="rOrder"></div></div>
          <div><div class="field-label">Last Update</div><div class="field-value" id="rUpdated"></div></div>
          <div><div class="field-label">Buyer</div><div class="field-value" id="rBuyer"></div></div>
          <div><div class="field-label">Contact</div><div class="field-value" id="rContact"></div></div>
          <div class="full"><div class="field-label">Delivery Address</div><div class="field-value" id="rAddress"></div></div>
          <div><div class="field-label">Delivery Area</div><div class="field-value" id="rArea"></div></div>
          <div><div class="field-label">Courier</div><div class="field-value" id="rCourier"></div></div>
        </div>

        <div class="scan-actions" id="statusActions"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
{{-- unpkg.com is unreliable/unreachable from some networks (the actual cause behind the
     camera scanner sometimes silently failing to start) — cdnjs is far more consistently
     reachable for the same file. --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="{{ asset('js/parcel-scan.js') }}?v={{ filemtime(public_path('js/parcel-scan.js')) }}"></script>
@endpush
