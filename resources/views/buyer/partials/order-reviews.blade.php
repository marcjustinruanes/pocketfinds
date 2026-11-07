@php
  $itemReviews = \App\Models\Review::where('order_id', $order->id)->where('buyer_id', auth()->id())->get()->keyBy('item_key');
  $serviceReviews = \Illuminate\Support\Facades\Schema::hasTable('order_service_reviews')
    ? \Illuminate\Support\Facades\DB::table('order_service_reviews')->where('order_id', $order->id)->where('buyer_id', auth()->id())->get()->keyBy('review_type')
    : collect();
  $completedAt = \Illuminate\Support\Facades\DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->max('created_at') ?? $order->updated_at;
  $withinReviewWindow = \Illuminate\Support\Carbon::parse($completedAt)->gte(now()->subDays(30));
@endphp
<div class="order-details-modal" id="rateOrder{{ $order->id }}" aria-hidden="true">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-labelledby="rateOrderTitle{{ $order->id }}">
    <div class="order-details-head">
      <div><span class="order-kicker">Share your experience</span><h3 id="rateOrderTitle{{ $order->id }}">Rate your order</h3></div>
      <button type="button" class="modal-close order-details-close" aria-label="Close reviews">&times;</button>
    </div>
    <div class="order-details-body shopee-review-sheet">
      <p class="review-sheet-intro">Your review helps other buyers make better choices.</p>
      @foreach($order->items as $key => $item)
        @php($itemReview = $itemReviews->get((string) $key))
        <section class="review-sheet-section">
          <div class="review-product-summary">
            <div class="review-product-thumb">@if(!empty($item['img']))<img src="{{ $item['img'] }}" alt="{{ $item['name'] }}">@endif</div>
            <div><h3>{{ $item['name'] }}</h3>@if(($item['variation_value'] ?? null) || ($item['size'] ?? null))<small>{{ collect([$item['variation_group'] ?? null, $item['variation_value'] ?? null, $item['size'] ?? null])->filter()->join(': ') }}</small>@endif</div>
          </div>
          @if($withinReviewWindow || $itemReview)
            <form method="POST" enctype="multipart/form-data" action="{{ $itemReview ? route('buyer.commerce.review.edit', [$order, $itemReview]) : route('buyer.commerce.review', $order) }}">
              @csrf
              <input type="hidden" name="review_type" value="product"><input type="hidden" name="item_key" value="{{ $key }}">
              <div class="review-star-row" role="radiogroup" aria-label="Rate {{ $item['name'] }}">@foreach(array_reverse(range(1, 5)) as $star)<label><input type="radio" name="rating" value="{{ $star }}" @checked($itemReview?->rating === $star) required><span>&#9733;</span></label>@endforeach</div>
              <textarea name="comment" rows="3" maxlength="500" placeholder="What did you like about this product? (optional)">{{ $itemReview?->comment }}</textarea>
              <label class="review-media-picker">@include('buyer.partials.icon',['name'=>'camera','size'=>17])<span>Add photos or video</span><input type="file" name="media[]" accept="image/jpeg,image/png,video/mp4,video/quicktime" multiple></label>
              <small class="review-media-note">Up to 5 photos or videos. Max 5 MB per photo and 20 MB per video.</small>
              <div class="order-modal-actions"><button class="btn btn-primary">{{ $itemReview ? 'Update product review' : 'Submit product review' }}</button></div>
            </form>
          @else <p class="review-closed-note">The 30-day product review period has ended.</p> @endif
        </section>
      @endforeach
      @foreach(['shop' => ['Rate the shop', 'How was your overall experience with '.($order->seller?->business_name ?: 'this shop').'?', 'Leave a comment for the shop (optional)'], 'rider' => ['Rate the rider', $order->shipment?->courier?->given_names ? 'How was your delivery with '.$order->shipment->courier->given_names.'?' : 'How was your delivery experience?', 'Leave a separate comment for the rider (optional)']] as $type => [$title, $question, $placeholder])
          @php($serviceReview = $serviceReviews->get($type))
          @if($withinReviewWindow || $serviceReview)
          <section class="review-sheet-section review-service-feedback">
            <h3>{{ $title }}</h3><p>{{ $question }}</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('buyer.commerce.review', $order) }}">
              @csrf<input type="hidden" name="review_type" value="{{ $type }}">
              <div class="review-star-row" role="radiogroup" aria-label="{{ $title }}">@foreach(array_reverse(range(1, 5)) as $star)<label><input type="radio" name="rating" value="{{ $star }}" @checked($serviceReview?->rating === $star) required><span>&#9733;</span></label>@endforeach</div>
              <textarea name="comment" rows="2" maxlength="500" placeholder="{{ $placeholder }}">{{ $serviceReview?->comment }}</textarea>
              <label class="review-media-picker">@include('buyer.partials.icon',['name'=>'camera','size'=>17])<span>Add photos or video</span><input type="file" name="media[]" accept="image/jpeg,image/png,video/mp4,video/quicktime" multiple></label>
              <div class="order-modal-actions"><button class="btn btn-outline">{{ $serviceReview ? 'Update' : 'Submit' }} {{ $type }} feedback</button></div>
            </form>
          </section>
          @endif
        @endforeach
    </div>
    <div class="order-details-footer"><button type="button" class="btn btn-outline order-details-close">Done</button></div>
  </div>
</div>
