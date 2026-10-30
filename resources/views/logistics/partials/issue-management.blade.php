<div class="card card-pad"><h2>Record a delivery issue</h2>
<p>Investigation records do not change parcel custody, inventory, payments or Rider earnings.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('logistics.issues.store') }}">@csrf
<div class="logistics-form-grid">
@if(auth()->user()->isLogisticsAdmin())<label>Responsible hub<select name="hub_id" required><option value="">Select a hub on the parcel route</option>@foreach($issueHubs as $hub)<option value="{{ $hub->id }}">{{ $hub->municipality }}, {{ $hub->province }}</option>@endforeach</select></label>@endif
<label>Tracking number<input name="tracking_number" required maxlength="100" value="{{ old('tracking_number') }}"></label>
<label>Issue type<select name="kind">@foreach(['delivery_failed','lost','damaged','transfer_problem','other'] as $kind)<option value="{{ $kind }}">{{ ucfirst(str_replace('_',' ',$kind)) }}</option>@endforeach</select></label>
<label>Description<textarea name="description" required maxlength="2000">{{ old('description') }}</textarea></label>
<label>Private evidence (optional, up to 5 MB)<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
</div><button class="btn btn-primary">Record issue</button></form></div>
<div class="card"><div class="card-head"><h2>Recorded issues</h2><span>{{ $issues->total() }} records</span></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Parcel / issue</th><th>Investigation</th><th>Owner / status</th><th>Update</th></tr></thead><tbody>
@forelse($issues as $issue)
<tr><td>#{{ $issue->id }} - {{ $issue->shipment?->tracking_number }}<br>{{ ucfirst(str_replace('_',' ',$issue->kind)) }}</td>
<td>{{ $issue->description }}@if($issue->resolution)<p>{{ $issue->resolution }}</p>@endif
@if($issue->investigation['outcome']??null)<p>Outcome: {{ ucfirst(str_replace('_',' ',$issue->investigation['outcome'])) }}</p>@endif
@foreach(($issueEvidence??collect())->get((string)$issue->id,collect()) as $evidence)<a href="{{ route('finance.evidence',$evidence->id) }}" target="_blank" rel="noopener">Private evidence {{ $loop->iteration }}</a><br>@endforeach
</td><td>{{ $issue->assignee?->username??'Unassigned' }}<br>{{ ucfirst(str_replace('_',' ',$issue->status)) }}</td><td>
@if($issue->rts)
<p>Return custody: {{ str_replace('_',' ',$issue->rts['state']) }}. Use physical custody actions in the parcel's return section.</p>
@else
<details><summary>Update investigation</summary><form method="POST" enctype="multipart/form-data" action="{{ route('logistics.issues.update',$issue->id) }}">@csrf @method('PATCH')
<input type="hidden" name="revision" value="{{ $issue->investigation['revision']??0 }}">
<div class="logistics-form-grid">
<label>Assignee<select name="assigned_to"><option value="">Unassigned</option>@foreach($assignees->where('logistics_hub_id',$issue->hub_id) as $person)<option value="{{ $person->id }}" @selected($issue->assigned_to===$person->id)>{{ $person->username }}</option>@endforeach</select></label>
<label>Status<select name="status">@foreach(['open','in_progress','resolved'] as $status)<option value="{{ $status }}" @selected($issue->status===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
<label>Resolution outcome (required when resolved)<select name="outcome"><option value="">Select outcome</option>@foreach(['located'=>'Located; custody recorded separately','damage_documented'=>'Damage documented','escalated'=>'Referred for support review','documented_only'=>'Investigation documented; no financial action'] as $value=>$label)<option value="{{ $value }}" @selected(($issue->investigation['outcome']??null)===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Investigation / resolution reason<textarea name="resolution" minlength="3" maxlength="2000">{{ $issue->resolution }}</textarea></label>
<label>Custody observations (not a handover)<textarea name="custody_note" maxlength="1000">{{ $issue->investigation['custody_note']??'' }}</textarea></label>
<label>Additional private evidence (optional)<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
</div>
@if(auth()->user()->isLogisticsAdmin() && in_array($issue->kind,['lost','damaged']))
<label style="display:flex;align-items:center;gap:8px;min-height:44px"><input type="checkbox" name="notify_participants" value="1">Notify buyer and Seller of a confirmed resolved milestone</label>
<label style="display:flex;align-items:center;gap:8px;min-height:44px"><input type="checkbox" name="public_update_confirmed" value="1">I confirmed this milestone; send generic wording without investigation allegations.</label>
@endif
<button class="btn btn-outline">Save investigation</button></form></details>
@endif</td></tr>
@empty<tr><td colspan="4"><div class="empty"><h3>No recorded issues</h3></div></td></tr>@endforelse
</tbody></table></div></div><div class="logistics-pagination">{{ $issues->links() }}</div>
<div class="card"><div class="card-head"><h2>Recent investigation history</h2></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Issue</th><th>Actor ID</th><th>Assignee ID</th><th>Status / recorded context</th><th>Time</th></tr></thead><tbody>
@forelse($issueEvents as $event)<tr><td>#{{ $event->issue_id }}</td><td>{{ $event->actor_id }}</td><td>{{ $event->assigned_to??'Unassigned' }}</td><td>{{ $event->status }}<pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ json_decode($event->note??'',true)!==null?json_encode(json_decode($event->note,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE):$event->note }}</pre></td><td>{{ $event->created_at }}</td></tr>@empty<tr><td colspan="5"><div class="empty"><h3>No issue history</h3></div></td></tr>@endforelse
</tbody></table></div></div>
