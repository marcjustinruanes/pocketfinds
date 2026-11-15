<form method="GET" action="{{ route('logistics.assignments', [], false) }}" class="assignment-view-filter">
<label for="assignment-view">Type</label>
<select name="view" id="assignment-view" onchange="this.form.requestSubmit()">
<option value="all" @selected(!request('view') || request('view') === 'all')>All assignments</option>
<option value="delivery" @selected(request('view') === 'delivery')>Delivery riders</option>
<option value="transfers" @selected(request()->routeIs('logistics.transfers*') || request('view') === 'transfers')>Hub transfers</option>
</select><noscript><button class="btn btn-outline">Show</button></noscript>
</form>
