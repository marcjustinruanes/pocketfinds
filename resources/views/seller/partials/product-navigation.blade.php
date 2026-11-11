<nav class="product-toolbar-navigation" aria-label="Products navigation">
 <a href="{{ route('seller.inventory') }}" @class(['active'=>request()->routeIs('seller.inventory*','seller.shop-categories.*')]) @if(request()->routeIs('seller.inventory*','seller.shop-categories.*')) aria-current="page" @endif>Inventory</a>
 <a href="{{ route('seller.feedback') }}" @class(['active'=>request()->routeIs('seller.feedback','seller.reviews.*')]) @if(request()->routeIs('seller.feedback','seller.reviews.*')) aria-current="page" @endif>Reviews</a>
</nav>
