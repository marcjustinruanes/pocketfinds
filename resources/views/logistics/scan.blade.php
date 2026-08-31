@extends('logistics.layout')
@section('title', 'Scan Parcel')
@section('page-title', 'Scan Parcel')
@section('page-sub', 'Webcam or USB barcode scanner')

@push('head')
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
</style>
@endpush

@section('content')
<div class="scan-grid">
  @php($scannerPref = auth()->user()->preferred_scanner ?? 'both')
  <div class="card">
    <div class="card-head"><h2>Universal Scanner</h2></div>
    <div class="card-pad">
      @if($scannerPref !== 'usb')
      <div id="reader" class="reader"></div>
      <div class="scan-actions" style="margin-top:0;margin-bottom:14px">
        <button type="button" class="btn btn-outline" id="startCamera">Start Camera</button>
        <button type="button" class="btn btn-outline" id="stopCamera">Stop Camera</button>
      </div>
      @endif

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

      <div id="scanMessage"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Shipment</h2></div>
    <div class="card-pad">
      <div id="emptyResult" class="scan-result-empty">Scan or enter a tracking number to view shipment details.</div>

      <div id="shipmentResult" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h2 class="mono" id="rTracking" style="margin:0;font-size:18px"></h2>
          <span class="stamp" id="rStatus"></span>
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
@if($scannerPref !== 'usb')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@endif
<script>
(() => {
  const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';
  const LOOKUP_URL = '{{ route('logistics.scan.lookup') }}';
  const STATUS_URL_BASE   = '{{ url('/logistics/status') }}';
  const RECEIVE_URL_BASE  = '{{ url('/logistics/requests') }}';
  const ASSIGN_URL_BASE   = '{{ url('/logistics/assignments') }}';

  const $ = (id) => document.getElementById(id);
  const input   = $('trackingInput');
  const msgBox  = $('scanMessage');
  const empty   = $('emptyResult');
  const result  = $('shipmentResult');
  let scanner   = null;

  function showMessage(text, type) {
    msgBox.innerHTML = `<div class="scan-msg ${type}">${text}</div>`;
  }

  function renderShipment(s, meta) {
    empty.style.display  = 'none';
    result.style.display = '';

    $('rTracking').textContent = s.tracking_number;
    $('rStatus').textContent   = s.status_label;
    $('rStatus').className     = 'stamp stamp-' + s.status;
    $('rOrder').textContent    = s.order_number || '—';
    $('rUpdated').textContent  = s.updated_at || '—';
    $('rBuyer').textContent    = s.buyer_name || '—';
    $('rContact').textContent  = s.buyer_contact || '—';
    $('rAddress').textContent  = s.address || '—';
    $('rArea').textContent     = s.delivery_area || 'Not determined';
    $('rCourier').textContent  = s.courier_name || 'Not assigned';

    const actions = $('statusActions');
    actions.innerHTML = '';

    if (meta.stage === 'receive') {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-success';
      btn.textContent = 'Receive Parcel';
      btn.addEventListener('click', () => receiveParcel(s.id, s.tracking_number));
      actions.appendChild(btn);
      return;
    }

    if (meta.stage === 'assign') {
      const wrap = document.createElement('div');
      wrap.style.width = '100%';

      if (meta.area_serviceable === false) {
        const warn = document.createElement('div');
        warn.className = 'scan-msg danger';
        warn.innerHTML = '<strong>' + (s.delivery_area || 'This area') + ' is marked unserviceable.</strong>'
          + (meta.area_note ? '<br>' + meta.area_note : '');
        wrap.appendChild(warn);
        actions.appendChild(wrap);
        return;
      }

      if (meta.area_riders.length && meta.area_matched) {
        const note = document.createElement('p');
        note.className = 'hint';
        note.textContent = 'Riders covering ' + s.delivery_area + ':';
        wrap.appendChild(note);
      } else if (meta.area_riders.length && s.delivery_area) {
        const note = document.createElement('p');
        note.className = 'hint';
        note.textContent = 'No rider registered in ' + s.delivery_area + ' yet — showing all approved riders.';
        wrap.appendChild(note);
      } else if (meta.area_riders.length) {
        const note = document.createElement('p');
        note.className = 'hint';
        note.textContent = 'Delivery area not determined — showing all approved riders.';
        wrap.appendChild(note);
      }

      if (!meta.area_riders.length) {
        const note = document.createElement('p');
        note.className = 'hint';
        note.textContent = 'No approved riders available to assign yet.';
        wrap.appendChild(note);
        actions.appendChild(wrap);
        return;
      }

      const row = document.createElement('div');
      row.className = 'scan-input-row';
      const select = document.createElement('select');
      select.className = 'select';
      select.style.flex = '1';
      meta.area_riders.forEach((r) => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = r.name + (r.municipality ? ' — ' + r.municipality : '');
        select.appendChild(opt);
      });
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary';
      btn.textContent = 'Assign to Rider';
      btn.addEventListener('click', () => assignToRider(s.id, select.value, s.tracking_number));
      row.appendChild(select);
      row.appendChild(btn);
      wrap.appendChild(row);
      actions.appendChild(wrap);
      return;
    }

    if (meta.next_status) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-success';
      btn.textContent = meta.next_label;
      btn.addEventListener('click', () => updateStatus(s.id, meta.next_status, s.tracking_number));
      actions.appendChild(btn);
    }
    if (meta.can_fail) {
      const failBtn = document.createElement('button');
      failBtn.type = 'button';
      failBtn.className = 'btn btn-danger';
      failBtn.textContent = 'Mark Failed';
      failBtn.addEventListener('click', () => updateStatus(s.id, 'failed', s.tracking_number));
      actions.appendChild(failBtn);
    }
    if (!meta.next_status && !meta.can_fail) {
      const note = document.createElement('p');
      note.className = 'hint';
      note.textContent = 'No scan action available for the current status.';
      actions.appendChild(note);
    }
  }

  async function lookup(code) {
    code = (code || '').trim();
    if (!code) return;
    showMessage('Looking up ' + code + '…', 'info');
    try {
      const res = await fetch(LOOKUP_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ code }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.message || 'Shipment not found.');
      renderShipment(data.shipment, data);
      showMessage('Shipment found.', 'success');
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  async function patchJson(url, body) {
    const res = await fetch(url, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify(body || {}),
    });
    if (!res.ok) throw new Error('Request could not be completed.');
  }

  async function updateStatus(shipmentId, status, trackingNumber) {
    try {
      await patchJson(STATUS_URL_BASE + '/' + shipmentId, { status });
      showMessage('Status updated.', 'success');
      lookup(trackingNumber);
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  async function receiveParcel(shipmentId, trackingNumber) {
    try {
      await patchJson(RECEIVE_URL_BASE + '/' + shipmentId + '/approve');
      showMessage('Parcel received. Delivery area determined.', 'success');
      lookup(trackingNumber);
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  async function assignToRider(shipmentId, courierId, trackingNumber) {
    if (!courierId) { showMessage('Select a rider first.', 'danger'); return; }
    try {
      await patchJson(ASSIGN_URL_BASE + '/' + shipmentId + '/assign', { courier_id: courierId });
      showMessage('Parcel assigned to rider.', 'success');
      lookup(trackingNumber);
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  $('trackingForm').addEventListener('submit', (e) => {
    e.preventDefault();
    lookup(input.value);
  });

  $('startCamera')?.addEventListener('click', async () => {
    if (scanner) return;
    scanner = new Html5Qrcode('reader');
    try {
      await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 150 } },
        (decodedText) => { input.value = decodedText; lookup(decodedText); },
        () => {}
      );
    } catch (e) {
      showMessage('Camera could not start. You can still use a USB scanner or type the tracking number.', 'danger');
      scanner = null;
    }
  });

  $('stopCamera')?.addEventListener('click', async () => {
    if (!scanner) return;
    try { await scanner.stop(); } catch (e) {}
    scanner.clear();
    scanner = null;
  });
})();
</script>
@endpush
