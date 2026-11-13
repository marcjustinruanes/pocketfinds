@extends('logistics.layout')
@section('title','Activity Logs')
@section('page-title','Activity Logs')
@section('page-sub','Recorded logistics actions in your company')
@section('content')
<div class="logistics-activity-page">
<section class="cash-card activity-filter-panel" aria-label="Filter activity logs"><form method="GET" class="logistics-toolbar activity-filter-card"><input name="search" value="{{ request('search') }}" placeholder="Actor, action, or record" aria-label="Search activity"><label>From<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label><button class="btn btn-primary">Filter</button></form></section>
<section class="cash-card activity-history-card"><div class="card-head"><h2><span class="activity-history-icon"><x-admin-icon name="clock" /></span>Activity history</h2><span class="activity-record-count">{{ $entries->total() }} {{ \Illuminate\Support\Str::plural('record', $entries->total()) }}</span></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Actor</th><th>Action</th><th>Affected record</th><th>Transition / reason</th><th>Timestamp</th></tr></thead><tbody>
@forelse($entries as $entry)<tr><td>{{ $entry->username ?? 'Deleted account' }}</td><td>{{ $entry->action }}</td><td class="mono">{{ $entry->record_id ?: 'Batch / account action' }}</td><td>@if($entry->changes ?? null)<details><summary>Recorded context</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ json_encode(json_decode($entry->changes,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@else Legacy action; no transition context recorded @endif</td><td>{{ $entry->created_at ? \Illuminate\Support\Carbon::parse($entry->created_at)->format('M j, Y g:i A') : '—' }}</td></tr>@empty<tr><td colspan="5"><div class="empty"><h3>No activity recorded yet</h3><p>Actions performed after this update appear here.</p></div></td></tr>@endforelse
</tbody></table></div></section><div class="logistics-pagination">{{ $entries->links() }}</div>
</div>
@endsection
