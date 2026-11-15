@extends('admin.layout')
@section('title', 'Registrations')
@section('page-title', 'Registrations')
@section('page-sub', 'Review and approve new account applications')
@php use Illuminate\Support\Facades\Storage; @endphp

@section('body-class', 'platform-accounts-page')
@push('account-head')
<link rel="stylesheet" href="{{ asset('css/platform-accounts.css') }}?v={{ filemtime(public_path('css/platform-accounts.css')) }}">
@endpush

@section('workspace-search')
<div class="platform-account-toolbar">
    <div class="filter-bar registration-filters">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search name or email..." aria-label="Search accounts" data-table-search="regTable">
      </div>

    </div>



</div>
@endsection

@section('content')
<div class="admin-registrations-page">
@include('admin.partials.ai-link', ['aiMode' => 'seller', 'aiLabel' => 'AI seller review'])
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif
<div class="card platform-accounts-card">

  <div class="card-pad">
    <div class="table-wrap">
      <table class="dtable" id="regTable" data-platform-filter-table>
        <thead>
          <tr><th>Applicant</th><th>@include('admin.partials.column-filter', ['filterTable'=>'regTable'])</th><th>Submitted</th><th>@include('admin.partials.column-filter', ['filterTable'=>'regTable','filterLabel'=>'Status','filterField'=>'status','filterOptions'=>['all'=>'All statuses','pending'=>'Pending','rejected'=>'Rejected']])</th><th class="platform-manage-heading">Manage</th></tr>
        </thead>
        <tbody>
          @forelse($users as $user)
          <tr class="rail-row rail-{{ $user->status }}" data-status="{{ $user->status }}" data-account-type="{{ $user->account_type }}">
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
                <button type="button" class="btn platform-detail-button" data-modal-open="reviewModal-{{ $user->id }}" aria-label="Review {{ $user->given_names }} {{ $user->last_name }}" title="Review account"><span aria-hidden="true">&#8599;</span></button>
                @if(config('features.admin_ai') && config('features.admin_ai_review') && $user->account_type === 'seller')<a class="btn btn-sm btn-outline" href="{{ route('admin.ai', ['mode' => 'seller', 'seller_id' => $user->id]) }}"><x-admin-icon name="chart" /> AI review</a>@endif
              </div>
            </td>
          </tr>

          {{-- Per-user review modal --}}
          @push('account-registration-modals')
          <div class="modal-overlay" id="reviewModal-{{ $user->id }}">
            <div class="modal modal-lg platform-user-modal" role="dialog" aria-modal="true" aria-label="Review application">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="edit" /></span>
                  <div class="modal-head-copy">
                    <h3>Review Application</h3>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
                @include('admin.partials.review-summary', ['user'=>$user])

                @include('admin.partials.user-profile-body', ['user'=>$user])
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Cancel</button>
                @if($user->status !== 'rejected')
                <button type="button" class="btn btn-outline-danger" onclick="openReasonModal('reject', '{{ route('admin.registrations.reject', $user->id) }}')">Reject</button>
                @else
                <span>{{ $user->can_resubmit ? 'Resubmission allowed' : 'Final rejection' }}</span>
                <button type="button" class="btn btn-outline" onclick="openReasonModal('reject', '{{ route('admin.registrations.reject', $user->id) }}')">Update rejection decision</button>
                @endif
                @if($user->status !== 'approved')
                <form method="POST" action="{{ route('admin.registrations.approve', $user->id) }}" style="display:inline" data-confirm data-confirm-title="Approve this application?" data-confirm-message="This will activate the account and complete its initial review." data-confirm-action="Approve account" data-confirm-tone="success">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Approve</button>
                </form>
                @endif
              </div>
            </div>
          </div>
          @endpush
          @empty
          <tr><td colspan="5"><div class="empty"><div class="ic"><x-admin-icon name="edit" /></div><h3>No registrations yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>



@stack('account-registration-modals')

@include('admin.partials.reason-modals', ['reasonModalTypes' => ['reject']])
@include('admin.partials.doc-lightbox')
@endsection
