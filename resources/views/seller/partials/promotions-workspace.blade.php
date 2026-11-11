@php
 $isVoucher=$promotionType==='voucher';
 $promotions=\Illuminate\Support\Facades\DB::table($isVoucher?'commerce_vouchers':'commerce_flash_sales')->where('seller_id',auth()->id())->orderByDesc('id')->get();
 $promotionProducts=\App\Models\Product::where('seller_id',auth()->id())->get()->keyBy('id');
 $allocationKey=$isVoucher?'voucher_id':'flash_sale_id';
 $usage=\Illuminate\Support\Facades\DB::table('commerce_allocations')->whereIn($allocationKey,$promotions->pluck('id'))->where('state','used')->select($allocationKey)->selectRaw($isVoucher?'COUNT(*) as total':'SUM(quantity) as total')->groupBy($allocationKey)->pluck('total',$allocationKey);
 foreach($promotions as $promotion){$promotion->used_count=(int)($usage[$promotion->id] ?? 0)+(int)($promotion->legacy_used_count ?? 0);$promotion->sold_count=$promotion->used_count;}
 $promotionStatus=function($offer) use($isVoucher){
  if(!$offer->is_active)return 'ended';
  if(\Illuminate\Support\Carbon::parse($offer->ends_at)->lte(now()))return 'expired';
  if($isVoucher && $offer->used_count >= $offer->usage_limit)return 'exhausted';
  if(!$isVoucher && $offer->sold_count >= $offer->quantity)return 'exhausted';
  return \Illuminate\Support\Carbon::parse($offer->starts_at)->isFuture()?'scheduled':'active';
 };
 $statusCounts=$promotions->countBy($promotionStatus);
 $promotionTiming=function($offer) use($promotionStatus){
  $status=$promotionStatus($offer);
  if(in_array($status,['ended','expired','exhausted']))return 'finished';
  if($status==='scheduled' && \Illuminate\Support\Carbon::parse($offer->starts_at)->lte(now()->addDay()))return 'starting_soon';
  if($status==='active' && \Illuminate\Support\Carbon::parse($offer->ends_at)->lte(now()->addDay()))return 'ending_soon';
  return 'other';
 };
 $timingCounts=$promotions->countBy($promotionTiming);
