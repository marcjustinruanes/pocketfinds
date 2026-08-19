@extends('logistics.layout')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-sub', 'System alerts and updates')

@section('content')
<div class="card card-pad">
  @if($notifications->isEmpty())
  <div class="empty">
    <div class="ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="28" height="28"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </div>
    <h3>No notifications</h3>
    <p>You're all caught up.</p>
  </div>
  @else
  <div class="notif-list" style="max-height:none">
    @foreach($notifications as $n)
    <div class="notif-item {{ $n->is_read ? 'read' : '' }}">
      <div class="dot"></div>
      <div>
        <p style="font-weight:600">{{ $n->title }}</p>
        <p>{{ $n->message }}</p>
        <time>{{ \Carbon\Carbon::parse($n->created_at)->format('M d, Y H:i') }}</time>
      </div>
    </div>
    @endforeach
  </div>
  @endif
</div>
@endsection
