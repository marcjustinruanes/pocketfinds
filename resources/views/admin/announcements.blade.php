@extends('admin.layout')
@section('title', 'Announcements')
@section('page-title', 'Announcements')
@section('page-sub', 'Broadcast messages to platform users')

@section('content')
<div class="admin-announcements-page">
@include('admin.partials.ai-link', ['aiMode' => 'draft', 'aiLabel' => 'AI announcement draft'])
@php $aiDraft = session('ai_announcement_draft', []); @endphp
@if($aiDraft)
<p class="announcement-draft-notice" role="status">AI draft loaded. Check the details before posting. Nothing has been published.</p>
@endif
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif

<div class="kpi-grid admin-announcement-kpis">
  <div class="kpi">
    <div class="label">Total Announcements</div><div class="value">{{ number_format($total) }}</div>
  </div>
  <div class="kpi tone-success">
    <div class="label">Active</div><div class="value">{{ number_format($active) }}</div><div class="delta up">Currently visible</div>
  </div>
  <div class="kpi tone-info">
    <div class="label">All Users</div><div class="value">{{ number_format($byAudience['all'] ?? 0) }}</div>
  </div>
  <div class="kpi">
    <div class="label">Latest Posted</div><div class="value" style="font-size:15px">{{ $latest?->created_at?->format('M d, Y') ?? '—' }}</div>
  </div>
  <div class="kpi tone-info">
    <div class="label">Buyers Only</div><div class="value">{{ number_format($byAudience['buyer'] ?? 0) }}</div>
  </div>
  <div class="kpi">
    <div class="label">Sellers Only</div><div class="value">{{ number_format($byAudience['seller'] ?? 0) }}</div>
  </div>
  <div class="kpi tone-warning">
    <div class="label">Riders Only</div><div class="value">{{ number_format($byAudience['rider'] ?? 0) }}</div>
  </div>
</div>

<div class="dash-grid admin-announcement-layout">
  <div class="card announcement-composer-card">
    <div class="card-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="megaphone" /></span>
        <div class="modal-head-copy"><h2 style="margin:0">Post Announcement</h2><p>Broadcast a message to platform users</p></div>
      </div>
    </div>
    <div class="card-pad">
      <form class="admin-announcement-form" method="POST" action="{{ route('admin.settings.announcements.store') }}">
        @csrf
        <div class="form-row">
          <label>Title</label>
          <input type="text" name="title" placeholder="Announcement title" required value="{{ old('title', $aiDraft['title'] ?? '') }}">
        </div>
        <div class="form-row">
          <label>Message</label>
          <textarea name="body" rows="4" placeholder="Write your announcement here..." required>{{ old('body', $aiDraft['body'] ?? '') }}</textarea>
        </div>
        <div class="form-row">
          <label>Audience</label>
          <select name="audience">
            <option value="all" @selected(old('audience', $aiDraft['audience'] ?? 'all') === 'all')>All Users</option>
            <option value="buyer" @selected(old('audience', $aiDraft['audience'] ?? 'all') === 'buyer')>Buyers Only</option>
            <option value="seller" @selected(old('audience', $aiDraft['audience'] ?? 'all') === 'seller')>Sellers Only</option>
            <option value="rider" @selected(old('audience', $aiDraft['audience'] ?? 'all') === 'rider')>Riders Only</option>
          </select>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><x-admin-icon name="megaphone" /> Post Announcement</button>
      </form>
    </div>
  </div>

  <div class="card active-announcements-card">
    <div class="card-head"><div><h2>Active Announcements</h2><p>{{ $announcements->count() }} total</p></div></div>
    <div class="active-announcements-list">
      @forelse($announcements as $ann)
      <article class="active-announcement-row">
        <div class="active-announcement-copy">
            <strong>{{ $ann->title }}</strong>
            <p>{{ $ann->body }}</p>
            <div class="active-announcement-meta">
              <span class="stamp stamp-approved">{{ ucfirst($ann->audience) }}</span>
              <span>{{ $ann->created_at?->format('Y-m-d') }}</span>
            </div>
        </div>
          <form class="active-announcement-delete" method="POST" action="{{ route('admin.settings.announcements.destroy', $ann->id) }}" data-confirm data-confirm-title="Delete this announcement?" data-confirm-message="This announcement will be permanently removed." data-confirm-action="Delete announcement" data-confirm-tone="danger">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-danger icon-only" type="submit" aria-label="Delete announcement"><x-admin-icon name="trash" /></button>
          </form>
      </article>
      @empty
      <div class="empty"><div class="ic"><x-admin-icon name="megaphone" /></div><h3>No announcements yet</h3></div>
      @endforelse
    </div>
  </div>
</div>
</div>
@endsection
