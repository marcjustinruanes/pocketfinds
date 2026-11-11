<section class="seller-cancellation-refund" aria-label="Cancellation refund">
  <div><strong>Cancellation refund · ₱{{ number_format($cancellationRefund->amount,2) }}</strong><span>{{ ucfirst($cancellationRefund->status) }}</span></div>
  @if($cancellationRefund->status === 'completed')
    <p>Repayment reference: {{ $cancellationRefund->repayment_reference }}</p>
  @elseif($order->gcashPurchase?->usesAdminReview())
    <p>The platform admin handles this repayment to the original GCash payer.</p>
  @else
    <p>Repay the original GCash payer before recording completion.</p>
    <form method="POST" action="{{ route('account.cancellation-refunds.complete',$cancellationRefund->id) }}">
      @csrf
      <label>Repayment reference<input name="repayment_reference" required minlength="6" maxlength="100"></label>
      <label class="seller-refund-confirm"><input type="checkbox" name="confirm_repaid" value="1" required> I have repaid the full amount to the original payer.</label>
      <button type="submit" class="btn btn-primary">Record completed repayment</button>
    </form>
  @endif
</section>
