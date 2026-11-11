@php
$storedUnread=\Illuminate\Support\Facades\DB::table('notifications')->where('user_id',auth()->id())->where('is_read',false)->count();
@endphp
<header class="topbar">
  <button class="menu-toggle" data-sidebar-toggle aria-label="Toggle navigation">
    <x-admin-icon name="menu" />
  </button>
  <div class="page-heading">
    <h1>@yield('page-title', 'Dashboard')</h1>
    <p>@yield('page-sub', '')</p>
  </div>
  <div class="topbar-search">
    <span class="ic"><x-admin-icon name="search" /></span>
    <input type="text" placeholder="Search...">
  </div>
  <div class="topbar-actions">
    @php
      $notifItems = [];
      if (($pendingRegistrations ?? 0) > 0)
          $notifItems[] = ['title'=>'Registrations', 'type'=>'registration', 'text' => $pendingRegistrations . ' registration' . ($pendingRegistrations > 1 ? 's' : '') . ' pending review.', 'link' => route('admin.registrations'), 'read' => false];
      if (($openDisputes ?? 0) > 0)
          $notifItems[] = ['title'=>'Disputes', 'type'=>'dispute', 'text' => $openDisputes . ' open dispute' . ($openDisputes > 1 ? 's' : '') . ' need attention.', 'link' => route('admin.complaints'), 'read' => false];
      if (($pendingProducts ?? 0) > 0)
          $notifItems[] = ['title'=>'Product approvals', 'type'=>'product_pending', 'text' => $pendingProducts . ' product' . ($pendingProducts > 1 ? 's' : '') . ' pending approval.', 'link' => route('admin.products'), 'read' => false];
    @endphp

    @include('account.partials.notification-menu', ['panelUnreadCount'=>$storedUnread, 'panelSummaries'=>array_map(fn($item)=>['title'=>$item['title'], 'message'=>$item['text'], 'url'=>$item['link'], 'notification_type'=>$item['type']], array_filter($notifItems, fn($item)=>!$item['read']))])

    <a href="{{ route('admin.account') }}" class="topbar-avatar" aria-label="My Account">
      <x-user-avatar :user="auth()->user()" size="38" />
    </a>
  </div>
</header>
