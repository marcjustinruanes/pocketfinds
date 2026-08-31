<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class RegisterController extends Controller
{
    /** Shown right after picking a role — choose Google or manual sign-up before the step-by-step form appears. */
    public function method(Request $request)
    {
        $type = in_array($request->query('type'), ['buyer', 'seller', 'rider'], true) ? $request->query('type') : 'buyer';
        return view('auth.register-method', ['type' => $type]);
    }

    public function sendOtp(Request $request)
    {
        $email = $request->input('email', '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Enter a valid email address.']);
        }

        if (!preg_match('/@gmail\.com$/i', $email)) {
            return response()->json(['success' => false, 'message' => 'Only Gmail addresses are accepted.']);
        }

        if (\App\Models\User::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => 'This email is already registered. Try signing in instead.']);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = 'otp_' . md5($email);

        Cache::put($key, $otp, now()->addMinutes(10));

        try {
            Mail::raw(
                "Your PocketFinds verification code is: {$otp}\n\nThis code expires in 10 minutes.",
                fn ($m) => $m->to($email)->subject('PocketFinds — Email Verification Code')
            );
        } catch (\Exception $e) {
            Cache::forget($key);
            return response()->json(['success' => false, 'message' => 'Could not send the code. Check the email address and try again.']);
        }

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

    public function checkUsername(Request $request)
    {
        $username = trim($request->input('username', ''));
        if (mb_strlen($username) < 8) {
            return response()->json([
                'available' => false,
                'suggestions' => [],
                'message' => 'Username must be at least 8 characters.',
            ]);
        }
        $exists   = User::where('username', $username)->exists();

        $suggestions = [];
        if ($exists) {
            $base = preg_replace('/\d+$/', '', $username);
            for ($i = 1; count($suggestions) < 3; $i++) {
                $candidate = $base . $i;
                if (!User::where('username', $candidate)->exists()) {
                    $suggestions[] = $candidate;
                }
            }
        }

        return response()->json(['available' => !$exists, 'suggestions' => $suggestions]);
    }

    public function checkBusinessName(Request $request)
    {
        $businessName = trim($request->input('business_name', ''));
        if (mb_strlen($businessName) < 2) {
            return response()->json(['available' => false, 'message' => 'Enter a business name.']);
        }

        $exists = User::whereRaw('LOWER(business_name) = ?', [mb_strtolower($businessName)])->exists();
        return response()->json(['available' => !$exists]);
    }

    public function categories()
    {
        return response()->json(Category::orderBy('name')->get(['id', 'name']));
    }

    public function store(Request $request)
    {
        $isSeller = $request->input('account_type') === 'seller';
        $isRider  = $request->input('account_type') === 'rider';

        $rules = [
            'last_name'    => 'required|string|max:100',
            'given_names'  => 'required|string|max:100',
            'middle_name'  => 'nullable|string|max:50',
            'sex'            => 'required|in:male,female',
            'birthday'       => 'required|date|before_or_equal:' . now()->subYears(16)->toDateString(),
            'age'            => 'sometimes|integer|min:0',
            'email'          => 'required|email|unique:users,email|regex:/@gmail\.com$/i',
            'contact_no'     => 'required|regex:/^09\d{9}$/',
            'province'       => 'required|string',
            'municipality'   => 'required|string',
            'barangay'       => 'required|string',
            'house_no'       => 'nullable|string|max:50',
            'street'         => 'nullable|string|max:100',
            'username'       => 'required|string|min:8|max:30|alpha_dash|unique:users,username',
            // Google sign-ups still set a password, so the account can also
            // be logged into with a username/password later without Google.
            'password'       => 'required|string|min:8|confirmed',
            'id_file'        => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'id_type_id'     => 'required|integer|exists:id_types,id',
            'selfie_file'    => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'account_type'   => 'required|in:buyer,rider,seller',
            'auth_method'    => 'required|in:manual,google',
            'google_id'      => 'nullable|string',
            'category_ids'   => $isSeller ? 'array|max:1' : 'sometimes|nullable',
            'category_ids.*' => 'integer|exists:categories,id',
            'category_other' => 'nullable|string|max:100',
            'business_name'  => $isSeller ? 'required|string|max:150|unique:users,business_name' : 'sometimes|nullable',
            'business_permit_file' => $isSeller ? 'required|file|mimes:jpg,jpeg,png,pdf|max:5120' : 'sometimes|nullable',
        ];

        if ($isRider) {
            $rules['vehicle_type']  = 'required|in:motorcycle,bicycle,car_van';
            $rules['vehicle_brand'] = 'required|string|max:100';
            $rules['vehicle_model'] = 'required|string|max:100';
            $motorRules = $request->input('vehicle_type') !== 'bicycle';
            if ($motorRules) {
                $rules['plate_number'] = 'required|string|max:20';
                $rules['or_file']      = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
                $rules['cr_file']      = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
                $rules['license_number'] = 'required|string|max:50';
                $rules['license_expiry'] = 'required|date';
                $rules['license_file']   = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
            }
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, [
            'birthday.before_or_equal' => 'You must be at least 16 years old to register.',
        ]);

        $validator->after(function ($validator) use ($request, $isSeller) {
            // Same-person check: block a second account under the same full name.
            if ($request->filled('given_names') && $request->filled('last_name')) {
                $duplicate = User::whereRaw('LOWER(given_names) = ?', [mb_strtolower(trim($request->given_names))])
                    ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($request->last_name))])
                    ->when($request->filled('middle_name'), fn ($q) => $q->whereRaw('LOWER(COALESCE(middle_name, \'\')) = ?', [mb_strtolower(trim($request->middle_name))]))
                    ->exists();
                if ($duplicate) {
                    $validator->errors()->add('given_names', 'An account under this name is already registered.');
                }
            }

            if ($isSeller) {
                // Exactly one category is stored per seller — either a pick
                // from the list or a typed "Other" category, never both.
                $categoryCount = count($request->input('category_ids', [])) + ($request->filled('category_other') ? 1 : 0);
                if ($categoryCount !== 1) {
                    $validator->errors()->add('category_ids', 'Please select exactly one category.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $idPath     = $request->file('id_file')->store('id_files', 'supabase');
        $selfiePath = $request->file('selfie_file')->store('selfie_files', 'supabase');

        $userData = [
            'account_type'   => $request->account_type,
            'auth_method'    => $request->auth_method,
            'google_id'      => $request->google_id,
            'username'       => $request->username,
            'last_name'    => $request->last_name,
            'given_names'  => $request->given_names,
            'middle_name'  => $request->middle_name,
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
            'password'       => $request->password,
            'id_file'        => $idPath,
            'id_type_id'     => $request->id_type_id,
            'selfie_file'    => $selfiePath,
            'status'         => 'pending',
            'category_id'    => $isSeller ? ($request->input('category_ids')[0] ?? null) : null,
            'category_other' => $isSeller ? $request->category_other : null,
            'business_name'  => $isSeller ? $request->business_name : null,
        ];

        if ($isSeller) {
            $userData['business_permit_file'] = $request->file('business_permit_file')->store('business_permits', 'supabase');
        }

        if ($isRider) {
            $userData['vehicle_type']  = $request->vehicle_type;
            $userData['vehicle_brand'] = $request->vehicle_brand;
            $userData['vehicle_model'] = $request->vehicle_model;
            $userData['plate_number']  = $request->plate_number;

            if ($request->hasFile('or_file')) {
                $userData['or_file'] = $request->file('or_file')->store('vehicle_docs', 'supabase');
            }
            if ($request->hasFile('cr_file')) {
                $userData['cr_file'] = $request->file('cr_file')->store('vehicle_docs', 'supabase');
            }
            if ($request->hasFile('license_file')) {
                $userData['license_number'] = $request->license_number;
                $userData['license_expiry'] = $request->license_expiry;
                $userData['license_file']   = $request->file('license_file')->store('license_files', 'supabase');
            }
        }

        User::create($userData);

        return response()->json(['success' => true]);
    }
}
