@extends('seller.layout')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('body-class', 'seller-notifications-page')

@section('content')
@php
  $unread = $notifications->where('is_read', false)->count();
@endphp
<section class="seller-notifications-inbox" aria-label="Notifications">
  <div class="dropdown-panel pf-buyer-notifications seller-notifications-panel">
    <div class="notif-dropdown-head">
      <div><strong>Notifications</strong><small>Recent account activity</small></div>
      @if($unread > 0)<span>{{ $unread }} new</span>@endif
    </div>
    @if($unread > 0)
    <form class="notif-mark-read" method="POST" action="{{ route('seller.notifications.read') }}">@csrf<button type="submit">Mark all as read</button></form>
    @endif
    <div class="notif-list">
      @forelse($notifications as $notification)
        @php
          $notificationStyle = \App\Support\NotificationAppearance::forType($notification->notification_type ?? '');
        @endphp
        <a href="{{ route('seller.notifications.open', $notification->id) }}" class="notif-item {{ $notification->is_read ? 'read' : '' }}">
          <span class="notif-entry-icon {{ $notificationStyle[1] }}">@include('buyer.partials.icon', ['name' => $notificationStyle[0], 'size' => 16])</span>
          <span class="notif-item-copy"><strong>{{ $notification->title ?: 'PocketFinds update' }}</strong><span>{{ $notification->message }}</span><small class="notif-item-time">{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</small></span>
          @if(! $notification->is_read)<span class="dot" aria-label="Unread"></span>@endif
        </a>
      @empty
        <p class="notif-empty">You're all caught up. New account and order updates will appear here.</p>
      @endforelse
    </div>
  </div>
</section>
@endsection
