@extends('seller.layout')
@section('title', 'Customer Reviews')
@section('page-title', 'Customer Reviews')
@section('body-class','seller-products-page')
@push('head')
<link rel="stylesheet" href="{{ asset('css/seller-products.css') }}?v={{ filemtime(public_path('css/seller-products.css')) }}">
<link rel="stylesheet" href="{{ asset('css/seller-reviews.css') }}?v={{ filemtime(public_path('css/seller-reviews.css')) }}">
@endpush
@section('content')
<div class="seller-reviews">
 @if(session('review_success'))
 <dialog class="review-posted-dialog" data-review-posted aria-labelledby="reviewPostedTitle" aria-describedby="reviewPostedDescription">
  <form method="dialog">
   <button type="submit" class="review-posted-close" aria-label="Close confirmation">&times;</button>
   <span class="review-posted-icon" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>
   <h2 id="reviewPostedTitle">Reply posted</h2>
   <p id="reviewPostedDescription">Your reply is now visible to the buyer and other shoppers.</p>
   <button type="submit" class="btn btn-primary review-posted-done" autofocus>Done</button>
  </form>
 </dialog>
 @endif
 @if($errors->any())<div class="reviews-notice reviews-error" role="alert">{{ $errors->first() }}</div>@endif
 <div class="reviews-topbar">
 @include('seller.partials.product-navigation')
 <label class="reviews-search">@include('buyer.partials.icon',['name'=>'search','size'=>17])<input type="search" id="reviewSearch" placeholder="Search buyer, product or review" aria-label="Search reviews"></label>
 <section class="reviews-summary-strip" aria-label="Review summary">
  <div class="reviews-top-stat"><strong>{{ $total }}</strong><span>Total ratings</span></div>
  <div class="reviews-top-stat"><strong>{{ $total }}</strong><span>Total reviews</span></div>
 </section>
 </div>
 <div class="reviews-columns">
 <section class="reviews-feed" aria-label="Customer reviews" tabindex="0">
  <span id="reviewResultCount" class="reviews-sr-only" aria-live="polite"></span>

  <div class="reviews-list">
  @forelse($reviews as $review)
   <article class="review-card" data-rating="{{ $review->rating }}" data-replied="{{ $review->seller_reply ? 'true' : 'false' }}">
    <header class="review-card-head"><div class="review-author"><span class="review-avatar">{{ mb_strtoupper(mb_substr($review->buyer?->given_names ?: '?',0,1)) }}</span><div><h3>{{ $review->buyer?->given_names ?: 'Buyer' }} {{ $review->buyer?->last_name }}</h3><time datetime="{{ $review->created_at?->toDateString() }}">{{ $review->created_at?->format('M d, Y') }}</time></div></div><div class="review-header-meta"><div class="review-rating" aria-label="{{ $review->rating }} out of 5 stars"><strong>{{ number_format($review->rating, 1) }}</strong><div aria-hidden="true">@for($s=1;$s<=5;$s++)<span class="{{ $s <= $review->rating ? 'filled' : '' }}">&#9733;</span>@endfor</div></div><span class="review-meta-divider" aria-hidden="true"></span><span class="review-status {{ $review->seller_reply ? 'is-replied' : '' }}">{{ $review->seller_reply ? 'Replied' : 'Needs reply' }}</span></div></header>
    <div class="review-card-content">

    @if($review->comment)<p class="review-comment">{{ $review->comment }}</p>@else<p class="review-no-comment">No written review</p>@endif
    <div class="review-product"><span class="review-product-icon" aria-hidden="true">@include('seller.partials.icon',['name'=>'image','size'=>20])</span><div class="review-product-details"><span>Reviewed product</span><strong>{{ $review->product?->name ?: 'Product removed' }}</strong>@if($review->variationLabel())<small>{{ $review->variationLabel() }}</small>@endif</div></div>
    @if($review->photos)<div class="review-photos">@foreach($review->photos as $photo)<a href="{{ Storage::disk('public')->url($photo) }}" target="_blank" rel="noopener" aria-label="Open buyer review photo {{ $loop->iteration }}"><img src="{{ Storage::disk('public')->url($photo) }}" alt="Buyer review photo" loading="lazy" width="76" height="76"></a>@endforeach</div>@endif
    @if($review->edited_at)<small class="review-edited">Edited by the buyer</small>@endif
    @include('partials.review-follow-ups',['review'=>$review])
    </div>
    @if($review->seller_reply)<div class="review-reply"><span>Your reply</span><p>{{ $review->seller_reply }}</p></div>
    @else
    <button class="btn btn-outline review-reply-button" type="button" data-modal="replyModal{{ $review->id }}">@include('seller.partials.icon',['name'=>'send','size'=>14]) Reply to buyer</button>
    <div class="modal-overlay" id="replyModal{{ $review->id }}"><div class="modal review-reply-dialog" role="dialog" aria-modal="true" aria-labelledby="replyTitle{{ $review->id }}"><div class="modal-head"><div><h3 id="replyTitle{{ $review->id }}">Reply to {{ $review->buyer?->given_names ?: 'buyer' }}</h3><p>Your reply will be visible to all buyers.</p></div><button type="button" class="modal-close" data-modal-close aria-label="Close reply">&times;</button></div><form method="POST" action="{{ route('seller.reviews.reply',$review) }}">@csrf<div class="modal-body"><div class="form-row"><label for="replyText{{ $review->id }}">Your reply</label><textarea id="replyText{{ $review->id }}" name="seller_reply" rows="5" maxlength="1000" placeholder="Thank the buyer or help with their concern…" required></textarea><small>Up to 1,000 characters.</small></div></div><div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Send reply</button></div></form></div></div>
    @endif
   </article>
  @empty
   <div class="reviews-empty"><span>@include('seller.partials.icon',['name'=>'chat','size'=>30])</span><h3>Your first review is on its way</h3><p>Buyer ratings and feedback will appear here after completed orders.</p></div>
  @endforelse
  <div class="reviews-empty" id="reviewNoResults" hidden><h3>No matching reviews</h3><p>Try another search or reset your filters.</p><button type="button" class="btn btn-outline" id="reviewReset">Reset filters</button></div>
  </div>
 </section>
 <aside class="reviews-ratings-card" aria-labelledby="ratingsTitle" tabindex="0">
  <div class="reviews-ratings-heading"><div><h2 id="ratingsTitle">Monthly ratings</h2><p>Last 6 months · All star ratings</p></div>
  <div class="reviews-sidebar-score"><span aria-hidden="true">&#9733;</span><strong>{{ $total ? number_format($avgRating, 1) : '—' }}</strong><span>/ 5</span></div></div>

  @php
   $chartMaximum=max(1,max(array_map(fn($month)=>array_sum($month['counts']),$reviewMonths)));
   $ratingColors=[1=>'#ead2df',2=>'#dba3c1',3=>'#c86b9e',4=>'#bc387f',5=>'#79506a'];
   $cumulative=array_fill(0,6,0);$ratingAreas=[];
   $curve=function($points,$command='M'){
    $coordinates=array_map(fn($point)=>array_map('floatval',explode(',',$point)),$points);
    $path=$command.implode(',',$coordinates[0]);
    for($index=1;$index<count($coordinates);$index++){
     [$previousX,$previousY]=$coordinates[$index-1];[$nextX,$nextY]=$coordinates[$index];$middleX=($previousX+$nextX)/2;
     $path.=' C'.$middleX.','.$previousY.' '.$middleX.','.$nextY.' '.$nextX.','.$nextY;
    }
    return $path;
   };
   foreach(range(1,5) as $ratingStar){
    $lower=[];$upper=[];
    foreach($reviewMonths as $monthIndex=>$month){
     $x=26+$monthIndex*48;
     $lower[]=$x.','.round(130-$cumulative[$monthIndex]/$chartMaximum*105,2);
     $cumulative[$monthIndex]+=$month['counts'][$ratingStar];
     $upper[]=$x.','.round(130-$cumulative[$monthIndex]/$chartMaximum*105,2);
    }
    $ratingAreas[$ratingStar]=['fill'=>$curve($upper).' '.$curve(array_reverse($lower),'L').' Z','line'=>$curve($upper)];
   }
  @endphp
  <div class="reviews-area-chart reviews-monthly-chart" role="group" aria-label="Monthly review counts by star rating">
   <div class="reviews-chart-period"><span>Review activity</span><strong>{{ \Illuminate\Support\Carbon::parse($reviewMonths[0]['key'].'-01')->format('M Y') }} – {{ \Illuminate\Support\Carbon::parse($reviewMonths[5]['key'].'-01')->format('M Y') }}</strong></div>
   <svg viewBox="0 0 292 158" role="group" aria-labelledby="monthlyRatingsTitle monthlyRatingsDescription">
    <title id="monthlyRatingsTitle">Monthly review counts for the last six months</title>
    <desc id="monthlyRatingsDescription">Five colored areas show the number of 1-star through 5-star reviews each month. Months without reviews remain at zero.</desc>
    <defs>@foreach(range(1,5) as $ratingStar)<linearGradient id="reviewArea{{ $ratingStar }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="{{ $ratingColors[$ratingStar] }}" stop-opacity="0.7"/><stop offset="100%" stop-color="{{ $ratingColors[$ratingStar] }}" stop-opacity="0.3"/></linearGradient>@endforeach</defs>
    @foreach([25,60,95,130] as $gridY)<line x1="26" y1="{{ $gridY }}" x2="266" y2="{{ $gridY }}" stroke="#eeedf0" stroke-width="0.8" stroke-dasharray="3 5"/>@endforeach
    <text x="18" y="28" text-anchor="end" class="reviews-axis-count">{{ $chartMaximum }}</text><text x="18" y="133" text-anchor="end" class="reviews-axis-count">0</text>
    @foreach(range(1,5) as $ratingStar)
     <path d="{{ $ratingAreas[$ratingStar]['fill'] }}" fill="url(#reviewArea{{ $ratingStar }})"><title>{{ $ratingStar }}-star reviews</title></path>
     <path d="{{ $ratingAreas[$ratingStar]['line'] }}" fill="none" stroke="{{ $ratingColors[$ratingStar] }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    @endforeach
    @foreach($reviewMonths as $monthIndex=>$month)
     <g class="reviews-month-point" role="button" tabindex="0" data-review-month="{{ $month['full_label'] }}" data-month-counts="{{ json_encode($month['counts']) }}" aria-label="Show review counts for {{ $month['full_label'] }}" aria-pressed="false"><rect class="reviews-month-hit" x="{{ 2+$monthIndex*48 }}" y="18" width="48" height="119" fill="transparent"/><line class="reviews-month-guide" x1="{{ 26+$monthIndex*48 }}" y1="20" x2="{{ 26+$monthIndex*48 }}" y2="130" stroke="#91b9aa" stroke-width="1" stroke-dasharray="3 3"/><title>{{ $month['full_label'] }}: @foreach(range(1,5) as $ratingStar){{ $ratingStar }} stars: {{ $month['counts'][$ratingStar] }}{{ $ratingStar<5?', ':'' }}@endforeach</title><circle cx="{{ 26+$monthIndex*48 }}" cy="{{ 130-array_sum($month['counts'])/$chartMaximum*105 }}" r="2.4" fill="#fff" stroke="#8ec5b2" stroke-width="1.3"/><text x="{{ 26+$monthIndex*48 }}" y="150" text-anchor="middle" class="reviews-axis-month">{{ $month['label'] }}</text></g>
    @endforeach
   </svg>

  </div>
  <div class="reviews-month-detail" data-month-detail hidden role="status" aria-live="polite"></div>
  <p class="reviews-chart-hint">Select a month to see the rating counts.</p>
  <div class="reviews-status-chart" role="group" aria-label="Filter by reply status">
   <button type="button" class="active" data-review-status="all" aria-pressed="true"><span class="reviews-status-label">All reviews<strong>{{ $total }}</strong></span><span class="reviews-status-track" aria-hidden="true"><span style="width:{{ $total ? 100 : 0 }}%"></span></span></button>
   <button type="button" data-review-status="unanswered" aria-pressed="false"><span class="reviews-status-label">Needs reply<strong>{{ $needsResponse }}</strong></span><span class="reviews-status-track" aria-hidden="true"><span style="width:{{ $total ? round($needsResponse / $total * 100) : 0 }}%"></span></span></button>
   <button type="button" data-review-status="replied" aria-pressed="false"><span class="reviews-status-label">Replied<strong>{{ $total - $needsResponse }}</strong></span><span class="reviews-status-track" aria-hidden="true"><span style="width:{{ $total ? round(($total - $needsResponse) / $total * 100) : 0 }}%"></span></span></button>
  </div>
 </aside>
 </div></div>
<script src="{{ asset('js/seller-reviews.js') }}?v={{ filemtime(public_path('js/seller-reviews.js')) }}" defer></script>
@endsection
