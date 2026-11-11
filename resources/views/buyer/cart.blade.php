@extends('buyer.layout')
@section('title', 'My Cart')
@section('page-title', 'My Cart')
@section('page-sub', 'Review your selected items')
@section('hide-page-heading', true)
@section('body-class', 'cart-page-layout')

@section('content')
@if($errors->any())<div class="auth-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@php
  $subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['qty']);
@endphp
@if(session('error'))
<div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px">{{ session('error') }}</div>
@endif
@if(session('success'))
<div class="auth-success" style="margin-bottom:16px">{{ session('success') }}</div>
@endif
<div class="cart-layout">
  <div class="stack">
    <div class="card cart-items-card">
      <div class="card-head"><h2>Your items <span class="cart-count">{{ $items->count() }}</span></h2>@if($items->isNotEmpty())<div class="cart-header-actions"><label class="cart-select-all"><input id="cartSelectAll" type="checkbox" autocomplete="off" aria-label="Select all items"></label><button type="button" class="cart-bulk-delete" data-remove-selected="all" hidden disabled aria-label="Delete selected items" title="Delete selected items">@include('buyer.partials.icon',['name'=>'trash','size'=>15])</button></div>@endif</div>
      <div class="card-pad">
        @if(count($items))
          <div class="cart-items-scroll">
          <div class="cart-column-head" aria-hidden="true">
            <span></span><span></span><span>Product</span><span style="text-align:right">Subtotal</span><span style="text-align:right">Actions</span>
          </div>
          @foreach($groups as $slug => $shopItems)
          @php
            $shopShipping = $shippingFees[$slug] ?? 0;
          @endphp
          <section class="cart-shop" data-seller-id="{{ $shopItems->first()['seller_id'] }}" data-shipping-fee="{{ $shopShipping }}" data-shop-slug="{{ $slug }}">
            <div class="cart-shop-head">
              <div class="cart-check-wrap">
                <input class="cart-shop-select" type="checkbox" autocomplete="off" aria-label="Select items from {{ $shopItems->first()['seller'] }}">
                <a href="{{ route('buyer.shop', $shopItems->first()['seller_slug']) }}">{{ $shopItems->first()['seller'] }} @include('buyer.partials.icon',['name'=>'shop','size'=>17])</a>
                <span class="cart-shop-message-divider" aria-hidden="true">|</span>
                <button type="button" class="cart-shop-message" data-messages-trigger data-messages-url="{{ route('buyer.messages',['seller'=>$shopItems->first()['seller_slug']]) }}" aria-label="Message {{ $shopItems->first()['seller'] }}" title="Message shop">@include('buyer.partials.icon',['name'=>'chat','size'=>16])</button>
              </div>
              <div class="cart-header-actions"><span class="cart-count">{{ $shopItems->count() }} item{{ $shopItems->count() === 1 ? '' : 's' }}</span><button type="button" class="cart-bulk-delete" data-remove-selected="shop" aria-label="Delete selected items from {{ $shopItems->first()['seller'] }}" title="Delete selected shop items">@include('buyer.partials.icon',['name'=>'trash','size'=>15])</button></div>
            </div>
            <div class="cart-items">
            @foreach($shopItems as $item)
              <div class="cart-item">
                <input class="cart-select" type="checkbox" autocomplete="off" aria-label="Select {{ $item['name'] }}" data-price="{{ $item['price'] }}" data-qty="{{ $item['qty'] }}" data-shop="{{ $item['seller_slug'] }}" data-product-id="{{ $item['product_id'] }}">
                <div class="cart-item-image">
                  @if(filled($item['img']))
                    <img src="{{ $item['img'] }}" alt="{{ $item['name'] }}">
                  @else
                    @include('buyer.partials.icon', ['name' => 'package', 'size' => 26])
                  @endif
                </div>
                <div style="min-width:0">
                  <a class="cart-item-name" href="{{ route('buyer.product', $item['product_id']) }}">{{ $item['name'] }}</a>
                  @if($item['variation_value'])
                    @php
                      $selectedAttributes = [];
                      foreach ($item['product_variations'] as $group) {
                        if (($group['name'] ?? '') !== $item['variation_group']) continue;
                        foreach ($group['options'] ?? [] as $option) {
                          if (($option['value'] ?? '') === $item['variation_value']) {
                            $selectedAttributes = $option['attributes'] ?? [];
                            break;
                          }
                        }
                      }
                    @endphp
                    <div class="cart-item-variant">
                      @if($selectedAttributes)
                        @foreach($selectedAttributes as $optionName => $optionValue)
                          <span>{{ $optionName }}: {{ $optionValue }}</span>@unless($loop->last) · @endunless
                        @endforeach
                      @else
                        {{ $item['variation_group'] ?: 'Option' }}: {{ $item['variation_value'] }}
                      @endif
                    </div>
                  @endif
                </div>
                <div class="cart-item-pricing">
                  <div class="cart-item-total">₱{{ number_format($item['price'] * $item['qty'], 2) }}</div>
                  <div class="cart-item-quantity">{{ $item['qty'] }} × ₱{{ number_format($item['price'], 2) }}</div>
                </div>
                <div class="cart-item-actions">
                  <button type="button" class="cart-action-btn" data-messages-trigger data-messages-url="{{ route('buyer.messages',['seller'=>$item['seller_slug'],'product'=>$item['product_id'],'variation_group'=>$item['variation_group'],'variation_value'=>$item['variation_value']]) }}" aria-label="Message about {{ $item['name'] }}" title="Message about this product">@include('buyer.partials.icon',['name'=>'chat','size'=>15])</button>
                  <button type="button" class="cart-action-btn" aria-label="Change quantity or options for {{ $item['name'] }}" title="Change quantity or options"
                    data-edit-key="{{ $item['key'] }}" data-edit-qty="{{ $item['qty'] }}" data-edit-value="{{ $item['variation_value'] }}"
                    data-edit-group="{{ $item['variation_group'] }}" data-edit-variations="{{ json_encode($item['product_variations']) }}"
                    data-edit-stock="{{ $item['product_stock'] }}"
                    data-edit-name="{{ $item['name'] }}" data-edit-image="{{ $item['img'] }}" data-edit-price="{{ number_format($item['price'], 2) }}">
                    @include('buyer.partials.icon', ['name' => 'edit', 'size' => 16])
                  </button>
                  <form method="POST" action="{{ route('buyer.cart.remove', ['key' => $item['key']]) }}" class="cart-remove-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cart-action-btn danger" data-remove-name="{{ $item['name'] }}" aria-label="Remove {{ $item['name'] }} from cart" title="Remove item">@include('buyer.partials.icon', ['name' => 'trash', 'size' => 16])</button>
                  </form>
                </div>
              </div>
            @endforeach
            </div>
          </section>
          @endforeach
          </div>
        @else
        <div class="empty">
          <div class="ic">@include('buyer.partials.icon', ['name' => 'cart', 'size' => 28])</div>
          <h3>Your cart is empty</h3>
          <p>Browse products and add items to your cart.</p>
          <a href="{{ route('buyer.browse') }}" class="btn btn-primary" style="margin-top:14px">Browse Products</a>
        </div>
        @endif
      </div>
    </div>
  </div>

  @if($items->isNotEmpty())
  <div class="stack cart-summary">
    <div class="card cart-summary-card">
      <div class="card-head">
        <h2>Checkout</h2>
        <button type="button" class="icon-btn" id="addNoteBtn" aria-label="Add a note for the seller" data-modal-open="orderNoteModal" title="Add a note for the seller" style="width:30px;height:30px">
          @include('buyer.partials.icon', ['name' => 'edit', 'size' => 14]) <span>Seller note</span>
        </button>
      </div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        @error('voucher_code')
          <div style="background:var(--danger-soft);border:1px solid var(--danger-line);color:var(--danger);padding:9px 12px;border-radius:9px;font-size:12.5px">{{ $message }}</div>
        @enderror
        <div class="form-row" style="margin:0">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <h3 class="cart-section-title">Delivery address</h3>
            <div class="cart-address-actions"><button type="button" class="cart-view-vouchers" id="cartAddressToggle" aria-haspopup="dialog" aria-controls="cartAddressModal">Change</button><span aria-hidden="true">|</span><button type="button" class="cart-view-vouchers" id="addAddressBtn" data-modal-open="addAddressModal">Add</button></div>
          </div>
          <div class="cart-address-picker">
            <div class="cart-address-selected"><p id="cartAddressSummary">Your selected delivery address</p><span class="address-default-badge" id="cartAddressDefaultBadge" hidden>Default</span></div>
            @include('buyer.partials.delivery-addresses', ['addresses' => $addresses, 'selectable' => true])
          </div>
        </div>