@endphp
<div class="promotions-workspace" data-promotions>
 @include('commerce.messages')
 <div class="promo-review-toolbar">
  <nav class="promo-section-tabs" aria-label="Promotions navigation"><a href="{{ route('seller.vouchers') }}" class="{{ $isVoucher?'active':'' }}" @if($isVoucher) aria-current="page" @endif>Vouchers</a><a href="{{ route('seller.promotions.flash') }}" class="{{ !$isVoucher?'active':'' }}" @unless($isVoucher) aria-current="page" @endunless>Flash sales</a></nav>
  <label class="promo-search">@include('seller.partials.icon',['name'=>'search','size'=>17])<input type="search" data-promo-search placeholder="{{ $isVoucher?'Search voucher codes':'Search products' }}" aria-label="Search promotions"></label>
  <button type="button" class="btn btn-primary promo-create" data-modal="createPromotionModal">@include('seller.partials.icon',['name'=>'plus','size'=>15]){{ $isVoucher?'Create voucher':'Create flash sale' }}</button>
 </div>
 <div class="promo-review-columns">
 <section class="promo-list-panel" tabindex="0" aria-label="{{ $isVoucher?'Voucher list':'Flash sale list' }}">
  <span class="promo-sr-only" data-promo-count aria-live="polite">{{ $promotions->count() }} offers</span>
  <div class="promo-grid">
   @forelse($promotions as $offer)
    @php
     $status=$promotionStatus($offer);
     $offerProduct=$isVoucher?null:$promotionProducts->get($offer->product_id);
     $used=$isVoucher?$offer->used_count:$offer->sold_count;
     $limit=$isVoucher?$offer->usage_limit:$offer->quantity;
    @endphp
    <article class="promo-offer {{ $isVoucher ? 'is-voucher' : '' }}" data-promo-card data-status="{{ $status }}" data-timing="{{ $promotionTiming($offer) }}">
     <div class="promo-offer-top">@if($isVoucher)<h3 class="promo-voucher-code">{{ $offer->code }}</h3>@else<span class="promo-kind">@include('seller.partials.icon',['name'=>'clock','size'=>14])Flash sale</span>@endif<span class="promo-status is-{{ $status }}">{{ ['active'=>'Live','scheduled'=>'Scheduled','ended'=>'Ended','expired'=>'Expired','exhausted'=>'Used up'][$status] }}</span></div>
     <div class="promo-offer-value">@if($isVoucher){{ $offer->discount_type==='percent'?number_format($offer->amount,0).'%':'₱'.number_format($offer->amount,2) }} <span>off</span>@else ₱{{ number_format($offer->price,2) }} <span>per unit</span>@endif</div>
     @unless($isVoucher)<h3>{{ $offerProduct?->name ?? 'Product removed' }}</h3>@endunless
     <p class="promo-offer-sub">@if($isVoucher)Minimum spend ₱{{ number_format($offer->minimum_spend,2) }}@if($offer->cap) · Up to ₱{{ number_format($offer->cap,2) }} off @endif @else Limited to {{ $offer->quantity }} units @endif</p>
     <div class="promo-dates"><span><span>Starts {{ \Illuminate\Support\Carbon::parse($offer->starts_at)->format('M d, Y · g:i A') }}</span></span><span><span>Until {{ \Illuminate\Support\Carbon::parse($offer->ends_at)->format('M d, Y · g:i A') }}</span></span></div>
     <div class="promo-usage"><div><span>{{ $used }} / {{ $limit }} {{ $isVoucher?'redemptions':'units sold' }}</span><strong>{{ $limit?min(100,round($used/$limit*100)):0 }}%</strong></div><meter min="0" max="{{ max(1,$limit) }}" value="{{ $used }}" aria-label="{{ $used }} of {{ $limit }} used"></meter></div>
     <footer><span>{{ $isVoucher?$offer->per_buyer_limit.' per buyer':'Limited-time price' }}</span>@if($offer->is_active && !in_array($status,['expired','exhausted']))<form method="POST" action="{{ route('seller.commerce.end',[$promotionType,$offer->id]) }}" data-end-promotion>@csrf<button type="submit" class="promo-end">{{ $isVoucher?'End voucher':'End sale' }}</button></form>@endif</footer>
    </article>
   @empty
    <div class="promo-empty"><span>@include('seller.partials.icon',['name'=>$isVoucher?'tag':'calendar','size'=>30])</span><h3>{{ $isVoucher?'Your first voucher starts here':'Make room for your next flash sale' }}</h3><p>{{ $isVoucher?'Create a discount code for buyers to use at checkout.':'Choose a product and schedule a special price.' }}</p><button type="button" class="btn btn-outline" data-modal="createPromotionModal">{{ $isVoucher?'Create your first voucher':'Create your first flash sale' }}</button></div>
   @endforelse
  </div>
  <div class="promo-empty" data-promo-no-results hidden><h3>No matching offers</h3><p>Try a different search or filter.</p><button type="button" class="btn btn-outline" data-promo-reset>Clear filters</button></div>
 </section>
 <aside class="promo-summary-panel" aria-labelledby="promotionOverviewTitle" tabindex="0">
  <header><div><h2 id="promotionOverviewTitle">Promotion timeline</h2><p>Starting or ending in the next 24 hours</p></div><strong>{{ $promotions->count() }}<small>total</small></strong></header>
  @php
   $startingSoon=$timingCounts['starting_soon'] ?? 0;
   $endingSoon=$timingCounts['ending_soon'] ?? 0;
   $finishedCount=$timingCounts['finished'] ?? 0;
   $liveCount=$statusCounts['active'] ?? 0;
   $scheduledCount=$statusCounts['scheduled'] ?? 0;
   $timelineMaximum=max(1,$liveCount,$scheduledCount,$finishedCount);
  @endphp
  <div class="promo-status-bars" role="group" aria-label="Filter offers by promotion status">
   @foreach(['active'=>['Live',$liveCount],'scheduled'=>['Scheduled',$scheduledCount],'finished'=>['Finished',$finishedCount]] as $barStatus=>[$barLabel,$barCount])
    <button type="button" data-promo-filter="{{ $barStatus }}" aria-pressed="false" aria-label="{{ $barCount }} {{ $barLabel }} offers. Filter list." class="promo-status-bar is-{{ $barStatus }}"><strong>{{ $barCount }}</strong><span class="promo-bar-track" aria-hidden="true"><span style="height:{{ $barCount/$timelineMaximum*100 }}%"></span></span><span>{{ $barLabel }}</span></button>
   @endforeach
  </div>
  <p class="promo-chart-caption">Select a bar to filter your offers.</p>
  <div class="promo-overview-filters" role="group" aria-label="Filter promotions">
   @foreach(['all'=>'All offers','starting_soon'=>'Starting soon','ending_soon'=>'Ending soon','finished'=>'Finished'] as $value=>$label)
    @php
     $filterCount=match($value){'all'=>$promotions->count(),'finished'=>$finishedCount,'starting_soon'=>$startingSoon,'ending_soon'=>$endingSoon,default=>$statusCounts[$value] ?? 0};
    @endphp
    <button type="button" data-promo-filter="{{ $value }}" aria-pressed="{{ $value==='all'?'true':'false' }}" class="{{ $value==='all'?'active':'' }}"><span class="promo-filter-heading"><span>{{ $label }}</span><strong>{{ $filterCount }}</strong></span><span class="promo-filter-progress" aria-hidden="true"><span style="width:{{ $promotions->count()?min(100,$filterCount/$promotions->count()*100):0 }}%"></span></span></button>
   @endforeach
  </div>
 </aside>
 </div>
