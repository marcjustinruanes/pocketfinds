@hasSection('workspace-search')
<div class="platform-tabs-search-row">
@endif
@foreach(\App\Support\PlatformWorkspace::sections() as $section)
  @if(\App\Support\PlatformWorkspace::active($section) && count($section['items']) > 1)
    <nav class="platform-section-tabs" aria-label="{{ $section['label'] }} sections">
      @foreach($section['items'] as $item)
        <a href="{{ route($item[1], [], false) }}" class="{{ request()->routeIs(...$item[2]) ? 'active' : '' }}" @if(request()->routeIs(...$item[2])) aria-current="page" @endif>{{ $item[0] }}</a>
      @endforeach
    </nav>
  @endif
@endforeach

@hasSection('workspace-search')
  @yield('workspace-search')
</div>
@endif
