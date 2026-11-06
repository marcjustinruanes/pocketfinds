@php
 $u=auth()->user();
 $products=\App\Models\Product::where('seller_id',$u->id)->where('status','active')->get();
 $sales=\Illuminate\Support\Facades\DB::table('commerce_flash_sales')->where('seller_id',$u->id)->get();
@endphp
<section class="card flash-sales-card" id="flash-sales" aria-labelledby="flash-sales-title">
  <div class="card-head">
    <div>
      <h2 id="flash-sales-title">Schedule a flash sale</h2>
      <p>Set a limited-time price and quantity for an in-stock product.</p>
    </div>
    <span class="flash-sales-heading-icon">@include('seller.partials.icon', ['name' => 'calendar', 'size' => 17])</span>
  </div>
  <div class="card-pad">
    <div class="flash-sales-note">
      <span>@include('seller.partials.icon', ['name' => 'clock', 'size' => 15])</span>
      <p>Schedule times use {{ config('app.timezone') }}. Quantity cannot exceed stock, and overlapping sales for the same product are blocked.</p>
    </div>
    <form class="flash-sale-form" method="POST" action="{{ route('seller.commerce.flash') }}">
      @csrf
      <label class="form-row flash-sale-product">Product
        <select class="select" name="product_id" required>
          @foreach($products as $product)
          <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->total_stock }} units)</option>
          @endforeach
        </select>
      </label>
      <div class="form-grid-2 flash-sale-fields">
        <label class="form-row">Flash unit price<input type="number" name="price" min="1" step="0.01" required></label>
        <label class="form-row">Flash quantity<input type="number" name="quantity" min="1" max="99999" required></label>
        <label class="form-row">Starts<input type="datetime-local" name="starts_at" required></label>
        <label class="form-row">Ends<input type="datetime-local" name="ends_at" required></label>
      </div>
      <div class="flash-sale-submit"><button class="btn btn-primary">Schedule sale</button></div>
    </form>

    @if($sales->isNotEmpty())
    <div class="flash-sale-history">
      <div class="flash-sale-history-heading">
        <div><h3>Scheduled sales</h3><p>Review or end a sale currently on your account.</p></div>
        <span>{{ $sales->count() }} {{ \Illuminate\Support\Str::plural('sale', $sales->count()) }}</span>
      </div>
      <div class="flash-sale-records">
        @foreach($sales as $sale)
        <article class="flash-sale-record">
          <span class="flash-sale-record-icon">@include('seller.partials.icon', ['name' => 'tag', 'size' => 16])</span>
          <div class="flash-sale-record-copy">
            <strong>Product {{ $sale->product_id }}</strong>
            <p>₱{{ number_format($sale->price,2) }} per unit <span aria-hidden="true">·</span> {{ $sale->starts_at }} – {{ $sale->ends_at }}</p>
          </div>
          <span class="flash-sale-status {{ $sale->is_active ? 'is-active' : 'is-ended' }}">{{ $sale->is_active ? 'Active' : 'Ended' }}</span>
          @if($sale->is_active)
          <form class="flash-sale-end-form" method="POST" action="{{ route('seller.commerce.end',['flash',$sale->id]) }}">
            @csrf
            <button class="btn btn-outline" type="submit">End sale</button>
          </form>
          @endif
        </article>
        @endforeach
      </div>
    </div>
    @endif
  </div>
</section>
