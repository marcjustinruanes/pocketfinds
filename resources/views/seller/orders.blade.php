@extends('seller.layout')
@section('title', 'Order Management')
@section('page-title', 'Order Management')
@section('body-class', 'seller-orders-page')
@push('head')<link rel="stylesheet" href="{{ asset('css/seller-order-categories.css') }}?v={{ filemtime(public_path('css/seller-order-categories.css')) }}">@endpush
@push('head')
<link rel="stylesheet" href="{{ asset('css/order-details.css') }}?v={{ filemtime(public_path('css/order-details.css')) }}">
<link rel="stylesheet" href="{{ asset('css/seller-order-cards.css') }}?v={{ filemtime(public_path('css/seller-order-cards.css')) }}">
@endpush
@push('scripts')<script src="{{ asset('js/seller-order-details.js') }}?v={{ filemtime(public_path('js/seller-order-details.js')) }}"></script>@endpush

@push('head')<link rel="stylesheet" href="{{ asset('css/order-record.css') }}?v={{ filemtime(public_path('css/order-record.css')) }}">@endpush
@push('head')<link rel="stylesheet" href="{{ asset('css/order-record.css') }}?v={{ filemtime(public_path('css/order-record.css')) }}">@endpush
@section('content')
<div class="orders-management">
@include('commerce.messages')
@if(session('order_success'))<div class="auth-success" style="margin-bottom:16px">{{ session('order_success') }}</div>@endif
@if(session('error'))<div style="background:var(--danger-soft,#fef2f2);border:1px solid var(--danger-line,#fecaca);color:var(--danger,#dc2626);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('error') }}</div>@endif

<div class="seller-order-layout">
@include('seller.partials.order-categories')
<div class="seller-order-content">
<form class="filter-bar orders-filter-bar" method="GET" action="{{ route('seller.orders') }}" aria-label="Filter orders">
  <input type="hidden" name="status" value="{{ $status }}">
  <div class="search-mini">
    <span class="ic">@include('seller.partials.icon', ['name' => 'search', 'size' => 13])</span>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by order ID or customer…" id="orderSearch" aria-label="Search orders">
  </div>
  <label class="seller-order-filter"><span>Date</span><select name="period" onchange="this.form.requestSubmit()">@foreach(['all'=>'All time','30d'=>'Last 30 days','6m'=>'Last 6 months','year'=>'This year'] as $key=>$label)<option value="{{ $key }}" @selected(request('period','all')===$key)>{{ $label }}</option>@endforeach</select></label>
  <label class="seller-order-filter"><span>Payment</span><select name="payment" onchange="this.form.requestSubmit()">@foreach(['all'=>'All payments','cod'=>'Cash on Delivery','gcash'=>'GCash'] as $key=>$label)<option value="{{ $key }}" @selected(request('payment','all')===$key)>{{ $label }}</option>@endforeach</select></label>
</form>

