<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Commission;
use App\Models\Complaint;
use App\Models\Message;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function login()
    {
        if (auth()->check() && auth()->user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return view('auth.login');
    }

    public function loginPost(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            if (auth()->user()->is_admin) {
                return redirect()->route('admin.dashboard');
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
        $user->update(['status' => 'rejected']);
        return back();
    }

    public function dashboard()
    {
        $counts     = $this->sidebarCounts();
        $totalUsers = User::where('is_admin', false)->count();
        $pendingCount = $counts['pendingRegistrations'];
        $recentUsers  = User::where('is_admin', false)->latest()->take(5)->get();

        return view('admin.dashboard', array_merge($counts, compact(
            'totalUsers', 'pendingCount', 'recentUsers'
        )));
    }

    public function registrations()
    {
        $counts = $this->sidebarCounts();
        $users  = User::where('is_admin', false)->latest()->get();
        return view('admin.registrations', array_merge($counts, compact('users')));
    }

    public function users()
    {
        $counts = $this->sidebarCounts();
        $users  = User::where('is_admin', false)->latest()->get();
        return view('admin.users', array_merge($counts, compact('users')));
    }

    public function compliance()
    {
        $counts  = $this->sidebarCounts();
        $sellers = User::where('account_type', 'seller')->where('is_admin', false)->latest()->get();
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
            $seller = $c->seller ? $c->seller->first_name.' '.$c->seller->last_name : 'Unknown';
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

    public function exportCommissionReport()
    {
        $commissions = Commission::with('seller')->latest()->get();
        $csv = "Seller,Total Orders,Total Commission,Date\n";
        $grouped = $commissions->groupBy('seller_id');
        foreach ($grouped as $sellerId => $items) {
            $seller = $items->first()->seller;
            $name   = $seller ? $seller->first_name.' '.$seller->last_name : 'Unknown';
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
        $counts        = $this->sidebarCounts();
        $announcements = Announcement::latest()->get();
        $policies      = Policy::latest()->get();
        return view('admin.settings', array_merge($counts, compact('announcements', 'policies')));
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
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

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
            'first_name'   => 'required|string|max:255',
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
            'first_name',
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
}
