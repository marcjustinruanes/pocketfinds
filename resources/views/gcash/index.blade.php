@extends(auth()->user()->account_type === 'admin' ? 'admin.layout' : (auth()->user()->account_type === 'buyer' ? 'buyer.layout' : 'seller.layout'))
@section('title', 'GCash purchases')
@section('page-title', 'GCash purchases')
@include('gcash.assets')
@section('content')
@php($reviewer = auth()->user()->account_type === 'admin')
<div class="gcash-page">
 <nav class="gcash-nav" aria-label="GCash navigation">
  @unless($reviewer)<a href="{{ route(auth()->user()->account_type === 'buyer' ? 'buyer.cart' : 'seller.orders') }}">{{ auth()->user()->account_type === 'buyer' ? 'Back to cart' : 'All orders' }}</a><a href="{{ route(auth()->user()->account_type.'.messages') }}">Messages</a>@endunless
  @if(request()->filled('contact'))<a href="{{ route('gcash.index') }}">Show all GCash purchases</a>@endif
 </nav>
 <p class="gcash-muted">Each purchase has its own amount, payment review and stock allocation. Sending proof does not verify payment.</p>
 <div class="gcash-purchase-list">
 @forelse($purchases as $purchase)
  <article class="gcash-card">
   <div class="gcash-purchase-heading">
    <div>
     <span class="gcash-badge {{ in_array($purchase->payment_state, ['verified', 'order_placed']) ? 'gcash-badge-success' : '' }}">{{ str_replace('_', ' ', ucfirst($purchase->payment_state)) }}</span>
     <h2>{{ ($purchase->snapshot['items'][0]['name'] ?? 'GCash purchase').(count($purchase->snapshot['items'] ?? []) > 1 ? ' + '.(count($purchase->snapshot['items']) - 1).' more' : '') }}</h2>
     <details class="gcash-reference"><summary>Purchase reference</summary><p>{{ $purchase->id }}</p></details>
    </div>
    <p class="gcash-amount">&#8369;{{ number_format($purchase->amount_due, 2) }}</p>
   </div>
   <p class="gcash-muted">{{ count($purchase->snapshot['items'] ?? []) }} item type(s) &middot; Stock: {{ str_replace('_', ' ', $purchase->reservation_state) }}</p>
   @if($purchase->payment_state === 'verified')<p>The seller confirmed payment. The buyer must still place the order.</p>@endif
   @if(in_array($purchase->reservation_state, ['expired', 'released']) && $purchase->payment_state !== 'order_placed')<p class="gcash-notice">If money was sent, open this purchase for manual resolution. Do not pay again.</p>@endif
   <div class="gcash-actions"><a class="gcash-button" href="{{ route('gcash.show', $purchase->id) }}">{{ $reviewer ? 'Review purchase' : ($purchase->payment_state === 'order_placed' ? 'View purchase & order' : 'Open payment review') }}</a></div>
  </article>
 @empty
  <section class="gcash-card"><h2>No GCash purchases yet</h2><p class="gcash-muted">New GCash purchases appear here after checkout. Return here to see instructions, submit proof and check the seller review.</p>@unless($reviewer)<a class="gcash-button" href="{{ route(auth()->user()->account_type === 'buyer' ? 'buyer.cart' : 'seller.messages') }}">{{ auth()->user()->account_type === 'buyer' ? 'Go to cart' : 'Open messages' }}</a>@endunless</section>
 @endforelse
 </div>
 {{ $purchases->links() }}
</div>
@endsection
