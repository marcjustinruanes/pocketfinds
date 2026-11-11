@if(in_array($order->status, \App\Models\Order::CANCELLABLE_STATUSES, true))
@php($adminRefund = $order->gcashPurchase?->usesAdminReview() && $order->gcashPurchase?->verified_at)
<div class="order-cancel-panel" data-seller-cancel-panel hidden>
    <form method="POST" action="{{ route('seller.orders.cancel', $order) }}">
      @csrf @method('PATCH')
      <div class="seller-cancel-fields">
        <div class="form-row">
          <label for="sellerCancelReason{{ $order->id }}">Reason</label>
          <select name="cancellation_reason" id="sellerCancelReason{{ $order->id }}" required>
            <option value="Out of stock">Out of stock</option>
            <option value="Unable to fulfil this order">Unable to fulfil this order</option>
          </select>
        </div>
        <div class="form-row">
          <label for="sellerCancelNote{{ $order->id }}">{{ $adminRefund ? 'Note to admin (optional)' : 'Note to the buyer (optional)' }}</label>
          <textarea name="cancellation_note" id="sellerCancelNote{{ $order->id }}" rows="2" maxlength="500"></textarea>
        </div>
        <p style="margin:0;font-size:12px;color:var(--muted)">
          {{ $adminRefund ? 'Admin receives the cancellation reason and note and handles returning the GCash payment to the buyer. The refund remains pending until admin uploads proof of repayment.' : 'The buyer is notified right away.' }} <span>Items already reserved for this order go back into your stock.</span> This can't be undone.
        </p>
      </div>
      <div class="seller-cancel-actions">
        <button type="button" class="btn btn-outline" data-seller-cancel-back>Keep Order</button>
        <button type="submit" class="btn btn-danger">{{ $adminRefund ? 'Cancel & notify admin' : 'Cancel Order' }}</button>
      </div>
    </form>
</div>
@endif
