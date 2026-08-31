<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FetchesProducts;
use App\Models\Product;
use App\Models\User;
use App\Models\Message;
use App\Models\Complaint;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\Review;
use App\Models\Voucher;
use App\Models\BuyerPaymentAccount;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BuyerController extends Controller
{
    use FetchesProducts;

    public function dashboard()
    {
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);
        $products   = $this->dbProducts(12);
        return view('buyer.dashboard', compact('categories', 'products'));
    }

    public function browse(Request $request)
    {
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);
        $products   = $this->dbProducts(
            0,
            $request->filled('category') ? (int) $request->input('category') : null,
            $request->filled('q') ? $request->input('q') : null,
            $request->input('sort')
        );
        return view('buyer.browse', compact('products', 'categories'));
    }

    public function product($id)
    {
        $p = Product::with(['seller', 'category'])->where('id', $id)->where('status', 'active')->firstOrFail();
        $product      = $this->mapProduct($p);
        $sellerOnline = Cache::has('seller-online-' . $p->seller_id);
        $shopProducts = Product::with(['seller','category'])->where('seller_id', $p->seller_id)->where('status','active')->where('id','!=',$id)->limit(6)->get()->map(fn($r) => $this->mapProduct($r))->all();
        $titleTerms = collect(preg_split('/[^\\pL\\pN]+/u', $p->name))
            ->filter(fn ($term) => mb_strlen($term) > 2)
            ->unique()
            ->take(5);
        // "Similar products" should point buyers toward other shops, not
        // just other listings from the same seller (that's what "More from
        // this shop" is for) — so a different seller is required here.
        $related = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->where('id', '!=', $id)
            ->where('seller_id', '!=', $p->seller_id)
            ->when($titleTerms->isNotEmpty(), function ($query) use ($titleTerms) {
                $query->where(function ($matches) use ($titleTerms) {
                    foreach ($titleTerms as $term) {
                        $matches->orWhere('name', 'ilike', '%' . $term . '%');
                    }
                });
            }, fn ($query) => $query->whereRaw('1 = 0'))
            ->limit(8)
            ->get()
            ->map(fn($r) => $this->mapProduct($r))
            ->all();
        $shop = null;
        return view('buyer.product', compact('product', 'shop', 'related', 'shopProducts', 'sellerOnline'));
    }

    public function shop($slug)
    {
        $seller = User::where('username', $slug)->where('account_type', 'seller')->firstOrFail();
        $items  = Product::with(['seller','category'])->where('seller_id', $seller->id)->where('status','active')->get()->map(fn($p) => $this->mapProduct($p))->all();
        $shop   = [
            'name'     => $seller->business_name ?? ($seller->given_names . ' ' . $seller->last_name),
            'initial'  => strtoupper(substr($seller->given_names, 0, 1)),
            'rating'   => 0,
            'products' => count($items),
            'sales'    => '0',
            'joined'   => $seller->created_at->format('M Y'),
            'desc'     => '',
        ];
        return view('buyer.shop', compact('shop', 'items', 'slug'));
    }

    public function cart()
    {
        // The cart lives in the database, scoped to the buyer's account —
        // it survives logging out and long idle gaps, unlike a session cart.
        $cart = CartItem::where('buyer_id', auth()->id())->get()->keyBy('id')->map->toArray();
        $products = Product::whereIn('id', collect($cart)->pluck('product_id')->filter()->unique())->get()->keyBy('id');
        $items = collect($cart)->map(function (array $item, string $key) use ($products) {
            return array_merge($item, [
                'key' => $key,
                // The full raw variation groups (name + every option, with
                // stock/price/image) so the edit modal can render the exact
                // same options UI as the product page's Options tab —
                // not just the one group this item happens to use.
                'product_variations' => $products->get($item['product_id'])?->variations ?? [],
            ]);
        });
        $groups = $items->groupBy('seller_slug');
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()->groupBy('type');

        // Real, seller-configured shipping fee per shop in the cart (defaults
        // to free when a seller hasn't set one).
        $sellers = User::whereIn('username', $groups->keys())->get()->keyBy('username');
        $shippingFees = $groups->keys()->mapWithKeys(fn ($slug) => [$slug => (float) ($sellers->get($slug)?->shipping_fee ?? 0)]);

        // Every voucher belonging to a shop currently in the cart, split into
        // ones that actually apply right now (shown up front) and ones that
        // don't yet — wrong shop's minimum spend not met, expired, inactive,
        // or used up (tucked away behind "View all").
        $sellerIds = $sellers->pluck('id')->filter()->values();
        $vouchersBySeller = Voucher::whereIn('seller_id', $sellerIds)->latest('created_at')->get()->groupBy('seller_id');
        $usableVouchers = collect();
        $otherVouchers = collect();
        foreach ($groups as $slug => $shopItems) {
            $seller = $sellers->get($slug);
            if (!$seller) continue;
            $shopSubtotal = $shopItems->sum(fn ($item) => $item['price'] * $item['qty']);
            foreach ($vouchersBySeller->get($seller->id, collect()) as $voucher) {
                $entry = ['voucher' => $voucher, 'shop' => $slug, 'shop_name' => $shopItems->first()['seller']];
                if ($voucher->isUsable() && $shopSubtotal >= $voucher->minimum_spend) {
                    $usableVouchers->push($entry);
                } else {
                    $entry['reason'] = !$voucher->is_active ? 'Inactive'
                        : ($voucher->expires_at && $voucher->expires_at->isPast() ? 'Expired'
                        : ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit ? 'Usage limit reached'
                        : 'Spend ₱' . number_format(max(0, $voucher->minimum_spend - $shopSubtotal), 2) . ' more to unlock'));
                    $otherVouchers->push($entry);
                }
            }
        }

        // Which of the buyer's own verified e-wallet/bank accounts match a
        // payment method offered here, so the cart can show a "Verified"
        // badge (or a prompt to verify) once a specific method is chosen.
        $paymentAccounts = BuyerPaymentAccount::where('buyer_id', auth()->id())->get();
        $verifiedLookup = [];
        foreach ($paymentAccounts as $account) {
            $key = $account->type === 'bank' ? 'bank:' . strtolower($account->bank_name ?? '') : $account->type;
            $verifiedLookup[$key] = true;
        }

        return view('buyer.cart', compact('items', 'groups', 'paymentMethods', 'shippingFees', 'usableVouchers', 'otherVouchers', 'verifiedLookup'));
    }

    public function cartAdd(Request $request)
    {
        $data = $request->validate([
            'product_id'      => ['required'],
            'variation_group' => ['nullable', 'string', 'max:150'],
            'variation_value' => ['nullable', 'string', 'max:150'],
            'qty'             => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $p    = Product::with(['seller','category'])->where('id', $data['product_id'])->where('status','active')->firstOrFail();
        $prod = $this->mapProduct($p);

        // A selected option carries its own authoritative price/stock — never
        // trust a client-submitted price, always resolve it here. A shop-wide
        // discount (mapProduct's resolved price) applies unless the chosen
        // variation option has its own explicit price.
        $price = (float) $prod['price'];
        $variationValue = '';
        $variationGroup = '';
        $available = (int) $p->stock;

        if (!empty($p->variations)) {
            abort_unless(!empty($data['variation_group']) && !empty($data['variation_value']), 422, 'Select an available variation first.');
            $option = $this->findVariationOption($p, $data['variation_group'], $data['variation_value']);
            abort_unless($option, 422, 'Selected option is no longer available.');
            $available = (int) ($option['stock'] ?? 0);
            abort_if($available <= 0, 422, 'Selected option is out of stock.');
            if (isset($option['price'])) $price = (float) $option['price'];
            $variationValue = $data['variation_value'];
            $variationGroup = $data['variation_group'];
        } else {
            abort_if($available <= 0, 422, 'This product is out of stock.');
        }

        $buyerId = auth()->id();
        $existing = CartItem::where('buyer_id', $buyerId)->where('product_id', $p->id)
            ->where('variation_value', $variationValue)->where('variation_group', $variationGroup)->first();
        $requestedQuantity = ($existing->qty ?? 0) + $data['qty'];
        abort_if($requestedQuantity > $available, 422, "Only {$available} item(s) are currently available.");

        if ($existing) {
            $existing->update(['qty' => min(99, $existing->qty + $data['qty'])]);
        } else {
            CartItem::create([
                'buyer_id'        => $buyerId,
                'product_id'      => $p->id,
                'name'            => $p->name,
                'price'           => $price,
                'variation_value' => $variationValue,
                'variation_group' => $variationGroup,
                'qty'             => $data['qty'],
                'img'             => $prod['img'],
                'seller'          => $prod['seller'],
                'seller_slug'     => $prod['seller_slug'],
            ]);
        }

        return response()->json(['count' => (int) CartItem::where('buyer_id', $buyerId)->sum('qty')]);
    }

    /** Authoritatively resolve one variation option from a product's own stored data — never trust client-submitted price/stock/image for it. */
    private function findVariationOption(Product $p, string $group, string $value): ?array
    {
        foreach ($p->variations ?? [] as $variation) {
            if (($variation['name'] ?? null) !== $group) continue;
            foreach ($variation['options'] ?? [] as $option) {
                if (($option['value'] ?? null) === $value) return $option;
            }
        }
        return null;
    }

    public function cartUpdate(Request $request, string $key)
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:1', 'max:99']]);
        $item = CartItem::where('buyer_id', auth()->id())->where('id', $key)->first();
        if (!$item) {
            return redirect()->route('buyer.cart')->with('error', 'This item is no longer in your cart.');
        }
        $item->update(['qty' => $data['qty']]);
        return redirect()->route('buyer.cart');
    }

    public function cartEdit(Request $request, string $key)
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
            'variation_value' => ['nullable', 'string', 'max:150'],
            'variation_group' => ['nullable', 'string', 'max:150'],
        ]);
        // Plain HTML form submits (no Accept: application/json header) here —
        // abort(404/422) would render Laravel's raw debug error page instead
        // of the cart, so every failure below bounces back with a flash
        // message instead, same as every other buyer-facing form.
        $item = CartItem::where('buyer_id', auth()->id())->where('id', $key)->first();
        if (!$item) {
            return redirect()->route('buyer.cart')->with('error', 'This item is no longer in your cart.');
        }
        $product = Product::find($item->product_id);
        if (!$product) {
            return back()->with('error', 'This product is no longer available.');
        }

        $newValue = $data['variation_value'] ?? '';
        $groupName = '';
        $price = $item->price;

        if (!empty($product->variations)) {
            // Same "pick exactly one option, from any of the product's
            // variation groups" model as adding to cart — re-resolve the
            // option authoritatively so switching to a different group
            // (and possibly a different price) can never use a stale price.
            $groupName = $data['variation_group'] ?? '';
            if (!$groupName || $newValue === '') {
                return back()->with('error', 'Select an option first.');
            }
            $option = $this->findVariationOption($product, $groupName, $newValue);
            if (!$option) {
                return back()->with('error', 'Selected option is no longer available.');
            }
            if (($option['stock'] ?? 0) <= 0) {
                return back()->with('error', 'Selected option is out of stock.');
            }
            if (isset($option['price'])) $price = (float) $option['price'];
        }

        $merge = CartItem::where('buyer_id', auth()->id())->where('product_id', $item->product_id)
            ->where('variation_value', $newValue)->where('variation_group', $groupName)
            ->where('id', '!=', $item->id)->first();

        if ($merge) {
            $merge->update(['qty' => min(99, $merge->qty + $data['qty'])]);
            $item->delete();
        } else {
            $item->update(['qty' => $data['qty'], 'variation_value' => $newValue, 'variation_group' => $groupName, 'price' => $price]);
        }

        return redirect()->route('buyer.cart');
    }

    public function cartRemove(string $key)
    {
        $item = CartItem::where('buyer_id', auth()->id())->where('id', urldecode($key))->first();
        if ($item) $item->delete();
        return redirect()->route('buyer.cart');
    }

    public function orders(Request $request)
    {
        $allowedTabs = ['all', 'to_ship', 'in_transit', 'out_for_delivery', 'completed', 'cancelled'];
        $tab = in_array($request->query('tab'), $allowedTabs, true) ? $request->query('tab') : 'all';
        $baseQuery = Order::where('buyer_id', auth()->id());
        $orderCounts = (clone $baseQuery)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $orders = $baseQuery->with(['seller', 'paymentMethod', 'shipment', 'review'])
            ->when($tab !== 'all', fn ($query) => $query->where('status', $tab))
            ->latest()
            ->get();
        return view('buyer.orders', compact('orders', 'tab', 'orderCounts'));
    }

    public function storeReview(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === auth()->id(), 403);
        if ($order->status !== 'completed') {
            return back()->with('error', 'You can only rate completed orders.');
        }
        if ($order->review()->exists()) {
            return back()->with('error', 'You already rated this order.');
        }

        $data = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $firstItem = collect($order->items ?? [])->first() ?? [];

        Review::create([
            'order_id'   => $order->id,
            'buyer_id'   => auth()->id(),
            'seller_id'  => $order->seller_id,
            'product_id' => $firstItem['product_id'] ?? null,
            'rating'     => $data['rating'],
            'comment'    => $data['comment'] ?? null,
        ]);

        return back()->with('success', 'Thanks for rating your order!');
    }

    public function cancelOrder(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === auth()->id(), 403);
        if ($order->status !== 'to_ship') {
            return back()->with('error', 'This order can no longer be cancelled.');
        }

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'in:Changed my mind,Found a better price,Ordered by mistake,Payment issue,Other'],
            'cancellation_note' => ['nullable', 'string', 'max:500'],
        ]);
        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $data['cancellation_reason'],
            'cancellation_note' => $data['cancellation_note'] ?? null,
        ]);

        return redirect()->route('buyer.orders', ['tab' => 'cancelled'])->with('success', 'Order cancelled successfully.');
    }

    /** Buyer-initiated "Order Received" — the courier app doesn't always get updated promptly, so the buyer can confirm delivery themselves. */
    public function confirmReceipt(Order $order)
    {
        abort_unless($order->buyer_id === auth()->id(), 403);
        if ($order->status !== 'out_for_delivery') {
            return back()->with('error', 'This order cannot be confirmed as received yet.');
        }

        $order->update(['status' => 'completed']);
        if ($order->shipment && !$order->shipment->delivered_at) {
            $order->shipment->update(['delivered_at' => now()]);
        }

        DB::table('notifications')->insert([
            'id'                => (string) Str::uuid(),
            'user_id'           => $order->seller_id,
            'title'             => 'Order Delivered',
            'message'           => 'Order #' . $order->order_number . ' has been delivered to the customer.',
            'notification_type' => 'order_delivered',
            'reference_id'      => $order->id,
            'is_read'           => false,
            'created_at'        => now(),
        ]);

        return redirect()->route('buyer.orders', ['tab' => 'completed'])->with('success', 'Thanks for confirming! Your order is now marked as completed.');
    }

    /** Re-adds every item from a past order back into the cart, at today's price/stock. */
    public function buyAgain(Order $order)
    {
        abort_unless($order->buyer_id === auth()->id(), 403);

        $added = 0;
        $skipped = 0;
        foreach ($order->items ?? [] as $item) {
            $product = Product::where('id', $item['product_id'] ?? null)->where('status', 'active')->first();
            if (!$product) { $skipped++; continue; }

            $prod = $this->mapProduct($product);
            $price = (float) $prod['price'];
            // Orders placed before the cart's variation_value/variation_group
            // rename still carry the old "color" key in their items JSON —
            // fall back to it so re-buying an old order still works.
            $value = $item['variation_value'] ?? $item['color'] ?? '';
            $variationGroup = '';
            $available = (int) $product->stock;

            if (!empty($product->variations) && $value !== '') {
                $group = collect($product->variations)->first(fn ($v) => collect($v['options'] ?? [])->contains(fn ($o) => ($o['value'] ?? null) === $value));
                if ($group) {
                    $option = collect($group['options'])->first(fn ($o) => ($o['value'] ?? null) === $value);
                    $available = (int) ($option['stock'] ?? 0);
                    if (isset($option['price'])) $price = (float) $option['price'];
                    $variationGroup = $group['name'];
                }
            }

            if ($available <= 0) { $skipped++; continue; }
            $qty = min((int) ($item['qty'] ?? 1), $available, 99);

            $existing = CartItem::where('buyer_id', auth()->id())->where('product_id', $product->id)
                ->where('variation_value', $value)->where('variation_group', $variationGroup)->first();
            if ($existing) {
                $existing->update(['qty' => min(99, $existing->qty + $qty)]);
            } else {
                CartItem::create([
                    'buyer_id' => auth()->id(), 'product_id' => $product->id,
                    'name' => $product->name, 'price' => $price, 'qty' => $qty,
                    'variation_value' => $value, 'variation_group' => $variationGroup,
                    'img' => $prod['img'], 'seller' => $prod['seller'], 'seller_slug' => $prod['seller_slug'],
                ]);
            }
            $added++;
        }

        if ($added === 0) {
            return back()->with('error', 'None of the items in this order are available anymore.');
        }

        return redirect()->route('buyer.cart')->with('success', $skipped
            ? "Added {$added} item(s) back to your cart. {$skipped} item(s) are no longer available."
            : "Added {$added} item(s) back to your cart.");
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string'],
            'voucher_code' => ['nullable', 'string', 'max:30'],
            'buyer_note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'integer', 'exists:payment_methods,id'],
        ]);
        $cart = CartItem::where('buyer_id', auth()->id())->get()->keyBy('id')->map->toArray()->all();
        $selected = collect($data['items'])->filter(fn ($key) => array_key_exists($key, $cart));
        if ($selected->isEmpty()) {
            return back()->with('error', 'Select at least one cart item.');
        }
        $items = $selected->map(fn ($key) => array_merge($cart[$key], ['key' => $key]))->values();
        $buyer = auth()->user();
        $address = collect(['house_no', 'street', 'barangay', 'municipality', 'province'])
            ->mapWithKeys(fn ($field) => [$field => $buyer->{$field} ?: 'Not provided'])->all();
        $paymentMethod = PaymentMethod::findOrFail($data['payment_method']);
        $voucherCode = !empty($data['voucher_code']) ? strtoupper($data['voucher_code']) : null;

        // Validate the voucher against the shops actually in this checkout
        // *before* creating anything — an invalid code should reject the
        // whole attempt, not silently go through without the discount.
        if ($voucherCode && !$this->resolveVoucherForItems($items, $voucherCode)) {
            return back()->withErrors(['voucher_code' => "That voucher code doesn't apply to your order."])->withInput();
        }

        $created = [];

        DB::transaction(function () use ($items, $data, $address, $paymentMethod, $voucherCode, $buyer, &$created) {
            foreach ($items->groupBy('seller_slug') as $sellerSlug => $sellerItems) {
                $seller = User::where('username', $sellerSlug)->where('account_type', 'seller')->first();
                if (!$seller) continue;
                $subtotal = $sellerItems->sum(fn ($item) => $item['price'] * $item['qty']);
                $shipping = (float) ($seller->shipping_fee ?? 0);

                // A voucher only ever belongs to one shop — apply it to that
                // shop's order when the code matches and the cart qualifies.
                $discount = 0;
                $appliedVoucher = null;
                if ($voucherCode) {
                    $voucher = Voucher::where('seller_id', $seller->id)->where('code', $voucherCode)->first();
                    if ($voucher && $voucher->isUsable() && $subtotal >= $voucher->minimum_spend) {
                        $discount = $voucher->isFreeShipping()
                            ? $shipping
                            : min((float) $voucher->discount_amount, $subtotal);
                        $appliedVoucher = $voucher;
                    }
                }

                $order = Order::create([
                    'order_number' => 'PF-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(5)),
                    'buyer_id' => auth()->id(), 'seller_id' => $seller->id, 'status' => 'to_ship',
                    'items' => $sellerItems->all(), 'subtotal' => $subtotal,
                    'shipping_amount' => $shipping, 'discount_amount' => $discount,
                    'voucher_code' => $appliedVoucher?->code,
                    'total' => max(0, $subtotal + $shipping - $discount), 'shipping_address' => $address,
                    'buyer_note' => $data['buyer_note'] ?? null,
                    'payment_method' => $paymentMethod->name,
                    'payment_method_id' => $data['payment_method'] ?? null,
                ]);
                $created[] = $order;
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $seller->id,
                    'title' => 'New Order',
                    'message' => 'New order ' . $order->order_number . ' from ' . trim($buyer->given_names . ' ' . $buyer->last_name) . ' totaling ₱' . number_format($order->total, 2) . '.',
                    'notification_type' => 'new_order',
                    'reference_id' => $order->id,
                    'is_read' => false,
                    'created_at' => now(),
                ]);
                if ($appliedVoucher) {
                    $appliedVoucher->increment('used_count');
                }
                CartItem::where('buyer_id', auth()->id())->whereIn('id', $sellerItems->pluck('key'))->delete();
            }
        });

        return redirect()->route('buyer.orders', ['tab' => 'to_ship'])->with('success', 'Order submitted successfully.');
    }

    /** Whether the given code matches a usable voucher for any shop present in $items. */
    private function resolveVoucherForItems($items, string $code): bool
    {
        $sellerSlugs = $items->pluck('seller_slug')->unique();
        $sellersInCart = User::whereIn('username', $sellerSlugs)->pluck('id', 'username');
        foreach ($items->groupBy('seller_slug') as $sellerSlug => $sellerItems) {
            $sellerId = $sellersInCart->get($sellerSlug);
            if (!$sellerId) continue;
            $subtotal = $sellerItems->sum(fn ($item) => $item['price'] * $item['qty']);
            $voucher = Voucher::where('seller_id', $sellerId)->where('code', $code)->first();
            if ($voucher && $voucher->isUsable() && $subtotal >= $voucher->minimum_spend) {
                return true;
            }
        }
        return false;
    }

    /** Live voucher preview for the cart page — same rules as checkout, no order created. */
    public function previewVoucher(Request $request)
    {
        $data = $request->validate([
            'code'    => ['required', 'string', 'max:30'],
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => ['string'],
        ]);
        $cart = CartItem::where('buyer_id', auth()->id())->get()->keyBy('id')->map->toArray()->all();
        $selected = collect($data['items'])->filter(fn ($key) => array_key_exists($key, $cart));
        if ($selected->isEmpty()) {
            return response()->json(['applies' => false, 'message' => 'Select at least one item first.']);
        }
        $items = $selected->map(fn ($key) => array_merge($cart[$key], ['key' => $key]))->values();
        $code = strtoupper($data['code']);

        $sellerSlugs = $items->pluck('seller_slug')->unique();
        $sellers = User::whereIn('username', $sellerSlugs)->get()->keyBy('username');
        foreach ($items->groupBy('seller_slug') as $sellerSlug => $sellerItems) {
            $seller = $sellers->get($sellerSlug);
            if (!$seller) continue;
            $subtotal = $sellerItems->sum(fn ($item) => $item['price'] * $item['qty']);
            $voucher = Voucher::where('seller_id', $seller->id)->where('code', $code)->first();
            if ($voucher && $voucher->isUsable() && $subtotal >= $voucher->minimum_spend) {
                $freeShipping = $voucher->isFreeShipping();
                return response()->json([
                    'applies'       => true,
                    'free_shipping' => $freeShipping,
                    'discount'      => $freeShipping ? (float) ($seller->shipping_fee ?? 0) : min((float) $voucher->discount_amount, $subtotal),
                    'shop'          => $sellerSlug,
                ]);
            }
        }
        return response()->json(['applies' => false, 'message' => "That code doesn't apply to your selected items."]);
    }

    public function messages(Request $request)
    {
        $product = null;
        if ($request->filled('product')) {
            $product = Product::with('seller')->where('id', $request->query('product'))->where('status', 'active')->first();
        }

        // A specific variation may be pre-attached (e.g. from the product
        // page's Chat button with an option selected) — resolve it
        // authoritatively so the preview shows that option's own price/photo.
        $productVariation = null;
        if ($product && $request->filled('variation_group') && $request->filled('variation_value')) {
            $option = $this->findVariationOption($product, $request->query('variation_group'), $request->query('variation_value'));
            if ($option) {
                $productVariation = [
                    'group' => $request->query('variation_group'),
                    'value' => $request->query('variation_value'),
                    'label' => $request->query('variation_group') . ': ' . $option['value'],
                    'price' => (float) ($option['price'] ?? $product->price),
                    'image' => !empty($option['image']) ? (rtrim(config('filesystems.disks.supabase.url'), '/') . '/' . ltrim($option['image'], '/')) : null,
                ];
            }
        }

        $seller = null;
        $sellerOnline = false;
        if ($request->filled('seller')) {
            $seller = User::where('username', $request->query('seller'))->where('account_type', 'seller')->first();
            if ($seller) $sellerOnline = Cache::has('seller-online-' . $seller->id);
        }

        $messages = [];
        if ($seller) {
            $messages = Message::with('product')
                ->where(function($q) use ($seller) {
                    $q->where('sender_id', auth()->id())->where('receiver_id', $seller->id);
                })->orWhere(function($q) use ($seller) {
                    $q->where('sender_id', $seller->id)->where('receiver_id', auth()->id());
                })
                ->orderBy('created_at')
                ->get();

            // Opening a conversation marks only the seller's persisted messages as read.
            Message::where('sender_id', $seller->id)
                ->where('receiver_id', auth()->id())
                ->where('read', false)
                ->update(['read' => true]);
        }

        $sellerProducts = $seller
            ? Product::where('seller_id', $seller->id)->where('status', 'active')->orderBy('name')->get()
            : collect();

        // All conversations this buyer has
        $conversations = Message::with(['sender','receiver','product'])
            ->where('sender_id', auth()->id())
            ->orWhere('receiver_id', auth()->id())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn($m) => $m->sender_id === auth()->id() ? $m->receiver_id : $m->sender_id)
            ->map(fn($msgs) => $msgs->first());

        $data = compact('product', 'productVariation', 'seller', 'sellerOnline', 'messages', 'conversations', 'sellerProducts');

        if ($request->ajax()) {
            return view('buyer.partials.messages-panel', $data);
        }

        return view('buyer.messages', $data);
    }

    public function messagesPoll(Request $request)
    {
        $data = $request->validate(['receiver_id' => ['required', 'integer']]);
        $seller = User::whereKey($data['receiver_id'])->where('account_type', 'seller')->firstOrFail();

        Message::where('sender_id', $seller->id)
            ->where('receiver_id', auth()->id())
            ->where('read', false)
            ->update(['read' => true]);

        $messages = Message::with('product')
            ->where(function ($query) use ($seller) {
                $query->where('sender_id', auth()->id())->where('receiver_id', $seller->id);
            })->orWhere(function ($query) use ($seller) {
                $query->where('sender_id', $seller->id)->where('receiver_id', auth()->id());
            })->orderBy('created_at')->get()
            ->map(fn ($message) => $this->formatMessage($message));

        return response()->json(['ok' => true, 'messages' => $messages]);
    }

    public function reportMessage(Request $request)
    {
        $data = $request->validate([
            'message_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'evidence' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
        ]);
        $message = Message::with(['sender', 'receiver'])->whereKey($data['message_id'])
            ->where(fn ($query) => $query->where('sender_id', auth()->id())->orWhere('receiver_id', auth()->id()))
            ->firstOrFail();
        $evidence = $request->file('evidence');
        $values = [
            'id' => (string) Str::uuid(), 'order_id' => null, 'complainant_id' => auth()->id(),
            'respondent_id' => $message->sender_id === auth()->id() ? $message->receiver_id : $message->sender_id,
            'complaint_type' => $data['reason'], 'subject' => 'Reported chat message',
            'description' => $data['description'] ?? null, 'status' => 'open',
            'message_id' => $message->id, 'shop_name' => $message->receiver?->business_name ?? $message->sender?->business_name,
            'message_body' => $message->body, 'message_type' => $message->attachment_type ?: ($message->body ? 'text' : 'message'),
        ];
        if ($evidence) {
            $values['evidence_path'] = $evidence->store('report_evidence', 'supabase');
            $values['evidence_name'] = $evidence->getClientOriginalName();
            $values['evidence_mime'] = $evidence->getMimeType();
            $values['evidence_type'] = str_starts_with($values['evidence_mime'], 'video/') ? 'video' : 'image';
            $values['evidence_size'] = $evidence->getSize();
        }
        Complaint::create($values);
        return response()->json(['ok' => true, 'message' => 'Report sent to admin.']);
    }

    public function reportProduct(Request $request)
    {
        $data = $request->validate([
            'product_id'  => ['required', 'string'],
            'reason'      => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'evidence'    => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
        ]);
        $product = Product::with('seller')->where('id', $data['product_id'])->where('status', 'active')->firstOrFail();
        $sellerName = $product->seller->business_name ?? trim($product->seller->given_names . ' ' . $product->seller->last_name);

        $evidence = $request->file('evidence');
        $values = [
            'id' => (string) Str::uuid(), 'order_id' => null, 'complainant_id' => auth()->id(),
            'respondent_id' => $product->seller_id,
            'complaint_type' => $data['reason'], 'subject' => 'Reported product listing',
            'description' => $data['description'] ?? null, 'status' => 'open',
            'message_id' => null, 'shop_name' => $sellerName,
            'message_body' => $product->name, 'message_type' => 'product',
        ];
        if ($evidence) {
            $values['evidence_path'] = $evidence->store('report_evidence', 'supabase');
            $values['evidence_name'] = $evidence->getClientOriginalName();
            $values['evidence_mime'] = $evidence->getMimeType();
            $values['evidence_type'] = str_starts_with($values['evidence_mime'], 'video/') ? 'video' : 'image';
            $values['evidence_size'] = $evidence->getSize();
        }
        Complaint::create($values);
        return response()->json(['ok' => true, 'message' => 'Report sent to admin.']);
    }

    public function messagesSend(Request $request)
    {
        $data = $request->validate([
            'receiver_id'      => ['required', 'integer'],
            'body'             => ['nullable', 'string', 'max:2000'],
            'product_id'       => ['nullable', 'string'],
            'variation_group'  => ['nullable', 'string', 'max:150'],
            'variation_value'  => ['nullable', 'string', 'max:150'],
            'attachments'   => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
            'attachment'    => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
        ]);

        $receiver = User::whereKey($data['receiver_id'])->where('account_type', 'seller')->firstOrFail();
        $files = $request->file('attachments', []);
        if ($request->hasFile('attachment')) $files[] = $request->file('attachment');
        abort_unless(filled($data['body'] ?? null) || $files || filled($data['product_id'] ?? null), 422, 'Send a message, product, image, or video.');

        $product = null;
        if (filled($data['product_id'] ?? null)) {
            $product = Product::whereKey($data['product_id'])->where('seller_id', $receiver->id)->where('status', 'active')->firstOrFail();
        }

        // Never trust a client-submitted label/price/image for the attached
        // variation — resolve it from the product's own stored data.
        $variationSnapshot = null;
        if ($product && filled($data['variation_group'] ?? null) && filled($data['variation_value'] ?? null)) {
            $option = $this->findVariationOption($product, $data['variation_group'], $data['variation_value']);
            if ($option) {
                $variationSnapshot = [
                    'variation_label' => $data['variation_group'] . ': ' . $option['value'],
                    'variation_price' => (float) ($option['price'] ?? $product->price),
                    'variation_image' => $option['image'] ?? null,
                ];
            }
        }

        $messages = [];
        foreach ($files ?: [null] as $index => $file) {
            $msg = ['sender_id' => auth()->id(), 'receiver_id' => $data['receiver_id'], 'read' => false];
            if ($index === 0 && filled($data['body'] ?? null)) $msg['body'] = $data['body'];
            if ($index === 0 && $product) {
                $msg['product_id'] = $product->id;
                if ($variationSnapshot) $msg = array_merge($msg, $variationSnapshot);
            }
            if ($file) $this->addAttachment($msg, $file);
            $saved = Message::create($msg); $saved->load('product');
            $messages[] = $this->formatMessage($saved);
        }

        return response()->json(['ok' => true, 'message' => $messages[0], 'messages' => $messages]);
    }

    private function addAttachment(array &$msg, $file): void
    {
        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        $msg['attachment_path'] = $file->store('message_attachments', 'supabase_messages');
        $msg['attachment_name'] = $file->getClientOriginalName();
        $msg['attachment_mime'] = $mime;
        $msg['attachment_size'] = $file->getSize();
        $msg['attachment_type'] = str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            ? 'image'
            : (str_starts_with($mime, 'video/') || in_array($extension, ['mp4', 'mov', 'avi'], true) ? 'video' : 'document');
    }

    private function formatMessage(\App\Models\Message $m): array
    {
        return [
            'id'              => $m->id,
            'sender_id'       => $m->sender_id,
            'body'            => $m->body,
            'product_id'      => $m->product_id,
            'product_name'    => $m->variation_label ?: $m->product?->name,
            'product_price'   => $m->variation_price ?? $m->product?->price,
            'product_img'     => $m->variation_image
                ? (rtrim(config('filesystems.disks.supabase.url'), '/') . '/' . ltrim($m->variation_image, '/'))
                : ($m->product?->image ? (rtrim(config('filesystems.disks.supabase.url'), '/') . '/' . ltrim($m->product->image, '/')) : null),
            'product_url'     => $m->product_id ? route('buyer.product', $m->product_id) : null,
            'attachment_path' => $m->attachment_path ? route('message.media', ['path' => $m->attachment_path]) : null,
            'attachment_name' => $m->attachment_name,
            'attachment_type' => $m->attachment_type,
            'attachment_mime' => $m->attachment_mime,
            'attachment_size' => $m->attachment_size,
            'read'            => $m->read,
            'created_at'      => $m->created_at->format('g:i A'),
        ];
    }
    /** Mark one notification read and send the buyer to whatever it's about. */
    public function openNotification(string $id)
    {
        $notification = DB::table('notifications')->where('id', $id)->where('user_id', auth()->id())->first();
        abort_unless($notification, 404);

        DB::table('notifications')->where('id', $id)->update(['is_read' => true]);

        return match ($notification->notification_type) {
            'order_status'  => redirect()->route('buyer.orders'),
            'review_reply'  => redirect()->route('buyer.orders'),
            default         => redirect()->route('buyer.dashboard'),
        };
    }

    public function account()
    {
        $paymentAccounts = BuyerPaymentAccount::where('buyer_id', auth()->id())->latest('created_at')->get();
        return view('buyer.account', compact('paymentAccounts'));
    }

    /** Send a real verification code to the buyer's own registered email before saving a GCash/bank account. */
    public function sendPaymentAccountCode(Request $request)
    {
        $data = $request->validate([
            'type'           => 'required|in:gcash,paymaya,bank',
            'account_name'   => 'required|string|max:150',
            'account_number' => 'required|string|max:50',
            'bank_name'      => 'required_if:type,bank|nullable|string|max:150',
        ]);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = 'payment_otp_' . auth()->id();
        Cache::put($key, ['otp' => $otp, 'data' => $data], now()->addMinutes(10));

        $typeLabel = ['gcash' => 'GCash', 'paymaya' => 'PayMaya', 'bank' => 'bank'][$data['type']];
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Your PocketFinds verification code is: {$otp}\n\nThis code confirms you want to add this {$typeLabel} account to your PocketFinds profile. It expires in 10 minutes.",
                fn ($m) => $m->to(auth()->user()->email)->subject('PocketFinds — Payment Account Verification')
            );
        } catch (\Exception $e) {
            Cache::forget($key);
            return response()->json(['success' => false, 'message' => 'Could not send the code. Please try again.']);
        }

        return response()->json(['success' => true, 'message' => 'Code sent to ' . auth()->user()->email]);
    }

    public function verifyPaymentAccountCode(Request $request)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $key = 'payment_otp_' . auth()->id();
        $stored = Cache::get($key);

        if (!$stored || $stored['otp'] !== $request->otp) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired code.']);
        }

        $account = BuyerPaymentAccount::create(array_merge($stored['data'], [
            'buyer_id'    => auth()->id(),
            'verified'    => true,
            'verified_at' => now(),
        ]));
        Cache::forget($key);

        return response()->json(['success' => true, 'account' => [
            'id' => $account->id,
            'type' => $account->type,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'bank_name' => $account->bank_name,
        ]]);
    }

    public function destroyPaymentAccount(BuyerPaymentAccount $account)
    {
        abort_unless($account->buyer_id === auth()->id(), 403);
        $account->delete();
        return back()->with('success', 'Payment account removed.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
