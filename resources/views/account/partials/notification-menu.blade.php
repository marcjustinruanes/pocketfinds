@php
 $menuUnreadCount = $panelUnreadCount ?? \Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->where('is_read',false)->count();
@endphp
<details class="pf-buyer-menu pf-notification-menu">
 <summary class="pf-acct-icon notification-trigger" aria-label="Notifications" title="Notifications">
  @include('buyer.partials.icon',['name'=>'bell','size'=>18])
  @if($menuUnreadCount)<span class="pf-buyer-dot" aria-hidden="true"></span>@endif
 </summary>
 <div class="pf-buyer-dropdown pf-buyer-notifications pf-notification-panel">
  @include('account.partials.notification-panel',['panelUnreadCount'=>$menuUnreadCount])
 </div>
</details>
