<form method="GET" class="logistics-toolbar">
<input name="search" value="{{ request('search') }}" placeholder="Tracking or order number" aria-label="Tracking or order number">
<label>Created from<input type="date" name="from" value="{{ request('from') }}"></label><label>Created to<input type="date" name="to" value="{{ request('to') }}"></label>
<select name="hub_id" aria-label="Municipal hub"><option value="">All authorized hubs</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((string)request('hub_id') === (string)$hub->id)>{{ $hub->municipality }}, {{ $hub->province }}</option>@endforeach</select>
<select name="status" aria-label="Delivery status"><option value="">All statuses</option>@foreach(['delivered','completed','delivery_failed','returned','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select>
<button class="btn btn-outline">Filter</button><a class="btn btn-outline" href="{{ url()->current() }}">Reset</a>
@if(request()->routeIs('logistics.reports'))<a class="btn btn-primary" href="{{ route('logistics.reports.export',request()->query()) }}">Export CSV</a>@endif
</form>
