@php
$storedUnread=\Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->where('is_read',false)->count();
@endphp
<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle aria-label="Toggle navigation">
    <x-admin-icon name="menu" />
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Logistics')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <span class="ic"><x-admin-icon name="search" /></span>
    <input type="text" placeholder="Search...">
  </div>
  @php
    // Respect Settings → Notifications: a toggled-off alert never surfaces here,
    // no matter how many requests/unassigned shipments are actually waiting.
    $showRequestAlert    = ($pendingDeliveries ?? 0) > 0 && auth()->user()->notify_new_requests;
    $showUnassignedAlert = ($unassigned ?? 0) > 0 && auth()->user()->notify_unassigned_shipments;
  @endphp
  <div class="topbar-actions">
        @php
          $notificationTasks=[];
          if($showRequestAlert) $notificationTasks[]=['title'=>'Delivery requests','message'=>$pendingDeliveries.' delivery requests pending approval.','url'=>route('logistics.requests'),'notification_type'=>'pickup_request'];
          if($showUnassignedAlert) $notificationTasks[]=['title'=>'Unassigned shipments','message'=>$unassigned.' shipments awaiting assignment.','url'=>route('logistics.assignments'),'notification_type'=>'shipment_unassigned'];
        @endphp
        @include('account.partials.notification-menu', ['panelUnreadCount'=>$storedUnread, 'panelSummaries'=>$notificationTasks])

    <a href="{{ route('logistics.account') }}" aria-label="My Account">
      <x-user-avatar :user="auth()->user()" size="38" class="topbar-avatar" />
    </a>
  </div>
</header>
