@php
  $itemReviews = \App\Models\Review::where('order_id', $order->id)->where('buyer_id', auth()->id())->get()->keyBy('item_key');
  $serviceReviews = \Illuminate\Support\Facades\Schema::hasTable('order_service_reviews')
    ? \Illuminate\Support\Facades\DB::table('order_service_reviews')->where('order_id', $order->id)->where('buyer_id', auth()->id())->get()->keyBy('review_type')
    : collect();
  $completedAt = \Illuminate\Support\Facades\DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->max('created_at') ?? $order->updated_at;
  $withinReviewWindow = \Illuminate\Support\Carbon::parse($completedAt)->gte(now()->subDays(30));
@endphp
<div class="order-details-modal order-review-modal review-compose-modal" id="rateOrder{{ $order->id }}" aria-hidden="true">
  <div class="order-details-dialog" role="dialog" aria-modal="true" aria-label="Rate your order">
    <button type="button" class="modal-close order-details-close review-compose-close" aria-label="Close reviews">&times;</button>
    <div class="order-details-body shopee-review-sheet">
      <p class="review-sheet-intro">Your review helps other buyers make better choices.</p>
      @foreach($order->itemsWithImages() as $key => $item)
        @php($itemReview = $itemReviews->get((string) $key))
        <section class="review-sheet-section">
          @if($withinReviewWindow || $itemReview)
            <form method="POST" enctype="multipart/form-data" action="{{ $itemReview ? route('buyer.commerce.review.edit', [$order, $itemReview]) : route('buyer.commerce.review', $order) }}">
              @csrf
              <input type="hidden" name="review_type" value="product"><input type="hidden" name="item_key" value="{{ $key }}">
              <div class="review-product-rating-row">
              <div class="review-product-summary">@include('buyer.partials.order-product-summary', ['item'=>$item])</div>
              <div class="review-product-rating-controls">
              @if($itemReview)<div class="review-rating-heading">Original rating: {{ number_format($itemReview->rating,1) }}</div>
              @else<div class="review-rating-heading">How was this product?</div>
              <div class="review-star-row" role="radiogroup" aria-label="Rate {{ $item['name'] }}">@foreach(array_reverse(range(1, 5)) as $star)<label><input type="radio" name="rating" value="{{ $star }}" aria-label="{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}" @checked((int)$itemReview?->rating === $star) required><span>&#9733;</span></label>@endforeach</div>
              @endif
              </div></div>
              <div class="review-comment-toolbar">
              <label class="review-comment-label" for="productReviewComment{{ $order->id }}-{{ $key }}">{{ $itemReview ? 'Follow-up review' : 'Your review' }} @unless($itemReview)<span>(optional)</span>@endunless</label>
              <label class="review-media-picker">@include('buyer.partials.icon',['name'=>'camera','size'=>14])<span>Add photos or video</span><input type="file" name="media[]" accept="image/jpeg,image/png,video/mp4,video/quicktime" multiple></label>
              </div>
              <textarea id="productReviewComment{{ $order->id }}-{{ $key }}" name="comment" rows="3" maxlength="500" placeholder="{{ $itemReview ? 'How has your experience been since your first review?' : 'What did you like about this product?' }}"></textarea>
              <small class="review-file-selection" data-review-file-selection hidden></small>
              <small class="review-media-note">Up to 5 photos or videos. Max 5 MB per photo and 20 MB per video.</small>
              <div class="order-modal-actions"><button class="btn btn-primary">{{ $itemReview ? 'Post follow-up review' : 'Submit product review' }}</button></div>
            </form>
          @else <div class="review-product-summary">@include('buyer.partials.order-product-summary', ['item'=>$item])</div><p class="review-closed-note">The 30-day product review period has ended.</p> @endif
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
  </div>
</div>
<div class="order-details-modal order-review-modal my-review-modal" id="myReviews{{ $order->id }}" aria-hidden="true">
 <div class="order-details-dialog" role="dialog" aria-modal="true" aria-label="My reviews">
  @php($reviewCloseShown = false)
  <div class="order-details-body shopee-review-sheet">
   @foreach($order->itemsWithImages() as $key=>$item)
    @php($savedReview=$itemReviews->get((string)$key))
    @if($savedReview)
    <section class="review-sheet-section">
     <div class="my-review-product-row">
      <div class="review-product-summary">@include('buyer.partials.order-product-summary',['item'=>$item])</div>
      <div class="my-review-rating-actions"><div class="my-review-rating" aria-label="{{ $savedReview->rating }} out of 5 stars"><strong>{{ number_format($savedReview->rating, 1) }}</strong><span aria-hidden="true">@for($star=1;$star<=5;$star++){{ $star<=$savedReview->rating ? '★' : '☆' }}@endfor</span></div>
       @unless($reviewCloseShown)<span class="my-review-rating-divider" aria-hidden="true"></span><button type="button" class="modal-close order-details-close my-review-close" aria-label="Close my reviews">&times;</button>@php($reviewCloseShown = true)@endunless
      </div>
     </div>
     <p class="my-review-comment">{{ $savedReview->comment ?: 'No written review' }}</p>
     <small class="review-media-note">Posted {{ $savedReview->created_at?->format('M d, Y') }}@if($savedReview->edited_at) · Updated {{ $savedReview->edited_at->format('M d, Y') }}@endif</small>
     <div class="my-review-media">
      @foreach($savedReview->media ?? [] as $media)
       @if(($media['type'] ?? '')==='video')<video controls preload="metadata" src="{{ Storage::disk('public')->url($media['path']) }}" aria-label="Your review video"></video>
       @else<a href="{{ Storage::disk('public')->url($media['path']) }}" target="_blank" rel="noopener"><img src="{{ Storage::disk('public')->url($media['path']) }}" alt="Your review photo" loading="lazy"></a>@endif
      @endforeach
      @if(empty($savedReview->media))@foreach($savedReview->photos ?? [] as $photo)<a href="{{ Storage::disk('public')->url($photo) }}" target="_blank" rel="noopener"><img src="{{ Storage::disk('public')->url($photo) }}" alt="Your review photo" loading="lazy"></a>@endforeach @endif
     </div>
     <div class="my-review-seller-reply"><strong>Seller reply</strong><p>{{ $savedReview->seller_reply ?: 'The seller has not replied yet. Check back here for updates.' }}</p></div>
     @include('partials.review-follow-ups',['review'=>$savedReview])
    </section>
    @endif
   @endforeach
  </div>
 </div>
</div>
