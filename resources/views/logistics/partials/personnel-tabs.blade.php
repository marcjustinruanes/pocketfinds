@if(request()->routeIs('logistics.staff', 'logistics.attendance*'))
<nav class="team-staff-tabs" aria-label="Hub staff views">
<a class="{{ request()->routeIs('logistics.staff') ? 'active' : '' }}" href="{{ route('logistics.staff') }}">Personnel</a>
<a class="{{ request()->routeIs('logistics.attendance*') ? 'active' : '' }}" href="{{ route('logistics.attendance') }}">Attendance</a>
</nav>
@endif
