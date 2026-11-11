@php
 $panelRows = $panelNotifications ?? \Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->orderByDesc('created_at')->limit(8)->get();
 $panelCount = $panelUnreadCount ?? \Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->where('is_read',false)->count();
 $panelTasks = $panelSummaries ?? [];
 $panelRoute = $panelOpenRoute ?? 'account.notifications.open';
@endphp
<div class="notif-dropdown-head"><div><strong>Notifications</strong><small>Recent account activity</small></div>@if($panelCount)<span>{{ $panelCount }} new</span>@endif</div>
@if($panelCount)
<form class="notif-mark-read" method="POST" action="{{ route('account.notifications.read-all') }}">@csrf<button type="submit">Mark all as read</button></form>
@endif
<div class="notif-list">
 @foreach($panelRows as $panelEntry)
  @include('account.partials.notification-entry',['entry'=>$panelEntry,'entryUrl'=>route($panelRoute,$panelEntry->id)])
 @endforeach
 @foreach($panelTasks as $panelTask)
  @include('account.partials.notification-entry',['entry'=>array_merge($panelTask,['is_read'=>true]),'entryUrl'=>$panelTask['url']])
 @endforeach
 @if($panelRows->isEmpty() && empty($panelTasks))<p class="notif-empty">You're all caught up. New account and order updates will appear here.</p>@endif
</div>
@if(!empty($panelFooterUrl))<div class="notif-footer"><a href="{{ $panelFooterUrl }}">{{ $panelFooterLabel ?? 'View all notifications' }}</a></div>@endif
