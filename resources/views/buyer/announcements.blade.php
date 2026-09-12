@extends('buyer.layout')
@section('title', 'Announcements')
@section('page-title', 'Announcements')
@section('page-sub', 'Updates from PocketFinds and the shops you buy from')

@section('content')
<div class="announcement-page-head">
  <div>
    <h1>Announcements</h1>
    <p>Platform news and updates from the sellers you buy from</p>
  </div>
  @if($announcements->count())
  <span class="announcement-count-pill">{{ $announcements->count() }} total</span>
  @endif
</div>

<div class="announcement-list">
  @forelse($announcements as $a)
    @php $isUnread = $unreadIds->contains($a->id); @endphp
    <a href="{{ route('buyer.announcements.show', $a->id) }}" class="announcement-card {{ $isUnread ? 'is-unread' : '' }}">
      <span class="announcement-icon">@include('buyer.partials.icon', ['name' => 'bell', 'size' => 17])</span>
      <span class="announcement-body">
        <span class="announcement-top">
          <span class="announcement-title">{{ $a->title }}</span>
          @if($isUnread)<span class="announcement-badge-new">New</span>@endif
        </span>
        <span class="announcement-preview">{{ \Illuminate\Support\Str::limit(strip_tags($a->body), 120) }}</span>
        <span class="announcement-meta">
          <span class="announcement-source">{{ $a->author?->account_type === 'seller' ? ($a->author->business_name ?: $a->author->given_names) : 'PocketFinds Team' }}</span>
          <span class="announcement-dot-sep">·</span>
          @include('buyer.partials.icon', ['name' => 'clock', 'size' => 11])
          {{ $a->created_at->diffForHumans() }}
        </span>
      </span>
      <span class="announcement-chevron">@include('buyer.partials.icon', ['name' => 'arrow-right', 'size' => 15])</span>
    </a>
  @empty
    <div class="empty" style="padding:60px 20px">
      @include('buyer.partials.icon', ['name' => 'bell', 'size' => 32, 'class' => 'ic'])
      <h3>No announcements yet</h3>
      <p>Updates from PocketFinds and your favorite shops will show up here.</p>
    </div>
  @endforelse
</div>
@endsection
