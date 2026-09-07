@extends('logistics.layout')
@section('title', 'Delivery Issues')
@section('page-title', 'Delivery Issues')
@section('page-sub', 'Failed and cancelled deliveries requiring attention')

@section('content')
<div class="card">
  <div class="card-head"><h2>Problem Deliveries</h2><span class="stamp stamp-rejected">{{ $shipments->count() }} issues</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Courier</th>
          <th>Issue</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $s)
        <tr class="rail-row rail-{{ $s->shipping_status }}">
          <td class="mono">{{ $s->tracking_number ?? substr($s->id, 0, 8) }}</td>
          <td>
            <div class="cell-user">
              <div class="avatar-sm">{{ strtoupper(substr(optional(optional($s->order)->buyer)->given_names ?? '?', 0, 1)) }}</div>
              <div>
                <strong>{{ optional(optional($s->order)->buyer)->given_names }} {{ optional(optional($s->order)->buyer)->last_name }}</strong>
                <span>{{ optional(optional($s->order)->buyer)->email }}</span>
              </div>
            </div>
          </td>
          <td>{{ optional($s->courier)->given_names ? optional($s->courier)->given_names . ' ' . optional($s->courier)->last_name : '—' }}</td>
          <td>
            <span class="stamp stamp-{{ $s->shipping_status }}">{{ ucfirst(str_replace('_', ' ', $s->shipping_status)) }}</span>
            @if($s->delivery_failed_reason)<div style="font-size:11px;color:var(--muted);margin-top:3px">{{ $s->delivery_failed_reason }}</div>@endif
          </td>
          <td class="mono">{{ $s->created_at?->format('M d, Y') }}</td>
          <td>
            @if($s->shipping_status === 'delivery_failed')
            <div class="row-actions">
              <form method="POST" action="{{ route('logistics.status.update', $s->id) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="assigned_to_rider">
                <button class="btn btn-sm btn-outline">Retry Delivery</button>
              </form>
              <form method="POST" action="{{ route('logistics.status.update', $s->id) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="returned">
                <button class="btn btn-sm btn-outline">Return to Seller</button>
              </form>
            </div>
            @else
            <span style="color:var(--muted);font-size:12px">No action needed</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty"><h3>No issues found</h3><p>All deliveries are running smoothly.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
