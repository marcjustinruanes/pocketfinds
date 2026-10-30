@extends('logistics.layout')
@section('title', 'Monitor Deliveries')
@section('page-title', 'Monitor Deliveries')
@section('page-sub', 'Track and manage ongoing delivery statuses')

@section('content')
<section class="card card-pad" id="parcel-lookup">
  <h2>Find a parcel</h2>
  <p>Enter a tracking or order number. Search includes historical parcels within your permitted scope. Hub Staff records physical receipt; Riders confirm their assigned work.</p>
  <form method="GET" action="{{ route('logistics.monitor') }}" class="parcel-lookup-form">
    <label for="monitor-search">Tracking or order number<input class="input" id="monitor-search" name="search" maxlength="100" value="{{ $search }}" placeholder="Enter tracking or order number"></label>
    @if(request('stage'))<input type="hidden" name="stage" value="{{ request('stage') }}">@endif
    <button class="btn btn-primary">Find parcel</button><a class="btn" href="{{ route('logistics.monitor') }}">Clear</a>
  </form>
  @if($search !== '')<p role="status">{{ $shipments->total() }} matching parcels. @if(request('stage'))The selected stage filter also applies.@endif</p>@endif
</section>
<div class="card card-pad"><p>Hub Staff confirm physical hub arrivals. The assigned rider confirms delivery. This page provides oversight and the remaining operational actions.</p></div>
@if($errors->has('status'))
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ $errors->first('status') }}</div>
@endif
<div class="card">
  <div class="card-head"><h2>{{ $search !== '' ? 'Parcel lookup results' : 'Active Deliveries' }}</h2></div>
  <div class="table-wrap">
    <table class="dtable">
      <thead>
        <tr>
          <th>Tracking #</th>
          <th>Buyer</th>
          <th>Courier</th>
          <th>Current Status</th>
          <th>Last Update</th>
          <th>Update Status</th>
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
          <td class="mono" style="font-size:11.5px">{{ $s->updated_at?->format('M d, Y H:i') ?? '—' }}</td>
          <td>
            @php($allowedNext = array_values(array_diff($transitions[$s->shipping_status] ?? [], ['returned','out_for_delivery'])))
            @if(count($allowedNext))
            <form method="POST" action="{{ route('logistics.status.update', $s->id) }}" style="display:flex;gap:8px">
              @csrf @method('PATCH')
              <select name="status" class="select">
                @foreach($allowedNext as $st)
                <option value="{{ $st }}">{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                @endforeach
              </select>
              <label>Reason<input name="reason" minlength="3" maxlength="500" required style="min-height:44px;max-width:180px"></label><button class="btn btn-sm btn-primary">Update</button>
            </form>
            @elseif($s->shipping_status === 'sorted')
            <a href="{{ route('logistics.assignments') }}" style="color:var(--pink-dark);font-size:12px;font-weight:600">Assign a courier →</a>
            @elseif($s->shipping_status === 'ready_for_pickup')
            @if(auth()->user()->isLogisticsAdmin())
            <a href="{{ route('logistics.requests', ['queue'=>$s->pickup_approved_at ? 'assignment' : 'review']) }}" style="color:var(--pink-dark);font-size:12px;font-weight:600">{{ $s->pickup_approved_at ? 'View pickup assignment' : 'Review pickup request' }} →</a>
            @else<span>Pickup approval is managed by Logistics Admin.</span>@endif
            @else
            <span style="color:var(--muted);font-size:12px">No action needed</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="empty"><h3>{{ $search !== '' ? 'No matching parcels' : 'No active deliveries' }}</h3><p>{{ $search !== '' ? 'Check the tracking or order number, or clear the filters.' : 'Deliveries in progress will appear here.' }}</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $shipments->links() }}
@endsection
@push('head')
<style>
.parcel-lookup-form{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.parcel-lookup-form label{display:grid;gap:8px;flex:1 1 260px;min-width:0}.parcel-lookup-form input{width:100%;min-height:44px;font:inherit;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--paper);color:var(--text)}.parcel-lookup-form .btn{min-height:44px;display:inline-flex;align-items:center;justify-content:center}#parcel-lookup{scroll-margin-top:90px;margin-bottom:20px}#parcel-lookup h2{margin-top:0}#parcel-lookup p{color:var(--muted);line-height:1.6}
@media(max-width:600px){.parcel-lookup-form label{flex-basis:100%}.parcel-lookup-form .btn{flex:1}}
</style>
@endpush