@if(\App\Services\BuyerCommerce::installed())@include('buyer.partials.cart-vouchers')@endif
<div @if(\App\Services\BuyerCommerce::installed()) hidden @endif>
        <div class="form-row" style="margin:0">
          <div style="display:flex;align-items:center;justify-content:space-between">
            <h3 class="cart-section-title">Vouchers & discounts</h3>
            <button type="button" class="cart-view-vouchers" id="viewAllVouchersBtn" data-modal-open="allVouchersModal" {{ $otherVouchers->isNotEmpty() ? '' : 'hidden' }}>
              View all
            </button>
          </div>
          <p id="noVoucherMessage" style="font-size:12px;color:var(--muted);margin:4px 0 0" {{ $usableVouchers->isEmpty() ? '' : 'hidden' }}>
            No vouchers available for your selected items.
          </p>
          <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px" id="usableVoucherList">
            @foreach($usableVouchers as $v)
            <button type="button" class="cart-offer" data-voucher-code="{{ $v['voucher']->code }}" data-shop="{{ $v['shop'] }}">
              <span>
                <b>{{ $v['voucher']->code }}</b>
                <small>{{ $v['voucher']->isFreeShipping() ? 'Free Shipping' : '₱' . number_format($v['voucher']->discount_amount, 2) . ' off' }} · {{ $v['shop_name'] }}</small>
              </span>
              <em>Apply</em>
            </button>
            @endforeach
          </div>
        </div>

</div>
        @php
          $typeMeta = ['ewallet' => ['wallet', 'E-Wallet'], 'cod' => ['cash', 'Cash on Delivery'], 'other' => ['tag', 'Other']];
          $firstAvailableType = collect($typeMeta)->keys()->first(fn ($type) => $paymentMethods->has($type));
        @endphp
        <div class="form-row" style="margin:0">
          <h3 class="cart-section-title" id="cartPaymentHeading">Payment method</h3>
          @if($paymentMethods->isEmpty())
            <p style="font-size:12.5px;color:var(--muted);margin:4px 0 0">No payment methods available.</p>
          @else
            <div class="cart-payment-select-row">
              <span class="cart-payment-selected-icon" aria-hidden="true">@foreach($typeMeta as $type=>[$icon,$label])<span class="payment-type-icon" data-type="{{ $type }}" @if($type!==$firstAvailableType) hidden @endif>@include('buyer.partials.icon',['name'=>$icon,'size'=>23])</span>@endforeach</span>
              <div class="cart-floating-field always-floating"><select class="select" id="paymentNameSelect" aria-labelledby="cartPaymentHeading"></select><label for="paymentNameSelect">Payment method</label><fieldset aria-hidden="true"><legend><span>Payment method</span></legend></fieldset></div>
            </div>
                <select id="paymentTypeSelect" hidden aria-hidden="true" tabindex="-1">
                  @foreach($typeMeta as $type => [$icon, $label])
                    @if($paymentMethods->has($type))<option value="{{ $type }}">{{ $label }}</option>@endif
                  @endforeach
                </select>
            <div id="paymentVerifyStatus" style="font-size:11.5px;margin-top:6px"></div>
          @endif
        </div>

        @if(\App\Services\GcashInventory::installed() && !\App\Services\BuyerCommerce::installed())
        <details class="cart-price-review"><summary>Check current prices</summary><p>Review current item prices before starting a GCash reservation.</p><button type="button" class="btn btn-outline" id="reviewGcashPrices">Review current prices for GCash</button>
        <p id="gcashPriceMessage" role="status" aria-live="polite"></p></details>
        @endif
        <p id="cartCheckoutFeedback" role="status" aria-live="polite"></p><div class="cart-costs" aria-live="polite" aria-atomic="true">
        <h3 class="cart-section-title">Order summary</h3>
        <div class="cart-summary-row"><span>Subtotal</span><span class="mono" id="cartSubtotal">₱{{ number_format($subtotal, 2) }}</span></div>
        <div class="cart-summary-row"><span>Shipping</span><span class="mono" id="cartShipping">Free</span></div>
        <div class="cart-summary-row" id="cartDiscountRow" style="display:none;color:var(--success)"><span id="cartDiscountLabel">Discount</span><span class="mono" id="cartDiscount">- ₱0.00</span></div>
        <hr style="border:0;border-top:1px solid var(--border)">
        <div class="cart-summary-total">
          <span><strong id="cartTotalLabel">Total</strong><small id="cartSelectedCount">{{ $items->count() }} item{{ $items->count() === 1 ? '' : 's' }}</small></span>
          <span class="mono" id="cartTotal">₱{{ number_format($subtotal, 2) }}</span>
        </div>

        </div>
        <form method="POST" action="{{ route('buyer.checkout') }}" id="checkoutForm" @if(\App\Services\BuyerCommerce::installed()) data-quote-url="{{ route('buyer.commerce.quote') }}" @endif>
          @csrf
          <input type="hidden" name="quote_hash" value=""><input type="hidden" name="checkout_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
          <input type="hidden" name="payment_method" id="checkoutPayment">
          <input type="hidden" name="voucher_code" id="checkoutVoucher">
          <input type="hidden" name="buyer_note" id="checkoutNote">
          <input type="hidden" name="delivery_address_id" id="checkoutAddress">
          <button class="btn btn-primary btn-block" id="checkoutButton" type="submit" style="margin-top:4px" {{ $items->isEmpty() || $paymentMethods->isEmpty() ? 'disabled' : '' }}>Review & Place Order</button>
        </form>
      </div>
    </div>
  </div>
  @endif
