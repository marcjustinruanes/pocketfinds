<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class RegisterController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|regex:/@gmail\.com$/i|unique:users,email',
        ]);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = 'otp_' . md5($request->email);

        Cache::put($key, $otp, now()->addMinutes(10));

        Mail::raw(
            "Your PocketFinds verification code is: {$otp}\n\nThis code expires in 10 minutes.",
            fn ($m) => $m->to($request->email)->subject('PocketFinds — Email Verification Code')
        );

        return response()->json(['success' => true]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $key    = 'otp_' . md5($request->email);
        $stored = Cache::get($key);

        if (!$stored || $stored !== $request->otp) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired code.']);
        }

        Cache::forget($key);
        return response()->json(['success' => true]);
    }

    public function categories()
    {
        return response()->json(Category::orderBy('name')->get(['id', 'name']));
    }

    public function store(Request $request)
    {
        $isSeller = $request->input('account_type') === 'seller';

        $request->validate([
            'last_name'      => 'required|string|max:100',
            'first_name'     => 'required|string|max:100',
            'middle_initial' => 'nullable|alpha|max:1',
            'sex'            => 'required|in:male,female',
            'birthday'       => 'required|date',
            'age'            => 'required|integer|min:0',
            'email'          => 'required|email|unique:users,email|regex:/@gmail\.com$/i',
            'contact_no'     => 'required|regex:/^09\d{9}$/',
            'province'       => 'required|string',
            'municipality'   => 'required|string',
            'barangay'       => 'required|string',
            'house_no'       => 'nullable|string|max:50',
            'street'         => 'nullable|string|max:100',
            'password'       => 'nullable|string|min:8|confirmed',
            'id_file'        => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'account_type'   => 'required|in:buyer,rider,seller',
            'auth_method'    => 'required|in:manual,google',
            'category_id'    => $isSeller ? 'required|exists:categories,id' : 'nullable',
        ]);

        $idPath = $request->file('id_file')->store('id_files', 'public');

        User::create([
            'account_type'   => $request->account_type,
            'auth_method'    => $request->auth_method,
            'google_id'      => $request->google_id,
            'last_name'      => $request->last_name,
            'first_name'     => $request->first_name,
            'middle_initial' => $request->middle_initial,
            'sex'            => $request->sex,
            'birthday'       => $request->birthday,
            'age'            => $request->age,
            'email'          => $request->email,
            'contact_no'     => $request->contact_no,
            'province'       => $request->province,
            'municipality'   => $request->municipality,
            'barangay'       => $request->barangay,
            'house_no'       => $request->house_no,
            'street'         => $request->street,
            'password'       => $request->auth_method === 'manual' ? Hash::make($request->password) : null,
            'id_file'        => $idPath,
            'status'         => 'pending',
            'category_id'    => $isSeller ? $request->category_id : null,
        ]);

        return response()->json(['success' => true]);
    }
}
