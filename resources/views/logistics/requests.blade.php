@extends('logistics.layout')
@section('title', 'Pickup Requests')
@section('page-title', 'Pickup Requests')
@section('page-sub', ($queue??'review')==='assignment'?'Approved pickups awaiting an eligible origin-hub Rider':'Incoming seller pickup requests awaiting your approval')

@section('content')
<nav class="logistics-toolbar" aria-label="Pickup queues"><a class="btn btn-outline" href="{{ route('logistics.requests') }}">Awaiting review</a><a class="btn btn-outline" href="{{ route('logistics.requests',['queue'=>'assignment']) }}">Awaiting pickup Rider</a></nav>
<div class="card">
  <div class="card-head"><h2>{{ ($queue??'review')==='assignment'?'Awaiting Pickup Rider':'Pending Requests' }}</h2><span class="stamp stamp-pending">{{ $shipments->total() }} pending</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Seller</th>
          <th>Buyer</th>
          <th>Items</th>
          <th>Order Amount</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr>
          <td class="mono"><a href="{{ route('logistics.monitor', ['search'=>$s->tracking_number ?? $s->order?->order_number]) }}">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</a></td>
          <td>{{ optional(optional($s->order)->seller)->business_name ?? optional(optional($s->order)->seller)->given_names }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->given_names ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</strong>
                <span>{{ optional(optional($s->order)->buyer)->email }}</span>
              </div>
            </div>
          </td>
          <td class="mono">{{ count(optional($s->order)->items ?? []) }} item(s)</td>
          <td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td>
          <td class="mono">{{ $s->created_at?->format('M d, Y') }}</td>
          <td>
            @php($shippingObligation = $shippingObligations[$s->id] ?? null)
            @php($shippingBlocked = (int)$s->shipping_finance_version === 1 && (!$shippingObligation || ($shippingObligation->collection_mode === 'gcash' && !in_array($shippingObligation->status, ['verified','not_required']))))
            @if($shippingObligation)<a href="{{ route('logistics.finance.shipping.show', $shippingObligation->id) }}">Shipping: {{ str_replace('_',' ',$shippingObligation->status) }}</a>@endif
            @if(($queue??'review')==='assignment')
            <form method="POST" action="{{ route('logistics.requests.assign-pickup',$s->id) }}">@csrf @method('PATCH')
            <label style="display:flex;flex-direction:column;gap:8px">Origin-hub pickup Rider<select class="select" name="courier_id" required style="min-height:44px;max-width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:8px;padding:8px"><option value="">Select eligible Rider</option>@foreach($pickupRiders as $rider)@if(app(App\Services\LogisticsAccess::class)->matches($rider->logisticsHub,$s->origin_hub,$s->origin_province))<option value="{{ $rider->id }}">{{ $rider->given_names }} {{ $rider->last_name }}</option>@endif @endforeach</select></label><button class="btn btn-primary" style="min-height:44px" @disabled($shippingBlocked)>Assign pickup Rider</button></form>
            @else
            <div class="row-actions">
              <form method="POST" action="{{ route('logistics.requests.approve', $s->id) }}">@csrf @method('PATCH')
                <button class="btn btn-sm btn-success" @disabled($shippingBlocked) @if($shippingBlocked) title="Verify the shipping obligation before pickup approval" @endif>Approve</button>
              </form>
              <form method="POST" action="{{ route('logistics.requests.reject', $s->id) }}" style="display:flex;gap:6px" onsubmit="return confirm('Reject this pickup? The order will be cancelled and the buyer and seller notified.')">@csrf @method('PATCH')
                <input type="text" name="reason" maxlength="200" placeholder="Reason (optional)" style="border:1px solid var(--border);border-radius:8px;padding:5px 8px;font-size:12px;width:150px">
                <button class="btn btn-sm btn-danger">Reject</button>
              </form>
            </div>            @endif

          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty"><h3>No parcels in this queue</h3><p>Choose another pickup queue to review other work.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $shipments->links() }}
@endsection