</div>
<div class="modal-overlay {{ $errors->any()?'open':'' }}" id="createPromotionModal">
 <div class="modal promo-dialog" role="dialog" aria-modal="true" aria-labelledby="createPromotionTitle"><header class="modal-head"><div><span class="promo-eyebrow">{{ $isVoucher?'A little saving goes a long way':'A limited-time opportunity' }}</span><h3 id="createPromotionTitle">{{ $isVoucher?'Create a voucher':'Create a flash sale' }}</h3></div><button type="button" class="modal-close" data-modal-close aria-label="Close promotion form">&times;</button></header>
 <form method="POST" action="{{ route($isVoucher?'seller.commerce.voucher':'seller.commerce.flash') }}">@csrf
  <div class="modal-body">
   @if($errors->any())<div class="promo-form-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
   <h4>Offer details</h4><div class="promo-form-grid">
   @if($isVoucher)
    <input type="hidden" name="kind" value="store">
    <label class="promo-field is-wide">Voucher code<input name="code" minlength="4" maxlength="12" pattern="[A-Za-z0-9]{4,12}" placeholder="e.g. HELLO50" value="{{ old('code') }}" required><small>4–12 letters or numbers.</small></label>
    <label class="promo-field">Discount type<select name="discount_type" data-discount-type><option value="fixed" @selected(old('discount_type')==='fixed')>Fixed amount (₱)</option><option value="percent" @selected(old('discount_type')==='percent')>Percentage (%)</option></select></label>
    <label class="promo-field"><span data-amount-label>Discount amount (₱)</span><input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required></label>
    <label class="promo-field">Minimum spend (₱)<input name="minimum_spend" type="number" min="0" step="0.01" value="{{ old('minimum_spend',0) }}" required></label>
    <label class="promo-field">Maximum discount (₱)<input name="cap" type="number" min="0.01" step="0.01" placeholder="Optional" value="{{ old('cap') }}"></label>
   @else
    <label class="promo-field is-wide">Product<select name="product_id" required><option value="">Choose a product</option>@foreach($promotionProducts->where('status','active')->filter(fn($product)=>$product->total_stock>0) as $product)<option value="{{ $product->id }}" @selected(old('product_id')===$product->id)>{{ $product->name }} · ₱{{ number_format($product->price,2) }} · {{ $product->total_stock }} in stock</option>@endforeach</select></label>
    <label class="promo-field">Sale price per unit (₱)<input name="price" type="number" min="1" step="0.01" value="{{ old('price') }}" required></label>
    <label class="promo-field">Sale quantity<input name="quantity" type="number" min="1" max="99999" step="1" value="{{ old('quantity') }}" required></label>
   @endif
   </div><div class="promo-schedule-heading"><h4>Schedule {{ $isVoucher?'& limits':'' }}</h4>
   @unless($isVoucher)<div class="promo-start-options" role="group" aria-label="Flash sale start time"><label><input type="radio" name="start_mode" value="now" @checked(old('start_mode','now')==='now')>Start now</label><label><input type="radio" name="start_mode" value="scheduled" @checked(old('start_mode')==='scheduled')>Schedule for later</label></div>@endunless
   </div>
   <div class="promo-form-grid">
    <label class="promo-field" data-promo-start-field>Starts<input name="starts_at" type="datetime-local" value="{{ old('starts_at') }}" required></label><label class="promo-field">Ends<input name="ends_at" type="datetime-local" value="{{ old('ends_at') }}" required></label>
    @if($isVoucher)<label class="promo-field">Total redemptions<input name="usage_limit" type="number" min="1" step="1" value="{{ old('usage_limit') }}" required></label><label class="promo-field">Uses per buyer<input name="per_buyer_limit" type="number" min="1" step="1" value="{{ old('per_buyer_limit',1) }}" required></label>@endif
   </div><p class="promo-form-note">{{ $isVoucher?'For fixed discounts, minimum spend must cover the discount amount.':'Sale price must be below the regular price. Quantity must fit available stock, and sale schedules cannot overlap for a product.' }}</p>
  </div><footer class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">{{ $isVoucher?'Create voucher':'Create flash sale' }}</button></footer>
 </form></div>
</div>
