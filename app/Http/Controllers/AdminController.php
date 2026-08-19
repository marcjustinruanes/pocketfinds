<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required',
        ]);

        $login    = $request->input('email');
        $field    = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'email';
        $credentials = [$field => $login, 'password' => $request->input('password')];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = auth()->user();
            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            }
            if ($user->account_type === 'buyer') {
                return redirect()->route('buyer.dashboard');
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

        return view('admin.reports', array_merge($counts, compact(
            'totalUsers', 'buyerCount', 'sellerCount', 'riderCount',
            'pending', 'approved', 'rejected'
        )));
    }

    public function settings()
    {
        $counts = $this->sidebarCounts();
        return view('admin.settings', $counts);
    }

    public function messages()
    {
        $counts = $this->sidebarCounts();
        $users  = User::where('is_admin', false)->latest()->take(20)->get();
        return view('admin.messages', array_merge($counts, compact('users')));
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
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
        ]);
        $user->update($request->only('first_name', 'last_name'));
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
