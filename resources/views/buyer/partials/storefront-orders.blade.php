<section class="pf-buyer-orders" aria-labelledby="buyerOrdersTitle">
  <div class="pf-buyer-welcome"><h2 id="buyerOrdersTitle">Hi, {{ auth()->user()->given_names }}!</h2><a href="{{ route('buyer.orders') }}">My Orders &rarr;</a></div>
  <div class="pf-buyer-order-links">
    @foreach([
      ['package', 'To Ship', 'to_ship'],
      ['truck', 'In Transit', 'in_transit'],
      ['bike', 'Out for Delivery', 'out_for_delivery'],
      ['check', 'Completed', 'completed'],
    ] as [$icon, $label, $tab])
    <a href="{{ route('buyer.orders', ['tab' => $tab]) }}">
      @include('buyer.partials.icon', ['name' => $icon, 'size' => 20])
      <span>{{ $label }}</span><strong>{{ $statusCounts[$tab] }}</strong>
    </a>
    @endforeach
  </div>
</section>
