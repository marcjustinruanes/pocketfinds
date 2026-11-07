@extends('buyer.layout')
@section('title', 'Announcements')
@section('hide-page-heading', true)

@push('head')
<link rel="stylesheet" href="{{ asset('css/buyer-announcements.css') }}?v={{ filemtime(public_path('css/buyer-announcements.css')) }}">
@endpush

@section('content')
<section class="announcement-list" aria-label="Announcements">
  @if($announcements->count())<div class="announcement-list-meta"><span>{{ $announcements->count() }} update{{ $announcements->count() === 1 ? '' : 's' }}</span><span>Newest first</span></div>@endif
  @forelse($announcements as $a)
    @php $isUnread = $unreadIds->contains($a->id); @endphp
    <button type="button" class="announcement-card {{ $isUnread ? 'is-unread' : '' }}" data-announcement-open="announcement{{ $a->id }}" aria-haspopup="dialog">
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
    </button>
    <dialog class="announcement-modal" id="announcement{{ $a->id }}" aria-labelledby="announcementTitle{{ $a->id }}">
      <form method="dialog" class="announcement-modal-close"><button type="submit" aria-label="Close announcement">&times;</button></form>
      <div class="announcement-modal-head">
        <span class="announcement-icon">@include('buyer.partials.icon', ['name' => 'bell', 'size' => 19])</span>
        <div><span class="announcement-modal-source">{{ $a->author?->account_type === 'seller' ? ($a->author->business_name ?: $a->author->given_names) : 'PocketFinds Team' }}</span><h2 id="announcementTitle{{ $a->id }}">{{ $a->title }}</h2><time datetime="{{ $a->created_at->toIso8601String() }}">{{ $a->created_at->format('M j, Y · g:i A') }}</time></div>
      </div>
      <div class="announcement-modal-body">{!! nl2br(e($a->body)) !!}</div>
    </dialog>
  @empty
    <div class="announcement-empty">
      <span class="announcement-icon">@include('buyer.partials.icon', ['name' => 'bell', 'size' => 28])</span>
      <h3>No announcements yet</h3>
      <p>Updates from PocketFinds and your favorite shops will show up here.</p>
    </div>
  @endforelse
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-announcement-open]').forEach((button) => {
    button.addEventListener('click', () => document.getElementById(button.dataset.announcementOpen)?.showModal());
  });
  document.querySelectorAll('.announcement-modal').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
  });
  const openId = new URLSearchParams(window.location.search).get('open');
  if (openId) document.getElementById(`announcement${openId}`)?.showModal();
});
</script>
@endpush
