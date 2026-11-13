@foreach(['return' => 'Return / refund', 'cancellation_refund' => 'Cancellation refund'] as $field => $label)
@if($refund = $detail[$field])
<section class="order-quantity-summary order-record-section" aria-label="{{ $label }} record">
  <h3>{{ $label }}</h3>
  <div class="order-detail-info-grid">
    <div><span>Status</span><strong>{{ str($refund->status)->replace('_', ' ')->title() }}</strong></div>
    <div><span>{{ in_array($refund->status, ['refunded', 'completed']) ? 'Recorded refund amount' : 'Requested refund amount' }}</span><strong>PHP {{ number_format($refund->amount, 2) }}</strong></div>
    @if($refund->reason ?? null)<div><span>Reason</span><strong>{{ $refund->reason }}</strong></div>@endif
    @if($refund->decision_reason ?? null)<div><span>Decision</span><strong>{{ $refund->decision_reason }}</strong></div>@endif
    @if($reference = ($refund->repayment_reference ?? $refund->refund_reference ?? null))<div><span>Repayment reference</span><strong>{{ $reference }}</strong></div>@endif
    @if($refund->completed_at ?? null)<div><span>Refund completed</span><strong>{{ \Illuminate\Support\Carbon::parse($refund->completed_at)->format('M j, Y g:i A') }}</strong></div>@endif
    @if($refund->status === 'refunded' && ($refund->updated_at ?? null))<div><span>Refund recorded</span><strong>{{ \Illuminate\Support\Carbon::parse($refund->updated_at)->format('M j, Y g:i A') }}</strong></div>@endif
  </div>
  @unless(in_array($refund->status, ['refunded', 'completed']))<p>This request is not proof of a completed repayment.</p>@endunless
</section>
@endif
@endforeach
