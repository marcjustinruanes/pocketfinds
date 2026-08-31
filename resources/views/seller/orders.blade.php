@extends('seller.layout')
@section('title', 'Order Management')
@section('page-title', 'Order Management')
@section('page-sub', 'Prepare, ship, and confirm delivery — all in one place')

@section('content')
@if(session('order_success'))<div class="auth-success" style="margin-bottom:16px">{{ session('order_success') }}</div>@endif

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px">
  <div class="kpi">
    <div class="label">To Ship</div>
    <div class="value">{{ $orders->where('status','to_ship')->count() }}</div>
    <div class="delta">Needs preparing</div>
  </div>
  <div class="kpi">
    <div class="label">Pending Confirmation</div>
    <div class="value">{{ $pendingConfirmation }}</div>
    <div class="delta">Out for delivery</div>
  </div>
  <div class="kpi">
    <div class="label">Delivered Today</div>
    <div class="value">{{ $deliveredToday }}</div>
    <div class="delta up">Confirmed</div>
  </div>
</div>

<div class="filter-bar">
  <div class="search-mini">
    <span class="ic">@include('seller.partials.icon', ['name' => 'search', 'size' => 13])</span>
    <input type="text" placeholder="Search by order ID or customer…" id="orderSearch">
  </div>
</div>

