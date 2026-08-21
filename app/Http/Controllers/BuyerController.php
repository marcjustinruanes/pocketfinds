<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FetchesProducts;
use App\Models\Product;
use App\Models\User;
use App\Models\Message;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuyerController extends Controller
{
    use FetchesProducts;

    public function dashboard()
    {
        $featured = $this->dbProducts(8);
        return view('buyer.dashboard', compact('featured'));
    }

    public function browse()
    {
        return view('buyer.browse', ['products' => $this->dbProducts()]);
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
        $related = Product::with(['seller', 'category'])
            ->where('status', 'active')
            ->where('id', '!=', $id)
            ->when($titleTerms->isNotEmpty(), function ($query) use ($titleTerms) {
                $query->where(function ($matches) use ($titleTerms) {
                    foreach ($titleTerms as $term) {
                        $matches->orWhere('name', 'like', '%' . $term . '%');
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
        $items = collect(session('cart', []))->map(function (array $item, string $key) {
            return array_merge($item, ['key' => $key]);
        });
        $groups = $items->groupBy('seller_slug');
        $voucherData = $this->cartVoucherData($groups);
        $shippingOptions = [
            ['id' => 'standard', 'label' => 'Standard delivery', 'detail' => '3-5 days', 'amount' => 65],
            ['id' => 'express', 'label' => 'Express delivery', 'detail' => '1-2 days', 'amount' => 120],
        ];

        return view('buyer.cart', compact('items', 'groups', 'voucherData', 'shippingOptions'));
    }

    private function cartVoucherData($groups): array
    {
        $rules = [
            ['type' => 'shop', 'code' => 'SHOP50', 'discount' => 50, 'minimum' => 500],
            ['type' => 'shop', 'code' => 'SHOP100', 'discount' => 100, 'minimum' => 1000],
            ['type' => 'system', 'code' => 'POCKET30', 'discount' => 30, 'minimum' => 300],
        ];

        $all = [];
        foreach ($groups as $slug => $shopItems) {
            $shopTotal = $shopItems->sum(fn ($item) => $item['price'] * $item['qty']);
            $seller = $shopItems->first()['seller'];
            foreach ($rules as $rule) {
                if ($rule['type'] === 'system' && $slug !== $groups->keys()->first()) continue;
                $all[] = array_merge($rule, [
                    'id' => $rule['type'] . '-' . $slug . '-' . $rule['code'],
                    'shop' => $rule['type'] === 'shop' ? $seller : 'PocketFinds',
                    'shop_slug' => $slug,
                    'current_total' => $rule['type'] === 'shop' ? $shopTotal : $groups->flatten(1)->sum(fn ($item) => $item['price'] * $item['qty']),
                ]);
            }
        }

        $all = collect($all)->map(function ($voucher) {
            $voucher['shortfall'] = max(0, $voucher['minimum'] - $voucher['current_total']);
            $voucher['applicable'] = $voucher['shortfall'] === 0;
            return $voucher;
        })->values();

        return [
            'all' => $all,
            'applicable' => $all->where('applicable', true)->values(),
        ];
    }

    public function cartAdd(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required'],
            'color'      => ['nullable', 'string', 'max:100'],
            'size'       => ['nullable', 'string', 'max:100'],
            'qty'        => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $p    = Product::with(['seller','category'])->where('id', $data['product_id'])->where('status','active')->firstOrFail();
        $prod = $this->mapProduct($p);
        $cart = session('cart', []);
        $key  = $p->id . '|' . ($data['color'] ?? '') . '|' . ($data['size'] ?? '');

        if (isset($cart[$key])) {
            $cart[$key]['qty'] = min(99, $cart[$key]['qty'] + $data['qty']);
        } else {
            $cart[$key] = [
                'product_id'  => $p->id,
                'name'        => $p->name,
                'price'       => (float) $p->price,
                'color'       => $data['color'] ?? '',
                'size'        => $data['size'] ?? '',
                'qty'         => $data['qty'],
                'img'         => $prod['img'],
                'seller'      => $prod['seller'],
                'seller_slug' => $prod['seller_slug'],
            ];
        }
        session(['cart' => $cart]);
        return response()->json(['count' => array_sum(array_column($cart, 'qty'))]);
    }

    public function cartUpdate(Request $request, string $key)
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:1', 'max:99']]);
        $cart = session('cart', []);
        abort_unless(isset($cart[$key]), 404);
        $cart[$key]['qty'] = $data['qty'];
        session(['cart' => $cart]);
        return redirect()->route('buyer.cart');
    }

    public function cartRemove(string $key)
    {
        $cart = session('cart', []);
        abort_unless(isset($cart[$key]), 404);
        unset($cart[$key]);
        session(['cart' => $cart]);
        return redirect()->route('buyer.cart');
    }

    public function orders()   { return view('buyer.orders'); }
    public function messages(Request $request)
    {
        $product = null;
        if ($request->filled('product')) {
            $product = Product::with('seller')->where('id', $request->query('product'))->where('status', 'active')->first();
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

        return view('buyer.messages', compact('product', 'seller', 'sellerOnline', 'messages', 'conversations', 'sellerProducts'));
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
            $values['evidence_path'] = $evidence->store('report_evidence', 'public');
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

        $receiver = User::whereKey($data['receiver_id'])->where('account_type', 'seller')->firstOrFail();
        $files = $request->file('attachments', []);
        if ($request->hasFile('attachment')) $files[] = $request->file('attachment');
        abort_unless(filled($data['body'] ?? null) || $files || filled($data['product_id'] ?? null), 422, 'Send a message, product, image, or video.');

        $product = null;
        if (filled($data['product_id'] ?? null)) {
            $product = Product::whereKey($data['product_id'])->where('seller_id', $receiver->id)->where('status', 'active')->firstOrFail();
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
        $msg['attachment_path'] = $file->store('message_attachments', 'public');
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
            'product_img'     => $m->product?->image ? \Illuminate\Support\Facades\Storage::url($m->product->image) : null,
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
    public function account()  { return view('buyer.account'); }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
