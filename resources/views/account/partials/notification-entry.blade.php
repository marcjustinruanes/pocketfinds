@php
 $entryStyle = \App\Support\NotificationAppearance::forType(data_get($entry, 'notification_type'));
 $entryRead = (bool) data_get($entry, 'is_read', false);
 $entryTime = data_get($entry, 'created_at');
@endphp
<a href="{{ $entryUrl }}" class="notif-item {{ $entryRead ? 'read' : '' }}">
 <span class="notif-entry-icon {{ $entryStyle[1] }}">@include('buyer.partials.icon', ['name'=>$entryStyle[0], 'size'=>16])</span>
 <span class="notif-item-copy"><strong>{{ data_get($entry, 'title') ?: 'PocketFinds update' }}</strong><span>{{ data_get($entry, 'message') }}</span>@if($entryTime)<small class="notif-item-time">{{ \Illuminate\Support\Carbon::parse($entryTime)->diffForHumans() }}</small>@endif</span>
 @if(!$entryRead)<span class="dot" aria-label="Unread"></span>@endif
</a>
