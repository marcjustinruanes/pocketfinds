@php
 $workspaceKey=\App\Support\LogisticsWorkspace::current(request()->route()?->getName());
 $workspace=$workspaceKey ? \App\Support\LogisticsWorkspace::sections()[$workspaceKey] : null;
@endphp
@if($workspace && count($workspace['links'])>1)
<nav class="logistics-workspace-tabs {{ $workspaceKey==='operations' ? 'logistics-operation-filters' : '' }}" aria-label="{{ $workspace['label'] }} sections">
  @foreach($workspace['links'] as [$label,$route,$patterns])
  @php($active=request()->routeIs(...$patterns) && !($route==='logistics.requests' && request()->query('queue')==='assignment') && !($route==='logistics.assignments' && request()->routeIs('logistics.assignments.review*')))
  <a href="{{ route($route) }}" @if($active) aria-current="page" @endif>{{ $route==='logistics.requests' ? 'Pickup review' : ($route==='logistics.assignments' ? 'Delivery riders' : $label) }}</a>
  @if($route==='logistics.requests')<a href="{{ route('logistics.requests',['queue'=>'assignment']) }}" @if(request()->routeIs('logistics.requests') && request()->query('queue')==='assignment') aria-current="page" @endif>Pickup riders</a>@endif
  @if($route==='logistics.assignments' && app(\App\Services\AssignmentReassignment::class)->installed())<a href="{{ route('logistics.assignments.review') }}" @if(request()->routeIs('logistics.assignments.review*')) aria-current="page" @endif>Assignment review</a>@endif
  @endforeach
</nav>
@endif
