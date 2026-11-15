@php
 $currentWorkspace=\App\Support\LogisticsWorkspace::current(request()->route()?->getName());
 $workspaceCounts=['operations'=>($pendingDeliveries??0)+($unassigned??0)+($pendingHubTransferRequests??0),'team'=>($pendingRiders??0)+($pendingStaff??0),'resources'=>$pendingVehicles??0];
 if(auth()->user()->isHubStaff()) $workspaceCounts=['operations'=>$unassigned??0];
@endphp
<nav class="sidebar" id="sidebar" aria-label="Logistics navigation">
  <div class="sidebar-brand" role="button" tabindex="0" data-sidebar-toggle aria-label="Toggle navigation" aria-expanded="false" aria-controls="sidebar">
    <div class="mark"><img src="{{ asset('images/logo.png') }}" alt="" class="brand-logo-img"></div>
    <div class="logistics-brand-copy"><div class="name">Pocket<span>Finds</span></div><span class="tag">{{ auth()->user()->isHubStaff() ? 'Hub portal' : 'Logistics portal' }}</span></div>
  </div>
  <div class="nav-groups">
    @foreach(\App\Support\LogisticsWorkspace::sections() as $key=>$section)
    <a href="{{ route($section['links'][0][1]) }}" class="nav-item {{ $currentWorkspace===$key?'active':'' }}" title="{{ $section['label'] }}" aria-label="{{ $section['label'] }}" @if($currentWorkspace===$key) aria-current="page" @endif>
      <span class="ic">@if($key==='overview')@include('seller.partials.icon',['name'=>'nav-dashboard','size'=>22])
      @else
      <x-admin-icon :name="$section['icon']" />
      @endif
      @if(($workspaceCounts[$key]??0)>0)<span class="logistics-nav-count">{{ $workspaceCounts[$key] }}</span>@endif</span><span class="logistics-nav-label">{{ $section['label'] }}</span>
    </a>
    @endforeach
  </div>
  <div class="sidebar-foot">
    <div class="sidebar-user"><x-user-avatar :user="auth()->user()" size="36" class="avatar" /><div class="who"><strong>{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</strong><span>{{ auth()->user()->isHubStaff() ? (auth()->user()->logisticsHub?->municipality ?? 'My').' Hub' : 'Logistics admin' }}</span></div></div>
    <button type="button" class="logout-btn" data-logout title="Sign out" aria-label="Sign out"><x-admin-icon name="logout" /><span>Sign out</span></button>
  </div>
</nav>
