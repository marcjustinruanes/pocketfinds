<?php

namespace App\Http\Controllers;

use App\Models\DocumentUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SellerController extends Controller
{
    public function dashboard()    { return view('seller.dashboard'); }
    public function orders()       { return view('seller.orders'); }
    public function inventory()    { return view('seller.inventory'); }
    public function prepare()      { return view('seller.prepare'); }
    public function shipments()    { return view('seller.shipments'); }
    public function deliveries()   { return view('seller.deliveries'); }
    public function feedback()     { return view('seller.feedback'); }
    public function reports()      { return view('seller.reports'); }
    public function messages()     { return view('seller.messages'); }

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
            ->whereIn('status', ['pending'])
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

        // Cancel any existing pending request
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
