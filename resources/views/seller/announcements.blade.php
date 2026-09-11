@extends('seller.layout')
@section('title', 'Announcements')
@section('page-title', 'Announcements')
@section('page-sub', 'Post updates to your buyers')

@section('content')

<div class="dash-grid">
  <div class="stack">
    {{-- Composer --}}
    <div class="card">
      <div class="card-head">
        <div><h2>New Announcement</h2><p>Sent to every buyer on PocketFinds — no admin approval needed</p></div>
      </div>
      <div class="card-pad">
        <form method="POST" action="{{ route('seller.announcements.store') }}">
          @csrf
          <div class="form-row">
            <label>Title</label>
            <input type="text" name="title" maxlength="255" required placeholder="e.g. Weekend Sale — 20% off all items">
          </div>
          <div class="form-row">
            <label>Message</label>
            <textarea name="body" rows="5" maxlength="5000" required placeholder="Write your announcement…" style="width:100%;border:1px solid var(--border);border-radius:9px;padding:10px 12px;font-size:13.5px;font-family:inherit;resize:vertical"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">
            @include('seller.partials.icon', ['name' => 'megaphone', 'size' => 14]) Publish to Buyers
          </button>
        </form>
      </div>
    </div>

    {{-- My announcements --}}
    <div class="card">
      <div class="card-head"><div><h2>My Announcements</h2><p>What you've published</p></div></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:0">
        @forelse($myAnnouncements as $a)
        <div class="announcement-row">
          <div class="announcement-row-body">
            <strong>{{ $a->title }}</strong>
            <p>{{ \Illuminate\Support\Str::limit($a->body, 140) }}</p>
            <span class="announcement-row-meta">{{ $a->created_at->diffForHumans() }} · Visible to Buyers</span>
          </div>
          <form method="POST" action="{{ route('seller.announcements.destroy', $a->id) }}" onsubmit="return confirm('Delete this announcement?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger icon-only" title="Delete" aria-label="Delete">
              @include('seller.partials.icon', ['name' => 'x', 'size' => 13])
            </button>
          </form>
        </div>
        @empty
        <div class="empty" style="padding:30px 0">
          @include('seller.partials.icon', ['name' => 'megaphone', 'size' => 26, 'class' => 'ic'])
          <h3>No announcements yet</h3>
          <p>Post your first update to your buyers above.</p>
        </div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="stack">
    {{-- Platform announcements (read-only, from admin) --}}
    <div class="card">
      <div class="card-head"><div><h2>Platform Announcements</h2><p>From the PocketFinds team</p></div></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:0">
        @forelse($platformAnnouncements as $a)
        <div class="announcement-row">
          <div class="announcement-row-body">
            <strong>{{ $a->title }}</strong>
            <p>{{ \Illuminate\Support\Str::limit($a->body, 140) }}</p>
            <span class="announcement-row-meta">{{ $a->created_at->diffForHumans() }}</span>
          </div>
        </div>
        @empty
        <div class="empty" style="padding:30px 0">
          @include('seller.partials.icon', ['name' => 'bell', 'size' => 26, 'class' => 'ic'])
          <h3>No platform announcements</h3>
          <p>Updates from PocketFinds will show up here.</p>
        </div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
