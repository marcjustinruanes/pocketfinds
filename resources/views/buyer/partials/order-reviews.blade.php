@php
  $itemReviews=\App\Models\Review::where('order_id',$order->id)->where('buyer_id',auth()->id())->get()->keyBy('item_key');
  $completedAt=\Illuminate\Support\Facades\DB::table('order_status_history')->where('order_id',$order->id)->where('status','completed')->max('created_at')??$order->updated_at;
  $canReview=\Illuminate\Support\Carbon::parse($completedAt)->gte(now()->subDays(30));
@endphp
<div class="order-details-modal" id="rateOrder{{ $order->id }}" aria-hidden="true">
<div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="rateOrderTitle{{ $order->id }}">
<div class="order-details-head"><div><span class="order-kicker">Product reviews</span><h3 id="rateOrderTitle{{ $order->id }}">{{ $order->order_number }}</h3></div><button type="button" class="modal-close order-details-close" aria-label="Close reviews">&times;</button></div>
<div class="order-details-body">
@foreach($order->items as $key=>$item)
@php($itemReview=$itemReviews->get((string)$key))
<div class="card-pad"><h3>{{ $item['name'] }}</h3>
@if($itemReview)<p>{{ $itemReview->rating }}/5 · {{ $itemReview->comment }}</p><div class="order-actions">@foreach($itemReview->photos??[] as $photo)<a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo) }}" target="_blank" rel="noopener"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo) }}" alt="Review photo" width="64" height="64" style="object-fit:cover;border-radius:8px"></a>@endforeach</div>@endif
@if($canReview&&(!$itemReview||!$itemReview->edited_at))
<form method="POST" enctype="multipart/form-data" action="{{ $itemReview?route('buyer.commerce.review.edit',[$order,$itemReview]):route('buyer.commerce.review',$order) }}">@csrf<input type="hidden" name="item_key" value="{{ $key }}">
<div class="form-row"><label>Rating<select name="rating" class="select" required><option value="">Select rating</option>@foreach(range(1,5) as $star)<option value="{{ $star }}" @selected($itemReview?->rating===$star)>{{ $star }} star{{ $star>1?'s':'' }}</option>@endforeach</select></label></div>
<div class="form-row"><label>Review<textarea name="comment" rows="3" maxlength="500" placeholder="Share your experience (up to 500 characters)">{{ $itemReview?->comment }}</textarea></label></div>
<div class="form-row"><label>Photos (optional)<input type="file" name="photos[]" accept="image/jpeg,image/png" multiple></label><small>Up to five JPG/PNG photos, 5 MB each. {{ $itemReview?'New uploads replace your existing photos.':'' }}</small></div>
<div class="order-modal-actions"><button class="btn btn-primary">{{ $itemReview?'Save One-time Edit':'Submit Review' }}</button></div></form>
@else<p>{{ !$canReview?'The 30-day review period has ended.':'This review has already been edited once.' }}</p>@endif
</div>@endforeach
</div><div class="order-details-footer"><button type="button" class="btn btn-outline order-details-close">Done</button></div></div></div>