</div>
@if($items->isNotEmpty())
<div class="cart-checkout-dock" aria-label="Cart checkout" hidden>
 <div><span id="cartDockTotalLabel">Total</span><strong id="cartDockTotal">₱{{ number_format($subtotal, 2) }}</strong></div>
 <button class="btn btn-primary" type="submit" form="checkoutForm" id="cartDockCheckout" disabled>Continue</button>
</div>
@endif
<div class="cart-confirm-modal" id="orderConfirmModal" aria-hidden="true">
  <div class="cart-confirm-dialog pf-confirm" role="dialog" aria-modal="true" aria-labelledby="orderConfirmTitle" aria-describedby="orderConfirmMessage">
    <h3 id="orderConfirmTitle">Place this order?</h3>
    <p id="orderConfirmMessage">Are you sure you want to place this order?</p>
    <div class="cart-confirm-actions">
      <button type="button" class="btn btn-outline" id="orderCancelButton">Cancel</button>
      <button type="button" class="btn btn-primary" id="orderConfirmButton">Place Order</button>
    </div>
  </div>
</div>
<form id="removeSelectedItemsForm" method="POST" action="{{ route('buyer.cart.removeSelected') }}" hidden>@csrf @method('DELETE')<div data-selected-remove-inputs></div></form>
<div class="cart-confirm-modal" id="removeItemModal" aria-hidden="true">
  <div class="cart-confirm-dialog pf-confirm" role="dialog" aria-modal="true" aria-labelledby="removeItemTitle" aria-describedby="removeItemMessage">
    <h3 id="removeItemTitle">Remove this item?</h3>
    <p id="removeItemMessage">Are you sure you want to remove this item from your cart?</p>
    <div class="cart-confirm-actions">
      <button type="button" class="btn btn-outline" id="removeItemCancelButton">Keep item</button>
      <button type="button" class="btn btn-danger" id="removeItemConfirmButton">Remove item</button>
    </div>
  </div>
</div>
<dialog class="pf-confirm" id="cartPriceConfirm" aria-labelledby="cartPriceConfirmTitle" aria-describedby="cartPriceConfirmMessage">
  <h3 id="cartPriceConfirmTitle">Update cart prices?</h3>
  <p id="cartPriceConfirmMessage">Review the changes below. Updating prices does not reserve stock or place an order.</p>
  <pre class="confirmation-details" id="cartPriceChanges"></pre>
  <form method="dialog" class="cart-confirm-actions"><button class="btn btn-outline" value="cancel" autofocus>Keep current</button><button class="btn btn-primary" value="accept">Update prices</button></form>
</dialog>
<div class="cart-voucher-modal" id="itemEditModal" aria-hidden="true">
  <div class="cart-voucher-dialog" role="dialog" aria-modal="true" aria-labelledby="itemEditTitle">
    <div class="cart-voucher-modal-head">
      <div><h3 id="itemEditTitle">Edit item</h3><p>Update the options and quantity for this cart item.</p></div>
      <button type="button" class="modal-close" data-item-edit-close aria-label="Close item editor">&times;</button>
    </div>
    <form method="POST" id="itemEditForm">
      @csrf
      @method('PATCH')
      <div class="cart-voucher-modal-body">
        <div class="cart-edit-context">
          <div class="cart-edit-context-img" id="editItemImgWrap">
            <img id="editItemImg" alt="">
          </div>
          <div>
            <div class="cart-edit-context-name" id="editItemName"></div>
            <div class="cart-edit-context-price" id="editItemPrice"></div>
          </div>
        </div>
        <input type="hidden" name="variation_value" id="editValue">
        <input type="hidden" name="variation_group" id="editVariationGroup">
        <div id="editVariationsContainer"></div>
        <div class="form-row">
          <div class="cart-floating-field always-floating"><input id="editQty" name="qty" type="number" min="1" max="99" placeholder=" " required><label for="editQty">Quantity</label><fieldset aria-hidden="true"><legend><span>Quantity</span></legend></fieldset></div>
          <span id="editQtyError" style="display:none;color:var(--danger);font-size:11.5px;margin-top:4px"></span>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Save item</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="orderNoteModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-head">
      <div><h3>Add a Note</h3><p>Let the seller know something about your order (optional).</p></div>
    </div>
    <div class="modal-body">
      <div class="cart-floating-field always-floating"><textarea id="orderNoteTextarea" rows="4" maxlength="200" placeholder=" "></textarea><label for="orderNoteTextarea">Seller note</label><fieldset aria-hidden="true"><legend><span>Seller note</span></legend></fieldset></div>
      <div style="text-align:right;font-size:11px;color:var(--muted);margin-top:4px" id="orderNoteCount">0/200</div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" id="saveNoteBtn" data-modal-close>Save Note</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="allVouchersModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-head">
      <div><h3>Shop Vouchers</h3><p>Vouchers from the shops in your cart.</p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:10px">
      @foreach($usableVouchers as $v)
      <div class="voucher-modal-item is-available" data-shop="{{ $v['shop'] }}">
        <div class="voucher-modal-value">{{ $v['voucher']->code }}</div>
        <div class="voucher-modal-copy">
          <strong>{{ $v['voucher']->isFreeShipping() ? 'Free Shipping' : '₱' . number_format($v['voucher']->discount_amount, 2) . ' off' }}</strong>
          <span>{{ $v['shop_name'] }} · Min. spend ₱{{ number_format($v['voucher']->minimum_spend, 2) }}</span>
          <span class="voucher-available">Available for your cart</span>
        </div>
        <button type="button" class="voucher-modal-use" data-voucher-code="{{ $v['voucher']->code }}" data-modal-close>Apply</button>
      </div>
      @endforeach
      @foreach($otherVouchers as $v)
      <div class="voucher-modal-item" data-shop="{{ $v['shop'] }}">
        <div class="voucher-modal-value">{{ $v['voucher']->code }}</div>
        <div class="voucher-modal-copy">
          <strong>{{ $v['voucher']->isFreeShipping() ? 'Free Shipping' : '₱' . number_format($v['voucher']->discount_amount, 2) . ' off' }}</strong>
          <span>{{ $v['shop_name'] }} · Min. spend ₱{{ number_format($v['voucher']->minimum_spend, 2) }}</span>
          <span class="voucher-nearest">{{ $v['reason'] }}</span>
        </div>
      </div>
      @endforeach
      <p id="allVouchersEmptyMessage" style="font-size:13px;color:var(--muted);margin:0" hidden>No vouchers for your currently selected items.</p>
      @if($usableVouchers->isEmpty() && $otherVouchers->isEmpty())
      <p style="font-size:13px;color:var(--muted);margin:0">No vouchers from these shops yet.</p>
      @endif
    </div>
  </div>
