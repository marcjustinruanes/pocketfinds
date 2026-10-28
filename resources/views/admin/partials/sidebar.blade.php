<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="mark"><img src="{{ asset('images/logo.png') }}?v={{ filemtime(public_path('images/logo.png')) }}" alt="PocketFinds" class="brand-logo-img"></div>
    <div>
      <div class="name">PocketFinds</div>
    </div>
  </div>

  <div class="nav-groups">
<a @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="dashboard" /></span> Dashboard
    </a>
<x-navigation-group id="admin-accounts-recruitment" label="Accounts & Recruitment" :active="request()->routeIs('admin.registrations', 'admin.users')">
<a @if(request()->routeIs('admin.registrations')) aria-current="page" @endif href="{{ route('admin.registrations') }}" class="nav-item {{ request()->routeIs('admin.registrations') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="edit" /></span> Registrations
      @if($pendingRegistrations) <span class="count">{{ $pendingRegistrations }}</span> @endif
    </a>
<a @if(request()->routeIs('admin.users')) aria-current="page" @endif href="{{ route('admin.users') }}" class="nav-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="users" /></span> User Accounts
    </a>
</x-navigation-group>
<x-navigation-group id="admin-marketplace-resources" label="Marketplace & Resources" :active="request()->routeIs('admin.products', 'admin.vehicles*')">
<a @if(request()->routeIs('admin.products')) aria-current="page" @endif href="{{ route('admin.products') }}" class="nav-item {{ request()->routeIs('admin.products') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="bag" /></span> Products
    </a>
<a @if(request()->routeIs('admin.vehicles*')) aria-current="page" @endif href="{{ route('admin.vehicles') }}" class="nav-item {{ request()->routeIs('admin.vehicles*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="shield" /></span> Company Vehicles
      @if(!empty($pendingVehicles)) <span class="count">{{ $pendingVehicles }}</span> @endif
    </a>
</x-navigation-group>
<x-navigation-group id="admin-compliance" label="Compliance" :active="request()->routeIs('admin.policies', 'admin.update-requests', 'admin.complaints', 'admin.compliance')">
<a @if(request()->routeIs('admin.policies')) aria-current="page" @endif href="{{ route('admin.policies') }}" class="nav-item {{ request()->routeIs('admin.policies') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="file" /></span> Policies
      @if(!empty($pendingCompanyPolicies)) <span class="count">{{ $pendingCompanyPolicies }}</span> @endif
    </a>
<a @if(request()->routeIs('admin.update-requests')) aria-current="page" @endif href="{{ route('admin.update-requests') }}" class="nav-item {{ request()->routeIs('admin.update-requests') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="file" /></span> Update Requests
      @if(!empty($pendingDocs)) <span class="count">{{ $pendingDocs }}</span> @endif
    </a>
<a @if(request()->routeIs('admin.complaints')) aria-current="page" @endif href="{{ route('admin.complaints') }}" class="nav-item {{ request()->routeIs('admin.complaints') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="flag" /></span> Complaints & Disputes
      @if($openDisputes) <span class="count">{{ $openDisputes }}</span> @endif
    </a>
<a @if(request()->routeIs('admin.compliance')) aria-current="page" @endif href="{{ route('admin.compliance') }}" class="nav-item {{ request()->routeIs('admin.compliance') ? 'active' : '' }}"><span class="ic"><x-admin-icon name="shield" /></span> Seller Compliance</a>
</x-navigation-group>
<a class="nav-item" href="{{ route('admin.products') }}#platform-vouchers">Platform & Shipping Vouchers</a>
<a class="nav-item" href="{{ route('admin.complaints') }}#returns-refunds">Returns & Refunds</a>
<x-navigation-group id="admin-finance-reports" label="Finance & Reports" :active="request()->routeIs('admin.commission', 'admin.reports')">
<a @if(request()->routeIs('admin.commission')) aria-current="page" @endif href="{{ route('admin.commission') }}" class="nav-item {{ request()->routeIs('admin.commission') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="peso" /></span> Commission
    </a>
<a @if(request()->routeIs('admin.reports')) aria-current="page" @endif href="{{ route('admin.reports') }}" class="nav-item {{ request()->routeIs('admin.reports') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> Reports
    </a>
</x-navigation-group>
<x-navigation-group id="admin-communications" label="Communications" :active="request()->routeIs('admin.messages*', 'admin.announcements*')">
<a @if(request()->routeIs('admin.messages*')) aria-current="page" @endif href="{{ route('admin.messages') }}" class="nav-item {{ request()->routeIs('admin.messages*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="mail" /></span> Messages
      @if(!empty($unreadMessages))
      <span class="count">{{ $unreadMessages }}</span>
      @endif
    </a>
<a @if(request()->routeIs('admin.announcements*')) aria-current="page" @endif href="{{ route('admin.announcements') }}" class="nav-item {{ request()->routeIs('admin.announcements*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="megaphone" /></span> Announcements
    </a>
</x-navigation-group>
<x-navigation-group id="admin-system" label="System" :active="request()->routeIs('admin.faqs*', 'admin.ai*', 'admin.settings')">
<a @if(request()->routeIs('admin.faqs*')) aria-current="page" @endif href="{{ route('admin.faqs') }}" class="nav-item {{ request()->routeIs('admin.faqs*') ? 'active' : '' }}"><span class="ic"><x-admin-icon name="file" /></span> FAQs</a>
<a @if(request()->routeIs('admin.ai*')) aria-current="page" @endif href="{{ route('admin.ai') }}" class="nav-item {{ request()->routeIs('admin.ai*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="chart" /></span> AI Workspace
    </a>
<a @if(request()->routeIs('admin.settings')) aria-current="page" @endif href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="settings" /></span> Settings
    </a>
</x-navigation-group>
<a @if(request()->routeIs('admin.account*')) aria-current="page" @endif href="{{ route('admin.account') }}" class="nav-item {{ request()->routeIs('admin.account*') ? 'active' : '' }}">
      <span class="ic"><x-admin-icon name="account" /></span> My Account
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="sidebar-user">
      <x-user-avatar :user="auth()->user()" size="36" class="avatar" />
      <div class="who">
        <strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong>
        <span>{{ ucfirst(auth()->user()->account_type) }}</span>
      </div>
    </div>
    <button class="logout-btn" data-logout><x-admin-icon name="logout" /> Sign out</button>
  </div>
</nav>
