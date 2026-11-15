<aside class="card issue-entry-panel" aria-label="Issue entry controls">
<form method="GET" action="{{ route('logistics.issues') }}" class="issue-view-filter">
<label for="issue-view">Show</label>
<select name="view" id="issue-view" onchange="this.form.requestSubmit()">
<option value="recorded" @selected($issueView === 'recorded')>Recorded issues</option>
<option value="history" @selected($issueView === 'history')>Investigation history</option>
<option value="deliveries" @selected($issueView === 'deliveries')>Problem deliveries</option>
</select>
<noscript><button class="btn btn-outline">Show table</button></noscript>
</form>
<form method="POST" enctype="multipart/form-data" action="{{ route('logistics.issues.store') }}">@csrf
@if($errors->any())<p class="issue-entry-error" role="alert">{{ $errors->first() }}</p>@endif
<div class="issue-entry-fields">
@if(auth()->user()->isLogisticsAdmin())<label>Responsible hub<select name="hub_id" required><option value="">Select responsible hub</option>@foreach($issueHubs as $hub)<option value="{{ $hub->id }}" @selected((string)old('hub_id') === (string)$hub->id)>{{ $hub->municipality }}, {{ $hub->province }}</option>@endforeach</select></label>@endif
<label>Tracking number<input placeholder="Enter tracking number" name="tracking_number" required maxlength="100" value="{{ old('tracking_number') }}"></label>
<label>Issue type<select name="kind" onchange="this.form.elements.proof.required = this.value === 'damaged'">@foreach(['delivery_failed','lost','damaged','transfer_problem','other'] as $kind)<option value="{{ $kind }}" @selected(old('kind', 'delivery_failed') === $kind)>{{ ucfirst(str_replace('_',' ',$kind)) }}</option>@endforeach</select></label>
<label class="issue-evidence-field">Private evidence<input type="file" name="proof" accept="image/jpeg,image/png" @required(old('kind') === 'damaged')><small>JPG or PNG, up to 5 MB. Required for damage reports.</small></label>
</div><button class="btn btn-primary">Record issue</button></form></aside>
