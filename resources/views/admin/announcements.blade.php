@extends('admin.layout')
@section('title', 'Announcements')
@section('page-title', 'Announcements')
@section('page-sub', 'Broadcast messages to platform users')

@section('content')
@if(session('success'))
<div style="background:var(--success-soft);border:1px solid var(--success-line);color:var(--success);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:18px">
  {{ session('success') }}
</div>
@endif

<div class="kpi-grid" style="margin-bottom:18px">
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
</div>

<div class="kpi-grid" style="margin-bottom:18px">
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

<div class="dash-grid">
  <div class="card">
    <div class="card-head">
      <div class="modal-head-main">
        <span class="modal-icon"><x-admin-icon name="megaphone" /></span>
        <div class="modal-head-copy"><h2 style="margin:0">Post Announcement</h2><p>Broadcast a message to platform users</p></div>
      </div>
    </div>
    <div class="card-pad">
      <form method="POST" action="{{ route('admin.settings.announcements.store') }}">
        @csrf
        <div class="form-row">
          <label>Title</label>
          <input type="text" name="title" placeholder="Announcement title" required value="{{ old('title') }}">
        </div>
        <div class="form-row">
          <label>Message</label>
          <textarea name="body" rows="4" placeholder="Write your announcement here..." required>{{ old('body') }}</textarea>
        </div>
        <div class="form-row">
          <label>Audience</label>
          <select name="audience">
            <option value="all">All Users</option>
            <option value="buyer">Buyers Only</option>
            <option value="seller">Sellers Only</option>
            <option value="rider">Riders Only</option>
          </select>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><x-admin-icon name="megaphone" /> Post Announcement</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Active Announcements</h2><p>{{ $announcements->count() }} total</p></div></div>
    <div style="max-height:480px;overflow-y:auto">
      @forelse($announcements as $ann)
      <div style="padding:14px 18px;border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
          <div style="min-width:0">
            <div style="font-weight:700;font-size:13.5px">{{ $ann->title }}</div>
            <div style="font-size:12px;color:var(--muted);margin:3px 0">{{ $ann->body }}</div>
            <div style="display:flex;gap:8px;margin-top:6px;align-items:center">
              <span class="stamp stamp-approved">{{ ucfirst($ann->audience) }}</span>
              <span style="font-size:11px;color:var(--muted);font-family:var(--font-mono)">{{ $ann->created_at?->format('Y-m-d') }}</span>
            </div>
          </div>
          <form method="POST" action="{{ route('admin.settings.announcements.destroy', $ann->id) }}" style="flex:none">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-danger icon-only" type="submit" onclick="return confirm('Delete this announcement?')" aria-label="Delete announcement"><x-admin-icon name="trash" /></button>
          </form>
        </div>
      </div>
      @empty
      <div class="empty"><div class="ic"><x-admin-icon name="megaphone" /></div><h3>No announcements yet</h3></div>
      @endforelse
    </div>
  </div>
</div>
@endsection
