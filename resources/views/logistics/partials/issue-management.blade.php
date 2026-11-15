@if($issueView === 'recorded')
<div class="card issue-recorded-card"><div class="card-head"><div><h2 class="issue-card-title"><span class="issue-heading-icon"><x-admin-icon name="flag" /></span>Recorded issues</h2><p>Review investigations, evidence, and resolution progress.</p></div><span class="issue-count-pill">{{ $issues->total() }} {{ \Illuminate\Support\Str::plural('record', $issues->total()) }}</span></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Parcel / issue</th><th>Investigation</th><th>Owner / status</th><th>Update</th></tr></thead><tbody>
@forelse($issues as $issue)
<tr><td>#{{ $issue->id }} - {{ $issue->shipment?->tracking_number }}<br>{{ ucfirst(str_replace('_',' ',$issue->kind)) }}</td>
<td>{{ $issue->description }}@if($issue->resolution)<p>{{ $issue->resolution }}</p>@endif
@if($issue->investigation['outcome']??null)<p>Outcome: {{ ucfirst(str_replace('_',' ',$issue->investigation['outcome'])) }}</p>@endif
@foreach(($issueEvidence??collect())->get((string)$issue->id,collect()) as $evidence)<a href="{{ route('finance.evidence',$evidence->id) }}" target="_blank" rel="noopener">Private evidence {{ $loop->iteration }}</a><br>@endforeach
</td><td>{{ $issue->assignee?->username??'Unassigned' }}<br>{{ ucfirst(str_replace('_',' ',$issue->status)) }}</td><td>
@if($issue->rts_shipment_id || $issue->rts)
<p>Return custody: {{ is_string($issue->rts['state']??null) ? str_replace('_',' ',$issue->rts['state']) : 'needs evidence review' }}. Use physical custody actions in the parcel's return section.</p>
@else
<details><summary>Update investigation</summary><form method="POST" enctype="multipart/form-data" action="{{ route('logistics.issues.update',$issue->id) }}">@csrf @method('PATCH')
<input type="hidden" name="revision" value="{{ $issue->investigation['revision']??0 }}">
<div class="logistics-form-grid">
<label>Assignee<select name="assigned_to"><option value="">Unassigned</option>@foreach($assignees->where('logistics_hub_id',$issue->hub_id) as $person)<option value="{{ $person->id }}" @selected($issue->assigned_to===$person->id)>{{ $person->username }}</option>@endforeach</select></label>
<label>Status<select name="status">@foreach(['open','in_progress','resolved'] as $status)<option value="{{ $status }}" @selected($issue->status===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
<label>Resolution outcome (required when resolved)<select name="outcome"><option value="">Select outcome</option>@foreach(['located'=>'Located; custody recorded separately','damage_documented'=>'Damage documented','escalated'=>'Referred for support review','documented_only'=>'Investigation documented; no financial action'] as $value=>$label)<option value="{{ $value }}" @selected(($issue->investigation['outcome']??null)===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Investigation / resolution reason<textarea name="resolution" minlength="3" maxlength="2000">{{ $issue->resolution }}</textarea></label>
<label>Custody observations (not a handover)<textarea name="custody_note" maxlength="1000">{{ $issue->investigation['custody_note']??'' }}</textarea></label>
<label>Additional private evidence (optional, up to 5 MB)<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
</div>
@if(auth()->user()->isLogisticsAdmin() && in_array($issue->kind,['lost','damaged']))
<label style="display:flex;align-items:center;gap:8px;min-height:44px"><input type="checkbox" name="notify_participants" value="1">Notify buyer and Seller of a confirmed resolved milestone</label>
<label style="display:flex;align-items:center;gap:8px;min-height:44px"><input type="checkbox" name="public_update_confirmed" value="1">I confirmed this milestone; send generic wording without investigation allegations.</label>
@endif
<button class="btn btn-outline">Save investigation</button></form></details>
@endif</td></tr>
@empty<tr><td colspan="4"><div class="empty"><h3>No recorded issues</h3></div></td></tr>@endforelse
</tbody></table></div></div><div class="logistics-pagination">{{ $issues->appends(['view' => 'recorded'])->links() }}</div>
@elseif($issueView === 'history')
<div class="card issue-history-card"><div class="card-head"><div><h2 class="issue-card-title"><span class="issue-heading-icon"><x-admin-icon name="clock" /></span>Recent investigation history</h2><p>Timeline of recorded changes and administrative actions.</p></div></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Issue</th><th>Actor ID</th><th>Assignee ID</th><th>Status / recorded context</th><th>Time</th></tr></thead><tbody>
@forelse($issueEvents as $event)<tr><td>#{{ $event->issue_id }}</td><td>{{ $event->actor_id }}</td><td>{{ $event->assigned_to??'Unassigned' }}</td><td>{{ $event->status }}<pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ json_decode($event->note??'',true)!==null?json_encode(json_decode($event->note,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE):$event->note }}</pre></td><td>{{ $event->created_at ? \Illuminate\Support\Carbon::parse($event->created_at)->format('M j, Y g:i A') : '—' }}</td></tr>@empty<tr><td colspan="5"><div class="empty"><h3>No issue history</h3></div></td></tr>@endforelse
</tbody></table></div></div>
@endif
