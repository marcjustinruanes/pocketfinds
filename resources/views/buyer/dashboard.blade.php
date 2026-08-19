@extends('buyer.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-sub', 'Welcome back, ' . auth()->user()->first_name . '!')

@section('content')
{{-- Quick stats --}}
<div class="kpi-grid">
  <div class="kpi">
    <div class="label">Active Orders</div>
    <div class="value">0</div>
    <div class="delta">In progress</div>
  </div>
  <div class="kpi">
    <div class="label">Cart Items</div>
    <div class="value">0</div>
    <div class="delta">Ready to checkout</div>
  </div>
  <div class="kpi">
    <div class="label">Completed Orders</div>
    <div class="value">0</div>
    <div class="delta up">All time</div>
  </div>
  <div class="kpi">
    <div class="label">Unread Messages</div>
    <div class="value">0</div>
    <div class="delta">From sellers</div>
  </div>
</div>

<div class="dash-grid">
  <div class="stack">
    {{-- Categories --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Browse by Category</h2><p>Find what you're looking for</p></div>
        <a href="{{ route('buyer.browse') }}" class="btn btn-sm btn-outline">View all</a>
      </div>
      <div class="card-pad">
        <div class="category-grid">
          @php
          $categories = [
            ['food',   'Food & Drinks'],
            ['shirt',  'Clothing'],
            ['sparkle','Beauty'],
            ['phone',  'Electronics'],
            ['home',   'Home & Living'],
            ['puzzle', 'Hobbies'],
          ];
          @endphp
          @foreach($categories as [$icon, $label])
          <a href="{{ route('buyer.browse') }}?category={{ urlencode($label) }}" class="category-chip">
            <span class="category-icon">@include('buyer.partials.icon', ['name' => $icon, 'size' => 24])</span>
            <span>{{ $label }}</span>
          </a>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Featured products --}}
    <div class="card">
      <div class="card-head">
        <div><h2>Featured Products</h2><p>Handpicked for you</p></div>
        <a href="{{ route('buyer.browse') }}" class="btn btn-sm btn-outline">See more</a>
      </div>
      <div class="card-pad">
        <div class="product-grid">
          @php
          $featured = [
            ['id'=>1,'name'=>'Wireless Earbuds Pro','price'=>799,'seller'=>'TechHub PH','seller_slug'=>'techhub-ph','rating'=>4.8,'sold'=>1200,'img'=>'headphones','variants'=>['color'=>['Black','White'],'size'=>[]]],
            ['id'=>2,'name'=>'Minimal Backpack','price'=>549,'seller'=>'UrbanCarry','seller_slug'=>'urbancarry','rating'=>4.7,'sold'=>856,'img'=>'bag','variants'=>['color'=>['Black','Olive','Navy'],'size'=>[]]],
            ['id'=>3,'name'=>'LED Desk Lamp','price'=>399,'seller'=>'HomeGlow','seller_slug'=>'homeglow','rating'=>4.8,'sold'=>731,'img'=>'sparkle','variants'=>['color'=>['White','Black'],'size'=>[]]],
            ['id'=>4,'name'=>'Mechanical Keyboard','price'=>1899,'seller'=>'TechHub PH','seller_slug'=>'techhub-ph','rating'=>4.9,'sold'=>512,'img'=>'phone','variants'=>['color'=>['Space Gray','White'],'size'=>['Blue Switch','Brown Switch','Red Switch']]],
          ];
          @endphp
          @foreach($featured as $p)
          <div class="product-card" onclick="window.location='{{ route('buyer.product', $p['id']) }}'">
            <div class="product-img">
              @include('buyer.partials.icon', ['name' => $p['img'], 'size' => 36])
            </div>
            <div class="product-info">
              <div class="product-name">{{ $p['name'] }}</div>
              <a class="product-seller" href="{{ route('buyer.shop', $p['seller_slug']) }}" onclick="event.stopPropagation()">by {{ $p['seller'] }}</a>
              <div class="product-price">₱{{ number_format($p['price']) }}</div>
              <div class="product-rating"><span class="star-ic">@include('buyer.partials.icon',['name'=>'star','size'=>11])</span> {{ $p['rating'] }} · {{ number_format($p['sold']) }} sold</div>
              <div class="pc-actions">
                <button class="pc-act pc-act-cart" onclick="event.stopPropagation();openCart('{{ addslashes($p['name']) }}',{{ $p['price'] }},{{ json_encode($p['variants']['color']) }},{{ json_encode($p['variants']['size']) }},false,this,'{{ $p['id'] }}','{{ $p['img'] }}')" title="Add to Cart">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
                </button>
                <button class="pc-act pc-act-buy" onclick="event.stopPropagation();openCart('{{ addslashes($p['name']) }}',{{ $p['price'] }},{{ json_encode($p['variants']['color']) }},{{ json_encode($p['variants']['size']) }},true,this,'{{ $p['id'] }}','{{ $p['img'] }}')" title="Buy Now">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </button>
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    {{-- Order status --}}
    <div class="card">
      <div class="card-head"><h2>My Orders</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        @php
        $orderStatuses = [
          ['package', 'To Ship',           'to_ship'],
          ['truck',   'In Transit',         'in_transit'],
          ['bike',    'Out for Delivery',   'out_for_delivery'],
          ['check',   'Completed',          'completed'],
        ];
        @endphp
        @foreach($orderStatuses as [$icon, $label, $tab])
        <a href="{{ route('buyer.orders') }}?tab={{ $tab }}" class="order-status-row">
          <span class="order-status-ic">@include('buyer.partials.icon', ['name' => $icon, 'size' => 18])</span>
          <span>{{ $label }}</span>
          <span class="order-status-count mono">0</span>
        </a>
        @endforeach
      </div>
    </div>

    {{-- Quick actions --}}
    <div class="card">
      <div class="card-head"><h2>Quick Actions</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('buyer.browse') }}" class="btn btn-outline">
          @include('buyer.partials.icon', ['name' => 'bag', 'size' => 15]) Browse Products
        </a>
        <a href="{{ route('buyer.cart') }}" class="btn btn-outline">
          @include('buyer.partials.icon', ['name' => 'cart', 'size' => 15]) View Cart
        </a>
        <a href="{{ route('buyer.orders') }}" class="btn btn-outline">
          @include('buyer.partials.icon', ['name' => 'package', 'size' => 15]) Track Orders
        </a>
        <a href="{{ route('buyer.messages') }}" class="btn btn-outline">
          @include('buyer.partials.icon', ['name' => 'mail', 'size' => 15]) Messages
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
