@php
 $workspaceKey=\App\Support\LogisticsWorkspace::current(request()->route()?->getName());
 $workspace=$workspaceKey ? \App\Support\LogisticsWorkspace::sections()[$workspaceKey] : null;
@endphp
@if($workspace && count($workspace['links'])>1)
<div class="logistics-workspace-tabs-row">
<nav class="logistics-workspace-tabs {{ $workspaceKey==='operations' ? 'logistics-operation-filters' : '' }}" aria-label="{{ $workspace['label'] }} sections">
  @foreach($workspace['links'] as [$label,$route,$patterns])
  @if($route==='logistics.requests' && auth()->user()->isHubStaff())
  <a href="{{ route('logistics.requests',['queue'=>'assignment'],false) }}" @if(request()->routeIs('logistics.requests')) aria-current="page" @endif>Pickup riders</a>
  @else
  @php($active=request()->routeIs(...$patterns) && !($route==='logistics.requests' && request()->query('queue')==='assignment'))
  <a href="{{ route($route, [], false) }}" @if($active) aria-current="page" @endif>{{ $route==='logistics.requests' ? 'Pickup review' : $label }}</a>
  @if($route==='logistics.requests')<a href="{{ route('logistics.requests',['queue'=>'assignment'],false) }}" @if(request()->routeIs('logistics.requests') && request()->query('queue')==='assignment') aria-current="page" @endif>Pickup riders</a>@endif
  @endif
  @endforeach
</nav>
@yield('workspace-actions')
</div>
@endif
