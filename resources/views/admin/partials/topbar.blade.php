@php
$storedUnread=\Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->where('is_read',false)->count();
@endphp
<header class="topbar">
  <div class="page-heading"><p>Welcome, {{ auth()->user()->given_names }}</p><h1>@yield('page-title', 'Platform overview')</h1></div>
  <div class="topbar-actions">
    <time class="platform-clock" datetime="{{ now()->toIso8601String() }}"><span>{{ now()->format('g:i A') }}</span><span>{{ now()->format('M j, Y') }}</span></time>
    @php
      $notifItems = [];
      if (($pendingRegistrations ?? 0) > 0)
          $notifItems[] = ['title'=>'Registrations', 'type'=>'registration', 'text' => $pendingRegistrations . ' registration' . ($pendingRegistrations > 1 ? 's' : '') . ' pending review.', 'link' => route('admin.registrations'), 'read' => false];
      if (($openDisputes ?? 0) > 0)
          $notifItems[] = ['title'=>'Disputes', 'type'=>'dispute', 'text' => $openDisputes . ' open dispute' . ($openDisputes > 1 ? 's' : '') . ' need attention.', 'link' => route('admin.complaints'), 'read' => false];
      if (($pendingProducts ?? 0) > 0)
          $notifItems[] = ['title'=>'Product approvals', 'type'=>'product_pending', 'text' => $pendingProducts . ' product' . ($pendingProducts > 1 ? 's' : '') . ' pending approval.', 'link' => route('admin.products'), 'read' => false];
    @endphp

    <a href="{{ route('admin.announcements') }}" class="platform-announcements-link" aria-label="Announcements" title="Announcements"><x-admin-icon name="megaphone" /></a>
    @include('account.partials.notification-menu', ['panelUnreadCount'=>$storedUnread, 'panelSummaries'=>array_map(fn($item)=>['title'=>$item['title'], 'message'=>$item['text'], 'url'=>$item['link'], 'notification_type'=>$item['type']], array_filter($notifItems, fn($item)=>!$item['read']))])

    <a href="{{ route('admin.account') }}" class="topbar-avatar" aria-label="My Account">
      <x-user-avatar :user="auth()->user()" size="38" />
    </a>
  </div>
</header>