<div class="card orders-list-panel">
  <div class="card-pad">
    <div class="order-cards" id="orderCards">
      @forelse($orders as $order)
      @php
        $shipment = $order->shipment;
        $items = collect($order->itemsWithImages());
        $visibleItems = $items;
        $extraCount = max(0, $items->count() - $visibleItems->count());
        $address = collect([
          $order->shipping_address['house_no'] ?? null, $order->shipping_address['street'] ?? null,
          $order->shipping_address['barangay'] ?? null, $order->shipping_address['municipality'] ?? null,
          $order->shipping_address['province'] ?? null, $order->shipping_address['region'] ?? null, $order->shipping_address['postal_code'] ?? null,
        ])->filter()->join(', ') ?: 'Address not provided';
        $customerName = trim((string) ($order->shipping_address['recipient_name'] ?? ''))
          ?: trim(($order->buyer?->given_names ?? '').' '.($order->buyer?->last_name ?? ''))
          ?: 'Customer not provided';
        $customerContact = $order->shipping_address['contact_no'] ?? $order->buyer?->contact_no;
        $orderedAt = $order->created_at?->format('M d, Y g:i A') ?? '—';
        // Only companies with a hub in BOTH your city and the buyer's city can
        // actually move this parcel through their own connected hub network.
        $eligibleCompanies = ($order->status === 'preparing' && !$shipment)
          ? \App\Models\LogisticsHub::companiesServicing(auth()->user()->municipality, $order->shipping_address['municipality'] ?? null, auth()->user()->province, $order->shipping_address['province'] ?? null)
          : collect();
        $messageUrl = route('seller.messages', ['buyer'=>$order->buyer_id,'order'=>$order->id]);
        $sellerCardRefund = $order->status === 'cancelled' ? $cancellationRefunds->get($order->id) : null;
        $sellerCardProgress = str_replace('_', ' ', ucfirst($order->status));
        $sellerCardCategory = match (true) {
          (bool) $sellerCardRefund, $order->status === 'returned' => 'Return / Refund',
          in_array($order->status, \App\Models\Order::IN_TRANSIT_STATUSES, true) => 'In Transit',
          $order->status === 'placed' => 'New',
          $order->status === 'delivery_failed' => 'Failed',
          default => $sellerCardProgress,
        };
        if ($sellerCardRefund) $sellerCardProgress = $sellerCardRefund->status === 'completed' ? 'Refunded' : 'Refund pending';
        if ($order->status === 'cancelled' && !$sellerCardRefund) $sellerCardProgress = preg_replace('/^Cancelled by (?:seller|buyer):\s*/i', '', $order->cancellation_reason ?? '');
        $sellerCardStamp = $sellerCardRefund ? 'returned' : ($order->status === 'placed' ? 'new' : $order->status);
      @endphp
      <div class="order-card-s" data-row data-search="{{ $order->order_number }} {{ $customerName }}">
        <div class="order-card-s-head">
          <div class="seller-order-customer"><strong>{{ $customerName }}</strong><a href="{{ $messageUrl }}" data-seller-messages="{{ $messageUrl }}" aria-label="Message customer about this order" title="Message customer">@include('buyer.partials.icon',['name'=>'chat','size'=>16])</a></div>
          <span class="stamp order-progress-capsule stamp-{{ $sellerCardStamp }}"><span>{{ $sellerCardCategory }}</span>@if(filled($sellerCardProgress) && strcasecmp($sellerCardCategory, $sellerCardProgress) !== 0)<span class="order-progress-divider" aria-hidden="true">|</span><span class="order-progress-detail">{{ $sellerCardProgress }}</span>@endif</span>
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
                <span>{{ collect([$item['variation_group'] ?? null, $item['variation_value'] ?? $item['color'] ?? null, $item['size'] ?? null])->filter()->unique()->join(': ') }}</span>
              @endif
            </div>
            <div class="order-card-s-item-qty">@if($loop->last)<strong aria-label="Order total">₱{{ number_format($order->total,2) }}</strong>@endif<small>{{ $item['qty'] }} × ₱{{ number_format($item['price']??0,2) }}</small>@if($loop->last)<button type="button" class="btn btn-outline" data-modal="sellerOrderDetails{{ $order->id }}">View Order Details</button>@endif</div>
          </div>
          @endforeach
          @if($extraCount > 0)
          <div class="order-card-s-more">+{{ $extraCount }} more item{{ $extraCount === 1 ? '' : 's' }}</div>
          @endif
        </div>

        @if($returnRequests->has($order->id))@include('account.partials.return-case',['return'=>$returnRequests->get($order->id)])@endif
        @if($cancellationRefunds->has($order->id))@include('seller.partials.cancellation-refund',['cancellationRefund'=>$cancellationRefunds->get($order->id)])@endif
        @include('logistics.partials.rts-custody')
      </div>
      @include('seller.partials.order-details')
      @empty
      <div class="empty orders-empty">
        <div class="ic">@include('seller.partials.icon', ['name' => 'orders', 'size' => 28])</div>
        <h3>No orders found</h3>
        <p>New orders matching this category will appear here as customers check out.</p>
      </div>
      @endforelse
    </div>
  </div>