<div class="card">
  @php
  $statusTabs = [
    ['all',               'All Orders'],
    ['to_ship',           'To Ship'],
    ['in_transit',        'In Transit'],
    ['out_for_delivery',  'Out for Delivery'],
    ['completed',         'Completed'],
    ['cancelled',         'Cancelled'],
  ];
  @endphp
  <div class="tabs" style="padding:0 20px;margin-bottom:0">
    @foreach($statusTabs as [$key, $label])
    <a href="{{ route('seller.orders', $key === 'all' ? [] : ['status' => $key]) }}" class="tab {{ $status === $key ? 'active' : '' }}">
      {{ $label }}
      @if(($key === 'all' ? $statusCounts->sum() : ($statusCounts[$key] ?? 0)) > 0)
        <span class="tab-count {{ $status === $key ? 'active' : '' }}">{{ $key === 'all' ? $statusCounts->sum() : ($statusCounts[$key] ?? 0) }}</span>
      @endif
    </a>
    @endforeach
  </div>

  <div class="card-pad">
    <div class="order-cards" id="orderCards">
      @forelse($orders as $order)
      @php
        $shipment = $order->shipment;
        $items = collect($order->items ?? []);
        $visibleItems = $items->take(3);
        $extraCount = max(0, $items->count() - $visibleItems->count());
        $address = collect([
          $order->shipping_address['house_no'] ?? null, $order->shipping_address['street'] ?? null,
          $order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null,
          $order->shipping_address['province'] ?? null,
        ])->filter()->join(', ') ?: 'Address not provided';
        $detailPayload = [
          'number' => $order->order_number,
          'date' => $order->created_at->format('M d, Y g:i A'),
          'status' => $order->status,
          'status_label' => str_replace('_', ' ', ucfirst($order->status)),
          'buyer_name' => trim(($order->buyer?->given_names ?: 'Customer not provided') . ' ' . $order->buyer?->last_name),
          'buyer_contact' => $order->buyer?->contact_no,
          'address' => $address,
          'payment' => $order->paymentMethod?->name ?: $order->payment_method ?: 'Payment not provided',
          'items' => $items->values(),
          'subtotal' => (float) $order->subtotal,
          'shipping' => (float) $order->shipping_amount,
          'discount' => (float) $order->discount_amount,
          'voucher_code' => $order->voucher_code,
          'total' => (float) $order->total,
          'note' => $order->buyer_note,
          'cancellation_reason' => $order->cancellation_reason,
          'cancellation_note' => $order->cancellation_note,
          'shipment' => $shipment ? [
            'tracking_number' => $shipment->tracking_number,
            'courier' => $shipment->courier ? $shipment->courier->given_names . ' ' . $shipment->courier->last_name : null,
            'scheduled_pickup_at' => $shipment->scheduled_pickup_at?->format('M d, Y g:i A'),
            'picked_up_at' => $shipment->picked_up_at?->format('M d, Y g:i A'),
            'in_transit_at' => $shipment->in_transit_at?->format('M d, Y g:i A'),
            'out_for_delivery_at' => $shipment->out_for_delivery_at?->format('M d, Y g:i A'),
            'delivered_at' => $shipment->delivered_at?->format('M d, Y g:i A'),
          ] : null,
          'waybill_url' => $shipment ? route('seller.orders.waybill', $order) : null,
          'message_url' => route('seller.messages', ['buyer' => $order->buyer_id]),
        ];
      @endphp
      <div class="order-card-s" data-row data-search="{{ $order->order_number }} {{ $detailPayload['buyer_name'] }}">
        <div class="order-card-s-head">
          <div class="order-card-s-id">
            <span class="mono">{{ $order->order_number }}</span>
            <button type="button" class="copy-btn" data-copy="{{ $order->order_number }}" title="Copy order ID">@include('seller.partials.icon', ['name' => 'copy', 'size' => 13])</button>
            <span class="order-card-s-date">{{ $order->created_at->format('M d, Y g:i A') }}</span>
          </div>
          <span class="stamp stamp-{{ $order->status === 'to_ship' ? 'new' : $order->status }}">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
        </div>

        <div class="order-card-s-buyer">
          <span>@include('seller.partials.icon', ['name' => 'user', 'size' => 13]) {{ $detailPayload['buyer_name'] }}</span>
          <a href="{{ $detailPayload['message_url'] }}">@include('seller.partials.icon', ['name' => 'mail', 'size' => 12]) Message</a>
        </div>

        <div class="order-card-s-items">
          @foreach($visibleItems as $item)
          <div class="order-card-s-item">
            <div class="order-card-s-item-thumb">
              @if(!empty($item['img']))
                <img src="{{ $item['img'] }}" alt="{{ $item['name'] ?? '' }}">
              @else
                @include('seller.partials.icon', ['name' => 'bag', 'size' => 18])
              @endif
            </div>
            <div class="order-card-s-item-info">
              <strong>{{ $item['name'] ?: 'Product not provided' }}</strong>
              @if(($item['variation_value'] ?? $item['color'] ?? '') || ($item['size'] ?? ''))
                <span>{{ collect([$item['variation_value'] ?? $item['color'] ?? null, $item['size'] ?? null])->filter()->join(' · ') }}</span>
              @endif
            </div>
            <div class="order-card-s-item-qty">{{ $item['qty'] }} × ₱{{ number_format($item['price'] ?? 0, 2) }}</div>
          </div>
          @endforeach
          @if($extraCount > 0)
          <div class="order-card-s-more">+{{ $extraCount }} more item{{ $extraCount === 1 ? '' : 's' }}</div>
          @endif
        </div>

        <div class="order-card-s-foot">
          <div class="order-card-s-shipment">
            @if($shipment)
              Tracking: <strong>{{ $shipment->tracking_number }}</strong><br>
              {{ $shipment->courier ? $shipment->courier->given_names . ' ' . $shipment->courier->last_name : 'Awaiting courier' }}
              @if($shipment->scheduled_pickup_at) · Pickup {{ $shipment->scheduled_pickup_at->format('M d, g:i A') }} @endif
            @else
              Not yet handed to courier
            @endif
          </div>
          <div class="order-card-s-total">₱{{ number_format($order->total, 2) }}</div>
          <div class="order-card-s-actions">
            @if($order->status === 'to_ship' && !$shipment)
              <form method="POST" action="{{ route('seller.orders.ready', $order) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-sm btn-primary">@include('seller.partials.icon', ['name' => 'truck', 'size' => 13]) Ready for Pickup</button>
              </form>
            @endif
            @if($shipment)
              <button type="button" class="btn btn-sm btn-outline" data-waybill-url="{{ route('seller.orders.waybill', ['order' => $order, 'embedded' => 1]) }}" title="Preview Waybill">@include('seller.partials.icon', ['name' => 'file', 'size' => 13]) Waybill</button>
              @if(!$shipment->picked_up_at)
              <button type="button" class="btn btn-sm btn-outline" title="Schedule Pickup" onclick="openScheduleModal('{{ $order->id }}', '{{ $order->order_number }}')">@include('seller.partials.icon', ['name' => 'truck', 'size' => 13]) Schedule</button>
              @endif
            @endif
            <button type="button" class="btn btn-sm btn-outline" data-view-order='@json($detailPayload)' title="View Details">@include('seller.partials.icon', ['name' => 'eye', 'size' => 13]) View</button>
          </div>
        </div>
      </div>
      @empty
      <div class="empty" style="padding:40px 20px">
        <div class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 28])</div>
        <h3>No orders yet</h3>
        <p>Orders will appear here once customers place them.</p>
      </div>
      @endforelse
    </div>
  </div>
