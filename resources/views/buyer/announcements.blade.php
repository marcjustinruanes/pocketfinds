@extends('buyer.layout')
@section('title', 'Announcements')
@section('page-title', 'Announcements')
@section('page-sub', 'Updates from PocketFinds and the shops you buy from')

@section('content')
<div class="card">
  <div class="card-head">
    <div><h2>Announcements</h2><p>Platform news and updates from sellers</p></div>
  </div>
  <div class="card-pad">
    @forelse($announcements as $a)
      @php $isUnread = $unreadIds->contains($a->id); @endphp
      <a href="{{ route('buyer.announcements.show', $a->id) }}" class="announcement-card {{ $isUnread ? 'is-unread' : '' }}">
        <span class="announcement-icon">@include('buyer.partials.icon', ['name' => 'bell', 'size' => 16])</span>
        <span class="announcement-body">
          <span class="announcement-top">
            <span class="announcement-title">{{ $a->title }}</span>
            @if($isUnread)<span class="announcement-dot" title="Unread"></span>@endif
          </span>
          <span class="announcement-preview">{{ \Illuminate\Support\Str::limit(strip_tags($a->body), 120) }}</span>
          <span class="announcement-meta">
            {{ $a->author?->account_type === 'seller' ? ($a->author->business_name ?: $a->author->given_names) : 'PocketFinds Team' }}
            · {{ $a->created_at->diffForHumans() }}
          </span>
        </span>
      </a>
    @empty
      <div class="empty">
        @include('buyer.partials.icon', ['name' => 'bell', 'size' => 28, 'class' => 'ic'])
        <h3>No announcements yet</h3>
        <p>Updates from PocketFinds and your favorite shops will show up here.</p>
      </div>
    @endforelse
  </div>
</div>
@endsection
