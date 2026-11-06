@extends('seller.layout')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-sub', 'Your alerts and account updates')

@section('content')
@php
  $unread = $notifications->where('is_read', false)->count();
  $docApproved = $notifications->where('notification_type', 'doc_approved')->count();
  $docRejected = $notifications->where('notification_type', 'doc_rejected')->count();
  $otherUpdates = $notifications->count() - $docApproved - $docRejected;
@endphp
<section class="notification-center" aria-label="Notification center">
  <div class="notification-center-column">
    <div class="notification-panel">
      <div class="notification-panel-head">
        <div>
          <h2 class="notification-panel-title">All notifications</h2>
          <p class="notification-panel-caption">{{ $notifications->count() }} {{ \Illuminate\Support\Str::plural('update', $notifications->count()) }} in your account</p>
        </div>
        @if($unread)
        <form method="POST" action="{{ route('seller.notifications.read') }}">
          @csrf
          <button class="btn btn-sm btn-outline" type="submit" style="display:inline-flex;align-items:center;gap:7px">
            @include('seller.partials.icon',['name'=>'check','size'=>13]) Mark all as read
          </button>
        </form>
        @endif
      </div>
      <div class="notification-panel-body">
        @forelse($notifications as $notif)
          @php
            $isApproved = $notif->notification_type === 'doc_approved';
            $isRejected = $notif->notification_type === 'doc_rejected';
            $icon = $isApproved ? 'check-circle' : ($isRejected ? 'x' : 'bell');
            $iconState = $isApproved ? 'is-success' : ($isRejected ? 'is-danger' : '');
          @endphp
          <a href="{{ route('seller.notifications.open', $notif->id) }}" class="notification-entry {{ $notif->is_read ? 'is-read' : 'is-unread' }}">
            <span class="notification-entry-icon {{ $iconState }}">@include('seller.partials.icon',['name'=>$icon,'size'=>16])</span>
            <span class="notification-entry-copy">
              <span class="notification-entry-title">{{ $notif->title }} @if(!$notif->is_read)<span class="notification-tag">New</span>@endif</span>
              <span class="notification-entry-message">{{ $notif->message }}</span>
            </span>
            <span class="notification-entry-meta">{{ \Carbon\Carbon::parse($notif->created_at)->diffForHumans() }}</span>
          </a>
        @empty
          <div class="notification-empty">
            <span class="notification-empty-icon">@include('seller.partials.icon',['name'=>'bell','size'=>22])</span>
            <h3>You're all caught up</h3>
            <p>Important order, product, and account updates will appear here.</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>

  <aside class="notification-center-column" aria-label="Notification summary and shortcuts">
    <section class="notification-panel">
      <div class="notification-panel-head"><div><h2 class="notification-panel-title">Summary</h2><p class="notification-panel-caption">Your latest account activity</p></div></div>
      <div class="notification-panel-body">
        <div class="notification-summary-list">
          <div class="notification-summary-item">
            <span class="notification-summary-icon">@include('seller.partials.icon',['name'=>'bell','size'=>15])</span>
            <span class="notification-summary-label">Unread</span>
            <span class="notification-summary-count {{ $unread ? 'is-unread' : '' }}">{{ $unread }}</span>
          </div>
          <div class="notification-summary-item">
            <span class="notification-summary-icon is-success">@include('seller.partials.icon',['name'=>'check-circle','size'=>15])</span>
            <span class="notification-summary-label">Documents approved</span>
            <span class="notification-summary-count">{{ $docApproved }}</span>
          </div>
          <div class="notification-summary-item">
            <span class="notification-summary-icon is-danger">@include('seller.partials.icon',['name'=>'x','size'=>15])</span>
            <span class="notification-summary-label">Documents declined</span>
            <span class="notification-summary-count">{{ $docRejected }}</span>
          </div>
          <div class="notification-summary-item">
            <span class="notification-summary-icon">@include('seller.partials.icon',['name'=>'bell','size'=>15])</span>
            <span class="notification-summary-label">Other updates</span>
            <span class="notification-summary-count">{{ $otherUpdates }}</span>
          </div>
        </div>
      </div>
    </section>
    <section class="notification-panel">
      <div class="notification-panel-head"><div><h2 class="notification-panel-title">Quick actions</h2><p class="notification-panel-caption">Jump to the tools you use most</p></div></div>
      <div class="notification-panel-body">
        <div class="notification-actions">
          <a href="{{ route('seller.orders') }}" class="btn btn-primary">@include('seller.partials.icon',['name'=>'orders','size'=>15]) View orders</a>
          <a href="{{ route('seller.account') }}" class="btn btn-outline">@include('seller.partials.icon',['name'=>'shield','size'=>15]) Manage documents</a>
        </div>
      </div>
    </section>
  </aside>
</section>
@endsection