</div>
</div>

</div></div>
<div class="modal-overlay" id="logisticsPickerModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-head">
      <div><h3>Choose a Logistics Company</h3><p>Only companies that cover both your area and the buyer's area can carry this order</p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <form method="POST" id="logisticsPickerForm">
      @csrf
      @method('PATCH')
      <div class="modal-body">
        <div class="form-row" id="logisticsPickerSelectRow">
          <label for="logisticsCompanySelect">Logistics Company</label>
          <select name="logistics_company" id="logisticsCompanySelect" required></select>
        </div>
        <p id="logisticsPickerEmpty" style="display:none;color:var(--muted);font-size:13px;margin:0">
          No logistics company currently covers both your location and the buyer's location yet — check back once one adds your area as a hub.
        </p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary" id="logisticsPickerSubmit">Hand Off for Pickup</button>
      </div>
    </form>
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

<div class="modal-overlay" id="confirmPickupModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-head">
      <div><h3>Confirm Pickup</h3><p id="confirmPickupOrderLabel"></p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <form method="POST" id="confirmPickupForm">
      @csrf @method('PATCH')
      <div class="modal-body">
        <p style="margin:0;font-size:13.5px;color:var(--text)">
          <strong id="confirmPickupRiderName"></strong> has already confirmed picking up this parcel from you. Confirm the handover on your end too to finish it.
        </p>
        <p style="margin:10px 0 0;font-size:12px;color:var(--muted)">
          Only confirm once the rider actually has the parcel in hand — this can't be undone from here.
        </p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">@include('seller.partials.icon', ['name' => 'truck', 'size' => 13]) Confirm Pickup</button>
      </div>
    </form>
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
function openLogisticsPickerModal(formAction, companies) {
  document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  const form = document.getElementById('logisticsPickerForm');
  form.action = formAction;
  const select = document.getElementById('logisticsCompanySelect');
  const selectRow = document.getElementById('logisticsPickerSelectRow');
  const empty = document.getElementById('logisticsPickerEmpty');
  const submit = document.getElementById('logisticsPickerSubmit');
  select.innerHTML = '';
  if (companies.length) {
    companies.forEach(name => {
      const opt = document.createElement('option');
      opt.value = name; opt.textContent = name;
      select.appendChild(opt);
    });
    selectRow.style.display = '';
    empty.style.display = 'none';
    submit.disabled = false;
  } else {
    selectRow.style.display = 'none';
    empty.style.display = 'block';
    submit.disabled = true;
  }
  document.getElementById('logisticsPickerModal').classList.add('open');
}

document.addEventListener('DOMContentLoaded', () => {
  const waybillModal = document.getElementById('waybillModal');
  const waybillFrame = document.getElementById('waybillFrame');
  const openWaybill = window.openWaybill = url => {
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

});

function openScheduleModal(orderId, orderNumber) {
  document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  document.getElementById('scheduleOrderLabel').textContent = 'Order ' + orderNumber;
  document.getElementById('scheduleForm').action = '{{ url('/seller/orders') }}/' + orderId + '/schedule-pickup';
  document.getElementById('scheduleModal').classList.add('open');
}

function openConfirmPickupModal(formAction, orderNumber, riderName) {
  document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  document.getElementById('confirmPickupOrderLabel').textContent = 'Order ' + orderNumber;
  document.getElementById('confirmPickupRiderName').textContent = riderName || 'the assigned rider';
  document.getElementById('confirmPickupForm').action = formAction;
  document.getElementById('confirmPickupModal').classList.add('open');
}
</script>
@endpush
