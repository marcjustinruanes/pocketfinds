<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Commission;
use App\Models\Complaint;
use App\Models\DocumentUpdateRequest;
use App\Models\Message;
use App\Models\Order;
use App\Models\Policy;
use App\Models\Product;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function login()
    {
        if (auth()->check()) {
            if (auth()->user()->is_admin) return redirect()->route('admin.dashboard');
            if (auth()->user()->is_logistics) return redirect()->route('logistics.dashboard');
        }
        return view('auth.login');
    }

    public function loginPost(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required',
        ]);

        $login = $request->input('email');

        // Find user by email or username
        $user = User::where('email', $login)->orWhere('username', $login)->first();

        if ($user) {
            if ($user->status === 'pending') {
                return back()->withErrors(['email' => 'Your account is still pending admin approval. Please wait for confirmation.'])->withInput();
            }
            if ($user->status === 'rejected') {
                return back()->withErrors(['email' => 'Your account application was rejected.'])
                    ->with('accountStatus', 'rejected')->withInput();
            }
            if ($user->status === 'suspended') {
                return back()->withErrors(['email' => 'Your account has been suspended.'])
                    ->with('accountStatus', 'suspended')->withInput();
            }
            // Google-only accounts have no usable password
            if ($user->auth_method === 'google' && !$user->password) {
                return back()->withErrors(['email' => 'This account was registered with Google. Please use "Continue with Google" to sign in.'])->withInput();
            }
        }

        // Auth::attempt only works with email, so resolve the email from username if needed
        $email = $user ? $user->email : $login;
        $credentials = ['email' => $email, 'password' => $request->input('password')];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = auth()->user();

            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            }
            if ($user->is_logistics) {
                return redirect()->route('logistics.dashboard');
            }
            if ($user->account_type === 'buyer') {
                return redirect()->route('buyer.dashboard');
            }
            if ($user->account_type === 'seller') {
                return redirect()->route('seller.dashboard');
            }
            return redirect()->intended('/');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function sidebarCounts(): array
    {
        return [
            'pendingRegistrations' => User::where('status', 'pending')->where('is_admin', false)->count(),
            'openDisputes'         => Complaint::whereIn('status', ['open', 'escalated'])->count(),
            'unreadMessages'       => Message::where('receiver_id', auth()->id())->where('read', false)->count(),
            'pendingDocs'          => DocumentUpdateRequest::where('status', 'pending')->count(),
        ];
    }

    public function approveUser(User $user)
    {
        $user->update(['status' => 'approved']);
        return back();
    }

    public function rejectUser(User $user)
    {
        $user->update(['status' => 'rejected']);
        return back();
    }

    public function activateUser(User $user)
    {
        $user->update(['status' => 'approved']);
        return back();
    }

    public function suspendUser(User $user)
    {
        $user->update(['status' => 'suspended']);
        return back();
    }

    public function dashboard(Request $request)
    {
        $counts     = $this->sidebarCounts();
        $totalUsers = User::where('is_admin', false)->count();
        $pendingCount = $counts['pendingRegistrations'];
        $recentUsers  = User::where('is_admin', false)->latest()->take(5)->get();
        $latestAnnouncement = Announcement::latest()->first();

        // Sales performance: real per-day order totals for the selected window (0 where no orders).
        $salesDays  = in_array((int) $request->query('days'), [7, 14, 30, 90], true) ? (int) $request->query('days') : 30;
        $rangeStart = now()->subDays($salesDays - 1)->startOfDay();
        $dailySales = Order::where('created_at', '>=', $rangeStart)
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $salesSeries = collect(range(0, $salesDays - 1))->map(function ($i) use ($rangeStart, $dailySales) {
            $date = $rangeStart->copy()->addDays($i);
            return ['date' => $date, 'total' => (float) ($dailySales[$date->format('Y-m-d')] ?? 0)];
        });
        $salesTotal = $salesSeries->sum('total');

        return view('admin.dashboard', array_merge($counts, compact(
            'totalUsers', 'pendingCount', 'recentUsers', 'latestAnnouncement', 'salesSeries', 'salesTotal', 'salesDays'
        )));
    }

    public function registrations()
    {
        $counts = $this->sidebarCounts();
        $users  = User::with('categories')->where('is_admin', false)->latest()->get();
        return view('admin.registrations', array_merge($counts, compact('users')));
    }

    public function users()
    {
        $counts = $this->sidebarCounts();
        $users  = User::with('categories')->where('is_admin', false)->latest()->get();
        return view('admin.users', array_merge($counts, compact('users')));
    }

    public function compliance()
    {
        $counts  = $this->sidebarCounts();
        $sellers = User::with('categories')->where('account_type', 'seller')->where('is_admin', false)->latest()->get();
        return view('admin.compliance', array_merge($counts, compact('sellers')));
    }

    public function complaints()
    {
        $counts     = $this->sidebarCounts();
        $complaints = Complaint::with(['complainant', 'respondent'])->latest('created_at')->get();
        return view('admin.complaints', array_merge($counts, compact('complaints')));
    }

    public function resolveComplaint(Request $request, $id)
    {
        Complaint::where('id', $id)->update([
            'status'      => 'resolved',
            'handled_by'  => auth()->id(),
            'resolved_at' => now(),
            'resolution'  => $request->input('resolution', 'Resolved by admin.'),
        ]);
        return back();
    }

    public function commission()
    {
        $counts      = $this->sidebarCounts();
        $commissions = Commission::with('seller')->latest('created_at')->get();
        $totalAmount = $commissions->sum('commission_amount');
        $sellers     = User::where('account_type', 'seller')->where('is_admin', false)->count();
        return view('admin.commission', array_merge($counts, compact('commissions', 'totalAmount', 'sellers')));
    }

    public function reports()
    {
        $counts      = $this->sidebarCounts();
        $totalUsers  = User::where('is_admin', false)->count();
        $buyerCount  = User::where('account_type', 'buyer')->count();
        $sellerCount = User::where('account_type', 'seller')->count();
        $riderCount  = User::where('account_type', 'rider')->count();
        $pending     = User::where('status', 'pending')->where('is_admin', false)->count();
        $approved    = User::where('status', 'approved')->where('is_admin', false)->count();
        $rejected    = User::where('status', 'rejected')->where('is_admin', false)->count();
        $totalCommission = Commission::sum('commission_amount');
        $commissionCount = Commission::count();

        return view('admin.reports', array_merge($counts, compact(
            'totalUsers', 'buyerCount', 'sellerCount', 'riderCount',
            'pending', 'approved', 'rejected', 'totalCommission', 'commissionCount'
        )));
    }

    public function exportSalesReport()
    {
        $commissions = Commission::with('seller')->latest()->get();
        $csv = "Order ID,Seller,Sale Amount,Commission Rate,Commission,Seller Earnings,Date\n";
        foreach ($commissions as $c) {
            $seller = $c->seller ? $c->seller->given_names.' '.$c->seller->last_name : 'Unknown';
            $csv .= implode(',', [
                strtoupper(substr($c->order_id ?? $c->id, 0, 8)),
                $seller,
                $c->order_amount,
                $c->commission_rate.'%',
                $c->commission_amount,
                $c->seller_earnings,
                $c->created_at?->format('Y-m-d'),
            ])."\n";
        }
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales_report_'.now()->format('Ymd').'.csv"',
        ]);
    }

    public function exportSalesReportPdf()
    {
        $commissions = Commission::with('seller')->latest()->get();
        $totalAmount = $commissions->sum('order_amount');
        $totalCommission = $commissions->sum('commission_amount');
        $pdf = Pdf::loadView('admin.reports-sales-pdf', compact('commissions', 'totalAmount', 'totalCommission'))
            ->setPaper('a4', 'portrait');
        return $pdf->download('sales_report_'.now()->format('Ymd').'.pdf');
    }

    public function exportCommissionReport()
    {
        $commissions = Commission::with('seller')->latest()->get();
        $csv = "Seller,Total Orders,Total Commission,Date\n";
        $grouped = $commissions->groupBy('seller_id');
        foreach ($grouped as $sellerId => $items) {
            $seller = $items->first()->seller;
            $name   = $seller ? $seller->given_names.' '.$seller->last_name : 'Unknown';
            $csv .= implode(',', [
                $name,
                $items->count(),
                number_format($items->sum('commission_amount'), 2),
                now()->format('Y-m-d'),
            ])."\n";
        }
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="commission_report_'.now()->format('Ymd').'.csv"',
        ]);
    }

    public function settings()
    {
        $counts   = $this->sidebarCounts();
        $policies = Policy::latest()->get();
        return view('admin.settings', array_merge($counts, compact('policies')));
    }

    public function announcements()
    {
        $counts        = $this->sidebarCounts();
        $announcements = Announcement::latest()->get();

        $total  = $announcements->count();
        $active = $announcements->where('is_active', true)->count();
        $byAudience = $announcements->groupBy('audience')->map->count();
        $latest = $announcements->first();

        return view('admin.announcements', array_merge($counts, compact(
            'announcements', 'total', 'active', 'byAudience', 'latest'
        )));
    }

    public function storeAnnouncement(Request $request)
    {
        $request->validate([
            'title'    => 'required|string|max:255',
            'body'     => 'required|string',
            'audience' => 'required|in:all,buyer,seller,rider',
        ]);
        Announcement::create([
            'title'      => $request->title,
            'body'       => $request->body,
            'audience'   => $request->audience,
            'is_active'  => true,
            'created_by' => auth()->id(),
        ]);
        return back()->with('success', 'Announcement posted.');
    }

    public function destroyAnnouncement($id)
    {
        Announcement::findOrFail($id)->delete();
        return back()->with('success', 'Announcement deleted.');
    }

    public function storePolicy(Request $request)
    {
        $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
            'slug'    => 'required|string|unique:policies,slug',
        ]);
        Policy::create([
            'title'      => $request->title,
            'content'    => $request->content,
            'slug'       => $request->slug,
            'updated_by' => auth()->id(),
        ]);
        return back()->with('success', 'Policy saved.');
    }

    public function updatePolicy(Request $request, Policy $policy)
    {
        $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
        ]);
        $policy->update([
            'title'      => $request->title,
            'content'    => $request->content,
            'updated_by' => auth()->id(),
        ]);
        return back()->with('success', 'Policy updated.');
    }

    public function destroyPolicy(Policy $policy)
    {
        $policy->delete();
        return back()->with('success', 'Policy deleted.');
    }

    public function messages(Request $request, ?User $user = null)
    {
        $counts = $this->sidebarCounts();
        $users = User::where('is_admin', false)
            ->orderBy('given_names')
            ->orderBy('last_name')
            ->get();

        // Enrich each user with their latest message + unread count against this admin,
        // so the inbox reads like a real conversation list, not a bare directory.
        $adminId = auth()->id();
        $threads = Message::where('sender_id', $adminId)->orWhere('receiver_id', $adminId)
            ->latest('created_at')->get()
            ->groupBy(fn ($m) => $m->sender_id === $adminId ? $m->receiver_id : $m->sender_id);

        $users = $users->map(function ($u) use ($threads, $adminId) {
            $thread = $threads->get($u->id);
            $u->last_message  = $thread?->first();
            $u->unread_count  = $thread ? $thread->where('receiver_id', $adminId)->where('read', false)->count() : 0;
            return $u;
        })->sortByDesc(fn ($u) => $u->last_message?->created_at ?? \Carbon\Carbon::createFromTimestamp(0))->values();

        $selectedUser = $user && ! $user->is_admin ? $user : $users->first();
        $messages = collect();

        if ($selectedUser) {
            Message::where('sender_id', $selectedUser->id)
                ->where('receiver_id', auth()->id())
                ->where('read', false)
                ->update(['read' => true]);

            $messages = Message::with(['sender', 'receiver'])
                ->where(function ($query) use ($selectedUser) {
                    $query->where('sender_id', auth()->id())
                        ->where('receiver_id', $selectedUser->id);
                })
                ->orWhere(function ($query) use ($selectedUser) {
                    $query->where('sender_id', $selectedUser->id)
                        ->where('receiver_id', auth()->id());
                })
                ->oldest()
                ->get();
        }

        return view('admin.messages', array_merge($counts, compact('users', 'selectedUser', 'messages')));
    }

    public function sendMessage(Request $request, User $user)
    {
        abort_if($user->is_admin, 404);

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        Message::create([
            'sender_id'   => auth()->id(),
            'receiver_id' => $user->id,
            'body'        => $request->body,
            'read'        => false,
        ]);

        return redirect()->route('admin.messages.user', $user)->with('success', 'Message sent.');
    }

    public function account()
    {
        $counts = $this->sidebarCounts();
        return view('admin.account', $counts);
    }

    public function accountUpdate(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'given_names'  => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_no'   => 'required|string|max:11',
            'province'     => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay'     => 'required|string|max:255',
            'house_no'     => 'nullable|string|max:255',
            'street'       => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = $request->only([
            'given_names',
            'last_name',
            'email',
            'contact_no',
            'province',
            'municipality',
            'barangay',
            'house_no',
            'street',
        ]);

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $data['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'current_password'      => 'required',
            'password'              => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        auth()->user()->update(['password' => Hash::make($request->password)]);
        return back()->with('success', 'Password updated.');
    }

    public function docRequests()
    {
        $counts   = $this->sidebarCounts();
        $requests = DocumentUpdateRequest::with('user')->latest()->get();
        $idTypes  = \DB::table('id_types')->orderBy('id')->get()->keyBy('id');
        return view('admin.doc-requests', array_merge($counts, compact('requests', 'idTypes')));
    }

    public function approveDocRequest($id)
    {
        $req = DocumentUpdateRequest::findOrFail($id);
        $req->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

        // Apply changes to user
        $data = array_filter([
            'id_type_id'           => $req->id_type_id,
            'id_file'              => $req->id_file,
            'business_permit_file' => $req->business_permit_file,
        ], fn($v) => !is_null($v));
        $req->user->update($data);

        // Notify seller
        \DB::table('notifications')->insert([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'user_id'           => $req->user_id,
            'title'             => 'Document Update Approved',
            'message'           => 'Your document update request has been approved and your account has been updated.',
            'notification_type' => 'doc_approved',
            'is_read'           => false,
            'created_at'        => now(),
        ]);

        return back()->with('success', 'Request approved and seller notified.');
    }

    public function rejectDocRequest(Request $request, $id)
    {
        $req = DocumentUpdateRequest::findOrFail($id);
        $req->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'note'        => $request->input('note'),
        ]);

        // Notify seller
        \DB::table('notifications')->insert([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'user_id'           => $req->user_id,
            'title'             => 'Document Update Rejected',
            'message'           => 'Your document update request was rejected.' . ($request->note ? ' Reason: ' . $request->note : ''),
            'notification_type' => 'doc_rejected',
            'is_read'           => false,
            'created_at'        => now(),
        ]);

        return back()->with('success', 'Request rejected and seller notified.');
    }

    public function products()
    {
        $counts     = $this->sidebarCounts();
        $products   = Product::with(['seller.categories', 'category', 'images'])->latest()->get();
        $categories = \DB::table('categories')->orderBy('name')->get()->keyBy('id');
        return view('admin.products', array_merge($counts, compact('products', 'categories')));
    }

    public function approveProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'active', 'rejection_note' => null]);

        \DB::table('notifications')->insert([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'user_id'           => $product->seller_id,
            'title'             => 'Product Approved',
            'message'           => 'Your product "' . $product->name . '" has been approved and is now live.',
            'notification_type' => 'product_approved',
            'is_read'           => false,
            'created_at'        => now(),
        ]);

        return back()->with('success', 'Product approved.');
    }

    public function rejectProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update([
            'status'         => 'rejected',
            'rejection_note' => $request->input('note'),
        ]);

        \DB::table('notifications')->insert([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'user_id'           => $product->seller_id,
            'title'             => 'Product Rejected',
            'message'           => 'Your product "' . $product->name . '" was rejected.' . ($request->note ? ' Reason: ' . $request->note : ''),
            'notification_type' => 'product_rejected',
            'is_read'           => false,
            'created_at'        => now(),
        ]);

        return back()->with('success', 'Product rejected and seller notified.');
    }
}
