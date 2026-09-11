@extends('buyer.layout')
@section('title', $announcement->title)
@section('page-title', 'Announcement')
@section('page-sub', $announcement->title)

@section('content')
<a href="{{ route('buyer.announcements') }}" class="pd-back">
  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  Back to Announcements
</a>

<div class="card">
  <div class="card-pad announcement-detail">
    <div class="announcement-detail-head">
      <span class="announcement-icon announcement-icon-lg">@include('buyer.partials.icon', ['name' => 'bell', 'size' => 20])</span>
      <div>
        <h1>{{ $announcement->title }}</h1>
        <p>
          {{ $announcement->author?->account_type === 'seller' ? ($announcement->author->business_name ?: $announcement->author->given_names) : 'PocketFinds Team' }}
          · {{ $announcement->created_at->format('F j, Y \a\t g:i A') }}
        </p>
      </div>
    </div>
    <div class="announcement-detail-body">{!! nl2br(e($announcement->body)) !!}</div>
  </div>
</div>
@endsection
