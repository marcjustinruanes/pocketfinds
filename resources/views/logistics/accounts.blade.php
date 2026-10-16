@extends('logistics.layout')
@section('title','User & Account Management')
@section('page-title','User & Account Management')
@section('page-sub','Manage your company riders and hub staff')
@section('content')
<form method="GET" class="logistics-toolbar">
<input name="search" value="{{ request('search') }}" placeholder="Search name or username" aria-label="Search accounts">
<select name="role" aria-label="Role"><option value="">All roles</option>@foreach(['rider','hub_staff'] as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ ucfirst(str_replace('_',' ',$role)) }}</option>@endforeach</select>
<select name="status" aria-label="Status"><option value="">All statuses</option>@foreach(['pending','pending_activation','interview','approved','rejected','suspended'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
<button class="btn btn-outline">Filter</button><a class="btn btn-outline" href="{{ route('logistics.accounts') }}">Reset</a>
</form>
<div class="card"><div class="card-head"><h2>Company accounts</h2><span>{{ $accounts->total() }} records</span></div>
<div class="table-wrap"><table class="dtable"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Hub</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($accounts as $user)<tr><td>{{ $user->given_names }} {{ $user->last_name }}</td><td>{{ $user->username }}</td><td>{{ $user->account_type === 'rider' ? 'Rider' : 'Hub staff' }}</td><td>{{ $user->logisticsHub?->municipality ?? 'Unassigned' }}, {{ $user->logisticsHub?->province }}</td><td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td><td><a class="btn btn-sm btn-outline" href="{{ route('logistics.accounts.edit',$user->id) }}">Edit / assignments</a></td></tr>
@empty<tr><td colspan="6"><div class="empty"><h3>No matching accounts</h3></div></td></tr>@endforelse
</tbody></table></div></div>
<div class="logistics-pagination">{{ $accounts->links() }}</div>
@php($creationRole = request('role'))
<div class="card card-pad">
  <p><a class="btn btn-primary" href="{{ route('logistics.riders.create') }}">Add rider and send activation invitation</a></p>
  @if($creationRole !== 'rider')
    <h2>Create hub staff</h2>
    <form method="POST" action="{{ route('logistics.staff.accounts.store') }}">@csrf @include('logistics.partials.account-form',['account'=>null,'creationRole'=>'hub_staff'])</form>
  @endif
</div>
@endsection
