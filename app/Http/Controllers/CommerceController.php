<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FetchesProducts;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\BuyerCommerce;
use App\Services\GcashWorkflow;
use App\Services\MediaLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommerceController extends Controller
{
    use FetchesProducts;

    private function flow(): BuyerCommerce
    {
        return app(BuyerCommerce::class);
    }

    private function buyer(Request $r): User
    {
        $u = $r->user()->fresh();
        abort_unless($u->account_type === 'buyer' && $u->status === 'approved', 403);

        return $u;
    }

    private function manager(Request $r): User
    {
        $u = $r->user()->fresh();
        abort_unless(($u->account_type === 'seller' || $u->is_admin) && $u->status === 'approved', 403);

        return $u;
    }

    private function emailAccount(Request $r): User
    {
        $user = $r->user()->fresh();
        abort_unless($user->status === 'approved', 403);

        return $user;
    }

    public function notificationsReadAll(Request $r)
    {
        $user = $this->emailAccount($r);
        DB::table('notifications')->where('user_id', $user->id)->where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function notificationOpen(Request $r, string $id)
    {
        $user = $this->emailAccount($r);
        $notification = DB::table('notifications')->where('id', $id)->where('user_id', $user->id)->first();
        abort_unless($notification, 404);
        if($notification->notification_type==='cash_handover'){
            $actor=app(\App\Services\LogisticsCashDesk::class)->actor($user);
            $handover=app(\App\Services\LogisticsCashDesk::class)->scope(DB::table('logistics_cash_handovers'),$actor)->where('id',$notification->reference_id)->firstOrFail();
            DB::table('notifications')->where('id',$id)->where('user_id',$actor->id)->update(['is_read'=>true]);
            if($actor->isLogisticsAdmin() && $handover->kind==='treasury')return redirect(route('logistics.finance.payouts').'#treasury');
            return redirect()->route($actor->account_type==='rider'?'rider.remittances':'logistics.cash-desk',['handover'=>$handover->id]);
        }
        DB::table('notifications')->where('id', $id)->where('user_id', $user->id)->update(['is_read' => true]);
        if ($user->is_admin) {
            return redirect(route('admin.complaints').'#returns-refunds');
        }

        return match ($user->account_type) {
            'seller' => redirect()->route('seller.orders', ['status' => 'refunds']),'rider' => redirect()->route('rider.deliveries'),'logistics' => redirect()->route('logistics.monitor'),default => redirect()->route('buyer.orders')
        };
    }

    public function index(Request $r)
    {
        $this->buyer($r);

        return match ($r->query('tab', 'wishlist')) {
            'sales' => redirect(route('buyer.dashboard').'#deals'),
            'returns' => redirect()->route('buyer.orders', ['tab' => 'refunds']),
            'reviews' => redirect()->route('buyer.orders', ['tab' => 'completed']),
            'email' => redirect(route('buyer.account').'#change-email'),
            'vouchers' => redirect(route('buyer.account').'#my-vouchers'),
            default => redirect()->route('buyer.wishlist'),
        };
    }

    public function wishlistPage(Request $r)
    {
        $u = $this->buyer($r);
        $this->flow()->sweep();
        $wishlist = DB::table('commerce_wishlist as w')->leftJoin('products as p', 'p.id', '=', 'w.product_id')->where('w.buyer_id', $u->id)->select('w.*', 'p.name', 'p.status', 'p.stock')->get();
        $wishlistProducts = Product::with(['seller', 'category'])->whereIn('id', $wishlist->pluck('product_id'))->where('status', 'active')->sellerApproved()->get()->mapWithKeys(fn ($product) => [$product->id => $this->mapProduct($product)]);

        return view('buyer.wishlist', compact('wishlist', 'wishlistProducts'));
    }

    public function wishlist(Request $r, string $product)
    {
        $u = $this->buyer($r);
        $r->validate(['saved' => 'required|boolean']);
        if ($r->boolean('saved')) {
            Product::whereKey($product)->where('status', 'active')->sellerApproved()->firstOrFail();
            DB::table('commerce_wishlist')->insertOrIgnore(['buyer_id' => $u->id, 'product_id' => $product, 'created_at' => now()]);
        } else {
            DB::table('commerce_wishlist')->where('buyer_id', $u->id)->where('product_id', $product)->delete();
        }

        return back()->with('success', $r->boolean('saved') ? 'Saved to wishlist.' : 'Removed from wishlist.');
    }

    public function completeAddress(Request $r, BuyerAddress $address)
    {
        $buyer = $this->buyer($r);
        abort_unless($address->buyer_id === $buyer->id, 403);
        $data = $r->validate(['region' => 'required|string|max:255', 'postal_code' => ['required', 'regex:/^[0-9]{4}$/D']]);
        DB::transaction(function () use ($address, $buyer, $data) {
            $saved = BuyerAddress::whereKey($address->id)->where('buyer_id', $buyer->id)->lockForUpdate()->firstOrFail();
            if (filled($saved->region) && $saved->region !== $data['region']) {
                $this->flow()->fail('The saved region cannot be replaced here. Submit an account address update.', 'region');
            }
            if (preg_match('/^[0-9]{4}$/D', (string) $saved->postal_code) && $saved->postal_code !== $data['postal_code']) {
                $this->flow()->fail('The saved postal code cannot be replaced here. Submit an account address update.', 'postal_code');
            }
            $saved->update($data);
        });

        return response()->json(['success' => true, 'message' => 'Missing delivery details saved.']);
    }

    public function claim(Request $r, int $voucher)
    {
        $u = $this->buyer($r);
        $v = DB::table('commerce_vouchers')->where('id', $voucher)->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now())->first();
        abort_unless($v, 422, 'This voucher cannot be claimed yet or has expired.');
        DB::table('commerce_claims')->insertOrIgnore(['buyer_id' => $u->id, 'voucher_id' => $voucher]);

        return back()->with('success', 'Voucher saved to your wallet.');
    }

    public function reminder(Request $r, int $sale)
    {
        $u = $this->buyer($r);
        abort_unless(DB::table('commerce_flash_sales')->where('id', $sale)->where('is_active', true)->where('ends_at', '>', now())->exists(), 404);
        DB::table('commerce_reminders')->insertOrIgnore(['buyer_id' => $u->id, 'flash_sale_id' => $sale]);
        $this->flow()->sweep();

        return redirect(route('buyer.dashboard').'#deals')->with('success', 'Flash-sale reminder saved.');
    }

    public function markAll(Request $r)
    {
        $u = $this->buyer($r);
        DB::table('notifications')->where('user_id', $u->id)->where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function emailCode(Request $r)
    {
        $u = $this->emailAccount($r);
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $d = $r->validate(['email' => ['required', 'email', 'regex:/@gmail\.com$/i', 'unique:users,email'], 'current_password' => 'required|string']);
        if (! Hash::check($d['current_password'], $u->password)) {
            $this->flow()->fail('Current password is incorrect.', 'current_password');
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            Mail::raw("Your PocketFinds email-change verification code is {$code}. It expires in 5 minutes.", fn ($m) => $m->to($d['email'])->subject('Verify your new PocketFinds email'));
        } catch (\Throwable) {
            $this->flow()->fail('The code could not be sent. Check the email and try again.', 'email');
        }
        Cache::put('buyer-email-change:'.$u->id, ['email' => $d['email'], 'hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(5));

        return back()->with('success', 'Code sent to your new email. Your current email remains unchanged.');
    }

    public function emailVerify(Request $r)
    {
        $u = $this->emailAccount($r);
        $d = $r->validate(['otp' => 'required|digits:6']);
        $key = 'buyer-email-change:'.$u->id;
        Cache::lock($key.':lock', 10)->block(5, function () use ($u, $d, $key) {
            $pending = Cache::get($key);
            if (! $pending) {
                $this->flow()->fail('Code expired. Request a new code.', 'otp');
            }
            if (! Hash::check($d['otp'], $pending['hash'])) {
                Cache::forget($key);
                $this->flow()->fail('Incorrect code. Request a new code.', 'otp');
            }
            DB::transaction(function () use ($u, $pending) {
                $account = User::whereKey($u->id)->lockForUpdate()->firstOrFail();
                if (User::where('email', $pending['email'])->where('id', '!=', $u->id)->exists()) {
                    $this->flow()->fail('Email is already in use.', 'email');
                }$account->forceFill(['email' => $pending['email'], 'email_verified_at' => now()])->save();
            });
            Cache::forget($key);
        });

        return back()->with('success', 'Email verified and updated.');
    }

    public function checkoutPage(Request $r)
    {
        $this->buyer($r);

        return redirect()->route('buyer.cart');
    }

    private function checkoutData(Request $r): array
    {
        return $r->validate(['items' => 'required|array|min:1|max:100', 'items.*' => 'required|string|distinct', 'delivery_address_id' => 'required|integer', 'payment_method' => 'required|integer', 'vouchers' => 'nullable|array|max:30', 'vouchers.*' => 'nullable|string|max:30', 'buyer_note' => 'nullable|string|max:200', 'checkout_id' => 'required|uuid', 'quote_hash' => 'nullable|string|size:64']);
    }

    public function quote(Request $r)
    {
        $u = $this->buyer($r);
        $preview = $r->boolean('preview');
        $d = $preview ? $r->validate(['items' => 'required|array|min:1|max:100', 'items.*' => 'required|string|distinct', 'delivery_address_id' => 'nullable|integer', 'vouchers' => 'nullable|array|max:30', 'vouchers.*' => 'nullable|string|max:30']) : $this->checkoutData($r);
        $quote = DB::transaction(fn () => $this->flow()->quote($u, $d['items'], $d['delivery_address_id'] ?? null, $d['vouchers'] ?? [], $preview));

        return response()->json(['quote' => $quote, 'hash' => $preview ? null : hash('sha256', json_encode($quote))]);
    }

    public function checkout(Request $r)
    {
        $u = $this->buyer($r);
        $d = $this->checkoutData($r);
        $hash = hash('sha256', json_encode([$d['items'], $d['delivery_address_id'], $d['payment_method'], $d['vouchers'] ?? [], $d['buyer_note'] ?? '']));
        $result = DB::transaction(function () use ($u, $d, $hash) {
            User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('commerce_checkouts')->where('id', $d['checkout_id'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->buyer_id === $u->id, 403);
                if ($existing->request_hash !== $hash) {
                    $this->flow()->fail('This checkout was already submitted with different details. Refresh to start another checkout.');
                }

                return json_decode($existing->result, true);
            }
            $quote = $this->flow()->quote($u, $d['items'], $d['delivery_address_id'], $d['vouchers'] ?? []);
            if (! isset($d['quote_hash']) || ! hash_equals(hash('sha256', json_encode($quote)), $d['quote_hash'])) {
                $this->flow()->fail('Review the current prices, shipping and discounts before placing your order.');
            }
            $method = PaymentMethod::findOrFail($d['payment_method']);
            $kind = $method->buyerCheckoutKind();
            if (! $kind) {
                $this->flow()->fail('Choose an active COD or GCash method.');
            }
            DB::table('commerce_checkouts')->insert(['id' => $d['checkout_id'], 'buyer_id' => $u->id, 'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            if ($kind === 'gcash') {
                $purchases = app(GcashWorkflow::class)->create($u, $d['items'], $method->id, $d['checkout_id'], null, $d['delivery_address_id'], $d['buyer_note'] ?? null, $quote);
                $result = ['kind' => 'gcash', 'ids' => array_map(fn ($p) => $p->id, $purchases)];
            } else {
                $ids = [];
                foreach ($quote['orders'] as $sellerId => $row) {
                    $order = Order::create(['order_number' => 'PF-'.strtoupper(Str::random(12)), 'buyer_id' => $u->id, 'seller_id' => $sellerId, 'status' => 'placed', 'items' => $row['items'], 'subtotal' => $row['subtotal'], 'shipping_amount' => $row['shipping_amount'], 'discount_amount' => $row['discount_amount'], 'total' => $row['total'], 'shipping_address' => $quote['address'], 'buyer_note' => $d['buyer_note'] ?? null, 'payment_method_id' => $method->id, 'payment_method' => $method->name, 'commerce_snapshot' => $row]);
                    foreach ($row['items'] as $item) {
                        Product::findOrFail($item['product_id'])->deductStock($item['qty'], $item['variation_group'], $item['variation_value']);
                    }
                    $this->flow()->allocate($order->id, $u, $row, false, $d['checkout_id']);
                    $order->logStatus('placed', $u->id);
                    Order::notifyUser($sellerId, 'New order', 'New order '.$order->order_number, 'order_status', $order->id);
                    $ids[] = $order->id;
                }$result = ['kind' => 'cod', 'ids' => $ids];
                CartItem::where('buyer_id', $u->id)->whereIn('id', $d['items'])->delete();
            }
            DB::table('commerce_checkouts')->where('id', $d['checkout_id'])->update(['result' => json_encode($result), 'updated_at' => now()]);

            return $result;
        }, 3);

        return redirect()->route($result['kind'] === 'gcash' ? 'gcash.index' : 'buyer.orders')->with('success', $result['kind'] === 'gcash' ? 'Stock reserved for one hour. Follow the GCash instructions to pay.' : 'Order placed.');
    }

    public function management(Request $r)
    {
        $user = $this->manager($r);

        return $user->is_admin
            ? redirect(route('admin.products').'#platform-vouchers')
            : redirect(route('seller.account').'#shipping-rates');
    }

    public function vacation(Request $r)
    {
        $u = $this->manager($r);
        abort_if($u->is_admin, 403);
        $d = $r->validate(['is_on_vacation' => 'required|boolean']);
        $u->update($d);

        return back()->with('success', 'Vacation setting saved.');
    }

    public function rate(Request $r)
    {
        $u = $this->manager($r);
        abort_if($u->is_admin, 403);
        $d = $r->validate(['region' => 'required|string|max:255', 'first_kg' => 'required|numeric|min:0|max:1000000', 'additional_kg' => 'required|numeric|min:0|max:1000000', 'is_active' => 'required|boolean']);
        DB::table('commerce_shipping_rates')->updateOrInsert(['seller_id' => $u->id, 'region' => $d['region']], $d);

        return back()->with('success', 'Shipping rate saved.');
    }

    public function voucher(Request $r)
    {
        $u = $this->manager($r);
        $d = $r->validate(['code' => ['required', 'regex:/^[A-Za-z0-9]{4,12}$/D', 'unique:commerce_vouchers,code'], 'kind' => 'required|in:store,platform,shipping', 'discount_type' => 'required|in:fixed,percent', 'amount' => 'required|numeric|min:0.01|max:1000000', 'cap' => 'nullable|numeric|min:0.01', 'minimum_spend' => 'required|numeric|min:0', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'usage_limit' => 'required|integer|min:1', 'per_buyer_limit' => 'required|integer|min:1']);
        abort_unless($u->is_admin || $d['kind'] === 'store', 403);
        if ($d['discount_type'] === 'percent' && $d['amount'] > 100) {
            $this->flow()->fail('Percentage cannot exceed 100.', 'amount');
        }
        $d['code'] = strtoupper($d['code']);
        $d['seller_id'] = $u->is_admin ? null : $u->id;
        if ($u->is_admin && $d['kind'] === 'store') {
            $this->flow()->fail('Store vouchers are created by their seller.', 'kind');
        }DB::table('commerce_vouchers')->insert($d);

        return back()->with('success', 'Voucher created.');
    }

    public function flashSale(Request $r)
    {
        $u = $this->manager($r);
        abort_if($u->is_admin, 403);
        $d = $r->validate(['product_id' => 'required|string', 'price' => 'required|numeric|min:1|max:1000000', 'quantity' => 'required|integer|min:1|max:99999', 'starts_at' => 'required|date|after_or_equal:now', 'ends_at' => 'required|date|after:starts_at']);
        DB::transaction(function () use ($u, $d) {
            $p = Product::where('seller_id', $u->id)->where('status', 'active')->lockForUpdate()->findOrFail($d['product_id']);
            if ($d['quantity'] > $p->total_stock || $d['price'] >= $p->price) {
                $this->flow()->fail('Flash quantity must fit stock and the price must be below the regular price.');
            }if (DB::table('commerce_flash_sales')->where('product_id', $p->id)->where('is_active', true)->where('starts_at', '<', $d['ends_at'])->where('ends_at', '>', $d['starts_at'])->exists()) {
                $this->flow()->fail('This product already has an overlapping flash sale.');
            }DB::table('commerce_flash_sales')->insert($d + ['seller_id' => $u->id]);
        });

        return back()->with('success', 'Flash sale scheduled.');
    }

    public function endPromotion(Request $r, string $kind, int $id)
    {
        $u = $this->manager($r);
        $table = match ($kind) {
            'voucher' => 'commerce_vouchers','flash' => 'commerce_flash_sales',default => abort(404)
        };
        DB::transaction(function () use ($u, $table, $id) {
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();
            abort_unless($row && ($u->is_admin || $row->seller_id === $u->id), 403);
            DB::table($table)->where('id', $id)->update(['is_active' => false]);
        });

        return back()->with('success', 'Promotion ended. Existing paid prices are preserved.');
    }

    private function completedAt(Order $order): Carbon
    {
        $date = DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'completed')->max('created_at');

        return Carbon::parse($date ?? $order->updated_at);
    }

    public function review(Request $r, Order $order, ?Review $review = null)
    {
        $u = $this->buyer($r);
        abort_unless($order->buyer_id === $u->id, 403);
        if ($order->status !== 'completed' || $this->completedAt($order)->lt(now()->subDays(30))) {
            $this->flow()->fail('Reviews are available for 30 days after completion.');
        }
        $d = $r->validate(['item_key' => 'required|integer|min:0', 'rating' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:500', 'photos' => 'nullable|array|max:5', 'photos.*' => 'image|mimes:jpg,jpeg,png|max:5120']);
        $item = $order->items[$d['item_key']] ?? null;
        abort_unless($item, 422);
        DB::transaction(function () use ($u, $order, $review, $d, $r, $item) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = Review::where('order_id', $order->id)->where('item_key', (string) $d['item_key'])->lockForUpdate()->first();
            if ($review) {
                abort_unless($review->buyer_id === $u->id && $existing?->id === $review->id, 403);
                if ($existing->edited_at) {
                    $this->flow()->fail('This review has already been edited once.');
                }
            } elseif ($existing) {
                $this->flow()->fail('You already reviewed this item. Use Edit.');
            }
            $photos = $existing?->photos ?? [];
            if ($r->hasFile('photos')) {
                $photos = [];
                foreach ($r->file('photos') as $file) {
                    $photos[] = app(MediaLifecycle::class)->store($file, 'review-photos', 'public');
                }
            }
            $values = ['order_id' => $order->id, 'item_key' => (string) $d['item_key'], 'buyer_id' => $u->id, 'seller_id' => $order->seller_id, 'product_id' => $item['product_id'], 'rating' => $d['rating'], 'comment' => $d['comment'] ?? null, 'photos' => $photos];
            if ($review) {
                $values['edited_at'] = now();
            }$existing ? $existing->update($values) : Review::create($values);
        });

        return back()->with('success', 'Review saved.');
    }

    public function requestReturn(Request $r, Order $order)
    {
        $u = $this->buyer($r);
        abort_unless($order->buyer_id === $u->id, 403);
        $d = $r->validate(['item_keys' => 'required|array|min:1', 'item_keys.*' => 'required|integer|min:0|distinct', 'reason' => 'required|in:Damaged item,Wrong item,Missing item,Not as described', 'description' => 'required|string|min:10|max:500', 'photos' => 'required|array|min:1|max:5', 'photos.*' => 'required|image|mimes:jpg,jpeg,png|max:5120', 'refund_destination' => 'nullable|string|max:255', 'refund_account_name' => ($order->gcash_purchase_id ? 'nullable' : 'required').'|string|min:2|max:150', 'refund_gcash_number' => [$order->gcash_purchase_id ? 'nullable' : 'required','string','regex:/^09[0-9]{9}$/D']]);
        DB::transaction(function () use ($u, $order, $d, $r) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $delivered = $order->shipment?->delivered_at ?? DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'delivered')->max('created_at') ?? $order->updated_at;
            if ($order->status !== 'delivered' || Carbon::parse($delivered)->lt(now()->subDays(7))) {
                $this->flow()->fail('Returns are available within seven days after delivery and before completion.');
            }if (DB::table('commerce_returns')->where('order_id', $order->id)->exists()) {
                $this->flow()->fail('A return request already exists for this order.');
            }
            $weights = [];
            foreach ($order->items as $key => $item) {
                $weights[$key] = (int) round($item['price'] * $item['qty'] * 100);
            }foreach ($d['item_keys'] as $key) {
                if (! array_key_exists($key, $weights)) {
                    $this->flow()->fail('Choose items from this order.');
                }
            }
            $merchDiscount = $order->commerce_snapshot ? min($order->subtotal, array_sum(array_column(array_filter($order->commerce_snapshot['vouchers'] ?? [], fn ($v) => $v['kind'] !== 'shipping'), 'amount'))) : min($order->subtotal, $order->discount_amount);
            $shares = $this->flow()->split((int) round($merchDiscount * 100), $weights);
            $cents = 0;
            foreach ($d['item_keys'] as $key) {
                $cents += $weights[$key] - ($shares[$key] ?? 0);
            }$cents = min($cents, (int) round($order->total * 100));
            $photos = [];
            foreach ($r->file('photos') as $file) {
                $photos[] = app(MediaLifecycle::class)->store($file, 'return-evidence', 'local');
            }$id = (string) Str::uuid();
            DB::table('commerce_returns')->insert(['id' => $id, 'order_id' => $order->id, 'buyer_id' => $u->id, 'seller_id' => $order->seller_id, 'item_keys' => json_encode($d['item_keys']), 'reason' => $d['reason'], 'description' => $d['description'], 'photos' => json_encode($photos), 'amount' => max(0, $cents) / 100, 'refund_destination' => $order->gcash_purchase_id ? 'Original GCash payment: '.$order->gcash_purchase_id : 'GCash: '.$d['refund_account_name'].' / '.$d['refund_gcash_number'], 'created_at' => now(), 'updated_at' => now()]);
            Order::notifyUser($order->seller_id, 'Return requested', 'Return/refund requested for '.$order->order_number, 'order_status', $order->id);
        });

        return back()->with('success', 'Return request sent. Automatic completion is paused during review.');
    }

    public function returnAction(Request $r, string $id)
    {
        $u = $r->user()->fresh();
        abort_unless($u->status === 'approved', 403);
        $d = $r->validate(['action' => 'required|in:approve,reject,escalate,received,refunded', 'reason' => 'nullable|string|min:10|max:500', 'reference' => 'nullable|string|max:255']);
        DB::transaction(function () use ($u, $d, $id) {
            $row = DB::table('commerce_returns')->where('id', $id)->first();
            abort_unless($row, 404);
            $order = Order::whereKey($row->order_id)->lockForUpdate()->firstOrFail();
            $row = DB::table('commerce_returns')->where('id', $id)->lockForUpdate()->first();
            $seller = $u->account_type === 'seller' && $row->seller_id === $u->id;
            $admin = $u->is_admin && $u->status === 'approved';
            $update = [];
            if ($d['action'] === 'escalate') {
                abort_unless($row->buyer_id === $u->id && $row->status === 'rejected' && ! $row->escalated_at && $row->decided_at >= now()->subDays(3)->toDateTimeString(), 403);
                $update = ['status' => 'escalated', 'escalated_at' => now()];
            } elseif (in_array($d['action'], ['approve', 'reject'])) {
                abort_unless(($seller && $row->status === 'pending') || ($admin && in_array($row->status, ['pending', 'escalated'])), 403);
                if ($d['action'] === 'reject' && ! filled($d['reason'] ?? null)) {
                    $this->flow()->fail('Provide a reason for rejection.', 'reason');
                }$update = ['status' => $d['action'] === 'approve' ? 'approved' : 'rejected', 'decision_reason' => $d['reason'] ?? null, 'decided_at' => now()];
            } elseif ($d['action'] === 'received') {
                abort_unless($seller && $u->status === 'approved' && $row->status === 'approved', 403);
                foreach (json_decode($row->item_keys, true) as $key) {
                    $i = $order->items[$key];
                    $p = Product::whereKey($i['product_id'])->lockForUpdate()->first();
                    if ($p) {
                        $p->deductStock(-$i['qty'], $i['variation_group'] ?? null, $i['variation_value'] ?? null);
                    }
                } $update = ['status' => 'received'];
            } else {
                abort_unless($admin && $row->status === 'received', 403);
                if (! filled($d['reference'] ?? null)) {
                    $this->flow()->fail('Record the actual repayment transaction reference.', 'reference');
                }$update = ['status' => 'refunded', 'refund_reference' => $d['reference']];
            }
            DB::table('commerce_returns')->where('id', $id)->update($update + ['updated_at' => now()]);
            Order::notifyUser($row->buyer_id, 'Return/refund update', 'Your request is now '.$update['status'].'.', 'order_status', $order->id);
            if ($u->id !== $row->seller_id) {
                Order::notifyUser($row->seller_id, 'Return/refund update', 'Return for '.$order->order_number.' is now '.$update['status'].'.', 'order_status', $order->id);
            }
            if (in_array($update['status'], ['escalated', 'received'])) {
                foreach (User::where('is_admin', true)->where('status', 'approved')->pluck('id') as $adminId) {
                    Order::notifyUser($adminId, 'Return/refund needs review', 'Return for '.$order->order_number.' is now '.$update['status'].'.', 'order_status', $order->id);
                }
            }
        });

        return back()->with('success', 'Return/refund request updated.');
    }

    public function evidence(Request $r, string $id, int $index)
    {
        $row = DB::table('commerce_returns')->where('id', $id)->first();
        abort_unless($row && ($r->user()->id === $row->buyer_id || $r->user()->id === $row->seller_id || $r->user()->is_admin), 403);
        $path = json_decode($row->photos, true)[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
