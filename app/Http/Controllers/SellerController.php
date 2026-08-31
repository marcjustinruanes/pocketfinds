<?php

namespace App\Http\Controllers;

use App\Models\DocumentUpdateRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\Message;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Review;
use App\Models\Shipment;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SellerController extends Controller
{
    /** Flat platform commission taken off completed sales, used across dashboard/reports. */
    const COMMISSION_RATE = 0.10;

    public function dashboard()
    {
        $sellerId = auth()->id();
        $orders = Order::where('seller_id', $sellerId);
        $totalSales = (clone $orders)->where('status', 'completed')->sum('total');
        $newOrders = (clone $orders)->where('status', 'to_ship')->count();
        $productsListed = Product::where('seller_id', $sellerId)->where('status', 'active')->count();
        $avgRating = round((float) Review::where('seller_id', $sellerId)->avg('rating'), 1);
        $recentOrders = Order::with('buyer')->where('seller_id', $sellerId)->latest()->limit(5)->get();
        $pipelineCounts = [
            'new' => (clone $orders)->where('status', 'to_ship')->count(),
            'prepare' => (clone $orders)->where('status', 'to_ship')->count(),
            'shipments' => (clone $orders)->where('status', 'in_transit')->count(),
            'deliveries' => (clone $orders)->whereIn('status', ['completed', 'delivered'])->count(),
        ];
        $chartStart = now()->startOfDay()->subDays(6);
        $salesByDay = (clone $orders)->where('status', 'completed')->where('created_at', '>=', $chartStart)
            ->get(['total', 'created_at'])->groupBy(fn ($order) => $order->created_at->format('Y-m-d'));
        $salesChart = collect(range(0, 6))->map(function ($days) use ($chartStart, $salesByDay) {
            $date = $chartStart->copy()->addDays($days);
            return ['label' => $date->format('D'), 'amount' => (float) $salesByDay->get($date->format('Y-m-d'), collect())->sum('total')];
        });
        $chartMax = max(1, $salesChart->max('amount'));
        $lowStock = Product::where('seller_id', $sellerId)->where('status', 'active')->get()->filter(fn ($product) => $product->total_stock <= 5)->take(5);

        return view('seller.dashboard', compact('totalSales', 'newOrders', 'productsListed', 'avgRating', 'recentOrders', 'pipelineCounts', 'salesChart', 'chartMax', 'lowStock'));
    }
    public function orders(Request $request)
    {
        $status = $request->query('status', 'all');
        $orders = Order::with(['buyer', 'paymentMethod', 'shipment.courier'])
            ->where('seller_id', auth()->id())
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->get();

        $sellerId = auth()->id();
        // "Pending confirmation" = the buyer hasn't confirmed receipt yet, whether the
        // courier is still en route (out_for_delivery) or has already dropped it off
        // and is waiting on the buyer to confirm (delivered).
        $pendingConfirmation = Order::where('seller_id', $sellerId)->whereIn('status', ['out_for_delivery', 'delivered'])->count();
        $deliveredToday = Order::where('seller_id', $sellerId)->where('status', 'completed')->whereDate('updated_at', today())->count();
        $statusCounts = Order::where('seller_id', $sellerId)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('seller.orders', compact('orders', 'status', 'pendingConfirmation', 'deliveredToday', 'statusCounts'));
    }

    /** Seller marks a packed order ready — opens a shipment request for Logistics to approve. */
    public function readyForPickup(Order $order)
    {
        abort_unless($order->seller_id === auth()->id(), 403);
        abort_unless($order->status === 'to_ship', 422, 'This order is not awaiting preparation.');

        if (!$order->shipment) {
            Shipment::create([
                'order_id'        => $order->id,
                'tracking_number' => 'PF-SHIP-' . now()->format('ymd') . '-' . strtoupper(Str::random(6)),
                'shipping_status' => 'pending',
            ]);
            DB::table('order_status_history')->insert([
                'id' => (string) Str::uuid(), 'order_id' => $order->id, 'status' => 'ready_for_pickup',
                'changed_by' => auth()->id(), 'created_at' => now(),
            ]);
        }

        return back()->with('order_success', 'Order handed off to logistics for courier pickup.');
    }

    /** Printable waybill/shipping label for a prepared order. */
    public function waybill(Order $order)
    {
        abort_if($order->seller_id !== auth()->id(), 403);
        $order->load(['buyer', 'shipment']);
        abort_unless($order->shipment, 404, 'This order has not been handed off for pickup yet.');

        return view('seller.waybill', ['order' => $order, 'seller' => auth()->user()]);
    }

    public function schedulePickup(Request $request, Order $order)
    {
        abort_if($order->seller_id !== auth()->id(), 403);
        $order->load('shipment');
        abort_unless($order->shipment, 404, 'This order has no shipment to schedule yet.');

        $data = $request->validate([
            'scheduled_pickup_at' => 'required|date|after:now',
        ]);
        $order->shipment->update(['scheduled_pickup_at' => $data['scheduled_pickup_at']]);

        return back()->with('order_success', 'Courier pickup scheduled for ' . \Illuminate\Support\Carbon::parse($data['scheduled_pickup_at'])->format('M d, Y g:i A') . '.');
    }

    public function feedback()
    {
        $sellerId = auth()->id();
        $reviews = Review::with(['buyer', 'product', 'order'])->where('seller_id', $sellerId)->latest('created_at')->get();
        $total = $reviews->count();
        $avgRating = $total ? round($reviews->avg('rating'), 1) : 0;
        $breakdown = collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($star) => [
            $star => $total ? round($reviews->where('rating', $star)->count() / $total * 100) : 0,
        ]);
        $needsResponse = $reviews->whereNull('seller_reply')->count();
        return view('seller.feedback', compact('reviews', 'total', 'avgRating', 'breakdown', 'needsResponse'));
    }

    public function replyReview(Request $request, Review $review)
    {
        abort_unless($review->seller_id === auth()->id(), 403);
        $data = $request->validate(['seller_reply' => 'required|string|max:1000']);
        $review->update(['seller_reply' => $data['seller_reply']]);

        DB::table('notifications')->insert([
            'id'                => (string) Str::uuid(),
            'user_id'           => $review->buyer_id,
            'title'             => 'Seller Replied to Your Review',
            'message'           => auth()->user()->business_name . ' replied to your review' . ($review->product ? ' for "' . $review->product->name . '"' : '') . '.',
            'notification_type' => 'review_reply',
            'reference_id'      => $review->id,
            'is_read'           => false,
            'created_at'        => now(),
        ]);

        return back()->with('review_success', 'Reply posted.');
    }

    public function reports(Request $request)
    {
        $sellerId = auth()->id();
        $from = $request->filled('from') ? \Illuminate\Support\Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $to   = $request->filled('to') ? \Illuminate\Support\Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        $completed = Order::where('seller_id', $sellerId)->where('status', 'completed')
            ->whereBetween('updated_at', [$from, $to])->get();

        $totalRevenue   = (float) $completed->sum('total');
        $commission     = round($totalRevenue * self::COMMISSION_RATE, 2);
        $shippingFees   = (float) $completed->sum('shipping_amount');
        $discounts      = (float) $completed->sum('discount_amount');
        $netProfit      = $totalRevenue - $commission;
        $ordersCompleted = $completed->count();
        $avgOrderValue  = $ordersCompleted ? $totalRevenue / $ordersCompleted : 0;

        $days = collect();
        for ($d = $from->copy(); $d->lte($to) && $days->count() < 60; $d->addDay()) $days->push($d->format('Y-m-d'));
        $byDay = $completed->groupBy(fn ($o) => $o->updated_at->format('Y-m-d'));
        $revenueChart = $days->map(fn ($day) => (float) $byDay->get($day, collect())->sum('total'));
        $chartMax = max(1, $revenueChart->max() ?: 1);

        $lineItems = $completed->flatMap(fn ($o) => collect($o->items ?? []))->values();
        $topProducts = $lineItems->groupBy('name')
            ->map(fn ($rows, $name) => [
                'name' => $name ?: 'Product not provided',
                'units' => $rows->sum('qty'),
                'revenue' => $rows->sum(fn ($r) => ($r['price'] ?? 0) * ($r['qty'] ?? 0)),
            ])
            ->sortByDesc('revenue')->take(5)->values();

        $productCategories = Product::where('seller_id', $sellerId)->with('category')->get()->keyBy('id');
        $salesByCategory = $lineItems->groupBy(fn ($r) => $productCategories->get($r['product_id'] ?? null)?->category?->name ?? 'Uncategorized')
            ->map(fn ($rows) => $rows->sum(fn ($r) => ($r['price'] ?? 0) * ($r['qty'] ?? 0)))
            ->sortByDesc(fn ($v) => $v);
        $categoryTotal = max(1, $salesByCategory->sum());

        $allOrders = Order::where('seller_id', $sellerId)->whereBetween('created_at', [$from, $to])->get();
        $fulfillable = $allOrders->whereIn('status', ['completed', 'in_transit', 'out_for_delivery', 'delivered', 'cancelled'])->count();
        $fulfillmentRate = $fulfillable ? round($allOrders->where('status', 'completed')->count() / $fulfillable * 100) : null;
        $satisfaction = Review::where('seller_id', $sellerId)->avg('rating');

        return view('seller.reports', [
            'from' => $from, 'to' => $to,
            'totalRevenue' => $totalRevenue, 'netProfit' => $netProfit, 'commission' => $commission,
            'shippingFees' => $shippingFees, 'discounts' => $discounts,
            'ordersCompleted' => $ordersCompleted, 'avgOrderValue' => $avgOrderValue,
            'revenueChart' => $revenueChart, 'chartMax' => $chartMax,
            'topProducts' => $topProducts,
            'salesByCategory' => $salesByCategory, 'categoryTotal' => $categoryTotal,
            'fulfillmentRate' => $fulfillmentRate,
            'satisfaction' => $satisfaction ? round($satisfaction, 1) : null,
        ]);
    }
    public function messages(Request $request)
    {
        $buyer = null;
        if ($request->filled('buyer')) {
            $buyer = User::where('id', $request->query('buyer'))
                ->where(fn ($q) => $q->where('account_type', 'buyer')->orWhere('is_admin', true))
                ->first();
        }

        $admin = User::where('is_admin', true)->first();

        $messages = [];
        if ($buyer) {
            $messages = Message::with('product')
                ->where(function($q) use ($buyer) {
                    $q->where('sender_id', auth()->id())->where('receiver_id', $buyer->id);
                })->orWhere(function($q) use ($buyer) {
                    $q->where('sender_id', $buyer->id)->where('receiver_id', auth()->id());
                })
                ->orderBy('created_at')
                ->get();
            // mark buyer messages as read
            Message::where('sender_id', $buyer->id)
                ->where('receiver_id', auth()->id())
                ->where('read', false)
                ->update(['read' => true]);
        }

        $sellerProducts = Product::where('seller_id', auth()->id())->where('status', 'active')->orderBy('name')->get();

        $conversations = Message::with(['sender','receiver','product'])
            ->where('sender_id', auth()->id())
            ->orWhere('receiver_id', auth()->id())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn($m) => $m->sender_id === auth()->id() ? $m->receiver_id : $m->sender_id)
            ->map(fn($msgs) => $msgs->first());

        return view('seller.messages', compact('buyer', 'admin', 'messages', 'conversations', 'sellerProducts'));
    }

    public function messagesPoll(Request $request)
    {
        $data = $request->validate(['receiver_id' => ['required', 'integer']]);
        $buyer = User::whereKey($data['receiver_id'])
            ->where(fn ($q) => $q->where('account_type', 'buyer')->orWhere('is_admin', true))
            ->firstOrFail();

        Message::where('sender_id', $buyer->id)
            ->where('receiver_id', auth()->id())
            ->where('read', false)
            ->update(['read' => true]);

        $messages = Message::with('product')
            ->where(function ($query) use ($buyer) {
                $query->where('sender_id', auth()->id())->where('receiver_id', $buyer->id);
            })->orWhere(function ($query) use ($buyer) {
                $query->where('sender_id', $buyer->id)->where('receiver_id', auth()->id());
            })->orderBy('created_at')->get()
            ->map(fn ($message) => $this->formatMessage($message));

        return response()->json(['ok' => true, 'messages' => $messages]);
    }

    public function reportMessage(Request $request)
    {
        $data = $request->validate([
            'message_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:120'],
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
            'message_id' => $message->id, 'shop_name' => $message->sender?->business_name,
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

    public function messagesSend(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'integer'],
            'body'        => ['nullable', 'string', 'max:2000'],
            'product_id'  => ['nullable', 'string'],
            'attachments'   => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
            'attachment'    => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
        ]);

        User::whereKey($data['receiver_id'])
            ->where(fn ($q) => $q->where('account_type', 'buyer')->orWhere('is_admin', true))
            ->firstOrFail();
        $files = $request->file('attachments', []);
        if ($request->hasFile('attachment')) $files[] = $request->file('attachment');
        abort_unless(filled($data['body'] ?? null) || $files || filled($data['product_id'] ?? null), 422, 'Send a message, product, image, or video.');

        $product = null;
        if (filled($data['product_id'] ?? null)) {
            $product = Product::whereKey($data['product_id'])->where('seller_id', auth()->id())->where('status', 'active')->firstOrFail();
        }

        $messages = [];
        foreach ($files ?: [null] as $index => $file) {
            $msg = ['sender_id' => auth()->id(), 'receiver_id' => $data['receiver_id'], 'read' => false];
            if ($index === 0 && filled($data['body'] ?? null)) $msg['body'] = $data['body'];
            if ($index === 0 && $product) $msg['product_id'] = $product->id;
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
            'product_name'    => $m->product?->name,
            'product_price'   => $m->product?->price,
            'product_img'     => $m->product?->image ? (rtrim(config('filesystems.disks.supabase.url'), '/') . '/' . ltrim($m->product->image, '/')) : null,
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

    public function inventory()
    {
        $products   = Product::with(['category'])
            ->where('seller_id', auth()->id())
            ->latest()
            ->get();
        $categories = DB::table('categories')->orderBy('name')->get();
        $seller     = auth()->user();
        return view('seller.inventory', compact('products', 'categories', 'seller'));
    }

    public function storeProduct(Request $request)
    {
        $request->validate([
            'name'                => 'required|string|max:255',
            'images'              => 'nullable|array|max:9',
            'images.*'            => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'video'               => 'nullable|file|mimes:mp4,mov,avi,webm|max:51200',
            'price'               => 'required|numeric|min:0',
            'discount_price'      => 'nullable|numeric|min:0|lt:price',
            'description'         => 'nullable|string',
            'sku'                 => 'nullable|string|max:100',
            'stock'               => 'nullable|integer|min:0',
            'restock_date'        => 'nullable|date|after_or_equal:today',
            'variations'          => 'nullable|string',
            'details'             => 'nullable|string',
            'variation_images'    => 'nullable|array',
            'variation_images.*'  => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'weight_grams'        => 'required|numeric|min:1',
            'weight_grams_max'    => 'nullable|numeric|gte:weight_grams',
            'length_cm'           => 'nullable|numeric|min:0',
            'width_cm'            => 'nullable|numeric|min:0',
            'height_cm'           => 'nullable|numeric|min:0',
            'condition'           => 'required|in:new,used',
        ], [
            'discount_price.lt' => 'Discount price must be lower than the regular price.',
        ]);

        $seller = auth()->user();

        if (!$seller->category_id) {
            return back()->withErrors(['images' => 'Please set your shop category in Account settings before adding products.']);
        }

        $imagePaths = collect($request->file('images', []))->map(fn ($file) => $file->store('product_images', 'supabase'))->values();
        $variations = $request->filled('variations') ? json_decode($request->variations, true) : null;
        $details    = $request->filled('details') ? json_decode($request->details, true) : null;

        $firstVariationImage = $this->applyVariationImagesAndPrices($variations, $request->file('variation_images', []));

        if ($imagePaths->isEmpty() && !$firstVariationImage) {
            return back()->withErrors(['images' => 'Add at least one product photo (either general photos or a photo on a variation option).']);
        }

        $coverImages = $imagePaths->isNotEmpty() ? $imagePaths->all() : [$firstVariationImage];
        $videoPath   = $request->hasFile('video') ? $request->file('video')->store('product_videos', 'supabase') : null;

        Product::create([
            'seller_id'    => $seller->id,
            'category_id'  => $seller->category_id,
            'name'         => $request->name,
            'image'        => $coverImages[0],
            'images'       => $coverImages,
            'video'        => $videoPath,
            'price'        => $request->price,
            'discount_price' => $request->discount_price ?: null,
            'description'  => $request->description,
            'sku'          => $request->sku,
            'stock'        => $variations ? collect($variations)->sum(fn ($v) => collect($v['options'] ?? [])->sum('stock')) : (int) $request->input('stock', 0),
            'restock_date' => $request->restock_date ?: null,
            'variations'   => $variations,
            'details'      => $details,
            'status'       => 'pending',
            'weight_grams' => (int) $request->weight_grams,
            'weight_grams_max' => $request->weight_grams_max ?: null,
            'length_cm'    => $request->length_cm ?: null,
            'width_cm'     => $request->width_cm ?: null,
            'height_cm'    => $request->height_cm ?: null,
            'condition'    => $request->condition,
        ]);

        $this->notifyAdminsProductPending($seller, $request->name, 'submitted a new product');

        return back()->with('product_success', 'Product submitted for admin review.');
    }

    public function updateProduct(Request $request, Product $product)
    {
        abort_if($product->seller_id !== auth()->id(), 403);

        $request->validate([
            'name'                => 'required|string|max:255',
            'images'              => 'nullable|array|max:9',
            'images.*'            => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'existing_images'     => 'nullable|array',
            'existing_images.*'   => 'string',
            'video'               => 'nullable|file|mimes:mp4,mov,avi,webm|max:51200',
            'keep_video'          => 'nullable|boolean',
            'price'               => 'required|numeric|min:0',
            'discount_price'      => 'nullable|numeric|min:0|lt:price',
            'description'         => 'nullable|string',
            'sku'                 => 'nullable|string|max:100',
            'stock'               => 'nullable|integer|min:0',
            'restock_date'        => 'nullable|date|after_or_equal:today',
            'variations'          => 'nullable|string',
            'details'             => 'nullable|string',
            'variation_images'    => 'nullable|array',
            'variation_images.*'  => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'weight_grams'        => 'required|numeric|min:1',
            'weight_grams_max'    => 'nullable|numeric|gte:weight_grams',
            'length_cm'           => 'nullable|numeric|min:0',
            'width_cm'            => 'nullable|numeric|min:0',
            'height_cm'           => 'nullable|numeric|min:0',
            'condition'           => 'required|in:new,used',
        ], [
            'discount_price.lt' => 'Discount price must be lower than the regular price.',
        ]);

        $seller = auth()->user();

        $keptImages = collect($request->input('existing_images', []));
        $newImages  = collect($request->file('images', []))->map(fn ($file) => $file->store('product_images', 'supabase'));
        $variations = $request->filled('variations') ? json_decode($request->variations, true) : null;
        $details    = $request->filled('details') ? json_decode($request->details, true) : null;

        // Each option keeps its previously-uploaded photo (sent back as
        // `existing_image`) unless the seller picked a new one for it.
        $firstVariationImage = $this->applyVariationImagesAndPrices($variations, $request->file('variation_images', []));

        $coverImages = $keptImages->merge($newImages)->values();
        if ($coverImages->isEmpty() && !$firstVariationImage) {
            return back()->withErrors(['images' => 'Add at least one product photo (either general photos or a photo on a variation option).']);
        }
        if ($coverImages->isEmpty()) $coverImages = collect([$firstVariationImage]);

        $videoPath = $request->hasFile('video')
            ? $request->file('video')->store('product_videos', 'supabase')
            : ($request->boolean('keep_video') ? $product->video : null);

        $product->update([
            'name'         => $request->name,
            'image'        => $coverImages->first(),
            'images'       => $coverImages->all(),
            'video'        => $videoPath,
            'price'        => $request->price,
            'discount_price' => $request->discount_price ?: null,
            'description'  => $request->description,
            'sku'          => $request->sku,
            'stock'        => $variations ? collect($variations)->sum(fn ($v) => collect($v['options'] ?? [])->sum('stock')) : (int) $request->input('stock', 0),
            'restock_date' => $request->restock_date ?: null,
            'variations'   => $variations,
            'details'      => $details,
            'status'       => 'pending',
            'rejection_note' => null,
            'weight_grams' => (int) $request->weight_grams,
            'weight_grams_max' => $request->weight_grams_max ?: null,
            'length_cm'    => $request->length_cm ?: null,
            'width_cm'     => $request->width_cm ?: null,
            'height_cm'    => $request->height_cm ?: null,
            'condition'    => $request->condition,
        ]);

        $this->notifyAdminsProductPending($seller, $request->name, 'updated a product, awaiting re-review');

        return back()->with('product_success', 'Product updated and resubmitted for admin review.');
    }

    /**
     * Resolve each variation option's `image_key` against the uploaded
     * variation_images[] files (setting `option.image` to the stored path),
     * or keep its `existing_image` (edit flow) if no new file was picked.
     * Also sanitizes each option's optional price override. Returns the
     * first option image path found, if any (used as a cover-photo fallback).
     */
    private function applyVariationImagesAndPrices(?array &$variations, array $variationImages): ?string
    {
        $firstImage = null;
        if (!$variations) return null;

        foreach ($variations as &$variation) {
            foreach ($variation['options'] as &$option) {
                $key = $option['image_key'] ?? null;
                unset($option['image_key']);
                if ($key && isset($variationImages[$key])) {
                    $path = $variationImages[$key]->store('product_images', 'supabase');
                    $option['image'] = $path;
                } elseif (!empty($option['existing_image'])) {
                    $option['image'] = $option['existing_image'];
                }
                unset($option['existing_image']);
                $firstImage ??= $option['image'] ?? null;

                if (isset($option['price']) && (!is_numeric($option['price']) || $option['price'] < 0)) {
                    unset($option['price']);
                } elseif (isset($option['price'])) {
                    $option['price'] = round((float) $option['price'], 2);
                }
            }
        }
        unset($variation, $option);

        return $firstImage;
    }

    private function notifyAdminsProductPending(User $seller, string $productName, string $verb): void
    {
        $admins = User::where('is_admin', true)->get();
        foreach ($admins as $admin) {
            DB::table('notifications')->insert([
                'id'                => (string) Str::uuid(),
                'user_id'           => $admin->id,
                'title'             => 'Product Pending Review',
                'message'           => $seller->given_names . ' ' . $seller->last_name . ' ' . $verb . ': ' . $productName,
                'notification_type' => 'product_pending',
                'is_read'           => false,
                'created_at'        => now(),
            ]);
        }
    }

    public function destroyProduct(Product $product)
    {
        abort_if($product->seller_id !== auth()->id(), 403);
        $product->delete();
        return back()->with('product_success', 'Product removed.');
    }

    /** Archiving hides a live listing from buyers without deleting it; unarchiving restores it as active. */
    public function archiveProduct(Request $request, Product $product)
    {
        abort_if($product->seller_id !== auth()->id(), 403);
        $archiving = $request->boolean('archived');
        abort_if($archiving && $product->status !== 'active', 422, 'Only active products can be archived.');
        abort_if(!$archiving && $product->status !== 'archived', 422, 'Only archived products can be unarchived.');

        $product->update(['status' => $archiving ? 'archived' : 'active']);
        return back()->with('product_success', $archiving ? 'Product archived.' : 'Product restored to active.');
    }

    public function notifications()
    {
        $notifications = DB::table('notifications')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
        return view('seller.notifications', compact('notifications'));
    }

    public function markNotifRead(Request $request)
    {
        DB::table('notifications')
            ->where('user_id', auth()->id())
            ->update(['is_read' => true]);
        return back();
    }

    /** Mark one notification read and send the seller to whatever it's about. */
    public function openNotification(string $id)
    {
        $notification = DB::table('notifications')->where('id', $id)->where('user_id', auth()->id())->first();
        abort_unless($notification, 404);

        DB::table('notifications')->where('id', $id)->update(['is_read' => true]);

        return match ($notification->notification_type) {
            'product_pending', 'product_approved', 'product_rejected' => redirect()->route('seller.inventory'),
            'doc_approved', 'doc_rejected' => redirect()->route('seller.account'),
            'order_delivered', 'new_order' => redirect()->route('seller.orders'),
            default => redirect()->route('seller.notifications'),
        };
    }

    public function account()
    {
        $pendingRequest = DocumentUpdateRequest::where('user_id', auth()->id())
            ->where('status', 'pending')
            ->latest()
            ->first();

        $lastRequest = DocumentUpdateRequest::where('user_id', auth()->id())
            ->latest()
            ->first();

        return view('seller.account', [
            'seller'          => auth()->user(),
            'idTypes'        => DB::table('id_types')->orderBy('id')->get(),
            'categories'     => DB::table('categories')->orderBy('name')->get(),
            'sexes'          => User::whereNotNull('sex')->distinct()->orderBy('sex')->pluck('sex'),
            'pendingRequest' => $pendingRequest,
            'lastRequest'    => $lastRequest,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'given_names' => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'contact_no'  => 'nullable|string|max:11',
            'sex'         => 'required|in:male,female',
            'birthday'    => 'nullable|date',
        ]);

        auth()->user()->update($data);
        return back()->with('profile_success', 'Profile updated successfully.');
    }

    public function updateAddress(Request $request)
    {
        $data = $request->validate([
            'province'     => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay'     => 'required|string|max:255',
            'house_no'     => 'nullable|string|max:255',
            'street'       => 'nullable|string|max:255',
        ]);

        auth()->user()->update($data);
        return back()->with('address_success', 'Address updated successfully.');
    }

    public function updateShop(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'username'      => 'nullable|string|max:255|unique:users,username,' . auth()->id(),
            'category_id'   => 'nullable|integer|exists:categories,id',
            'shipping_fee'  => 'nullable|numeric|min:0|max:99999.99',
        ]);

        auth()->user()->update($data);
        return back()->with('shop_success', 'Shop information updated successfully.');
    }

    public function vouchers()
    {
        $vouchers = Voucher::where('seller_id', auth()->id())->latest('created_at')->get();
        return view('seller.vouchers', compact('vouchers'));
    }

    public function storeVoucher(Request $request)
    {
        $data = $request->validate([
            'code'            => 'required|string|max:30|alpha_dash',
            'type'            => 'required|in:amount,free_shipping',
            'discount_amount' => 'required_if:type,amount|nullable|numeric|min:1|max:99999.99',
            'minimum_spend'   => 'nullable|numeric|min:0|max:99999.99',
            'usage_limit'     => 'nullable|integer|min:1',
            'expires_at'      => 'nullable|date|after:today',
        ]);
        $data['code'] = strtoupper($data['code']);
        if ($data['type'] === 'free_shipping') {
            $data['discount_amount'] = null;
        }

        $exists = Voucher::where('seller_id', auth()->id())->where('code', $data['code'])->exists();
        if ($exists) {
            return back()->withErrors(['code' => 'You already have a voucher with this code.'])->withInput();
        }

        Voucher::create(array_merge($data, ['seller_id' => auth()->id()]));
        return back()->with('voucher_success', 'Voucher created.');
    }

    public function updateVoucher(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->seller_id === auth()->id(), 403);
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);
        $voucher->update($data);
        return back()->with('voucher_success', $data['is_active'] ? 'Voucher activated.' : 'Voucher deactivated.');
    }

    public function destroyVoucher(Voucher $voucher)
    {
        abort_unless($voucher->seller_id === auth()->id(), 403);
        $voucher->delete();
        return back()->with('voucher_success', 'Voucher deleted.');
    }

    public function updateDocuments(Request $request)
    {
        $request->validate([
            'id_type_id'           => 'nullable|integer|exists:id_types,id',
            'id_file'              => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'business_permit_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        DocumentUpdateRequest::where('user_id', auth()->id())
            ->where('status', 'pending')
            ->delete();

        $data = [
            'user_id'    => auth()->id(),
            'id_type_id' => $request->id_type_id,
            'status'     => 'pending',
        ];

        if ($request->hasFile('id_file')) {
            $data['id_file'] = $request->file('id_file')->store('id_files', 'supabase');
        }
        if ($request->hasFile('business_permit_file')) {
            $data['business_permit_file'] = $request->file('business_permit_file')->store('permit_files', 'supabase');
        }

        DocumentUpdateRequest::create($data);
        return back()->with('docs_success', 'Your document update request has been submitted and is pending admin approval.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        auth()->user()->update(['password' => $request->password]);
        return back()->with('password_success', 'Password updated successfully.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