</div>

<div class="modal-overlay" id="scheduleModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-head">
      <div><h3>Schedule Courier Pickup</h3><p id="scheduleOrderLabel"></p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <form method="POST" id="scheduleForm">
      @csrf
      @method('PATCH')
      <div class="modal-body">
        <div class="form-row">
          <label for="scheduledPickupAt">Pickup date &amp; time</label>
          <input type="datetime-local" name="scheduled_pickup_at" id="scheduledPickupAt" required>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Schedule</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="orderDetailModal">
  <div class="modal" style="max-width:680px">
    <div class="modal-head">
      <div>
        <h3 id="odNumber">Order details</h3>
        <p id="odDate"></p>
      </div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:18px">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <span id="odStatus" class="stamp"></span>
        <a id="odMessageLink" href="#" class="btn btn-sm btn-outline">@include('seller.partials.icon', ['name' => 'mail', 'size' => 13]) Message Buyer</a>
      </div>

      <div class="detail-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:12.5px">
        <div><div class="field-label" style="font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Buyer</div><strong id="odBuyerName"></strong><div id="odBuyerContact" style="color:var(--muted);margin-top:2px"></div></div>
        <div><div class="field-label" style="font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Payment Method</div><strong id="odPayment"></strong></div>
        <div style="grid-column:span 2"><div class="field-label" style="font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Deliver To</div><strong id="odAddress"></strong></div>
      </div>

      <div id="odNoteWrap" hidden>
        <div class="field-label" style="font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px">Buyer's Note</div>
        <p id="odNote" style="margin:0;font-size:13px;background:var(--paper);border-radius:8px;padding:10px 12px"></p>
      </div>

      <div id="odCancelWrap" hidden style="background:var(--danger-soft);border:1px solid var(--danger-line);border-radius:9px;padding:12px 14px;font-size:12.5px;color:var(--danger)">
        <strong>Cancelled</strong> — <span id="odCancelReason"></span>
        <div id="odCancelNote" style="margin-top:4px;color:var(--text)"></div>
      </div>

      <div>
        <div class="field-label" style="font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Items</div>
        <div id="odItems" style="display:flex;flex-direction:column;gap:0"></div>
      </div>

      <div style="border-top:1px solid var(--border);padding-top:12px;display:flex;flex-direction:column;gap:6px;font-size:13px">
        <div style="display:flex;justify-content:space-between"><span>Subtotal</span><span class="mono" id="odSubtotal"></span></div>
        <div style="display:flex;justify-content:space-between"><span>Shipping</span><span class="mono" id="odShipping"></span></div>
        <div style="display:flex;justify-content:space-between;color:var(--success)" id="odDiscountRow" hidden><span id="odDiscountLabel">Discount</span><span class="mono" id="odDiscount"></span></div>
        <div style="display:flex;justify-content:space-between;font-weight:800;font-size:15px;color:var(--pink-dark);padding-top:6px;border-top:1px dashed var(--border)"><span>Total</span><span class="mono" id="odTotal"></span></div>
      </div>

      <div id="odShipmentWrap" hidden>
        <div class="field-label" style="font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Shipment Tracking</div>
        <div id="odTrackingNums" style="font-size:12.5px;color:var(--muted);margin-bottom:12px"></div>
        <div class="tracking-line" id="odTrackingLine"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button id="odWaybillLink" type="button" class="btn btn-outline" hidden>@include('seller.partials.icon', ['name' => 'file', 'size' => 13]) View Waybill</button>
      <button type="button" class="btn btn-primary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="waybillModal" aria-hidden="true">
  <div class="modal" style="width:min(760px,calc(100vw - 32px));max-width:760px;height:min(820px,calc(100vh - 32px));display:flex;flex-direction:column">
    <div class="modal-head">
      <div><h3>Waybill Preview</h3><p>Review the shipping label before printing.</p></div>
      <button class="modal-close" type="button" data-modal-close aria-label="Close waybill">✕</button>
    </div>
    <div class="modal-body" style="padding:0;flex:1;min-height:0;background:var(--paper)">
      <iframe id="waybillFrame" title="Waybill preview" style="display:block;width:100%;height:100%;border:0;background:#fff"></iframe>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Close</button>
      <button type="button" class="btn btn-primary" id="printWaybillButton">@include('seller.partials.icon', ['name' => 'file', 'size' => 13]) Print Waybill</button>
    </div>
  </div>
