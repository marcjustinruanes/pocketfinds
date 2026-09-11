<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FetchesProducts;
use App\Http\Controllers\Concerns\HandlesAccountUpdateRequests;
use App\Models\Product;
use App\Models\User;
use App\Models\Message;
use App\Models\Complaint;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\Review;
use App\Models\Voucher;
use App\Models\BuyerPaymentAccount;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\ShopFollow;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BuyerController extends Controller
{
    use FetchesProducts;
    use HandlesAccountUpdateRequests;

    public function dashboard()
    {
        $buyerId    = auth()->id();
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);
        $featured   = $this->dbProducts(8);

        $orderCounts = Order::where('app_buyer_id', $buyerId)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $statusCounts = [
            'to_ship'          => $orderCounts->get('to_ship', 0),
            'in_transit'       => $orderCounts->get('in_transit', 0),
            'out_for_delivery' => $orderCounts->get('out_for_delivery', 0),
            'completed'        => $orderCounts->get('completed', 0),
        ];
        $activeOrders    = $statusCounts['to_ship'] + $statusCounts['in_transit'] + $statusCounts['out_for_delivery'];
        $completedOrders = $statusCounts['completed'];
        $cartCount       = collect(session('cart', []))->count();
        $unreadMessages  = Message::where('receiver_id', $buyerId)->where('read', false)->count();

        $recentOrders = Order::with('seller')
            ->where('app_buyer_id', $buyerId)
            ->latest()
            ->limit(3)
            ->get();

        return view('buyer.dashboard', compact(
            'categories', 'featured', 'statusCounts', 'activeOrders', 'completedOrders',
            'cartCount', 'unreadMessages', 'recentOrders'
        ));
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
        $p = Product::with(['seller', 'category'])->where('id', $id)->where('status', 'active')->sellerApproved()->firstOrFail();
        $product      = $this->mapProduct($p);
        $sellerOnline = Cache::has('seller-online-' . $p->seller_id);
        $shopProducts = Product::with(['seller','category'])->where('seller_id', $p->seller_id)->where('status','active')->sellerApproved()->where('id','!=',$id)->limit(6)->get()->map(fn($r) => $this->mapProduct($r))->all();
        $titleTerms = collect(preg_split('/[^\\pL\\pN]+/u', $p->name))
            ->filter(fn ($term) => mb_strlen($term) > 2)
            ->unique()
            ->take(5);
        // "Similar products" should point buyers toward other shops, not
        // just other listings from the same seller (that's what "More from
        // this shop" is for) — so a different seller is required here.
        $related = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->sellerApproved()
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
        $items  = Product::with(['seller','category'])->where('seller_id', $seller->id)->where('status','active')->sellerApproved()->get()->map(fn($p) => $this->mapProduct($p))->all();
        $shop   = [
            'name'         => $seller->business_name ?? ($seller->given_names . ' ' . $seller->last_name),
            'initial'      => strtoupper(substr($seller->given_names, 0, 1)),
            'rating'       => 0,
            'products'     => count($items),
            'sales'        => '0',
            'joined'       => $seller->created_at->format('M Y'),
            'desc'         => '',
            'followers'    => $seller->followers()->count(),
            'is_following' => ShopFollow::where('buyer_id', auth()->id())->where('seller_id', $seller->id)->exists(),
        ];
        return view('buyer.shop', compact('shop', 'items', 'slug'));
    }

    /** Toggle the current buyer's follow of a seller's shop. */
    public function followShop($slug)
    {
        $seller = User::where('username', $slug)->where('account_type', 'seller')->firstOrFail();

        $follow = ShopFollow::where('buyer_id', auth()->id())->where('seller_id', $seller->id)->first();
        if ($follow) {
            $follow->delete();
            $following = false;
        } else {
            ShopFollow::create(['buyer_id' => auth()->id(), 'seller_id' => $seller->id]);
            $following = true;
        }

        return response()->json([
            'following' => $following,
            'followers' => $seller->followers()->count(),
        ]);
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
                // Base stock for a plain (no-variation) product — the edit
                // modal's quantity cap when there's no variation to check instead.
                'product_stock' => $products->get($item['product_id'])?->total_stock ?? 0,
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

        // Saved delivery addresses — which one the buyer picks decides where every
        // order created by this checkout actually ships to (see checkout()). A buyer
        // who has never saved one gets their registration address seeded as the default.
        BuyerAddress::ensureDefaultFor(auth()->user());
        $addresses = BuyerAddress::where('buyer_id', auth()->id())->orderByDesc('is_default')->latest()->get();

        return view('buyer.cart', compact('items', 'groups', 'paymentMethods', 'shippingFees', 'usableVouchers', 'otherVouchers', 'verifiedLookup', 'addresses'));
    }

    public function storeAddress(Request $request)
    {
        $data = $request->validate([
            'label'          => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'contact_no'     => ['nullable', 'regex:/^09\d{9}$/'],
            'province'       => ['required', 'string', 'max:150'],
            'municipality'   => ['required', 'string', 'max:150'],
            'barangay'       => ['required', 'string', 'max:150'],
            'house_no'       => ['nullable', 'string', 'max:100'],
            'street'         => ['nullable', 'string', 'max:150'],
        ], [
            'contact_no.regex' => 'Contact number must start with 09 and be exactly 11 digits.',
        ]);

        // The very first address a buyer ever saves becomes their default automatically.
        $isFirst = !BuyerAddress::where('buyer_id', auth()->id())->exists();
        $address = BuyerAddress::create(array_merge($data, [
            'buyer_id'   => auth()->id(),
            'is_default' => $isFirst,
        ]));

        return response()->json([
            'success' => true,
            'address' => [
                'id'            => $address->id,
                'label'         => $address->label,
                'recipient_name' => $address->recipient_name,
                'contact_no'    => $address->contact_no,
                'full_address'  => $address->full_address,
                'is_default'    => $address->is_default,
            ],
        ]);
    }

    public function destroyAddress(BuyerAddress $address)
    {
        abort_unless($address->buyer_id === auth()->id(), 403);

        // The default address mirrors the buyer's own approved account address —
        // it isn't just another saved address, so it can't be removed directly.
        // It only ever changes when a profile/address update request they submit
        // gets approved by an admin (see AccountUpdateRequest::apply()).
        if ($address->is_default) {
            return back()->with('error', 'Your default address is set from your account address. To change it, submit an account update request and wait for admin approval.');
        }

        $address->delete();

        return back()->with('success', 'Address removed.');
    }

    public function cartAdd(Request $request)
    {
        $data = $request->validate([
            'product_id'      => ['required'],
            'variation_group' => ['nullable', 'string', 'max:150'],
            'variation_value' => ['nullable', 'string', 'max:150'],
            'qty'             => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $p    = Product::with(['seller','category'])->where('id', $data['product_id'])->where('status','active')->sellerApproved()->firstOrFail();
        $prod = $this->mapProduct($p);

        // A selected option carries its own authoritative price/stock — never
        // trust a client-submitted price, always resolve it here. A shop-wide
        // discount (mapProduct's resolved price) applies unless the chosen
        // variation option has its own explicit price.
        $price = (float) $prod['price'];
        $img = $prod['img'];
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
            // The chosen option's own photo (e.g. a specific color) beats the
            // product's generic cover image whenever the seller set one.
            if (!empty($option['image'])) $img = $this->supabaseUrl($option['image']);
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
                'img'             => $img,
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

        $product = Product::find($item->product_id);
        $available = $product?->availableStock($item->variation_group ?: null, $item->variation_value ?: null) ?? 0;
        if ($data['qty'] > $available) {
            return back()->with('error', $available > 0
                ? "Only {$available} item(s) are currently available."
                : 'This item is currently out of stock.');
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
        // Falls back to the product's own default photo unless the newly
        // selected option below carries its own (e.g. switching color).
        $img = $this->mapProduct($product)['img'];

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
            $optionStock = (int) ($option['stock'] ?? 0);
            if ($optionStock <= 0) {
                return back()->with('error', 'Selected option is out of stock.');
            }
            if ($data['qty'] > $optionStock) {
                return back()->with('error', "Only {$optionStock} item(s) available for this option.");
            }
            if (isset($option['price'])) $price = (float) $option['price'];
            if (!empty($option['image'])) $img = $this->supabaseUrl($option['image']);
        } else {
            $available = $product->total_stock;
            if ($available <= 0) {
                return back()->with('error', 'This product is out of stock.');
            }
            if ($data['qty'] > $available) {
                return back()->with('error', "Only {$available} item(s) are currently available.");
            }
        }

        $merge = CartItem::where('buyer_id', auth()->id())->where('product_id', $item->product_id)
            ->where('variation_value', $newValue)->where('variation_group', $groupName)
            ->where('id', '!=', $item->id)->first();

        if ($merge) {
            $merge->update(['qty' => min(99, $merge->qty + $data['qty'])]);
            $item->delete();
        } else {
            $item->update(['qty' => $data['qty'], 'variation_value' => $newValue, 'variation_group' => $groupName, 'price' => $price, 'img' => $img]);
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
        $allowedTabs = ['all', 'to_ship', 'in_transit', 'out_for_delivery', 'delivered', 'completed', 'cancelled'];
        $tab = in_array($request->query('tab'), $allowedTabs, true) ? $request->query('tab') : 'all';
        $baseQuery = Order::where('buyer_id', auth()->id());
        $orderCounts = (clone $baseQuery)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $orders = $baseQuery->with(['seller', 'paymentMethod', 'shipment.courier', 'shipment.pickupRider', 'review'])
            ->when($tab === 'to_ship', fn ($query) => $query->whereIn('status', Order::BUYER_TO_SHIP_STATUSES))
            ->when($tab === 'in_transit', fn ($query) => $query->whereIn('status', Order::BUYER_IN_TRANSIT_STATUSES))
            ->when(!in_array($tab, ['all', 'to_ship', 'in_transit'], true), fn ($query) => $query->where('status', $tab))
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
        if (!in_array($order->status, ['placed', 'confirmed', 'preparing'], true)) {
            return back()->with('error', 'This order can no longer be cancelled.');
        }

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'in:Changed my mind,Found a better price,Ordered by mistake,Payment issue,Other'],
            'cancellation_note' => ['nullable', 'string', 'max:500'],
        ]);

        // Stock only actually left inventory once the seller confirmed this order
        // (see SellerController::confirmOrder()) — a still-"placed" order never
        // touched it, so there's nothing to give back in that case.
        $hadDeductedStock = $order->status !== 'placed';

        DB::transaction(function () use ($order, $data, $hadDeductedStock) {
            $order->update([
                'status' => 'cancelled',
                'cancellation_reason' => $data['cancellation_reason'],
                'cancellation_note' => $data['cancellation_note'] ?? null,
            ]);

            if ($hadDeductedStock) {
                foreach ($order->items ?? [] as $item) {
                    $product = Product::where('id', $item['product_id'])->lockForUpdate()->first();
                    $product?->deductStock(-(int) $item['qty'], $item['variation_group'] ?: null, $item['variation_value'] ?: null);
                }
            }
        });

        return redirect()->route('buyer.orders', ['tab' => 'cancelled'])->with('success', 'Order cancelled successfully.');
    }

    /** Buyer-initiated "Order Received" — the courier app doesn't always get updated promptly, so the buyer can confirm delivery themselves. */
    public function confirmReceipt(Order $order)
    {
        abort_unless($order->buyer_id === auth()->id(), 403);
        // Only confirmable once the courier has actually marked it delivered — not merely
        // "out for delivery" (still en route). See RiderController/LogisticsController's
        // ORDER_STATUS_MAP for why 'delivered' stops short of 'completed' on its own.
        if ($order->status !== 'delivered') {
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
            $product = Product::where('id', $item['product_id'] ?? null)->where('status', 'active')->sellerApproved()->first();
            if (!$product) { $skipped++; continue; }

            $prod = $this->mapProduct($product);
            $price = (float) $prod['price'];
            // Orders placed before the cart's variation_value/variation_group
            // rename still carry the old "color" key in their items JSON —
            // fall back to it so re-buying an old order still works.
            $value = $item['variation_value'] ?? $item['color'] ?? '';
            $variationGroup = '';
            $available = (int) $product->stock;
            $img = $prod['img'];

            if (!empty($product->variations) && $value !== '') {
                $group = collect($product->variations)->first(fn ($v) => collect($v['options'] ?? [])->contains(fn ($o) => ($o['value'] ?? null) === $value));
                if ($group) {
                    $option = collect($group['options'])->first(fn ($o) => ($o['value'] ?? null) === $value);
                    $available = (int) ($option['stock'] ?? 0);
                    if (isset($option['price'])) $price = (float) $option['price'];
                    if (!empty($option['image'])) $img = $this->supabaseUrl($option['image']);
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
                    'img' => $img, 'seller' => $prod['seller'], 'seller_slug' => $prod['seller_slug'],
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
            'delivery_address_id' => ['nullable', 'integer'],
        ]);
        $cart = CartItem::where('buyer_id', auth()->id())->get()->keyBy('id')->map->toArray()->all();
        $selected = collect($data['items'])->filter(fn ($key) => array_key_exists($key, $cart));
        if ($selected->isEmpty()) {
            return back()->with('error', 'Select at least one cart item.');
        }
        $items = $selected->map(fn ($key) => array_merge($cart[$key], ['key' => $key]))->values();
        $buyer = auth()->user();

        // A saved address the buyer picked wins; falls back to their profile
        // address so checkout still works for a buyer who's never saved one.
        $deliveryAddress = !empty($data['delivery_address_id'])
            ? BuyerAddress::where('id', $data['delivery_address_id'])->where('buyer_id', auth()->id())->first()
            : null;
        $address = $deliveryAddress
            ? $deliveryAddress->toShippingArray()
            : collect(['house_no', 'street', 'barangay', 'municipality', 'province'])
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

        try {
            DB::transaction(function () use ($items, $data, $address, $paymentMethod, $voucherCode, $buyer, &$created) {
            foreach ($items->groupBy('seller_slug') as $sellerSlug => $sellerItems) {
                $seller = User::where('username', $sellerSlug)->where('account_type', 'seller')->first();
                if (!$seller) continue;

                // A courtesy check only — placing an order never touches stock, so
                // this doesn't lock anything. Stock only actually leaves inventory
                // once the seller confirms the order (see SellerController::confirmOrder()),
                // which means several buyers can "place" against the same limited
                // stock and it's the seller who decides who gets confirmed.
                foreach ($sellerItems as $item) {
                    $product = Product::find($item['product_id']);
                    if (!$product) {
                        throw new \RuntimeException("\"{$item['name']}\" is no longer available.");
                    }
                    $available = $product->availableStock($item['variation_group'] ?: null, $item['variation_value'] ?: null);
                    if ($available < (int) $item['qty']) {
                        throw new \RuntimeException($available > 0
                            ? "Only {$available} of \"{$item['name']}\" left in stock."
                            : "\"{$item['name']}\" just sold out.");
                    }
                }

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
                    'buyer_id' => auth()->id(), 'seller_id' => $seller->id, 'status' => 'placed',
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
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('buyer.orders', ['tab' => 'placed'])->with('success', 'Order submitted successfully.');
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
            $messages = Message::with(['product', 'order'])
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

        // This buyer's own orders with this shop, for the Orders picker in the composer.
        $sellerOrders = $seller
            ? Order::where('seller_id', $seller->id)->where('buyer_id', auth()->id())->latest()->get()
            : collect();

        // "Message Seller" from My Orders (?order=<id>) arrives here ready to attach —
        // the buyer doesn't have to open the picker and find it themselves.
        $autoAttachOrder = ($seller && $request->filled('order'))
            ? $sellerOrders->firstWhere('id', $request->query('order'))
            : null;

        // All conversations this buyer has
        $conversations = Message::with(['sender','receiver','product'])
            ->where('sender_id', auth()->id())
            ->orWhere('receiver_id', auth()->id())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn($m) => $m->sender_id === auth()->id() ? $m->receiver_id : $m->sender_id)
            ->map(fn($msgs) => $msgs->first());

        $data = compact('product', 'productVariation', 'seller', 'sellerOnline', 'messages', 'conversations', 'sellerProducts', 'sellerOrders', 'autoAttachOrder');

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

        $messages = Message::with(['product', 'order'])
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
            'order_id'         => ['nullable', 'string'],
            'variation_group'  => ['nullable', 'string', 'max:150'],
            'variation_value'  => ['nullable', 'string', 'max:150'],
            'attachments'   => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
            'attachment'    => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
        ]);

        $receiver = User::whereKey($data['receiver_id'])->where('account_type', 'seller')->firstOrFail();
        $files = $request->file('attachments', []);
        if ($request->hasFile('attachment')) $files[] = $request->file('attachment');
        abort_unless(filled($data['body'] ?? null) || $files || filled($data['product_id'] ?? null) || filled($data['order_id'] ?? null), 422, 'Send a message, product, order, image, or video.');

        $product = null;
        if (filled($data['product_id'] ?? null)) {
            $product = Product::whereKey($data['product_id'])->where('seller_id', $receiver->id)->where('status', 'active')->firstOrFail();
        }
        $order = null;
        if (filled($data['order_id'] ?? null)) {
            // Only one of the buyer's own orders with this exact seller can be attached.
            $order = Order::whereKey($data['order_id'])->where('buyer_id', auth()->id())->where('seller_id', $receiver->id)->firstOrFail();
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
            if ($index === 0 && $order) $msg['order_id'] = $order->id;
            if ($file) $this->addAttachment($msg, $file);
            $saved = Message::create($msg); $saved->load(['product', 'order']);
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
            'order_id'        => $m->order_id,
            'order_number'    => $m->order?->order_number,
            'order_status'    => $m->order ? str_replace('_', ' ', ucfirst($m->order->status)) : null,
            'order_total'     => $m->order?->total,
            'order_url'       => $m->order_id ? route('buyer.orders') : null,
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
            'announcement'  => redirect()->route('buyer.announcements.show', $notification->reference_id),
            default         => redirect()->route('buyer.dashboard'),
        };
    }

    /** Real, read-only list of announcements aimed at buyers (admin "all"/"buyer",
     *  or any seller's — sellers can only ever target buyers). */
    public function announcements()
    {
        $announcements = Announcement::with('author')
            ->where('is_active', true)
            ->whereIn('audience', ['all', 'buyer'])
            ->latest('created_at')
            ->get();

        $unreadIds = DB::table('notifications')
            ->where('user_id', auth()->id())
            ->where('notification_type', 'announcement')
            ->where('is_read', false)
            ->pluck('reference_id');

        // Visiting the list is enough to consider them seen — the same
        // convention openNotification() already uses when a single one is
        // opened from the bell, just applied to the whole visible page.
        DB::table('notifications')
            ->where('user_id', auth()->id())
            ->where('notification_type', 'announcement')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return view('buyer.announcements', compact('announcements', 'unreadIds'));
    }

    public function showAnnouncement(string $id)
    {
        $announcement = Announcement::with('author')
            ->where('is_active', true)
            ->whereIn('audience', ['all', 'buyer'])
            ->findOrFail($id);

        DB::table('notifications')
            ->where('user_id', auth()->id())
            ->where('notification_type', 'announcement')
            ->where('reference_id', $id)
            ->update(['is_read' => true]);

        return view('buyer.announcement-show', compact('announcement'));
    }

    public function account()
    {
        $paymentAccounts = BuyerPaymentAccount::where('buyer_id', auth()->id())->latest('created_at')->get();
        BuyerAddress::ensureDefaultFor(auth()->user());
        $addresses = BuyerAddress::where('buyer_id', auth()->id())->orderByDesc('is_default')->latest()->get();
        return view('buyer.account', array_merge(compact('paymentAccounts', 'addresses'), [
            'pendingRequest' => $this->pendingAccountUpdateRequest(),
            'lastRequest'    => $this->lastAccountUpdateRequest(),
        ]));
    }

    /** Submits a profile-change request — nothing changes on the account until admin approves it. */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'given_names' => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'contact_no'  => ['nullable', 'regex:/^09[0-9]{9}$/'],
        ], [
            'contact_no.regex' => 'Contact number must start with 09 and be exactly 11 digits.',
        ]);

        return $this->submitAccountUpdateRequest(
            $request,
            ['given_names', 'last_name', 'contact_no'],
            [],
            'profile_success'
        );
    }

    /** Submits an address-change request — nothing changes on the account until admin approves it. */
    public function updateAddress(Request $request)
    {
        $request->validate([
            'province'     => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay'     => 'required|string|max:255',
            'house_no'     => 'nullable|string|max:255',
            'street'       => 'nullable|string|max:255',
        ]);

        return $this->submitAccountUpdateRequest(
            $request,
            ['province', 'municipality', 'barangay', 'house_no', 'street'],
            [],
            'address_success'
        );
    }

    /** Password changes are immediate/self-service — never gated behind admin approval. */
    public function passwordUpdate(Request $request)
    {
        $request->validate(['current_password' => 'required', 'password' => 'required|min:8|confirmed']);
        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        auth()->user()->update(['password' => Hash::make($request->password)]);
        return back()->with('password_success', 'Password updated.');
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
