@extends('admin.layout')
@section('title', 'Complaints & Disputes')
@section('page-title', 'Complaints & Disputes')
@section('page-sub', 'Manage buyer/seller disputes and platform complaints')

@section('content')
<div class="card">
  <div class="card-head"><div><h2>Dispute Cases</h2><p>{{ $complaints->count() }} total cases</p></div></div>
  <div class="card-pad">
    <div class="filter-bar">
      <div class="search-mini">
        <span class="ic"><x-admin-icon name="search" /></span>
        <input type="text" placeholder="Search case..." data-table-search="dispTable">
      </div>
    </div>
    @php
      $complaintsByStatus = $complaints->countBy('status');
    @endphp
    <div data-tabs>
      <a class="tab active" data-tab="all">All <span class="tab-count">{{ $complaints->count() }}</span></a>
      <a class="tab" data-tab="open">Open <span class="tab-count">{{ $complaintsByStatus->get('open', 0) }}</span></a>
      <a class="tab" data-tab="escalated">Escalated <span class="tab-count">{{ $complaintsByStatus->get('escalated', 0) }}</span></a>
      <a class="tab" data-tab="resolved">Resolved <span class="tab-count">{{ $complaintsByStatus->get('resolved', 0) }}</span></a>
    </div>
    <div class="table-wrap">
      <table class="dtable" id="dispTable">
        <thead><tr><th>Case ID</th><th>Filed By</th><th>Against</th><th>Type</th><th>Subject</th><th>Date</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse($complaints as $c)
          <tr class="rail-row rail-{{ $c->status }}" data-type="{{ $c->status }}">
            <td class="mono">#{{ strtoupper(substr($c->id, 0, 8)) }}</td>
            <td>
              @if($c->complainant)
                <div class="cell-user">
                  <div class="avatar-sm">{{ strtoupper(substr($c->complainant->given_names,0,1).substr($c->complainant->last_name,0,1)) }}</div>
                  <div><strong>{{ $c->complainant->given_names }} {{ $c->complainant->last_name }}</strong><span>{{ ucfirst($c->complainant->account_type) }}</span></div>
                </div>
              @else — @endif
            </td>
            <td>
              @if($c->respondent)
                <div class="cell-user">
                  <div class="avatar-sm">{{ strtoupper(substr($c->respondent->given_names,0,1).substr($c->respondent->last_name,0,1)) }}</div>
                  <div><strong>{{ $c->respondent->given_names }} {{ $c->respondent->last_name }}</strong><span>{{ ucfirst($c->respondent->account_type) }}</span></div>
                </div>
              @else — @endif
            </td>
            <td style="font-size:12px">{{ $c->complaint_type ?? '—' }}</td>
            <td style="font-size:12.5px;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $c->subject }}</td>
            <td class="mono" style="font-size:12px">{{ $c->created_at?->format('M d, Y') }}</td>
            <td><span class="stamp stamp-{{ $c->status }}">{{ ucfirst($c->status) }}</span></td>
            <td>
              <div class="row-actions">
                <button class="btn btn-sm btn-outline" data-modal-open="dispModal-{{ $c->id }}">Open</button>
              </div>
            </td>
          </tr>

          <div class="modal-overlay" id="dispModal-{{ $c->id }}">
            <div class="modal modal-lg">
              <div class="modal-head">
                <div class="modal-head-main">
                  <span class="modal-icon"><x-admin-icon name="flag" /></span>
                  <div class="modal-head-copy">
                    <h3>Case #{{ strtoupper(substr($c->id, 0, 8)) }}</h3>
                    <p>{{ $c->complainant?->given_names }} vs {{ $c->respondent?->given_names }}</p>
                  </div>
                </div>
                <button class="modal-close" data-modal-close aria-label="Close"><x-admin-icon name="close" /></button>
              </div>
              <div class="modal-body">
                <div class="detail-grid">
                  <div><div class="field-label">Filed By</div><div class="field-value">{{ $c->complainant ? $c->complainant->given_names.' '.$c->complainant->last_name.' ('.ucfirst($c->complainant->account_type).')' : '—' }}</div></div>
                  <div><div class="field-label">Against</div><div class="field-value">{{ $c->respondent ? $c->respondent->given_names.' '.$c->respondent->last_name.' ('.ucfirst($c->respondent->account_type).')' : '—' }}</div></div>
                  <div><div class="field-label">Type</div><div class="field-value">{{ $c->complaint_type ?? '—' }}</div></div>
                  <div><div class="field-label">Status</div><div class="field-value"><span class="stamp stamp-{{ $c->status }}">{{ ucfirst($c->status) }}</span></div></div>
                  <div class="full"><div class="field-label">Subject</div><div class="field-value">{{ $c->subject }}</div></div>
                  @if($c->description)
                  <div class="full"><div class="field-label">Description</div><div class="field-value" style="font-size:13px;line-height:1.6">{{ $c->description }}</div></div>
                  @endif
                  @if($c->shop_name || $c->message_id)
                  <div class="full"><div class="field-label">Reported Shop</div><div class="field-value">{{ $c->shop_name ?: '—' }}</div></div>
                  <div class="full"><div class="field-label">Reported Message</div><div class="field-value" style="font-size:13px;line-height:1.6">{{ $c->message_body ?: ucfirst($c->message_type ?: 'Attachment') }}</div></div>
                  @endif
                  @if($c->evidence_path)
                  <div class="full"><div class="field-label">Evidence</div>
                    @if($c->evidence_type === 'video')
                      <video src="{{ route('report.evidence', ['path' => $c->evidence_path]) }}" controls style="max-width:100%;max-height:280px;border-radius:9px"></video>
                    @else
                      <img src="{{ route('report.evidence', ['path' => $c->evidence_path]) }}" alt="Report evidence" style="max-width:100%;max-height:280px;object-fit:contain;border-radius:9px">
                    @endif
                    <div style="font-size:11px;color:var(--muted);margin-top:5px">{{ $c->evidence_name }}</div>
                  </div>
                  @endif
                  @if($c->resolution)
                  <div class="full"><div class="field-label">Resolution</div><div class="field-value">{{ $c->resolution }}</div></div>
                  @endif
                </div>
              </div>
              <div class="modal-foot">
                <button class="btn btn-outline" data-modal-close>Close</button>
                @if($c->status !== 'resolved')
                <form method="POST" action="{{ route('admin.complaints.resolve', $c->id) }}">
                  @csrf @method('PATCH')
                  <button class="btn btn-success" type="submit">Mark Resolved</button>
                </form>
                @endif
              </div>
            </div>
          </div>
          @empty
          <tr><td colspan="8"><div class="empty"><div class="ic"><x-admin-icon name="flag" /></div><h3>No complaints yet</h3></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
