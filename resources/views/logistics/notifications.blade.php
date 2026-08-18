@extends('logistics.layout')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-sub', 'Delivery alerts and system notifications')

@section('content')
<div class="card">
  <div class="card-head"><div><h2>All Notifications</h2><p>{{ $notifications->count() }} total</p></div></div>
  <div class="card-pad">
    @forelse($notifications as $n)
    <div class="activity-item">
      <div class="ic-badge" style="{{ $n->is_read ? 'background:var(--neutral-soft);color:var(--neutral)' : '' }}">🔔</div>
      <div style="flex:1">
        <p style="margin:0;font-size:13px;font-weight:{{ $n->is_read ? '400' : '600' }}">{{ $n->title }}</p>
        <p style="margin:2px 0 0;font-size:12px;color:var(--muted)">{{ $n->message }}</p>
        <time style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">{{ $n->created_at?->diffForHumans() }}</time>
      </div>
      @if(!$n->is_read)<span class="stamp stamp-pending" style="flex:none">New</span>@endif
    </div>
    @empty
    <div class="empty"><div class="ic">🔔</div><h3>No notifications</h3><p>You're all caught up.</p></div>
    @endforelse
  </div>
</div>
@endsection
