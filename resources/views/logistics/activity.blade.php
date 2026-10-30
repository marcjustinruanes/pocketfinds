@extends('logistics.layout')
@section('title','Activity Logs')
@section('page-title','Activity Logs')
@section('page-sub','Recorded logistics actions in your company')
@section('content')
<form method="GET" class="logistics-toolbar"><input name="search" value="{{ request('search') }}" placeholder="Actor, action, or record" aria-label="Search activity"><label>From<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label><button class="btn btn-outline">Filter</button></form>
<div class="card"><div class="card-head"><h2>Activity history</h2></div><div class="table-wrap"><table class="dtable"><thead><tr><th>Actor</th><th>Action</th><th>Affected record</th><th>Transition / reason</th><th>Timestamp</th></tr></thead><tbody>
@forelse($entries as $entry)<tr><td>{{ $entry->username ?? 'Deleted account' }}</td><td>{{ $entry->action }}</td><td class="mono">{{ $entry->record_id ?: 'Batch / account action' }}</td><td>@if($entry->changes ?? null)<details><summary>Recorded context</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ json_encode(json_decode($entry->changes,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@else Legacy action; no transition context recorded @endif</td><td>{{ $entry->created_at }}</td></tr>@empty<tr><td colspan="5"><div class="empty"><h3>No activity recorded yet</h3><p>Actions performed after this update appear here.</p></div></td></tr>@endforelse
</tbody></table></div></div><div class="logistics-pagination">{{ $entries->links() }}</div>
@endsection
