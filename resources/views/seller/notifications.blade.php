@extends('seller.layout')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-sub', 'Your alerts and account updates')

@section('content')
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head">
        <div><h2>All Notifications</h2><p>{{ $notifications->count() }} total</p></div>
        @if($notifications->where('is_read', false)->count())
          <form method="POST" action="{{ route('seller.notifications.read') }}">
            @csrf
            <button class="btn btn-sm btn-outline" type="submit" style="display:inline-flex;align-items:center;gap:6px">
              @include('seller.partials.icon',['name'=>'check','size'=>13]) Mark all read
            </button>
          </form>
        @endif
      </div>
      <div class="card-pad">
        @forelse($notifications as $notif)
          @php
            $isDoc = in_array($notif->notification_type, ['doc_approved','doc_rejected']);
            $icon  = $notif->notification_type === 'doc_approved' ? 'check-circle'
                   : ($notif->notification_type === 'doc_rejected' ? 'x' : 'bell');
            $color = $notif->notification_type === 'doc_approved' ? 'var(--success)'
                   : ($notif->notification_type === 'doc_rejected' ? 'var(--danger)' : 'var(--pink)');
          @endphp
          <div class="notif-row">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--paper);border:1px solid var(--border);display:grid;place-items:center;flex:none;color:{{ $color }}">
              @include('seller.partials.icon',['name'=>$icon,'size'=>15])
            </div>
            <div class="notif-body">
              <div class="notif-title" style="{{ $notif->is_read ? 'font-weight:500' : '' }}">{{ $notif->title }}</div>
              <div class="notif-sub">{{ $notif->message }}</div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex:none">
              <div class="notif-time">{{ \Carbon\Carbon::parse($notif->created_at)->diffForHumans() }}</div>
              @if(!$notif->is_read)
                <div style="width:7px;height:7px;border-radius:50%;background:var(--pink)"></div>
              @endif
            </div>
          </div>
        @empty
          <div class="empty">
            @include('seller.partials.icon',['name'=>'bell','size'=>32,'class'=>'ic'])
            <h3>No notifications yet</h3>
            <p>You'll be notified here about orders and account updates.</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Summary</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        @php
          $unread  = $notifications->where('is_read', false)->count();
          $docAppr = $notifications->where('notification_type','doc_approved')->count();
          $docRej  = $notifications->where('notification_type','doc_rejected')->count();
        @endphp
        @foreach([
          ['bell',        'Unread',           $unread,  'stamp-new'],
          ['check-circle','Doc Approved',      $docAppr, 'stamp-approved'],
          ['x',           'Doc Rejected',      $docRej,  'stamp-rejected'],
        ] as [$icon,$label,$count,$stamp])
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:9px">
          <span style="color:var(--pink-dark)">@include('seller.partials.icon',['name'=>$icon,'size'=>18])</span>
          <span style="font-size:13px;font-weight:600;flex:1">{{ $label }}</span>
          <span class="stamp {{ $stamp }}">{{ $count }}</span>
        </div>
        @endforeach
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('seller.orders') }}" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:7px">
          @include('seller.partials.icon',['name'=>'orders','size'=>15]) View All Orders
        </a>
        <a href="{{ route('seller.account') }}" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:7px">
          @include('seller.partials.icon',['name'=>'shield','size'=>15]) My Documents
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
