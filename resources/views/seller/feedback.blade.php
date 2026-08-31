@extends('seller.layout')
@section('title', 'Customer Feedback')
@section('page-title', 'Customer Feedback')
@section('page-sub', 'Reviews and ratings from your buyers')

@section('content')
@if(session('review_success'))<div class="auth-success" style="margin-bottom:16px">{{ session('review_success') }}</div>@endif
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="kpi"><div class="label">Avg. Rating</div><div class="value">{{ $avgRating ?: '—' }}</div><div class="delta up">Out of 5</div></div>
  <div class="kpi"><div class="label">Total Reviews</div><div class="value">{{ $total }}</div><div class="delta">All time</div></div>
  <div class="kpi"><div class="label">5 Stars</div><div class="value">{{ $breakdown[5] }}%</div><div class="delta up">—</div></div>
  <div class="kpi"><div class="label">Needs Response</div><div class="value">{{ $needsResponse }}</div><div class="delta">Unanswered</div></div>
</div>

<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><div><h2>All Reviews</h2><p>Customer feedback on your products</p></div></div>
      <div class="card-pad">
        @forelse($reviews as $review)
        <div style="padding:16px 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:12px">{{ strtoupper(substr($review->buyer?->given_names ?: '?', 0, 1)) }}</div>
              <div>
                <div style="font-size:13px;font-weight:650">{{ $review->buyer?->given_names ?: 'Buyer' }} {{ $review->buyer?->last_name }}</div>
                <div style="font-size:11px;color:var(--muted)">{{ $review->product?->name ?: 'Product removed' }}</div>
              </div>
            </div>
            <span style="font-family:var(--font-mono);font-size:10.5px;color:var(--muted)">{{ $review->order?->created_at?->format('M d, Y') }}</span>
          </div>
          <div style="margin-bottom:6px">
            @for($s=1;$s<=5;$s++)<span style="color:{{ $s<=$review->rating ? '#f59e0b' : 'var(--border)' }};font-size:15px">★</span>@endfor
          </div>
          @if($review->comment)<p style="margin:0 0 10px;font-size:13px;color:var(--text)">{{ $review->comment }}</p>@endif
          @if($review->seller_reply)
          <div style="background:var(--paper);border-radius:8px;padding:10px 12px;font-size:12.5px;color:var(--text)"><strong>Your reply:</strong> {{ $review->seller_reply }}</div>
          @else
          <button class="btn btn-sm btn-outline" data-modal="replyModal{{ $review->id }}">@include('seller.partials.icon', ['name' => 'send', 'size' => 12]) Reply</button>
          <div class="modal-overlay" id="replyModal{{ $review->id }}">
            <div class="modal" style="max-width:440px">
              <div class="modal-head">
                <div><h3>Reply to Review</h3><p>Your response will be visible to all buyers</p></div>
                <button class="modal-close" data-modal-close>✕</button>
              </div>
              <form method="POST" action="{{ route('seller.reviews.reply', $review) }}">
                @csrf
                <div class="modal-body">
                  <div class="form-row"><label>Your Reply</label><textarea name="seller_reply" rows="4" placeholder="Thank the customer or address their concern…" required></textarea></div>
                </div>
                <div class="modal-foot">
                  <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
                  <button class="btn btn-primary" type="submit">@include('seller.partials.icon', ['name' => 'send', 'size' => 14]) Send Reply</button>
                </div>
              </form>
            </div>
          </div>
          @endif
        </div>
        @empty
        <div class="empty" style="padding:40px 20px">
          <div class="ic">@include('seller.partials.icon', ['name' => 'chat', 'size' => 28])</div>
          <h3>No reviews yet</h3><p>Reviews will appear here once buyers rate a completed order.</p>
        </div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Rating Breakdown</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        @foreach([5,4,3,2,1] as $star)
        <div style="display:flex;align-items:center;gap:10px;font-size:12px">
          <span style="width:14px;text-align:right;color:var(--muted)">{{ $star }}</span>
          <span style="color:#f59e0b">★</span>
          <div style="flex:1;height:8px;background:var(--border);border-radius:4px;overflow:hidden">
            <div style="height:100%;background:{{ $star>=4 ? '#f59e0b' : 'var(--border)' }};width:{{ $breakdown[$star] }}%;border-radius:4px"></div>
          </div>
          <span style="width:28px;text-align:right;color:var(--muted);font-family:var(--font-mono)">{{ $reviews->where('rating', $star)->count() }}</span>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
@endsection
