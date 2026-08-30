<?php

namespace App\Http\Controllers;

use App\Models\DocumentUpdateRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\Message;
use App\Models\Complaint;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SellerController extends Controller
{
    public function dashboard()    { return view('seller.dashboard'); }
    public function orders(Request $request)
    {
        $status = $request->query('status', 'all');
        $orders = Order::with(['buyer', 'paymentMethod'])
            ->where('app_seller_id', auth()->id())
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->get();
        return view('seller.orders', compact('orders', 'status'));
    }
    public function prepare()      { return view('seller.prepare'); }
    public function shipments()    { return view('seller.shipments'); }
    public function deliveries()   { return view('seller.deliveries'); }
    public function feedback()     { return view('seller.feedback'); }
    public function reports()      { return view('seller.reports'); }
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

    public function inventory()
    {
        $products   = Product::with('category')
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
            'name'              => 'required|string|max:255',
            'image'             => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'price'             => 'required|numeric|min:0',
            'description'       => 'nullable|string',
            'sku'               => 'nullable|string|max:100',
            'stock'             => 'nullable|integer|min:0',
            'variations'        => 'nullable|string',
            'details'           => 'nullable|string',
        ]);

        $seller = auth()->user();

        if (!$seller->category_id) {
            return back()->withErrors(['image' => 'Please set your shop category in Account settings before adding products.']);
        }

        $imagePath  = $request->file('image')->store('product_images', 'public');
        $variations = $request->filled('variations') ? json_decode($request->variations, true) : null;
        $details    = $request->filled('details')    ? json_decode($request->details, true)    : null;

        Product::create([
            'seller_id'   => $seller->id,
            'category_id' => $seller->category_id,
            'name'        => $request->name,
            'image'       => $imagePath,
            'price'       => $request->price,
            'description' => $request->description,
            'sku'         => $request->sku,
            'stock'       => $variations ? 0 : (int) $request->input('stock', 0),
            'variations'  => $variations,
            'details'     => $details,
            'status'      => 'pending',
        ]);

        // Notify all admins
        $admins = User::where('is_admin', true)->get();
        foreach ($admins as $admin) {
            DB::table('notifications')->insert([
                'id'                => (string) Str::uuid(),
                'user_id'           => $admin->id,
                'title'             => 'New Product Pending Review',
                'message'           => $seller->given_names . ' ' . $seller->last_name . ' submitted a new product: ' . $request->name,
                'notification_type' => 'product_pending',
                'is_read'           => false,
                'created_at'        => now(),
            ]);
        }

        return back()->with('product_success', 'Product submitted for admin review.');
    }

    public function destroyProduct(Product $product)
    {
        abort_if($product->seller_id !== auth()->id(), 403);
        $product->delete();
        return back()->with('product_success', 'Product removed.');
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
            'idTypes'        => DB::table('id_types')->orderBy('id')->get(),
            'categories'     => DB::table('categories')->orderBy('name')->get(),
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
        ]);

        auth()->user()->update($data);
        return back()->with('shop_success', 'Shop information updated successfully.');
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
            $data['id_file'] = $request->file('id_file')->store('id_files', 'public');
        }
        if ($request->hasFile('business_permit_file')) {
            $data['business_permit_file'] = $request->file('business_permit_file')->store('permit_files', 'public');
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
