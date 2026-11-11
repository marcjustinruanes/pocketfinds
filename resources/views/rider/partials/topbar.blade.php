@php
$storedUnread=\Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->where('is_read',false)->count();
@endphp
<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle aria-label="Toggle navigation">
    <x-admin-icon name="menu" />
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Courier')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-actions">
    <form method="POST" action="{{ route('rider.availability') }}" style="display:flex;align-items:center;gap:7px;margin-right:6px">
      @csrf @method('PATCH')
      <input type="hidden" name="is_available" value="{{ auth()->user()->is_available ? 0 : 1 }}">
      <button class="btn btn-sm {{ auth()->user()->is_available ? 'btn-primary' : 'btn-outline' }}" type="submit">{{ auth()->user()->is_available ? '● Online' : '○ Offline' }}</button>
    </form>
        @php
          $notificationTasks=[];
          if(($availableRequests??0)>0) $notificationTasks[]=['title'=>'Pickup requests','message'=>$availableRequests.' pickup requests available.','url'=>route('rider.requests'),'notification_type'=>'pickup_request'];
          if(($activeDeliveries??0)>0) $notificationTasks[]=['title'=>'Active deliveries','message'=>$activeDeliveries.' deliveries in progress.','url'=>route('rider.deliveries'),'notification_type'=>'delivery_assigned'];
        @endphp
        @include('account.partials.notification-menu', ['panelUnreadCount'=>$storedUnread, 'panelSummaries'=>$notificationTasks])

    <a href="{{ route('rider.account') }}" class="topbar-avatar" aria-label="My Account">
      @if(auth()->user()->profile_picture)
      <img src="{{ \Illuminate\Support\Facades\Storage::disk('profile_images')->url(auth()->user()->profile_picture) }}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
      @else
      {{ strtoupper(substr(auth()->user()->given_names, 0, 1)) }}
      @endif
    </a>
  </div>
</header>
