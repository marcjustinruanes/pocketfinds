<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\AccountVerification;
use App\Services\MediaLifecycle;
use App\Support\AccountInput;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountRegistrationController extends Controller
{
    public function form(Request $request)
    {
        $role = $request->route('role');
        abort_unless(in_array($role, ['buyer', 'seller', 'logistics'], true), 404);

        return view('auth.registration', ['role' => $role, 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request, AccountVerification $verification)
    {
        // Hub staff keep their existing application and interview flow.
        if ($request->input('account_type') === 'logistics' && $request->input('logistics_mode') === 'join') {
            return app(RegisterController::class)->store($request);
        }
        abort_if($request->input('account_type') === 'rider', 403, 'Rider accounts are created by their logistics company.');
        $email = is_string($request->input('email')) ? mb_strtolower(trim($request->email)) : $request->input('email');
        $request->merge(['email' => $email, 'contact_no' => AccountInput::phone($request->input('contact_no'))]);
        $existing = is_string($email) ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null;
        $returning = $existing && $existing->status === 'rejected' && $existing->account_type === $request->account_type
            && is_string($request->input('password')) && Hash::check($request->password, $existing->password ?? '');
        $ignore = $returning ? $existing->id : null;
        $role = $request->input('account_type');
        $business = in_array($role, ['seller', 'logistics'], true);
        $document = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        $rules = [
            'account_type' => ['required', Rule::in(['buyer', 'seller', 'logistics'])],
            'username' => ['sometimes', 'string', 'min:8', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($ignore)],
            'given_names' => AccountInput::name(), 'last_name' => AccountInput::name(),
            'email' => ['required', 'email', 'max:255', 'regex:/\A[^@\s]+@[^@\s.]+(?:\.[^@\s.]+)+\z/u', function ($attribute, $value, $fail) use ($ignore) {
                if (User::whereRaw('LOWER(email) = ?', [mb_strtolower($value)])->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
                    $fail('This email is already registered.');
                }
            }],
            'contact_no' => ['required', 'regex:/\A09[0-9]{9}\z/', Rule::unique('users', 'contact_no')->ignore($ignore)],
            'birthday' => [$role === 'buyer' ? 'required' : 'nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'password' => AccountInput::password(),
            'terms' => ['accepted'],
        ];
        if ($business) {
            $rules += [
                'business_name' => ['required', 'string', 'min:3', 'max:'.($role === 'seller' ? 30 : 150), function ($attribute, $value, $fail) use ($ignore, $role) {
                    if (User::where('account_type', $role)->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($value))])
                        ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
                        $fail('This name is already registered.');
                    }
                }],
                'tin' => ['required', 'regex:/\A[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}\z/', Rule::unique('users', 'tin')->ignore($ignore)],
                'region' => ['required', 'string', 'max:255'], 'province' => ['required', 'string', 'max:255'],
                'municipality' => ['required', 'string', 'max:255'], 'barangay' => ['required', 'string', 'max:255'],
                'street' => ['required', 'string', 'max:255'], 'postal_code' => ['required', 'regex:/\A[0-9]{4}\z/'],
                'id_file' => array_merge(['required'], $document),
            ];
        }
        if ($role === 'seller') {
            $rules += ['category_id' => ['required', 'integer', 'exists:categories,id'],
                'seller_type' => ['required', Rule::in(['individual', 'sole_proprietorship', 'corporation'])],
                'dti_file' => array_merge(['required_if:seller_type,sole_proprietorship', 'nullable'], $document),
                'sec_file' => array_merge(['required_if:seller_type,corporation', 'nullable'], $document),
                'bir_file' => array_merge(['required_if:seller_type,sole_proprietorship,corporation', 'nullable'], $document)];
        }
        if ($role === 'logistics') {
            $rules += ['sec_registration_no' => ['required', 'regex:/\A[A-Za-z]{2}[0-9]{9}\z/', Rule::unique('users', 'sec_registration_no')->ignore($ignore)],
                'position' => ['required', 'string', 'max:255'], 'service_areas' => ['required', 'array', 'min:1'],
                'service_areas.*' => ['required', 'string', 'distinct', 'max:255'],
                'sec_file' => array_merge(['required'], $document), 'bir_file' => array_merge(['required'], $document),
                'business_permit_file' => array_merge(['required'], $document)];
        }
        $data = $request->validate($rules, [
            'given_names.regex' => 'Names can contain only letters, spaces, periods, apostrophes and hyphens.',
            'last_name.regex' => 'Names can contain only letters, spaces, periods, apostrophes and hyphens.',
        ]);
        try {
            $user = DB::transaction(function () use ($request, $data, $returning, $existing, $role, $verification) {
                $user = $returning ? User::whereKey($existing->id)->lockForUpdate()->firstOrFail() : new User;
                if ($returning && $user->status !== 'rejected') {
                    throw ValidationException::withMessages(['email' => 'This application changed. Refresh before resubmitting.']);
                }
                if ($returning) {
                    DB::table('hiring_application_history')->insert(['user_id' => $user->id, 'attempt_id' => (string) Str::uuid(),
                        'company_name' => $user->business_name ?? '', 'hub_id' => $user->logistics_hub_id, 'role' => $user->account_type,
                        'status' => 'rejected', 'reason' => $user->status_reason, 'occurred_at' => $user->updated_at ?? now(), 'recorded_at' => now(), 'legacy_snapshot' => true]);
                }
                $values = collect($data)->except(['password_confirmation', 'terms'])->all();
                foreach (['id_file', 'dti_file', 'sec_file', 'bir_file', 'business_permit_file'] as $field) {
                    unset($values[$field]);
                    if ($request->hasFile($field)) {
                        $values[$field] = app(MediaLifecycle::class)->store($request->file($field), 'registration', 'public');
                    }
                }
                $values += ['username' => $user->username ?: 'pf_'.Str::lower(Str::random(20)),
                    'province' => '', 'municipality' => '', 'barangay' => '', 'status' => 'unverified',
                    'is_logistics' => $role === 'logistics', 'logistics_role' => $role === 'logistics' ? 'admin' : null];
                $values['age'] = ! empty($data['birthday']) ? Carbon::parse($data['birthday'])->age : null;
                $values['status_reason'] = null;
                $values['email_verified_at'] = null;
                if ($request->session()->get('google_id') && strcasecmp((string) $request->session()->get('google_email'), $data['email']) === 0) {
                    $values['auth_method'] = 'google';
                    $values['google_id'] = $request->session()->get('google_id');
                }
                $user->fill($values)->save();
                if ($role === 'logistics') {
                    LogisticsHub::firstOrCreate(['company_name' => $user->business_name, 'province' => $user->province, 'municipality' => $user->municipality],
                        ['is_operational' => true, 'is_regional_hub' => true]);
                }
                $verification->send($user);

                return $user;
            });
        } catch (UniqueConstraintViolationException $error) {
            throw ValidationException::withMessages(['email' => 'An account with these details already exists.']);
        }
        $request->session()->put('registration_user_id', $user->id);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'redirect' => route('registration.verify')])
            : redirect()->route('registration.verify')->with('success', 'Account saved. Check your email for a verification code.');
    }

    public function verificationPage(Request $request)
    {
        $user = User::find($request->session()->get('registration_user_id'));
        abort_unless($user && $user->status === 'unverified', 403);

        return view('auth.verify-registration', ['email' => $user->email]);
    }

    public function resend(Request $request, AccountVerification $verification)
    {
        $user = User::find($request->session()->get('registration_user_id'));
        abort_unless($user && $user->status === 'unverified', 403);
        $verification->send($user);

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('success', 'A new verification code has been sent.');
    }

    public function verify(Request $request, AccountVerification $verification)
    {
        $data = $request->validate(['otp' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $user = User::find($request->session()->get('registration_user_id'));
        abort_unless($user, 403);
        if (! $verification->verify($user, $data['otp'])) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired verification code.']);
        }
        $request->session()->forget('registration_user_id');

        return $request->expectsJson()
            ? response()->json(['success' => true, 'status' => $user->fresh()->status])
            : redirect()->route('login')->with('success', $user->account_type === 'buyer'
                ? 'Email verified. You can now sign in.' : 'Email verified. Your application is pending admin approval.');
    }
}
