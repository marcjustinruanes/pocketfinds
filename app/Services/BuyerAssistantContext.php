<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Policy;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BuyerAssistantContext
{
    public function build(?User $buyer, bool $includeOrders, string $query = '', ?string $productId = null): array
    {
        $signedIn = $buyer && $buyer->account_type === 'buyer' && ! $buyer->is_admin;
        // Retrieve matching listings across the catalog before locally ranking a bounded snapshot.
        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)))
            ->filter(fn ($word) => mb_strlen($word) > 2 && ! in_array($word, ['the', 'for', 'with', 'and', 'under', 'pesos', 'find', 'help', 'something', 'what', 'are', 'can', 'please', 'products']))->unique()->take(20)->all();
        $aliases = ['puppy' => 'pet', 'dog' => 'pet', 'cat' => 'pet', 'aso' => 'pet', 'pusa' => 'pet', 'infant' => 'baby', 'sanggol' => 'baby', 'child' => 'kids', 'gadget' => 'electronics', 'skincare' => 'beauty'];
        foreach ($tokens as $token) {
            if (isset($aliases[$token])) {
                $tokens[] = $aliases[$token];
            }
        }
        $catalog = Product::where('status', 'active')->sellerApproved()->with(['category:id,name', 'seller:id,shipping_fee']);
        $candidates = (clone $catalog)->when($tokens, function ($query) use ($tokens) {
            $query->where(function ($matches) use ($tokens) {
                foreach ($tokens as $token) {
                    $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $token).'%';
                    $matches->orWhere('name', 'like', $pattern)->orWhere('description', 'like', $pattern)
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $pattern));
                }
            });
        })->latest()->limit(200)->get();
        if ($candidates->isEmpty()) {
            $candidates = $catalog->latest()->limit(200)->get();
        }
        $selected = $productId ? Product::where('status', 'active')->sellerApproved()->with(['category:id,name', 'seller:id,shipping_fee'])->find($productId) : null;
        if ($selected && ! $candidates->contains('id', $selected->id)) {
            $candidates->prepend($selected);
        }
        $products = $candidates->sortByDesc(function ($product) use ($tokens, $selected) {
            $name = mb_strtolower($product->name.' '.($product->category?->name ?? ''));
            $text = $name.' '.mb_strtolower(strip_tags($product->description ?? ''));

            return ($selected && $product->id === $selected->id ? 100 : 0) + ($selected && $product->category_id === $selected->category_id ? 2 : 0)
                + collect($tokens)->sum(fn ($token) => str_contains($name, $token) ? 3 : (str_contains($text, $token) ? 1 : 0));
        })->take(24)->map(function (Product $product) use ($signedIn) {
            $price = (float) $product->price;
            $discount = (float) $product->discount_price;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name,
                'description' => Str::limit(strip_tags($product->description ?? ''), 250),
                'price' => $discount > 0 && $discount < $price ? $discount : $price,
                'stock' => $product->total_stock,
                'shipping_fee' => (float) ($product->seller?->shipping_fee ?? 0),
                'options' => collect($product->variations ?? [])->take(5)->map(fn ($group) => [
                    'name' => $group['name'] ?? '', 'options' => collect($group['options'] ?? [])->take(12)->map(fn ($option) => [
                        'value' => $option['value'] ?? '', 'stock' => (int) ($option['stock'] ?? 0),
                    ])->all(),
                ])->all(),
                'url' => route($signedIn ? 'buyer.product' : 'guest.product', $product->id),
            ];
        })->values()->all();

        // Order context is opt-in and always scoped to the authenticated buyer.
        $includeOrders = $signedIn && $includeOrders;
        $orders = $includeOrders ? Order::where('buyer_id', $buyer->id)->latest()->limit(10)
            ->get(['order_number', 'status', 'total', 'created_at'])->map(fn (Order $order) => [
                'number' => $order->order_number,
                'status' => $order->status,
                'total' => (float) $order->total,
                'placed_on' => $order->created_at?->toDateString(),
            ])->all() : [];

        return [
            'currency' => 'PHP', 'catalog_limit' => 24, 'products' => $products,
            'catalog_candidates_scanned' => $candidates->count(), 'signed_in_buyer' => (bool) $signedIn,
            'current_product_id' => $selected?->id,
            'policies' => Schema::hasTable('policies') ? Policy::where('account_type', 'buyer')->whereNull('company_name')->limit(20)->get()->map(function ($policy) use ($tokens) {
                $text = strip_tags(preg_replace('/<\/(?:p|div|li)>|<br\s*\/?\s*>/i', "\n", $policy->content ?? ''));
                $chunks = collect(preg_split('/\R+/u', $text))->filter()->flatMap(fn ($paragraph) => mb_str_split($paragraph, 1000));

                return ['title' => $policy->title ?? 'Buyer policy', 'excerpt' => $chunks->sortByDesc(fn ($chunk) => collect($tokens)
                    ->sum(fn ($token) => str_contains(mb_strtolower($chunk), $token) ? 1 : 0))->take(2)->implode("\n")];
            })->all() : [],
            'categories' => Schema::hasTable('categories') ? Category::orderBy('name')->limit(50)->pluck('name')->all() : [],
            'faq_guidance' => [
                'orders' => 'Use My orders for recorded order status. The chatbot cannot place or cancel orders.',
                'shipping' => 'Checkout calculates shipping per seller using that seller\'s configured fee. Product prices exclude shipping.',
                'returns' => 'Use published buyer-policy excerpts; if terms are missing or unclear, contact human support. Do not promise a refund.',
                'support' => 'Contact support opens a form that the buyer must review and submit. No automatic escalation or response-time guarantee.',
            ],
            'knowledge_updated_at' => now()->toIso8601String(),
            'faqs' => $signedIn ? app(FaqKnowledge::class)->retrieve('buyer', $query) : [],
            'active_payment_methods' => Schema::hasTable('payment_methods') ? PaymentMethod::forBuyerCheckout()->orderBy('sort_order')->limit(12)->pluck('name')->all() : [],
            'shipping_note' => 'Shipping fees are set per seller and shown at checkout. No delivery-date guarantee is supplied.',
            'orders_shared' => $includeOrders, 'recent_orders' => $orders,
            'pages' => [
                'products' => ['label' => 'Browse products', 'url' => ($signedIn ? route('buyer.dashboard') : url('/')).'#products'],
                'orders' => ['label' => $signedIn ? 'My orders' : 'Sign in for orders', 'url' => $signedIn ? route('buyer.orders') : route('login')],
                'messages' => ['label' => $signedIn ? 'Message a seller' : 'Sign in to message a seller', 'url' => $signedIn ? route('buyer.messages') : route('login')],
                'cart' => ['label' => 'My cart', 'url' => $signedIn ? route('buyer.cart') : route('login')],
                'account' => ['label' => 'My account', 'url' => $signedIn ? route('buyer.account') : route('login')],
            ],
        ];
    }
}
