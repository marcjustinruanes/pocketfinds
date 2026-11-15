@php
 $storedUnread=\Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->where('is_read',false)->count();
 $notificationTasks=[];
 if(($pendingDeliveries??0)>0 && auth()->user()->notify_new_requests) $notificationTasks[]=['title'=>'Pickup requests','message'=>$pendingDeliveries.' requests pending approval.','url'=>route(auth()->user()->isHubStaff() ? 'logistics.dashboard' : 'logistics.requests'),'notification_type'=>'pickup_request'];
 if(($unassigned??0)>0 && auth()->user()->notify_unassigned_shipments) $notificationTasks[]=['title'=>'Unassigned shipments','message'=>$unassigned.' shipments awaiting assignment.','url'=>route('logistics.assignments'),'notification_type'=>'shipment_unassigned'];
@endphp
<div class="logistics-utility-row" role="group" aria-label="Account and notifications">
  <div class="logistics-page-heading">
    <p>Welcome, {{ auth()->user()->given_names }}</p>
    <h1>{{ request()->routeIs('logistics.dashboard') && !auth()->user()->isHubStaff() ? 'Logistics overview' : trim($__env->yieldContent('page-title','My hub')) }}</h1>
  </div>
  <div class="logistics-utility-actions">
    <time class="logistics-clock" datetime="{{ now()->toIso8601String() }}" data-seller-clock data-time-zone="{{ config('app.timezone') }}"><span data-clock-time>{{ now()->format('g:i A') }}</span><span data-clock-date>{{ now()->format('M j, Y') }}</span></time>
    @include('account.partials.notification-menu',['panelUnreadCount'=>$storedUnread,'panelSummaries'=>$notificationTasks])
    <a href="{{ route('logistics.account') }}" class="logistics-profile-link" aria-label="My account" title="My account"><x-user-avatar :user="auth()->user()" size="38" /></a>
  </div>
</div>
