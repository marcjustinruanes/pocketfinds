(() => {
  const config = document.getElementById('phoneScannerConnection');
  const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';
  const LOOKUP_URL = config.dataset.lookupUrl;
  const STATUS_URL_BASE   = config.dataset.statusBase;
  const RECEIVE_URL_BASE  = config.dataset.receiveBase;
  const ASSIGN_URL_BASE   = config.dataset.assignBase;

  const $ = (id) => document.getElementById(id);
  const input   = $('trackingInput');
  const msgBox  = $('scanMessage');
  const empty   = $('emptyResult');
  const result  = $('shipmentResult');
  let scanner   = null;
  let lookupVersion = 0;
  let actionBusy = false;
  let lookupBusy = false;
  let phonePair = null;
  let pollTimer = null;
  let phoneCursor = 0;
  let lastCameraCode = null;

  function showMessage(text, type) {
    const message = document.createElement('div');
    message.className = 'scan-msg ' + type;
    message.textContent = text;
    msgBox.replaceChildren(message);
  }

  /**
   * Every scan-page action (receive, assign, advance status…) is a couple of sequential
   * round trips to a remote DB — with no visual feedback at all it just looks stuck, and
   * an impatient second click hits the same shipment mid-transition and throws a confusing
   * "wrong state" error. This disables the button and shows a busy label immediately on
   * click, and only restores it on failure — success replaces it via renderShipment() anyway.
   */
  function runAction(btn, busyLabel, fn) {
    if (btn.disabled || actionBusy || lookupBusy) return; // already in flight — ignore a second click
    const originalLabel = btn.textContent;
    actionBusy = true;
    document.querySelectorAll('#statusActions button').forEach(button => button.disabled = true);
    input.disabled = true;
    btn.disabled = true;
    btn.textContent = busyLabel;
    Promise.resolve().then(fn).finally(() => {
      actionBusy = false;
      input.disabled = false;
      document.querySelectorAll('#statusActions button').forEach(button => button.disabled = false);
      if (btn.isConnected) { btn.disabled = false; btn.textContent = originalLabel; }
    });
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

    if (meta.stage === 'awaiting_pickup_rider') {
      const note = document.createElement('p');
      note.className = 'hint';
      note.textContent = 'This parcel is approved for pickup but no rider has claimed it yet.';
      actions.appendChild(note);
      return;
    }

    if (meta.stage === 'awaiting_seller_confirm') {
      const note = document.createElement('p');
      note.className = 'hint';
      note.textContent = 'A pickup rider has claimed this parcel — waiting on the seller to confirm the handoff.';
      actions.appendChild(note);
      return;
    }

    if (meta.stage === 'receive') {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-success';
      btn.textContent = 'Receive & Sort';
      btn.addEventListener('click', () => runAction(btn, 'Receiving…', () => receiveParcel(s.id, s.tracking_number)));
      actions.appendChild(btn);
      return;
    }

    if (meta.stage === 'in_hub_transfer') {
      const leg = s.current_leg;
      const fromHub = leg ? leg.from_hub : (s.origin_hub || 'the origin hub');
      const toHub   = leg ? leg.to_hub   : (s.destination_hub || 'the destination hub');
      const note = document.createElement('p');
      note.className = 'hint';
      note.textContent = (s.hub_leg_progress ? s.hub_leg_progress + ' — ' : '') + (s.hub_transfer_rider_name || 'A rider') + ' is carrying this parcel from '
        + fromHub + ' to ' + toHub + '.';
      actions.appendChild(note);

      // Two independent gates on "Mark Received at <destination>":
      //  1. can_complete — only that destination hub's own staff (or the admin) even see the
      //     button at all. The origin hub (or any other hub) looking up the same tracking
      //     number has nothing to click here — it isn't their hub to receive it at.
      //  2. rider_confirmed — even the right hub can't mark it received until the rider has
      //     confirmed picking it up from the origin hub (Rider account -> My Hub Transfers).
      if (leg && !leg.can_complete) {
        const notForThisHub = document.createElement('p');
        notForThisHub.className = 'hint';
        notForThisHub.textContent = 'This parcel is headed to ' + toHub + ' — only that hub\'s own staff can mark it received.';
        actions.appendChild(notForThisHub);
      } else if (!leg || leg.rider_confirmed) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-success';
        btn.textContent = 'Mark Received at ' + toHub;
        btn.addEventListener('click', () => runAction(btn, 'Receiving…', () => completeHubTransferScan(s.id, s.tracking_number)));
        actions.appendChild(btn);
      } else {
        const waiting = document.createElement('p');
        waiting.className = 'hint';
        waiting.style.color = '#b45309';
        waiting.textContent = 'Waiting for the rider to confirm they picked this up from ' + fromHub + ' before it can be marked received here.';
        actions.appendChild(waiting);
      }
      return;
    }

    if (meta.stage === 'awaiting_transfer_approval') {
      const leg = s.current_leg;
      const note = document.createElement('p');
      note.className = 'hint';
      note.textContent = (s.hub_leg_progress ? s.hub_leg_progress + ' — ' : '') + 'At ' + (leg ? leg.from_hub : 'this hub') + ', headed to ' + (leg ? leg.to_hub : 'the next hub') + '. '
        + (leg && leg.requested ? 'Transfer requested — waiting on the admin to approve it.' : 'This hub\'s staff need to request the transfer from their dashboard before a rider can be assigned.');
      actions.appendChild(note);
      return;
    }

    if (meta.stage === 'assign_hub_transfer') {
      const leg = s.current_leg;
      const wrap = document.createElement('div');
      wrap.style.width = '100%';
      const note = document.createElement('p');
      note.className = 'hint';
      note.textContent = (s.hub_leg_progress ? s.hub_leg_progress + ' — ' : '') + 'Received at '
        + (leg ? leg.from_hub : (s.origin_hub || 'this hub')) + ' — assign a rider to carry it to '
        + (leg ? leg.to_hub : (s.destination_hub || 'the destination hub')) + '. Dispatch includes all parcels in this approved batch:';
      wrap.appendChild(note);

      if (!meta.hub_transfer_riders.length) {
        const empty = document.createElement('p');
        empty.className = 'hint';
        empty.textContent = 'No approved riders available to assign yet.';
        wrap.appendChild(empty);
        actions.appendChild(wrap);
        return;
      }

      const row = document.createElement('div');
      row.className = 'scan-input-row';
      const select = document.createElement('select');
      select.className = 'select';
      select.style.flex = '1';
      meta.hub_transfer_riders.forEach((r) => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = r.name;
        select.appendChild(opt);
      });
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary';
      btn.textContent = 'Dispatch Approved Batch';
      btn.addEventListener('click', () => runAction(btn, 'Assigning…', () => assignHubTransfer(s.id, select.value, s.tracking_number)));
      row.appendChild(select);
      row.appendChild(btn);
      wrap.appendChild(row);
      actions.appendChild(wrap);
      return;
    }

    if (meta.stage === 'assign') {
      const wrap = document.createElement('div');
      wrap.style.width = '100%';

      if (meta.area_serviceable === false) {
        const warn = document.createElement('div');
        warn.className = 'scan-msg danger';
        warn.textContent = (s.delivery_area || 'This area') + ' is marked unserviceable.'
          + (meta.area_note ? ' ' + meta.area_note : '');
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
      btn.addEventListener('click', () => runAction(btn, 'Assigning…', () => assignToRider(s.id, select.value, s.tracking_number)));
      row.appendChild(select);
      row.appendChild(btn);
      wrap.appendChild(row);
      actions.appendChild(wrap);
      return;
    }

    // "Out for Delivery" is the assigned rider's own call — they mark it themselves from
    // their own "My Deliveries" page once they actually leave with it, not something hub
    // staff do on their behalf from here.
    if (meta.next_status === 'out_for_delivery') {
      const note = document.createElement('p');
      note.className = 'hint';
      note.textContent = 'Waiting for the assigned rider to mark this out for delivery from their own account.';
      actions.appendChild(note);
    } else if (meta.next_status) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-success';
      btn.textContent = meta.next_label;
      btn.addEventListener('click', () => runAction(btn, 'Updating…', () => updateStatus(s.id, meta.next_status, s.tracking_number)));
      actions.appendChild(btn);
    }
    if (meta.can_fail) {
      const failBtn = document.createElement('button');
      failBtn.type = 'button';
      failBtn.className = 'btn btn-danger';
      failBtn.textContent = 'Mark Failed';
      failBtn.addEventListener('click', () => {
        const reason = prompt('Why did this delivery fail?');
        if (reason === null) return;
        runAction(failBtn, 'Updating…', () => updateStatus(s.id, 'delivery_failed', s.tracking_number, reason));
      });
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
    if (!code) return false;
    const version = ++lookupVersion;
    lookupBusy = true;
    result.style.display = 'none';
    empty.style.display = '';
    $('statusActions').replaceChildren();
    empty.textContent = 'Looking up parcel...';
    showMessage('Looking up ' + code + '...', 'info');
    try {
      const res = await fetch(LOOKUP_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ code }),
      });
      const data = await readResponse(res);
      if (!data.ok) throw new Error(data.message || 'Shipment not found.');
      if (version !== lookupVersion) return false;
      renderShipment(data.shipment, data);
      showMessage('Shipment found. Review the details before taking an action.', 'success');
      return true;
    } catch (e) {
      if (version === lookupVersion) {
        empty.textContent = 'No parcel selected. Scan or enter another code.';
        showMessage(e.message, 'danger');
      }
      return false;
    } finally {
      if (version === lookupVersion) lookupBusy = false;
    }
  }

  async function readResponse(response) {
    let data;
    try { data = await response.json(); } catch { throw new Error('The server could not respond. Sign in again or retry.'); }
    if (!response.ok) {
      const error = new Error(response.status >= 500 ? 'The server could not complete this request. Please retry.' : (data.message || 'Request could not be completed.'));
      error.status = response.status;
      throw error;
    }
    return data;
  }

  async function patchJson(url, body) {
    const res = await fetch(url, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify(body || {}),
    });
    if (res.ok && !(res.headers.get('Content-Type') || '').includes('application/json')) {
      if (res.redirected && new URL(res.url).pathname === new URL(config.dataset.loginUrl).pathname) throw new Error('Sign in again before changing this parcel.');
      return {};
    }
    return readResponse(res);
  }

  async function updateStatus(shipmentId, status, trackingNumber, reason) {
    try {
      await patchJson(STATUS_URL_BASE + '/' + shipmentId, { status, reason });
      showMessage('Status updated.', 'success');
      await lookup(trackingNumber);
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  async function receiveParcel(shipmentId, trackingNumber) {
    try {
      await patchJson(RECEIVE_URL_BASE + '/' + shipmentId + '/receive');
      showMessage('Parcel received and sorted.', 'success');
      await lookup(trackingNumber);
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  async function assignToRider(shipmentId, courierId, trackingNumber) {
    if (!courierId) { showMessage('Select a rider first.', 'danger'); return; }
    try {
      await patchJson(ASSIGN_URL_BASE + '/' + shipmentId + '/assign', { courier_id: courierId });
      showMessage('Parcel assigned to rider.', 'success');
      await lookup(trackingNumber);
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  async function assignHubTransfer(shipmentId, courierId, trackingNumber) {
    if (!courierId) { showMessage('Select a rider first.', 'danger'); return; }
    try {
      await patchJson(ASSIGN_URL_BASE + '/' + shipmentId + '/assign-hub-transfer', { courier_id: courierId });
      showMessage('Hub transfer rider assigned.', 'success');
      await lookup(trackingNumber);
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  async function completeHubTransferScan(shipmentId, trackingNumber) {
    try {
      await patchJson(RECEIVE_URL_BASE + '/' + shipmentId + '/complete-hub-transfer');
      showMessage('Parcel received at destination hub.', 'success');
      await lookup(trackingNumber);
    } catch (e) {
      showMessage(e.message, 'danger');
    }
  }

  $('trackingForm').addEventListener('submit', (e) => {
    e.preventDefault();
    if (!actionBusy) lookup(input.value);
  });

  $('startCamera')?.addEventListener('click', async () => {
    if (scanner) return;
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      showMessage('Camera scanning requires HTTPS and camera permission. You can still type a tracking number or use a USB scanner.', 'danger');
      return;
    }
    $('reader').hidden = false;
    lastCameraCode = null;

    // The scanner library loads from an external CDN — if that fails or is slow (a real,
    // occasional issue, not just a camera-permission problem), `Html5Qrcode` never exists
    // and this used to throw silently with no message at all, leaving the button looking
    // like it just did nothing. Both failure modes now show the same clear fallback.
    if (typeof Html5Qrcode === 'undefined') {
      showMessage('The camera scanner failed to load. You can still use a USB scanner or type the tracking number below.', 'danger');
      return;
    }

    try {
      scanner = new Html5Qrcode('reader');
      await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 150 } },
        (decodedText) => {
          if (actionBusy || lookupBusy || decodedText === lastCameraCode) return;
          lastCameraCode = decodedText;
          input.value = decodedText;
          lookup(decodedText);
        },
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
  function phoneMessage(text) { $('phonePairStatus').textContent = text; }
  function stopPolling() { clearTimeout(pollTimer); pollTimer = null; }
  async function phoneRequest(url, method = 'GET', body) {
    const response = await fetch(url, {
      method, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      ...(body ? { body: JSON.stringify(body) } : {}),
    });
    return readResponse(response);
  }
  let phonePollBusy = false;
  function endPhonePair(message) {
    phonePair = null;
    stopPolling();
    $('connectPhoneScanner').disabled = false;
    $('phonePairLink').value = '';
    $('phonePairQr').replaceChildren();
    $('phoneNextScan').hidden = true;
    $('phoneDisconnect').hidden = true;
    phoneMessage(message);
  }
  async function pollPhone() {
    const pair = phonePair;
    if (!pair || phonePollBusy) return;
    phonePollBusy = true;
    try {
      const data = await phoneRequest(pair.events_url);
      if (phonePair !== pair) return;
      if (data.state === 'disconnected') {
        endPhonePair(data.disconnected_by === 'phone' ? 'Your phone disconnected from this computer.' : 'You disconnected the phone on this computer.');
        return;
      }
      if (actionBusy || lookupBusy || !$('phoneNextScan').hidden) return;
      // Handle one queued scan at a time; keep each parcel visible until the operator chooses Next.
      const event = data.events.find(event => event.sequence > phoneCursor);
      if (event) {
        input.value = event.code;
        await lookup(event.code);
        if (phonePair !== pair) return;
        phoneCursor = event.sequence;
        $('phoneNextScan').hidden = false;
        await phoneRequest(pair.ack_url, 'POST', { sequence: event.sequence });
        phoneMessage('Phone scan collected. Review this parcel, then choose Next phone scan.');
        return;
      }
      if (Date.now() >= pair.expires_at * 1000) throw Object.assign(new Error('This phone connection has expired.'), { status: 410 });
      phoneMessage('Connected. Waiting for a code from your phone...');
    } catch (error) {
      if (phonePair !== pair) return;
      phoneMessage(error.message);
      if (error.status === 410) {
        try {
          const status = await phoneRequest(pair.events_url);
          if (phonePair !== pair) return;
          if (status.state === 'disconnected') {
            endPhonePair(status.disconnected_by === 'phone' ? 'Your phone disconnected from this computer.' : 'You disconnected the phone on this computer.');
            return;
          }
        } catch (_) { /* Expired sessions have no retained state. */ }
      }
      if ([401, 403, 410].includes(error.status)) { if (phonePair === pair) endPhonePair(error.message); return; }
    } finally {
      phonePollBusy = false;
      if (phonePair) { stopPolling(); pollTimer = setTimeout(pollPhone, 2000); }
    }
  }
  $('connectPhoneScanner').addEventListener('click', async () => {
    if (phonePair) return;
    const button = $('connectPhoneScanner');
    button.disabled = true;
    $('phonePairPanel').hidden = false;
    phoneMessage('Creating a phone connection...');
    try {
      const pair = await phoneRequest(config.dataset.createUrl, 'POST');
      phonePair = pair;
      phoneCursor = 0;
      $('phoneNextScan').hidden = true;
      $('phonePairLink').value = pair.url;
      $('phonePairQr').replaceChildren();
      if (typeof QRCode !== 'undefined') new QRCode($('phonePairQr'), { text: pair.url, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M });
      $('phoneDisconnect').hidden = false;
      if (['localhost', '127.0.0.1', '[::1]'].includes(new URL(pair.url).hostname)) {
        $('phoneAddressNote').textContent = 'This link points to this computer only. Open PocketFinds through an HTTPS address reachable from both devices before connecting your phone.';
      } else $('phoneAddressNote').textContent = 'In PocketFinds Scanner for Android, sign into the same account and choose Pair with Computer. Scan this QR in the app. This connection lasts 10 minutes.';
      stopPolling();
      pollPhone();
    } catch (error) { phoneMessage(error.message); button.disabled = false; }
  });
  $('phoneNextScan').addEventListener('click', () => {
    if (actionBusy || lookupBusy) return;
    $('phoneNextScan').hidden = true;
    result.style.display = 'none';
    empty.style.display = '';
    empty.textContent = 'Waiting for the next phone scan...';
    $('statusActions').replaceChildren();
    input.value = '';
    stopPolling();
    pollPhone();
  });
  $('phoneDisconnect').addEventListener('click', async () => {
    const pair = phonePair;
    if (!pair) return;
    try {
      await phoneRequest(pair.disconnect_url, 'DELETE');
      if (phonePair === pair) endPhonePair('You disconnected the phone on this computer.');
    } catch (error) { phoneMessage(error.message); }
  });
  window.addEventListener('pagehide', stopPolling);
})();
