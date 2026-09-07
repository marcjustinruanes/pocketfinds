@extends('logistics.layout')
@section('title', 'Hub Transfer Requests')
@section('page-title', 'Hub Transfer Requests')
@section('page-sub', "Batches your hubs want to move on — approve before a rider can be assigned")

@section('content')
@if(session('success'))<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('success') }}</div>@endif
@if($errors->any())<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first() }}</div>@endif

<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <h2>Not Yet Requested</h2>
    <span class="stamp stamp-pending">{{ $notYetRequested->count() }} batch(es)</span>
  </div>
  <div class="card-pad" style="padding-bottom:4px">
    <p style="margin:0 0 4px;font-size:12.5px;color:var(--muted)">Waiting on the origin hub's own staff to request these — you can push any of them through yourself instead, same or different province.</p>
  </div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr><th>Route</th><th>Parcels</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($notYetRequested as $key => $legs)
        @php([$fromHub, $toHub] = explode('|', $key, 2))
        @php($first = $legs->first())
        <tr>
          <td><strong>{{ $fromHub }}</strong> &rarr; <strong>{{ $toHub }}</strong><br><span style="color:var(--muted);font-size:11px">{{ ucfirst($first->leg_type) }} leg</span></td>
          <td class="mono">{{ $legs->count() }} parcel(s)</td>
          <td>
            <form method="POST" action="{{ route('logistics.hub-requests.admin-request') }}">
              @csrf @method('PATCH')
              <input type="hidden" name="from_hub" value="{{ $fromHub }}">
              <input type="hidden" name="to_hub" value="{{ $toHub }}">
              <button class="btn btn-sm btn-primary">Request &amp; Approve</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="3"><div class="empty"><h3>Nothing waiting</h3><p>Every hub has already requested what's sitting at it.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Pending Requests</h2><span class="stamp stamp-pending">{{ $pendingTransferRequests->count() }} batch(es)</span></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Route</th>
          <th>Parcels</th>
          <th>Requested By</th>
          <th>Requested</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($pendingTransferRequests as $key => $legs)
        @php([$fromHub, $toHub] = explode('|', $key, 2))
        @php($first = $legs->first())
        <tr>
          <td><strong>{{ $fromHub }}</strong> &rarr; <strong>{{ $toHub }}</strong><br><span style="color:var(--muted);font-size:11px">{{ ucfirst($first->leg_type) }} leg</span></td>
          <td class="mono">{{ $legs->count() }} parcel(s)</td>
          <td>{{ optional($first->requestedBy)->given_names }} {{ optional($first->requestedBy)->last_name }}</td>
          <td class="mono">{{ optional($first->requested_at)?->format('M d, Y H:i') }}</td>
          <td>
            <div class="row-actions">
              <form method="POST" action="{{ route('logistics.hub-requests.approve') }}">
                @csrf @method('PATCH')
                <input type="hidden" name="from_hub" value="{{ $fromHub }}">
                <input type="hidden" name="to_hub" value="{{ $toHub }}">
                <button class="btn btn-sm btn-success">Approve All</button>
              </form>
              <form method="POST" action="{{ route('logistics.hub-requests.reject') }}" onsubmit="return confirm('Send these back to the hub to re-request?')">
                @csrf @method('PATCH')
                <input type="hidden" name="from_hub" value="{{ $fromHub }}">
                <input type="hidden" name="to_hub" value="{{ $toHub }}">
                <button class="btn btn-sm btn-outline">Send Back</button>
              </form>
            </div>
          </td>
        </tr>
        <tr>
          <td colspan="5" style="padding-top:0">
            <div style="display:flex;flex-wrap:wrap;gap:6px;font-size:11.5px;color:var(--muted)">
              @foreach($legs as $leg)
                <span class="mono">#{{ optional($leg->shipment->order)->order_number ?? substr($leg->shipment_id, 0, 8) }}</span>
              @endforeach
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="empty"><h3>No pending requests</h3><p>Every hub's transfer requests have been handled.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
