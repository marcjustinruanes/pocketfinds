@extends('admin.layout')
@section('title', 'User Accounts')
@section('page-title', 'User Accounts')
@section('page-sub', 'Manage all platform accounts')
@php use Illuminate\Support\Facades\Storage; @endphp

@section('body-class', 'platform-accounts-page')
@push('account-head')
<link rel="stylesheet" href="{{ asset('css/platform-accounts.css') }}?v={{ filemtime(public_path('css/platform-accounts.css')) }}">
@endpush

@section('workspace-search')
<div class="platform-account-toolbar">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search name, email or username…" aria-label="Search accounts" data-table-search="usersTable">
      </div>
    </div>



</div>
@endsection

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif
<div class="card platform-accounts-card">

  <div class="card-pad">
    <div class="table-wrap">
      <table class="dtable" id="usersTable" data-platform-filter-table>
        <thead>
          <tr><th>User</th><th>@include('admin.partials.column-filter', ['filterTable'=>'usersTable'])</th><th>Joined</th><th>@include('admin.partials.column-filter', ['filterTable'=>'usersTable','filterLabel'=>'Status','filterField'=>'status','filterOptions'=>['all'=>'All statuses','pending'=>'Pending','interview'=>'Interview','approved'=>'Approved','suspended'=>'Suspended']])</th><th class="platform-manage-heading">Manage</th></tr>
        </thead>
        <tbody>
          @forelse($users as $user)
          <tr class="rail-row rail-{{ $user->status }}" data-account-type="{{ $user->account_type }}" data-status="{{ $user->status }}" data-search="{{ $user->username }} {{ $user->contact_no }}">
            <td>
              <div class="cell-user">
                <x-user-avatar :user="$user" size="30" class="avatar-sm" />
                <div><strong>{{ $user->given_names }} {{ $user->last_name }}</strong><span>{{ $user->email }}</span></div>
              </div>
            </td>
            <td><span class="stamp stamp-{{ $user->account_type }}">{{ ucfirst($user->account_type) }}</span></td>
            <td class="mono">{{ $user->created_at?->format('M d, Y') ?? '—' }}</td>
            <td><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button type="button" class="btn platform-detail-button" data-modal-open="userModal-{{ $user->id }}" aria-label="Manage {{ $user->given_names }} {{ $user->last_name }}" title="Manage account"><span aria-hidden="true">&#8599;</span></button>
              </div>
            </td>
          </tr>

          @push('account-user-modals')
          <div class="modal-overlay" id="userModal-{{ $user->id }}">
            <div class="modal modal-lg platform-user-modal" role="dialog" aria-modal="true" aria-label="Manage user">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="users" /></span>
                  <div class="modal-head-copy">
                    <h3>Manage User</h3>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
                @include('admin.partials.review-summary', ['user'=>$user])
                @include('admin.partials.user-profile-body', ['user' => $user])
              </div>
              <div class="modal-foot">
                @if(app(\App\Services\HubHiring::class)->role($user) !== null && in_array($user->status, ['pending', 'interview', 'rejected'], true))
                <p>Application decisions are handled by the company Logistics Admin.</p>
                @endif
                @php($personnel = app(\App\Services\HubHiring::class)->role($user) !== null)
                @if($user->status !== 'suspended' && (!$personnel || $user->status === 'approved'))
                <button type="button" class="btn btn-outline" onclick="openReasonModal('suspend', '{{ route('admin.users.suspend', $user->id) }}')">Suspend</button>
                @endif
                @if($user->status !== 'approved' && (!$personnel || $user->status === 'suspended'))
                @if($user->account_type==='rider')<button type="button" class="btn btn-success" onclick="openReasonModal('activate', '{{ route('admin.users.approve',$user->id) }}')">Reactivate</button>@else
<form method="POST" action="{{ route('admin.users.approve', $user->id) }}" style="display:inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Activate</button>
                </form>
                @endif
                @endif
              </div>
            </div>
          </div>
          @endpush
          @empty
          <tr><td colspan="5"><div class="empty"><div class="ic"><x-admin-icon name="users" /></div><h3>No users yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@stack('account-user-modals')

@include('admin.partials.reason-modals', ['reasonModalTypes' => ['suspend','activate']])
@include('admin.partials.doc-lightbox')
@endsection
