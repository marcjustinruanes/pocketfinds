<div class="order-details-modal order-refund-modal" id="refundOrder{{ $order->id }}" aria-hidden="true">
 <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="refundTitle{{ $order->id }}">
  <header class="order-details-head"><div><span class="order-kicker">Order {{ $order->order_number }}</span><h3 id="refundTitle{{ $order->id }}">Request Return / Refund</h3></div><button type="button" class="modal-close order-details-close" aria-label="Close refund request">&times;</button></header>
  <form method="POST" enctype="multipart/form-data" action="{{ route('buyer.commerce.return',$order) }}">
   @csrf
   <div class="order-details-body">
    <fieldset class="refund-items"><legend>Select items to return</legend>
     @foreach($order->itemsWithImages() as $key=>$item)
     <label class="refund-item"><input type="checkbox" name="item_keys[]" value="{{ $key }}"><span class="refund-item-copy">@include('buyer.partials.order-product-summary',['item'=>$item])<small>Quantity: {{ $item['qty'] }}</small></span></label>
     @endforeach
    </fieldset>
    <small class="refund-photo-hint refund-photo-hint-outside">Attach 1–5 JPG/PNG photos, up to 5 MB each.</small>
    <div class="form-row"><label for="refundReason{{ $order->id }}">Reason</label><select id="refundReason{{ $order->id }}" name="reason" required><option value="">Select a reason</option>@foreach(['Damaged item','Wrong item','Missing item','Not as described'] as $reason)<option>{{ $reason }}</option>@endforeach</select></div>
    <div class="form-row"><label for="refundDescription{{ $order->id }}">Describe the issue</label><textarea id="refundDescription{{ $order->id }}" name="description" rows="3" minlength="10" maxlength="500" required placeholder="What happened? Include details that help us understand the issue."></textarea><small>10–500 characters.</small></div>
    <label class="refund-photo-field refund-photo-upload" for="refundPhotos{{ $order->id }}">
     <span class="refund-photo-caption">Photo evidence</span>
     <span class="review-media-picker refund-photo-picker"><span>Choose photos</span><input id="refundPhotos{{ $order->id }}" type="file" name="photos[]" accept="image/jpeg,image/png" multiple required></span>
     <span class="refund-upload-arrow" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m-4 4 4-4 4 4M4 16v4h16v-4"/></svg></span>
    </label>
    <small class="review-file-selection" data-review-file-selection hidden></small>
    <small class="refund-photo-hint refund-photo-hint-outside">Attach 1–5 JPG/PNG photos, up to 5 MB each.</small>
    <section class="refund-destination"><h4>Where your refund goes</h4>
     @if($order->gcash_purchase_id)<input type="hidden" name="refund_destination" value="Original GCash payment"><p>Approved refunds return to the original GCash payment account.</p>
     @else<p>For this COD order, provide the GCash account that should receive your approved refund.</p><div class="refund-account-fields"><div class="form-row"><label for="refundName{{ $order->id }}">GCash account name</label><input id="refundName{{ $order->id }}" type="text" name="refund_account_name" minlength="2" maxlength="150" required placeholder="Full account name"></div><div class="form-row"><label for="refundNumber{{ $order->id }}">GCash mobile number</label><span class="refund-mobile-field"><span class="refund-mobile-prefix">+63</span><input id="refundNumber{{ $order->id }}" type="tel" name="refund_gcash_number" inputmode="numeric" pattern="9[0-9]{9}" maxlength="10" required placeholder="9XXXXXXXXX" aria-label="GCash mobile number, 10 digits after +63"></span></div></div>@endif
    </section>
    <p class="refund-review-note">Requests are reviewed before repayment. @unless(app()->environment('local') && config('buyer-commerce.refund_test_mode'))Submit within seven days of delivery.@endunless</p>
   </div>
   <footer class="order-details-footer"><button type="button" class="btn btn-outline order-details-close">Cancel</button><button type="submit" class="btn btn-primary">Submit refund request</button></footer>
  </form>
 </div>
</div>