</div>
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const waybillModal = document.getElementById('waybillModal');
  const waybillFrame = document.getElementById('waybillFrame');
  const openWaybill = url => {
    if (!url) return;
    waybillFrame.src = url;
    waybillModal.classList.add('open');
    waybillModal.setAttribute('aria-hidden', 'false');
  };
  document.querySelectorAll('[data-waybill-url]').forEach(button => {
    button.addEventListener('click', () => openWaybill(button.dataset.waybillUrl));
  });
  document.getElementById('printWaybillButton')?.addEventListener('click', () => {
    waybillFrame.contentWindow?.focus();
    waybillFrame.contentWindow?.print();
  });

  const search = document.getElementById('orderSearch');
  search?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#orderCards .order-card-s[data-row]').forEach(card => {
      card.style.display = card.dataset.search.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  document.querySelectorAll('.copy-btn[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
      navigator.clipboard?.writeText(btn.dataset.copy).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = `@include('seller.partials.icon', ['name' => 'check', 'size' => 13])`;
        setTimeout(() => { btn.innerHTML = original; }, 1200);
      }).catch(() => {});
    });
  });

  const money = v => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  document.querySelectorAll('[data-view-order]').forEach(btn => {
    btn.addEventListener('click', () => {
      const o = JSON.parse(btn.dataset.viewOrder);
      document.getElementById('odNumber').textContent = o.number;
      document.getElementById('odDate').textContent = 'Placed ' + o.date;
      const statusEl = document.getElementById('odStatus');
      statusEl.className = 'stamp stamp-' + (o.status === 'to_ship' ? 'new' : o.status);
      statusEl.textContent = o.status_label;
      document.getElementById('odMessageLink').href = o.message_url;
      document.getElementById('odBuyerName').textContent = o.buyer_name;
      document.getElementById('odBuyerContact').textContent = o.buyer_contact || '';
      document.getElementById('odPayment').textContent = o.payment;
      document.getElementById('odAddress').textContent = o.address;

      const noteWrap = document.getElementById('odNoteWrap');
      if (o.note) { noteWrap.hidden = false; document.getElementById('odNote').textContent = o.note; }
      else { noteWrap.hidden = true; }

      const cancelWrap = document.getElementById('odCancelWrap');
      if (o.status === 'cancelled') {
        cancelWrap.hidden = false;
        document.getElementById('odCancelReason').textContent = o.cancellation_reason || 'No reason provided';
        document.getElementById('odCancelNote').textContent = o.cancellation_note || '';
      } else { cancelWrap.hidden = true; }

      const itemsEl = document.getElementById('odItems');
      itemsEl.innerHTML = '';
      (o.items || []).forEach(item => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px dashed var(--border);font-size:13px';
        const variant = [item.variation_value || item.color, item.size].filter(Boolean).join(' · ');
        row.innerHTML = `<span>${item.name || 'Product not provided'}${variant ? ' <small style="color:var(--muted)">(' + variant + ')</small>' : ''}</span><span class="mono">${item.qty} × ${money(item.price)}</span>`;
        itemsEl.appendChild(row);
      });

      document.getElementById('odSubtotal').textContent = money(o.subtotal);
      document.getElementById('odShipping').textContent = money(o.shipping);
      const discountRow = document.getElementById('odDiscountRow');
      if (o.discount > 0) {
        discountRow.style.display = 'flex';
        document.getElementById('odDiscountLabel').textContent = o.voucher_code ? 'Discount (' + o.voucher_code + ')' : 'Discount';
        document.getElementById('odDiscount').textContent = '- ' + money(o.discount);
      } else { discountRow.style.display = 'none'; }
      document.getElementById('odTotal').textContent = money(o.total);

      const shipmentWrap = document.getElementById('odShipmentWrap');
      const waybillLink = document.getElementById('odWaybillLink');
      if (o.shipment) {
        shipmentWrap.hidden = false;
        document.getElementById('odTrackingNums').innerHTML = `Tracking: <strong style="color:var(--text)">${o.shipment.tracking_number}</strong>` + (o.shipment.courier ? ' · Courier: ' + o.shipment.courier : ' · Awaiting courier assignment');
        const steps = [
          ['Ready for Pickup', o.shipment.scheduled_pickup_at ? 'Scheduled ' + o.shipment.scheduled_pickup_at : 'Awaiting schedule'],
          ['Picked Up', o.shipment.picked_up_at],
          ['In Transit', o.shipment.in_transit_at],
          ['Out for Delivery', o.shipment.out_for_delivery_at],
          ['Delivered', o.shipment.delivered_at],
        ];
        const line = document.getElementById('odTrackingLine');
        line.innerHTML = '';
        steps.forEach(([label, when], i) => {
          const done = !!when || i === 0;
          const div = document.createElement('div');
          div.className = 'tracking-line-step' + (done ? ' done' : '');
          div.innerHTML = `<span class="tracking-line-dot">${done ? '✓' : i + 1}</span><div class="tracking-line-copy"><strong>${label}</strong><span>${when || 'Pending'}</span></div>`;
          line.appendChild(div);
        });
      } else {
        shipmentWrap.hidden = true;
      }
      if (o.waybill_url) {
        waybillLink.style.display = '';
        waybillLink.onclick = () => {
          document.getElementById('orderDetailModal').classList.remove('open');
          openWaybill(o.waybill_url + (o.waybill_url.includes('?') ? '&' : '?') + 'embedded=1');
        };
      }
      else { waybillLink.style.display = 'none'; }

      document.getElementById('orderDetailModal').classList.add('open');
    });
  });
});

function openScheduleModal(orderId, orderNumber) {
  document.getElementById('scheduleOrderLabel').textContent = 'Order ' + orderNumber;
  document.getElementById('scheduleForm').action = '{{ url('/seller/orders') }}/' + orderId + '/schedule-pickup';
  document.getElementById('scheduleModal').classList.add('open');
}
</script>
@endpush
