@php
  $accountNotifications=\Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->orderByDesc('created_at')->limit(8)->get();
  $accountUnreadCount=$accountNotifications->where('is_read',false)->count();
@endphp
@if($accountNotifications->isNotEmpty())
@if($accountUnreadCount)
<form method="POST" action="{{ route('account.notifications.read-all') }}" style="padding:8px">@csrf<button class="btn btn-outline" type="submit">Mark all as read</button></form>
@endif
@foreach($accountNotifications as $accountNotification)
<a class="notif-item {{ $accountNotification->is_read?'read':'' }}" href="{{ route('account.notifications.open',$accountNotification->id) }}"><div class="dot"></div><div><p>{{ $accountNotification->message }}</p><small>{{ \Illuminate\Support\Carbon::parse($accountNotification->created_at)->diffForHumans() }}</small></div></a>
@endforeach
@endif
