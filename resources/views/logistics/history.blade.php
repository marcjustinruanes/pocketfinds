@extends('logistics.layout')
@section('title', 'Delivery History')
@section('page-title', 'Delivery History')
@section('page-sub', 'Completed, failed, returned, and cancelled delivery records')

@section('content')
<section class="delivery-history-page">
<div class="card history-filter-card">
  <div class="history-filter-heading"><span class="history-filter-icon"><x-admin-icon name="search" /></span><div><h2>Find delivery records</h2><p>Filter by tracking number, date, hub, or delivery status.</p></div></div>
  @include('logistics.partials.filters')
</div>
<div class="card history-table-card">
  <div class="card-head history-table-heading"><div><h2>Delivery Records</h2><p>Completed, failed, returned, and cancelled deliveries.</p></div><span class="history-record-count">{{ number_format($shipments->total()) }} {{ \Illuminate\Support\Str::plural('record', $shipments->total()) }}</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Courier</th>
          <th>Status</th>
          <th>Delivered At</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->given_names ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</strong>
              </div>
            </div>
          </td>
          <td>{{ optional($s->courier)->given_names ? optional($s->courier)->given_names . ' ' . optional($s->courier)->last_name : '—' }}</td>
          <td><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_',' ',$s->shipping_status)) }}</span></td>
          <td class="mono">{{ $s->delivered_at ? \Carbon\Carbon::parse($s->delivered_at)->format('M d, Y g:i A') : '—' }}</td>
          <td><button type="button" class="btn btn-sm btn-outline" data-modal-open="deliveryDetails-{{ $s->id }}">View details</button></td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty history-empty"><span class="history-empty-icon"><x-admin-icon name="box" /></span><h3>No delivery records found</h3><p>Try adjusting the filters or reset them to view all authorized records.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@foreach($shipments as $s)
<div class="modal-overlay" id="deliveryDetails-{{ $s->id }}">
  <section class="modal modal-lg logistics-history-modal" role="dialog" aria-modal="true" aria-labelledby="deliveryDetailsTitle-{{ $s->id }}">
    <header class="modal-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="truck" /></span>
        <div class="modal-head-copy">
          <h3 id="deliveryDetailsTitle-{{ $s->id }}">Delivery Details</h3>
          <p>{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</p>
        </div>
      </div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
    </header>
    <div class="modal-body">
      <div class="section-card">
        <div class="section-head"><span class="ic"><x-admin-icon name="box" /></span><span>Shipment</span></div>
        <div class="detail-grid">
          <div><div class="field-label">Tracking Number</div><div class="field-value mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</div></div>
          <div><div class="field-label">Order Number</div><div class="field-value mono">{{ $s->order?->order_number ?? '—' }}</div></div>
          <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $s->shipping_status)) }}</span></div></div>
          <div><div class="field-label">Created</div><div class="field-value">{{ $s->created_at?->format('M d, Y g:i A') ?? '—' }}</div></div>
          <div><div class="field-label">Origin Hub</div><div class="field-value">{{ $s->origin_hub ?: '—' }}{{ $s->origin_province ? ', '.$s->origin_province : '' }}</div></div>
          <div><div class="field-label">Destination Hub</div><div class="field-value">{{ $s->destination_hub ?: '—' }}{{ $s->destination_province ? ', '.$s->destination_province : '' }}</div></div>
        </div>
      </div>
      <div class="section-card">
        <div class="section-head"><span class="ic"><x-admin-icon name="users" /></span><span>People</span></div>
        <div class="detail-grid">
          <div><div class="field-label">Buyer</div><div class="field-value">{{ trim(($s->order?->buyer?->given_names ?? '').' '.($s->order?->buyer?->last_name ?? '')) ?: '—' }}</div></div>
          <div><div class="field-label">Courier</div><div class="field-value">{{ trim(($s->courier?->given_names ?? '').' '.($s->courier?->last_name ?? '')) ?: '—' }}</div></div>
        </div>
      </div>
      <div class="section-card">
        <div class="section-head"><span class="ic"><x-admin-icon name="clock" /></span><span>Delivery Timeline</span></div>
        <div class="detail-grid">
          <div><div class="field-label">Picked Up</div><div class="field-value">{{ $s->picked_up_at ? \Carbon\Carbon::parse($s->picked_up_at)->format('M d, Y g:i A') : 'Not recorded' }}</div></div>
          <div><div class="field-label">Out for Delivery</div><div class="field-value">{{ $s->out_for_delivery_at ? \Carbon\Carbon::parse($s->out_for_delivery_at)->format('M d, Y g:i A') : 'Not recorded' }}</div></div>
          <div><div class="field-label">Delivered</div><div class="field-value">{{ $s->delivered_at ? \Carbon\Carbon::parse($s->delivered_at)->format('M d, Y g:i A') : 'Not recorded' }}</div></div>
          @if($s->delivery_failed_reason)<div class="full"><div class="field-label">Delivery Issue</div><div class="field-value">{{ $s->delivery_failed_reason }}</div></div>@endif
        </div>
      </div>
    </div>
    <footer class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close>Close</button></footer>
  </section>
</div>
@endforeach
<div class="logistics-pagination">{{ $shipments->links() }}</div>
</section>
@endsection
