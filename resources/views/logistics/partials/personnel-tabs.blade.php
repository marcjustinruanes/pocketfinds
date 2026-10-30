<nav class="tabs" aria-label="Personnel" style="margin-bottom:16px">
<a class="tab {{ request()->routeIs('logistics.riders') ? 'active' : '' }}" href="{{ route('logistics.riders') }}">Riders</a>
<a class="tab {{ request()->routeIs('logistics.staff') ? 'active' : '' }}" href="{{ route('logistics.staff') }}">Hub staff</a>
</nav>
