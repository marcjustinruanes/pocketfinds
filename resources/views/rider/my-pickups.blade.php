@extends('rider.layout')
@section('title', 'My Pickups')
@section('page-title', 'My Pickups')
@section('page-sub', 'Accepted — proceed to the seller, hand it over, and both of you confirm the pickup')

@section('content')
@if(session('success'))<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>@endif
@if($errors->any())<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first() }}</div>@endif

<div class="card">
  <div class="card-head"><h2>My Pickups</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Seller</th>
          <th>Seller Location</th>
          <th>Seller Contact</th>
          <th>Order Amount</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        @php($seller = optional($s->order)->seller)
        <tr>
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr($seller?->given_names ?? '?', 0, 1)) }}</div>
              <div><strong>{{ $seller?->business_name ?? $seller?->given_names }}</strong></div>
            </div>
          </td>
          <td style="font-size:12px;max-width:220px">{{ collect([$seller?->house_no, $seller?->street, $seller?->barangay, $seller?->municipality, $seller?->province])->filter()->implode(', ') ?: '—' }}</td>
          <td class="mono">{{ $seller?->contact_no ?: '—' }}</td>
          <td class="mono">₱{{ number_format(optional($s->order)->total ?? 0, 2) }}</td>
          <td>
            @if($s->shipping_status === 'picked_up')
              <span class="stamp stamp-active">Confirmed — bring to hub</span>
            @elseif($s->rider_confirmed_pickup_at)
              <span class="stamp stamp-pending">Waiting on seller to confirm</span>
            @else
              <span class="stamp stamp-pending">Confirm once you have it</span>
            @endif
          </td>
          <td>
            @if($s->shipping_status === 'ready_for_pickup' && !$s->rider_confirmed_pickup_at)
            <form method="POST" action="{{ route('rider.my-pickups.confirm', $s->id) }}" onsubmit="return confirm('Confirm you received this parcel from the seller?')">
              @csrf @method('PATCH')
              <button type="submit" class="btn btn-sm btn-primary">Confirm Receipt</button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="empty"><h3>No pickups in progress</h3><p>Accept a pickup request to see it here — once you arrive, confirm you received the parcel and the seller confirms handing it over.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
