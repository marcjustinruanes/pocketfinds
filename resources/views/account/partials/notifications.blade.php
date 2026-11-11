@php
  $accountNotifications=\Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->orderByDesc('created_at')->limit(8)->get();
  $accountUnreadCount=$accountNotifications->where('is_read',false)->count();
@endphp
@if($accountNotifications->isNotEmpty())
@if($accountUnreadCount)
<form class="notif-mark-read" method="POST" action="{{ route('account.notifications.read-all') }}">@csrf<button type="submit">Mark all as read</button></form>
@endif
@foreach($accountNotifications as $accountNotification)
@include('account.partials.notification-entry',['entry'=>$accountNotification,'entryUrl'=>route('account.notifications.open',$accountNotification->id)])
@endforeach
@endif