</div>

@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const itemChecks = [...document.querySelectorAll('.cart-select')];
  const shopChecks = [...document.querySelectorAll('.cart-shop-select')];
  const selectAll = document.getElementById('cartSelectAll');
  const resetCartSelection = () => {
    [...itemChecks, ...shopChecks, selectAll].filter(Boolean).forEach(input => {
      input.checked = false;
      input.indeterminate = false;
    });
    document.querySelectorAll('[data-remove-selected]').forEach(button => { button.hidden = true; button.disabled = true; });
  };
  resetCartSelection();
  const checkoutButton = document.getElementById('checkoutButton');
  const checkoutForm = document.getElementById('checkoutForm');
  const itemEditModal = document.getElementById('itemEditModal');
  const itemEditForm = document.getElementById('itemEditForm');
  const orderConfirmModal = document.getElementById('orderConfirmModal');
  const orderCancelButton = document.getElementById('orderCancelButton');
  const orderConfirmButton = document.getElementById('orderConfirmButton');
  const addressList = document.getElementById('addressList');
  function updateAddressSummary() {
    const selected = addressList?.querySelector('input[name="delivery_address_radio"]:checked')?.closest('.address-option');
    const summary = document.getElementById('cartAddressSummary');
    if (summary) summary.textContent = selected ? [selected.querySelector('strong')?.textContent, selected.querySelector('.saved-address-location')?.textContent].filter(Boolean).join(' - ') : 'No address selected. Choose or add an address.';
    const badge=document.getElementById('cartAddressDefaultBadge');if(badge)badge.hidden=!selected?.querySelector('[data-default-address]');
  }
  document.getElementById('cartAddressToggle')?.addEventListener('click',()=>{const modal=document.getElementById('cartAddressModal');modal?.classList.add('open');modal?.setAttribute('aria-hidden','false');modal?.querySelector('input:checked,input,button')?.focus();});
  addressList?.addEventListener('change',event=>{if(!event.target.matches('[name="delivery_address_radio"]'))return;updateAddressSummary();const modal=document.getElementById('cartAddressModal');modal?.classList.remove('open');modal?.setAttribute('aria-hidden','true');document.getElementById('cartAddressToggle')?.focus();});
  if (addressList) new MutationObserver(updateAddressSummary).observe(addressList, {childList:true});
  updateAddressSummary();
  const paymentTypeSelect = document.getElementById('paymentTypeSelect');
  const paymentNameSelect = document.getElementById('paymentNameSelect');
  const paymentMethodsByType = @json($paymentMethods->map(fn ($list) => $list->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values()));
  let submittingOrder = false;
  let checkoutReturnFocus = null;
  let orderNoteValue = '';
  if (!selectAll || !checkoutButton) return;
  const money = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function refreshSummary() {
    itemChecks.forEach(input => {
      const row = input.closest('.cart-item');
      row.querySelector('.cart-item-total').textContent = '₱' + money.format(Number(input.dataset.price) * Number(input.dataset.qty));
    });
    const selected = itemChecks.filter(input => input.checked);
    const subtotal = selected.reduce((sum, input) => sum + Number(input.dataset.price) * Number(input.dataset.qty), 0);

    // Shipping is charged once per shop that still has a selected item —
    // not per item — using each shop's own real shipping fee.
    let shipping = 0;
    const selectedShops = new Set();
    document.querySelectorAll('.cart-shop').forEach(shop => {
      const hasSelected = [...shop.querySelectorAll('.cart-select')].some(input => input.checked);
      if (hasSelected) {
        shipping += Number(shop.dataset.shippingFee || 0);
        selectedShops.add(shop.dataset.shopSlug);
      }
    });

    // A shop's voucher is only worth showing while at least one item from
    // that shop is actually selected — otherwise it looks applicable when
    // it isn't. (.cart-offer has its own `display:flex`, which beats the
    // native [hidden] attribute's display:none — toggle display directly.)
    let anyVoucherVisible = false;
    document.querySelectorAll('.cart-offer[data-shop]').forEach(chip => {
      const visible = selectedShops.has(chip.dataset.shop);
      chip.style.display = visible ? '' : 'none';
      if (visible) anyVoucherVisible = true;
    });
    const noVoucherMessage = document.getElementById('noVoucherMessage');
    if (noVoucherMessage) noVoucherMessage.hidden = anyVoucherVisible;

    // Same reactive filtering inside the "View all" modal — it must never
    // show a shop's voucher when nothing from that shop is selected.
    let anyModalVoucherVisible = false;
    let anyLockedForSelectedShop = false;
    document.querySelectorAll('#allVouchersModal .voucher-modal-item[data-shop]').forEach(row => {
      const visible = selectedShops.has(row.dataset.shop);
      row.style.display = visible ? '' : 'none';
      if (visible) {
        anyModalVoucherVisible = true;
        if (!row.classList.contains('is-available')) anyLockedForSelectedShop = true;
      }
    });
    const allVouchersEmptyMessage = document.getElementById('allVouchersEmptyMessage');
    if (allVouchersEmptyMessage) allVouchersEmptyMessage.hidden = anyModalVoucherVisible;

    // "View all" is only worth showing when it has something the inline
    // chip list doesn't already show — i.e. at least one locked/not-yet-
    // applicable voucher for a shop that's actually selected. No selection,
    // a selected shop with no vouchers, or a selected shop whose vouchers
    // are all already shown inline should all hide it.
    const viewAllBtn = document.getElementById('viewAllVouchersBtn');
    if (viewAllBtn) {
      viewAllBtn.hidden = !anyLockedForSelectedShop;
    }

    if (!checkoutForm.dataset.quoteUrl) {
    document.getElementById('cartSubtotal').textContent = '₱' + money.format(subtotal);
    document.getElementById('cartShipping').textContent = @json(\App\Services\BuyerCommerce::installed()) ? 'Calculated at checkout' : selected.length === 0 ? '—' : (shipping > 0 ? '₱' + money.format(shipping) : 'Free');
    }
    const units = selected.reduce((sum, input) => sum + Number(input.dataset.qty), 0);
    document.getElementById('cartSelectedCount').textContent = `${units} item${units === 1 ? '' : 's'} selected`;
    checkoutButton.disabled = selected.length === 0 || Object.keys(paymentMethodsByType).length === 0;

    if (!checkoutForm.dataset.quoteUrl) {
    const discount = appliedVoucherFreeShipping ? shipping : appliedVoucherDiscount;
    document.getElementById('cartDiscountRow').style.display = discount > 0 ? '' : 'none';
    document.getElementById('cartDiscountLabel').textContent = appliedVoucherFreeShipping ? 'Free Shipping' : 'Discount';
    document.getElementById('cartDiscount').textContent = '- ₱' + money.format(discount);
    document.getElementById('cartTotal').textContent = '₱' + money.format(Math.max(0, subtotal + (@json(\App\Services\BuyerCommerce::installed()) ? 0 : shipping - discount)));
    const dockTotal = document.getElementById('cartDockTotal'), dockButton = document.getElementById('cartDockCheckout');
    if (dockTotal) dockTotal.textContent = document.getElementById('cartTotal').textContent;
    if (dockButton) dockButton.disabled = checkoutButton.disabled;

    } else {
      const dockButton=document.getElementById('cartDockCheckout');
      if(dockButton)dockButton.disabled=checkoutButton.disabled;
    }
    shopChecks.forEach(check => {
      const shopItems = [...check.closest('.cart-shop').querySelectorAll('.cart-select')];
      const selectedCount = shopItems.filter(item => item.checked).length;
      check.checked = selectedCount === shopItems.length;
      check.indeterminate = false;
    });
    selectAll.checked = selected.length === itemChecks.length;
    selectAll.indeterminate = false;
    if(checkoutForm.dataset.quoteUrl)document.dispatchEvent(new Event('buyer-cart-summary-changed'));
  }
  window.cartRefreshSummary = refreshSummary;
  window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    resetCartSelection();
    refreshSummary();
  });

  let appliedVoucherDiscount = 0;
  let appliedVoucherFreeShipping = false;
  let appliedVoucherCode = null;

  // Vouchers are only ever applied by clicking "Apply" on a discoverable
  // chip (inline or in the "View all" modal) — never typed in, so there's
  // nothing here to keep in sync with a text field.
  function runVoucherPreview(code) {
    if (!code) {
      appliedVoucherDiscount = 0;
      appliedVoucherFreeShipping = false;
      appliedVoucherCode = null;
      document.querySelectorAll('.cart-offer.selected').forEach(chip => chip.classList.remove('selected'));
      refreshSummary();
      return;
    }
    const selectedKeys = itemChecks.filter(i => i.checked).map(i => i.closest('.cart-item').querySelector('[data-edit-key]')?.dataset.editKey).filter(Boolean);
    fetch('{{ route('buyer.cart.preview-voucher') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
      body: JSON.stringify({ code, items: selectedKeys }),
    })
    .then(r => r.json())
    .then(data => {
      appliedVoucherFreeShipping = !!(data.applies && data.free_shipping);
      appliedVoucherDiscount = (data.applies && !data.free_shipping) ? data.discount : 0;
      appliedVoucherCode = data.applies ? code.toUpperCase() : null;
      document.querySelectorAll('.cart-offer').forEach(chip => chip.classList.toggle('selected', data.applies && chip.dataset.voucherCode === code.toUpperCase()));
      refreshSummary();
    })
    .catch(() => {});
  }

  function applyVoucherCode(code) {
    runVoucherPreview(code);
  }
  document.querySelectorAll('.cart-offer, .voucher-modal-use').forEach(el => {
    el.addEventListener('click', () => applyVoucherCode(el.dataset.voucherCode));
  });

  // Re-checks whatever voucher is currently applied against the
  // newly-selected set of items (e.g. its shop just got deselected).
  function revalidateAppliedVoucher() {
    if (appliedVoucherCode) runVoucherPreview(appliedVoucherCode);
  }

  itemChecks.forEach(input => input.addEventListener('change', () => { refreshSummary(); revalidateAppliedVoucher(); }));
  shopChecks.forEach(input => input.addEventListener('change', () => {
    input.closest('.cart-shop').querySelectorAll('.cart-select').forEach(check => check.checked = input.checked);
    refreshSummary();
    revalidateAppliedVoucher();
  }));
  selectAll.addEventListener('change', () => {
    itemChecks.forEach(check => check.checked = selectAll.checked);
    refreshSummary();
    revalidateAppliedVoucher();
  });
  // Renders the exact same options UI as the product page's Options tab
  // (.pd-opt-group / .pd-opt-row / .pd-opt-btn) — every variation group,
  // every option, with stock shown and out-of-stock ones disabled — so
  // editing a cart item can switch to ANY option the product offers, not
  // just the one dropdown this item happened to be added with.
  // Caps #editQty at whatever's actually in stock for the currently-selected
  // option (or the plain product, when it has no variations) — clamps the
  // current value down if it no longer fits, and shows why.
  function setEditQtyMax(stock) {
    const input = document.getElementById('editQty');
    const errorEl = document.getElementById('editQtyError');
    input.max = Math.max(stock, 0);
    if (Number(input.value) > stock) {
      input.value = Math.max(stock, 1);
      errorEl.textContent = stock > 0 ? `Only ${stock} item(s) available — quantity adjusted.` : 'This option is out of stock.';
      errorEl.style.display = 'block';
    } else {
      errorEl.style.display = 'none';
    }
  }

  function renderEditVariations(variations, currentGroup, currentValue, plainStock) {
    const container = document.getElementById('editVariationsContainer');
    container.innerHTML = '';
    if (!variations || !variations.length) {
      setEditQtyMax(plainStock ?? 99);
      return;
    }

    let matched = false;
    variations.forEach(variation => {
      const group = document.createElement('div');
      group.className = 'pd-opt-group';
      const label = document.createElement('div');
      label.className = 'pd-opt-label';
      label.textContent = variation.name;
      const row = document.createElement('div');
      row.className = 'pd-opt-row';

      (variation.options || []).forEach(opt => {
        const inStock = (opt.stock ?? 0) > 0;
        const isActive = !matched && variation.name === currentGroup && opt.value === currentValue;
        if (isActive) matched = true;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pd-opt-btn' + (isActive ? ' active' : '');
        btn.dataset.group = variation.name;
        btn.dataset.value = opt.value;
        btn.dataset.stock = opt.stock ?? 0;
        if (!inStock) { btn.disabled = true; btn.style.opacity = '.4'; btn.style.cursor = 'not-allowed'; }
        btn.innerHTML = opt.value + (inStock
          ? `<span style="font-size:10px;color:var(--muted);display:block">${opt.stock} left</span>`
          : `<span style="font-size:10px;color:var(--danger);display:block">Out of stock</span>`);
        btn.addEventListener('click', () => {
          container.querySelectorAll('.pd-opt-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          document.getElementById('editValue').value = opt.value;
          document.getElementById('editVariationGroup').value = variation.name;
          setEditQtyMax(opt.stock ?? 0);
        });
        row.appendChild(btn);
      });

      group.appendChild(label);
      group.appendChild(row);
      container.appendChild(group);
    });

    // If nothing matched the item's stored option (e.g. it went out of
    // stock), fall back to leaving the first available one selected —
    // same as how the product page picks a default.
    if (!matched) {
      const firstEnabled = container.querySelector('.pd-opt-btn:not(:disabled)');
      if (firstEnabled) {
        firstEnabled.classList.add('active');
        document.getElementById('editValue').value = firstEnabled.dataset.value;
        document.getElementById('editVariationGroup').value = firstEnabled.dataset.group;
        setEditQtyMax(parseInt(firstEnabled.dataset.stock ?? '0', 10));
      } else {
        setEditQtyMax(0);
      }
    } else {
      document.getElementById('editValue').value = currentValue;
      document.getElementById('editVariationGroup').value = currentGroup;
      const activeBtn = container.querySelector('.pd-opt-btn.active');
      setEditQtyMax(parseInt(activeBtn?.dataset.stock ?? '0', 10));
    }
  }

  document.querySelectorAll('[data-edit-key]').forEach(button => button.addEventListener('click', () => {
    document.getElementById('editQtyError').style.display = 'none';
    document.getElementById('editQty').value = button.dataset.editQty;
    const variations = JSON.parse(button.dataset.editVariations || '[]');
    renderEditVariations(variations, button.dataset.editGroup, button.dataset.editValue, parseInt(button.dataset.editStock ?? '0', 10));

    // Product context, so the modal is never just a bare set of dropdowns.
    const imgWrap = document.getElementById('editItemImgWrap');
    const img = document.getElementById('editItemImg');
    if (button.dataset.editImage) {
      img.src = button.dataset.editImage;
      imgWrap.hidden = false;
    } else {
      imgWrap.hidden = true;
    }
    document.getElementById('editItemName').textContent = button.dataset.editName || '';
    document.getElementById('editItemPrice').textContent = '₱' + (button.dataset.editPrice || '0.00') + ' each';

    itemEditForm.action = '{{ url('/buyer/cart') }}/' + encodeURIComponent(button.dataset.editKey) + '/edit';
    itemEditModal.classList.add('open');
    itemEditModal.setAttribute('aria-hidden', 'false');
    document.getElementById('editQty').focus();
    document.getElementById('editQty').select();
  }));
  document.querySelector('[data-item-edit-close]').addEventListener('click', () => {
    itemEditModal.classList.remove('open');
    itemEditModal.setAttribute('aria-hidden', 'true');
  });

  document.getElementById('editQty').addEventListener('input', function () {
    const max = Number(this.max);
    const errorEl = document.getElementById('editQtyError');
    if (max >= 0 && Number(this.value) > max) {
      errorEl.textContent = max > 0 ? `Only ${max} item(s) available.` : 'This option is out of stock.';
      errorEl.style.display = 'block';
    } else {
      errorEl.style.display = 'none';
    }
  });

  itemEditForm.addEventListener('submit', function (event) {
    const input = document.getElementById('editQty');
    const max = Number(input.max);
    const errorEl = document.getElementById('editQtyError');
    if (max >= 0 && Number(input.value) > max) {
      event.preventDefault();
      errorEl.textContent = max > 0 ? `Only ${max} item(s) available.` : 'This option is out of stock.';
      errorEl.style.display = 'block';
      input.focus();
    }
  });

  // ---- Confirm before removing a cart item ----
  const removeItemModal = document.getElementById('removeItemModal');
  const removeItemCancelButton = document.getElementById('removeItemCancelButton');
  const removeItemConfirmButton = document.getElementById('removeItemConfirmButton');
  let pendingRemoveForm = null;
  let removeReturnFocus = null;

  document.querySelectorAll('[data-remove-selected]').forEach(button => {
    const scope = button.dataset.removeSelected === 'shop' ? button.closest('.cart-shop') : document;
    const selected = () => [...scope.querySelectorAll('.cart-select:checked')];
    const allSelected = () => {
      const items = [...scope.querySelectorAll('.cart-select')];
      return items.length > 0 && items.every(item => item.checked);
    };
    const sync = () => {
      const visible = allSelected();
      button.hidden = !visible;
      button.disabled = !visible;
    };
    document.addEventListener('change', sync);
    sync();
    button.addEventListener('click', () => {
      if (!allSelected()) return;
      const keys = selected().map(input => input.closest('.cart-item').querySelector('[data-edit-key]')?.dataset.editKey).filter(Boolean);
      if (!keys.length) return;
      const form = document.getElementById('removeSelectedItemsForm');
      const inputs = form.querySelector('[data-selected-remove-inputs]');
      inputs.replaceChildren();
      keys.forEach(key => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'items[]'; input.value = key; inputs.append(input); });
      pendingRemoveForm = form;
      removeReturnFocus = button;
      document.getElementById('removeItemTitle').textContent = 'Remove selected items?';
      document.getElementById('removeItemMessage').textContent = `Remove ${keys.length} selected item${keys.length === 1 ? '' : 's'} from your cart?`;
      removeItemCancelButton.textContent = 'Cancel';
      removeItemConfirmButton.textContent = 'Remove';
      removeItemModal.classList.add('open');
      removeItemModal.setAttribute('aria-hidden', 'false');
      removeItemCancelButton.focus();
    });
  });

  function closeRemoveItemModal() {
    removeItemModal.classList.remove('open');
    removeItemModal.setAttribute('aria-hidden', 'true');
    pendingRemoveForm = null;
    removeReturnFocus?.focus();
  }

  document.querySelectorAll('.cart-remove-form').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault();
    pendingRemoveForm = form;
    removeReturnFocus = event.submitter || form.querySelector('button');
    document.getElementById('removeItemTitle').textContent = 'Remove this item?';
    removeItemCancelButton.textContent = 'Keep item';
    removeItemConfirmButton.textContent = 'Remove item';
    const name = form.querySelector('[data-remove-name]')?.dataset.removeName;
    document.getElementById('removeItemMessage').textContent = name
      ? `Remove "${name}" from your cart?`
      : 'Are you sure you want to remove this item from your cart?';
    removeItemModal.classList.add('open');
    removeItemModal.setAttribute('aria-hidden', 'false');
    removeItemCancelButton.focus();
  }));
  removeItemCancelButton.addEventListener('click', closeRemoveItemModal);
  removeItemModal.addEventListener('click', event => {
    if (event.target === removeItemModal) closeRemoveItemModal();
  });
  removeItemModal.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); closeRemoveItemModal(); }
    if (event.key === 'Tab') {
      if (event.shiftKey && document.activeElement === removeItemCancelButton) { event.preventDefault(); removeItemConfirmButton.focus(); }
      else if (!event.shiftKey && document.activeElement === removeItemConfirmButton) { event.preventDefault(); removeItemCancelButton.focus(); }
    }
  });
  removeItemConfirmButton.addEventListener('click', () => {
    if (!pendingRemoveForm || removeItemConfirmButton.disabled) return;
    const form = pendingRemoveForm;
    pendingRemoveForm = null;
    removeItemConfirmButton.disabled = true;
    form.submit();
  });
  window.addEventListener('pageshow', event => { if (event.persisted) removeItemConfirmButton.disabled = false; });

  document.getElementById('reviewGcashPrices')?.addEventListener('click', async event => {
    const button=event.currentTarget, message=document.getElementById('gcashPriceMessage');
    const items=itemChecks.filter(input=>input.checked).map(input=>input.closest('.cart-item').querySelector('[data-edit-key]')?.dataset.editKey).filter(Boolean);
    if(!items.length){message.textContent='Select items to review.';return;}
    button.disabled=true;
    const send=async payload=>{
      const response=await fetch(@json(route('gcash.refreshPrices')),{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':checkoutForm.querySelector('[name="_token"]').value},body:JSON.stringify(payload)});
      const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message);return data;
    };
    try{
      const review=await send({items});
      const changed=review.changes.filter(row=>row.old_price!==row.new_price);
      if(!changed.length){message.textContent='Your selected cart prices are current.';return;}
      const details=changed.map(row=>row.name+': PHP '+row.old_price+' to PHP '+row.new_price+' each (quantity '+row.qty+')').join('\n');
      const dialog = document.getElementById('cartPriceConfirm');
      if (!dialog.showModal) { message.textContent = 'Use a current browser to review price changes safely.'; return; }
      document.getElementById('cartPriceChanges').textContent = details;
      const accepted = await new Promise(resolve => {
        dialog.returnValue = 'cancel';
        dialog.addEventListener('close', () => { button.focus(); resolve(dialog.returnValue === 'accept'); }, {once: true});
        dialog.showModal();
      });
      if (!accepted) return;
      await send({items,acknowledge:true,acknowledgement:review.acknowledgement});location.reload();
    }catch(error){message.textContent=error.message;}finally{button.disabled=false;}
  });

  checkoutForm.addEventListener('submit', async event => {
    const selected = itemChecks.filter(input => input.checked);
    event.preventDefault();
    if (submittingOrder) return;
    if (!selected.length) {
      return;
    }
    if (checkoutForm.dataset.quoteUrl) {
      const data = {items: selected.map(input=>input.closest('.cart-item').querySelector('[data-edit-key]')?.dataset.editKey).filter(Boolean), buyer_note: orderNoteValue};
      if (!window.prepareBuyerCheckout) {
        const feedback = document.getElementById('cartCheckoutFeedback');
        feedback.textContent = 'Checkout could not load. Refresh the page and try again.';
        feedback.setAttribute('role', 'alert');
        feedback.classList.add('auth-error');
        feedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      if (!await window.prepareBuyerCheckout(data)) return;
    }
    const gcash = isGcashSelection();
    const platformGcash = @json(\Illuminate\Support\Facades\Schema::hasColumn('gcash_purchases','review_flow'));
    document.getElementById('orderConfirmTitle').textContent = gcash ? 'Place order with GCash?' : 'Place this order?';
    document.getElementById('orderConfirmMessage').textContent = gcash
      ? (platformGcash ? `Place this order for ${document.getElementById('cartTotal').textContent}, then pay the PocketFinds GCash account and upload your receipt. PocketFinds reviews your payment before the seller prepares the order.` : `Reserve your selected items for one hour for ${document.getElementById('cartTotal').textContent} and open the seller conversation for payment details. After the seller verifies payment, return to the purchase to select Place Order.`)
      : `Place this order for ${selected.length} item${selected.length === 1 ? '' : 's'} with a total of ${document.getElementById('cartTotal').textContent}?`;
    orderConfirmButton.textContent = gcash && !platformGcash ? 'Reserve & message seller' : 'Place Order';
    checkoutReturnFocus = document.activeElement;
    orderConfirmModal.classList.add('open');
    orderConfirmModal.setAttribute('aria-hidden', 'false');
    orderCancelButton.focus();
  });

  function closeOrderConfirm() {
    orderConfirmModal.classList.remove('open');
    orderConfirmModal.setAttribute('aria-hidden', 'true');
    if (!submittingOrder) checkoutReturnFocus?.focus();
  }

  orderCancelButton.addEventListener('click', closeOrderConfirm);
  orderConfirmModal.addEventListener('click', event => {
    if (event.target === orderConfirmModal) closeOrderConfirm();
  });
  orderConfirmModal.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); closeOrderConfirm(); }
    if (event.key === 'Tab') {
      if (event.shiftKey && document.activeElement === orderCancelButton) { event.preventDefault(); orderConfirmButton.focus(); }
      else if (!event.shiftKey && document.activeElement === orderConfirmButton) { event.preventDefault(); orderCancelButton.focus(); }
    }
  });
  orderConfirmButton.addEventListener('click', () => {
    if (submittingOrder) return;
    const selected = itemChecks.filter(input => input.checked);
    if (!selected.length) {
      closeOrderConfirm();
      return;
    }
    selected.forEach(input => {
      const key = input.closest('.cart-item').querySelector('[data-edit-key]')?.dataset.editKey;
      if (!key) return;
      const hidden = document.createElement('input');
      hidden.type = 'hidden'; hidden.name = 'items[]'; hidden.value = key;
      checkoutForm.appendChild(hidden);
    });
    document.getElementById('checkoutPayment').value = paymentNameSelect?.value || '';
    document.getElementById('checkoutVoucher').value = appliedVoucherCode || '';
    document.getElementById('checkoutNote').value = orderNoteValue;
    document.getElementById('checkoutAddress').value = document.querySelector('input[name="delivery_address_radio"]:checked')?.value || '';
    submittingOrder = true;
    orderConfirmButton.disabled = true;
    checkoutButton.disabled = true;
    const dockButton = document.getElementById('cartDockCheckout');
    if (dockButton) dockButton.disabled = true;
    closeOrderConfirm();
    checkoutForm.submit();
  });
  window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    submittingOrder = false;
    orderConfirmButton.disabled = false;
    refreshSummary();
  });

  // ---- Add Note modal ----
  const orderNoteTextarea = document.getElementById('orderNoteTextarea');
  const orderNoteCount = document.getElementById('orderNoteCount');
  const addNoteBtn = document.getElementById('addNoteBtn');

  document.querySelector('[data-modal-open="orderNoteModal"]')?.addEventListener('click', () => {
    orderNoteTextarea.value = orderNoteValue;
    orderNoteCount.textContent = orderNoteTextarea.value.length + '/200';
  });
  orderNoteTextarea?.addEventListener('input', () => {
    orderNoteCount.textContent = orderNoteTextarea.value.length + '/200';
  });
  document.getElementById('saveNoteBtn')?.addEventListener('click', () => {
    orderNoteValue = orderNoteTextarea.value.trim();
    addNoteBtn.style.color = orderNoteValue ? 'var(--pink-dark)' : '';
    addNoteBtn.style.borderColor = orderNoteValue ? 'var(--pink)' : '';
    addNoteBtn.title = orderNoteValue ? 'Edit your note' : 'Add a note for the seller';
  });

  // ---- Payment method: type + name dropdowns ----
  function renderPaymentTypeIcon(type) {
    document.querySelectorAll('.payment-type-icon').forEach(span => span.hidden = span.dataset.type !== type);
  }
  function populatePaymentNames(type) {
    const previous = paymentNameSelect.value;
    paymentNameSelect.innerHTML = '';
    Object.entries(paymentMethodsByType).forEach(([kind,list])=>list.forEach(pm=>{const option=new Option(pm.name,pm.id);option.dataset.type=kind;paymentNameSelect.add(option);}));
    const matching=[...paymentNameSelect.options].find(option=>option.value===previous)||[...paymentNameSelect.options].find(option=>option.dataset.type===type);
    if(matching)paymentNameSelect.value=matching.value;
  }
  function isGcashSelection() {
    return paymentTypeSelect?.value === 'ewallet'
      && (paymentNameSelect?.selectedOptions[0]?.text || '').trim().toLowerCase() === 'gcash';
  }
  function updateVerificationStatus() {
    const selectedType=paymentNameSelect?.selectedOptions[0]?.dataset.type;
    if(selectedType&&paymentTypeSelect)paymentTypeSelect.value=selectedType;
    const type = paymentTypeSelect?.value;
    renderPaymentTypeIcon(type);
    const statusEl = document.getElementById('paymentVerifyStatus');
    checkoutButton.textContent = isGcashSelection() ? 'Continue with GCash' : 'Review & Place Order';
    const priceReview = document.querySelector('.cart-price-review');
    if (priceReview) priceReview.hidden = !isGcashSelection();
    document.querySelectorAll('[name=cart_payment_choice]').forEach(choice => choice.checked = choice.value === paymentNameSelect.value);
    const dockButton = document.getElementById('cartDockCheckout');
    if (dockButton) dockButton.textContent = checkoutButton.textContent;
    if (!statusEl) return;
    if (!type || type === 'cod' || type === 'other') { statusEl.innerHTML = ''; return; }
    const name = paymentNameSelect.options[paymentNameSelect.selectedIndex]?.text || '';
    if(isGcashSelection()){ statusEl.textContent=''; return; }
    statusEl.textContent = '';
  }
  if (paymentTypeSelect && paymentNameSelect) {
    populatePaymentNames(paymentTypeSelect.value);
    updateVerificationStatus();
    document.querySelectorAll('[name=cart_payment_choice]').forEach(choice => choice.addEventListener('change', () => {
      if (!choice.checked) return;
      paymentTypeSelect.value = choice.dataset.type;
      populatePaymentNames(choice.dataset.type);
      paymentNameSelect.value = choice.value;
      updateVerificationStatus();
    }));
    paymentTypeSelect.addEventListener('change', () => {
      renderPaymentTypeIcon(paymentTypeSelect.value);
      populatePaymentNames(paymentTypeSelect.value);
      updateVerificationStatus();
    });
    paymentNameSelect.addEventListener('change', updateVerificationStatus);
  }

  const checkoutDock = document.querySelector('.cart-checkout-dock');
  const mobileNav = document.querySelector('.mobile-nav');
  if (checkoutDock && mobileNav) {
    const positionCheckoutDock = () => {
      const mobileCheckout = window.matchMedia('(max-width: 960px)').matches;
      checkoutDock.hidden = !mobileCheckout;
      checkoutButton.hidden = mobileCheckout;
      checkoutDock.style.bottom = `${mobileNav.getBoundingClientRect().height}px`;
      document.body.style.setProperty('--cart-fixed-controls-height', `${mobileCheckout ? mobileNav.getBoundingClientRect().height + checkoutDock.getBoundingClientRect().height : 0}px`);
    };
    positionCheckoutDock();
    window.addEventListener('resize', positionCheckoutDock);
    if (window.ResizeObserver) new ResizeObserver(positionCheckoutDock).observe(mobileNav);
  }

  refreshSummary();
});

</script>
@endpush

@push('head')
{{-- Essential cart layout travels with the page, without a separate asset request. --}}
<style id="buyer-cart-page-styles">{!! file_get_contents(public_path('css/cart-page.css')) !!}</style>
@endpush

@push('scripts')
@if(\App\Services\BuyerCommerce::installed())<script src="{{ asset('js/buyer-cart-checkout.js') }}?v={{ filemtime(public_path('js/buyer-cart-checkout.js')) }}"></script>@endif
<script src="{{ asset('js/cart-page.js') }}?v={{ filemtime(public_path('js/cart-page.js')) }}" defer></script>
@endpush
