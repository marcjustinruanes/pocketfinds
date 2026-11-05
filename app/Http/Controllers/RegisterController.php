<?php

namespace App\Http\Controllers;

use App\Enums\Suffix;
use App\Models\Category;
use App\Models\CompanyVehicle;
use App\Models\LogisticsCompany;
use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\HubHiring;
use App\Services\MediaLifecycle;
use App\Support\PhilippineMobile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /** Legacy entry point after picking a role — choose Google or manual sign-up before the step-by-step form appears. */
    public function method(Request $request)
    {
        $type = in_array($request->query('type'), ['buyer', 'seller', 'rider', 'logistics', 'logistics-staff'], true) ? $request->query('type') : 'buyer';

        return redirect()->route('register.'.$type);
    }

    public function postalCode(Request $request)
    {
        $data = $request->validate(['municipality_code'=>['required','regex:/^\d{9,10}$/D'], 'municipality'=>['required','string','max:255']]);
        $postal = app(\App\Services\RegistrationPostalCode::class)->lookup($data['municipality_code'], $data['municipality']);
        return response()->json(['postal_code'=>$postal, 'status'=>$postal ? 'found' : 'unavailable']);
    }

    public function checkIdentity(Request $request)
    {
        $request->validate([
            'given_names'=>['required','string','max:50'], 'last_name'=>['required','string','max:50'],
            'middle_name'=>['nullable','string','max:50'], 'no_middle_name'=>['nullable','boolean'],
            'suffix'=>['nullable',new Enum(Suffix::class)], 'birthday'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'sex'=>['required','string','max:20'],
        ]);
        return response()->json(['duplicate'=>$this->identityExists($request)]);
    }

    private function identityExists(Request $request): bool
    {
        if (!$request->filled('given_names') || !$request->filled('last_name') || !$request->filled('birthday') || !$request->filled('sex')) return false;
        return User::whereRaw('LOWER(given_names) = ?', [mb_strtolower(trim($request->given_names))])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($request->last_name))])
            ->whereRaw('LOWER(COALESCE(middle_name, \'\')) = ?', [$request->boolean('no_middle_name') ? '' : mb_strtolower(trim((string)$request->middle_name))])
            ->whereRaw('LOWER(COALESCE(suffix, \'\')) = ?', [mb_strtolower(trim((string)$request->suffix))])
            ->where('birthday', $request->birthday)->where('sex', $request->sex)
            ->where('status', '!=', 'rejected')->exists();
    }

    public function sendOtp(Request $request)
    {
        $email = $request->input('email', '');

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Enter a valid email address.']);
        }

        if (! preg_match('/@gmail\.com$/i', $email)) {
            return response()->json(['success' => false, 'message' => 'Only Gmail addresses are accepted.']);
        }

        if (User::where('email', $email)->where('status', '!=', 'rejected')->exists()) {
            return response()->json(['success' => false, 'message' => 'This email is already registered. Try signing in instead.']);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = ($request->input('purpose') === 'representative' ? 'representative_otp_' : 'otp_').md5($email);

        Cache::put($key, $otp, now()->addMinutes(5));

        try {
            Mail::raw(
                "Your PocketFinds verification code is: {$otp}\n\nThis code expires in 5 minutes.",
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
            'otp' => 'required|string|size:6',
        ]);

        $key = ($request->input('purpose') === 'representative' ? 'representative_otp_' : 'otp_').md5($request->email);
        $stored = Cache::get($key);

        if (! $stored || $stored !== $request->otp) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired code.']);
        }

        Cache::forget($key);
        $proofKey=$request->input('purpose')==='representative' ? 'registration_verified_representative_email' : 'registration_verified_email';
        $request->session()->put($proofKey, ['email' => strtolower($request->email), 'expires_at' => now()->addHour()->timestamp]);

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
        $exists = User::where('username', $username)->where('status', '!=', 'rejected')->exists();

        $suggestions = [];
        if ($exists) {
            $base = preg_replace('/\d+$/', '', $username);
            for ($i = 1; count($suggestions) < 3; $i++) {
                $candidate = $base.$i;
                if (! User::where('username', $candidate)->where('status', '!=', 'rejected')->exists()) {
                    $suggestions[] = $candidate;
                }
            }
        }

        return response()->json(['available' => ! $exists, 'suggestions' => $suggestions]);
    }

    public function checkBusinessName(Request $request)
    {
        $businessName = trim($request->input('business_name', ''));
        if (mb_strlen($businessName) < 2) {
            return response()->json(['available' => false, 'message' => 'Enter a business name.']);
        }

        $owners=User::whereRaw('LOWER(TRIM(business_name)) = ?',[mb_strtolower($businessName)])->where('account_type','logistics')->where('is_logistics',true)->where(fn($q)=>$q->where('logistics_role','admin')->orWhereNull('logistics_role'))->get();
        $exists=User::whereRaw('LOWER(TRIM(business_name)) = ?',[mb_strtolower($businessName)])->where('status','!=','rejected')->exists() || $owners->isNotEmpty();
        $reapply=$request->input('account_type')==='logistics' && $owners->count()===1 && $owners->first()->status==='rejected';
        return response()->json(['available'=>!$exists,'reapplication_only'=>$reapply]);
    }

    public function checkBusinessNumber(Request $request)
    {
        $data=$request->validate(['field'=>['required',Rule::in(['tin','sec_registration_no'])],'value'=>['required','string','max:32']]);
        $field=$data['field'];
        $value=$field==='tin' ? preg_replace('/[-\s]/u','',$data['value']) : strtoupper(trim($data['value']));
        if (!preg_match($field==='tin'?'/^\d{12}$/D':'/^[A-Z]{2}\d{9}$/D',$value)) {
            return response()->json(['available'=>false,'valid'=>false]);
        }
        $matches=User::whereRaw($field==='tin'?"REPLACE(REPLACE(tin, '-', ''), ' ', '') = ?":'UPPER(TRIM(sec_registration_no)) = ?',[$value])->get(['status','can_resubmit']);
        $reserved=$matches->isNotEmpty()&&$matches->every(fn($user)=>$user->status==='rejected'&&$user->can_resubmit);
        return response()->json(['available'=>$matches->isEmpty(),'valid'=>true,'reapplication_only'=>$reserved]);
    }

    /** A driver's license belongs to one person — block a second rider account reusing it. */
    public function checkLicenseNumber(Request $request)
    {
        $request->validate(['license_number'=>['required','string','max:32']]);
        $licenseNumber = \App\Support\DriverLicenseNumber::normalize($request->input('license_number'));
        if (!preg_match('/^[A-Z]\d{2}-\d{2}-\d{6}$/D', $licenseNumber)) {
            return response()->json(['available' => false, 'valid'=>false, 'message' => 'Use the format A12-34-123456.']);
        }

        $exists = \App\Support\DriverLicenseNumber::exists($licenseNumber);

        return response()->json(['available' => ! $exists, 'valid'=>true]);
    }

    /** A plate number belongs to one vehicle — block a second rider account reusing it. */
    public function checkPlateNumber(Request $request)
    {
        $plateNumber = trim($request->input('plate_number', ''));
        if (mb_strlen($plateNumber) < 2) {
            return response()->json(['available' => false, 'message' => 'Enter a valid plate number.']);
        }

        $exists = User::whereRaw('LOWER(plate_number) = ?', [mb_strtolower($plateNumber)])->where('status', '!=', 'rejected')->exists();

        return response()->json(['available' => ! $exists]);
    }

    public function categories()
    {
        return response()->json(Category::orderBy('name')->get(['id', 'name'])->map(fn ($category) => [
            'id'=>$category->id, 'name'=>$category->name,
            'icon_html'=>view('guest.partials.category-filter-icon', ['category'=>$category])->render(),
        ]));
    }

    /** Store registration documents on the configured local disk. */
    private function storeWithRetry(UploadedFile $file, string $path, string $disk = 'public', int $attempts = 3): string
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return app(MediaLifecycle::class)->store($file, $path, $disk);
            } catch (\Throwable $e) {
                if ($attempt === $attempts) {
                    throw $e;
                }
                usleep(400000); // brief pause before retrying — most DNS/connection blips clear within a second
            }
        }
    }

    public function store(Request $request)
    {
        if (is_string($request->input('tin'))) {
            $request->merge(['tin' => preg_replace('/[-\s]/u', '', $request->input('tin'))]);
        }
        if (is_string($request->input('contact_no'))) {
            $request->merge(['contact_no' => PhilippineMobile::normalize($request->input('contact_no'))]);
        }
        $minimumAge = in_array($request->input('account_type'), ['buyer', 'rider'], true) ? 18 : 16;
        $isSeller = $request->input('account_type') === 'seller';
        $isRider = $request->input('account_type') === 'rider';
        $isLogistics = $request->input('account_type') === 'logistics';
        // A logistics registration either founds a brand new company (the default,
        // unchanged flow) or joins one that already exists as hub staff — same
        // account_type either way (is_logistics is the real gate), distinguished only
        // by this one internal field so nothing else in the app needs to know about it.
        $isLogisticsJoin = $isLogistics && $request->input('logistics_mode') === 'join';
        $isLogisticsFound = $isLogistics && ! $isLogisticsJoin;
        if($isLogisticsFound && is_string($request->input('business_name'))) $request->merge(['business_name'=>trim($request->business_name)]);

        if (is_string($request->input('sec_registration_no'))) {
            $request->merge(['sec_registration_no' => strtoupper(trim($request->input('sec_registration_no')))]);
        }
        if ($request->filled('representative_contact_no')) {
            $request->merge(['representative_contact_no'=>PhilippineMobile::normalize($request->input('representative_contact_no'))]);
        }

        // Match the registration fields: Unicode letters, hyphens, and single internal spaces.
        $nameRegex = "/^[\\p{L}'’\\-]+(\\s[\\p{L}'’\\-]+)*$/u";

        $returningSeller = $isSeller ? User::where('account_type', 'seller')->where('status', 'rejected')
            ->where('email', $request->input('email'))->where('username', $request->input('username'))->first() : null;
        if ($returningSeller && ! \Illuminate\Support\Facades\Hash::check((string) $request->input('password'), $returningSeller->password)) {
            throw ValidationException::withMessages(['password' => 'Use your existing application password, or sign in to correct your rejected application.']);
        }

        $returningFounder=$isLogisticsFound?User::where('account_type','logistics')->where('is_logistics',true)->where(fn($q)=>$q->where('logistics_role','admin')->orWhereNull('logistics_role'))->where('status','rejected')->where('email',$request->email)->where('username',$request->username)->whereRaw('LOWER(TRIM(business_name)) = ?',[mb_strtolower(trim((string)$request->business_name))])->first():null;
        $returningApplicant = User::where('account_type', $request->input('account_type'))->where('status', 'rejected')->where('email', $request->input('email'))->where('username', $request->input('username'))->first();
        if ($returningApplicant && !\Illuminate\Support\Facades\Hash::check((string)$request->input('password'), $returningApplicant->password)) {
            throw ValidationException::withMessages(['password'=>'Use your existing application password, or sign in to correct your rejected application.']);
        }
        if ($returningApplicant && !$returningApplicant->can_resubmit) {
            throw ValidationException::withMessages(['email'=>'This application was rejected. Editing and resubmission are not permitted.']);
        }
        if ($request->boolean('no_middle_name')) $request->merge(['middle_name' => null]);
        $rules = [
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:'.$nameRegex],
            'given_names' => ['required', 'string', 'min:2', 'max:50', 'regex:'.$nameRegex],
            'middle_name' => [Rule::requiredIf(! $request->boolean('no_middle_name')), 'nullable', 'string', 'min:2', 'max:50', 'regex:'.$nameRegex],
            'no_middle_name' => ['sometimes', 'boolean'],
            'suffix' => ['nullable', new Enum(Suffix::class)],
            'sex' => ['required', new \Illuminate\Validation\Rules\Enum(\App\Enums\Sex::class)],
            'birthday' => 'required|date|before_or_equal:'.now()->subYears($minimumAge)->toDateString(),
            'age' => 'sometimes|integer|min:0',
            'email' => ['required', 'email', 'regex:/@gmail\.com$/i',
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('status', '!=', 'rejected'))],
            'contact_no' => ['bail', 'required', 'string', 'regex:/^09\d{9}$/', function ($attribute, $value, $fail) use ($request) {
                if (User::whereIn('contact_no', PhilippineMobile::variants($value))->whereNot(fn($q)=>$q->where('status','rejected')->where('email',is_string($request->input('email'))?$request->input('email'):'')->where('username',is_string($request->input('username'))?$request->input('username'):''))->exists()) {
                    $fail('This mobile number is already registered.');
                }
            }],
            'region' => 'required|string|max:255',
            'seller_type' => $isSeller ? ['required', new Enum(\App\Enums\SellerType::class)] : ['prohibited'],
            'shop_logo' => $isSeller ? ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'] : ['prohibited'],
            'tin' => ($isSeller || $isLogisticsFound)
                ? ['bail', 'required', 'string', 'regex:/^\d{12}$/D', Rule::unique('users', 'tin')->ignore($returningSeller?->id ?? $returningFounder?->id)]
                : ['prohibited'],
            'position' => $isLogisticsFound ? ['required', 'string', 'max:100'] : ['prohibited'],
            'representative_email' => $isLogisticsFound ? ['nullable', 'email', 'max:255'] : ['prohibited'],
            'representative_contact_no' => $isLogisticsFound ? ['nullable', 'regex:/^09\d{9}$/'] : ['prohibited'],
            'sec_registration_no' => $isLogisticsFound ? ['bail', 'required', 'string', 'regex:/^[A-Z]{2}[0-9]{9}$/D', Rule::unique('users', 'sec_registration_no')->ignore($returningFounder?->id)] : ['prohibited'],
            'postal_code' => ['nullable', 'string', 'regex:/^[0-9]{4}$/D'],
            'municipality_psgc_code' => ['nullable', 'string', 'regex:/^\d{9,10}$/D'],
            'province' => 'required|string',
            'municipality' => 'required|string',
            'barangay' => 'required|string',
            'house_no' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:100',
            'username' => ['required', 'string', 'min:8', 'max:30', 'alpha_dash',
                Rule::unique('users', 'username')->where(fn ($q) => $q->where('status', '!=', 'rejected'))],
            // Google sign-ups still set a password, so the account can also
            // be logged into with a username/password later without Google.
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            // Riders don't upload a separate government ID — their driver's
            // license (required below) already serves as identification.
            'id_file' => $isRider ? 'sometimes|nullable' : 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'id_type_id' => $isRider ? 'sometimes|nullable' : 'required|integer|exists:id_types,id',
            'selfie_file' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'account_type' => 'required|in:buyer,rider,seller,logistics',
            'auth_method' => 'required|in:manual,google',
            'google_id' => 'nullable|string',
            'category_ids' => $isSeller ? 'array|max:1' : 'sometimes|nullable',
            // Only sellers actually pick a category — every other role's form (hub staff
            // included) carries a leftover empty category_ids[] field that would otherwise
            // fail this as "not an integer".
            'category_ids.*' => $isSeller ? 'integer|exists:categories,id' : 'sometimes',
            'category_other' => 'nullable|string|max:100',
            'business_name' => 'sometimes|nullable',
            'business_permit_file' => 'sometimes|nullable',
        ];

        if ($isRider) {
            $rules['resume_file'] = 'nullable|file|mimes:pdf|max:5120';
            $rules['vehicle_type'] = 'required|exists:vehicle_types,slug';
            $rules['vehicle_ownership'] = ['required', new \Illuminate\Validation\Rules\Enum(\App\Enums\VehicleOwnership::class)];
            // Case-insensitive uniqueness (a closure, not plain unique:, so it matches the
            // same LOWER() comparison the live /register/check-license endpoint uses —
            // otherwise a differently-cased duplicate could pass the live check yet still
            // collide here, or vice versa).
            $rules['license_number'] = ['required', 'string', 'regex:/^[A-Z]\d{2}-\d{2}-\d{6}$/D', function ($attribute, $value, $fail) {
                if (\App\Support\DriverLicenseNumber::exists($value)) {
                    $fail("This driver's license number is already registered to another account.");
                }
            }];
            $rules['license_expiry'] = 'required|date|after_or_equal:'.today()->addMonthNoOverflow()->toDateString();
            $rules['license_file'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
            if ($request->input('vehicle_ownership') === 'own') {
                $rules['vehicle_brand'] = 'required|string|max:100';
                $rules['vehicle_model'] = 'required|string|max:100';
                $rules['plate_number'] = ['required', 'string', 'max:20', function ($attribute, $value, $fail) {
                    if (User::whereRaw('LOWER(plate_number) = ?', [mb_strtolower(trim($value))])->where('status', '!=', 'rejected')->exists()) {
                        $fail('This plate number is already registered to another account.');
                    }
                }];
                $rules['or_file'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
                $rules['cr_file'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
            }
        }

        if ($isRider || $isLogisticsJoin) {
            app(\App\Services\PersonnelApplication::class)->normalize($request);
            $rules = array_replace($rules, app(\App\Services\PersonnelApplication::class)->rules($isRider?'rider':'hub_staff',$request->input('vehicle_ownership')));
        }

        // A seller or a "found a company" logistics registration both stand up something
        // brand new (a "company" isn't its own table — it's just a users.business_name
        // shared by every staff/rider row that joined it later, see App\Models\LogisticsCompany).
        // A rider, or logistics staff "joining" a company, instead pick one that already
        // exists and got admin-approved.
        if ($isSeller || $isLogisticsFound) {
            $rules['business_name'] = ['required', 'string', 'min:'.($isSeller ? 3 : 1), 'max:'.($isSeller ? 30 : 150),
                Rule::unique('users', 'business_name')->where(fn($q)=>$q->where('status','!=','rejected')->orWhere(fn($q)=>$q->where('account_type','logistics')->where('is_logistics',true)->where(fn($q)=>$q->where('logistics_role','admin')->orWhereNull('logistics_role'))))->ignore($returningFounder?->id)];
            $rules['business_permit_file'] = $isLogisticsFound ? 'required|file|mimes:jpg,jpeg,png,pdf|max:5120' : 'prohibited';
            if ($isSeller) {
                $requiredDocuments = \App\Support\SellerDocuments::required($request->input('seller_type'));
                foreach (\App\Support\SellerDocuments::LABELS as $field => $label) {
                    $rules[$field] = in_array($field, $requiredDocuments, true) ? 'required|file|mimes:jpg,jpeg,png,pdf|max:5120' : 'prohibited';
                }
            }
            if ($isLogisticsFound) {
                foreach (\App\Support\LogisticsDocuments::LABELS as $field => $label) {
                    $rules[$field] = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
                }
                $rules['company_logo'] = 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120';
                // A JSON-encoded array of {province, municipality} pairs — the areas this
                // company covers, picked live from the PSGC API, never a hardcoded list.
                // Each one becomes a hub in App\Models\LogisticsHub once the account exists.
                $rules['hub_areas'] = ['required', 'string'];
            }
        } elseif ($isRider || $isLogisticsJoin) {
            $rules['business_name'] = ['required', 'string', function ($attribute, $value, $fail) {
                if (! LogisticsCompany::approved()->pluck('business_name')->contains($value)) {
                    $fail('Please choose a valid company from the list.');
                }
            }];
            if ($isLogisticsJoin) {
                $rules['resume_file'] = 'nullable|file|mimes:pdf|max:5120';
            }
        }

        $validator = Validator::make($request->all(), $rules, [
            'birthday.before_or_equal' => "You must be at least {$minimumAge} years old to register.",
            'given_names.regex' => 'Given names can contain letters, apostrophes, hyphens, and single spaces between words.',
            'middle_name.regex' => 'Middle name can contain letters, apostrophes, hyphens, and single spaces between words.',
            'last_name.regex' => 'Last name can contain letters, apostrophes, hyphens, and single spaces between words.',
            'tin.regex' => 'Enter a 12-digit TIN, such as 123-456-789-000.',
            'tin.unique' => 'This TIN is already registered.',
            'sec_registration_no.regex' => 'Enter two letters followed by nine digits, such as CS202012345.',
            'sec_registration_no.unique' => 'This SEC registration number is already registered.',
            'postal_code.regex' => 'Enter a four-digit postal code.',
            'license_expiry.after_or_equal' => 'Your driver\'s license must have at least one month remaining before expiry.',
        ]);

        $validator->after(function ($validator) use ($request, $isSeller, $isRider, $isLogisticsFound, $isLogisticsJoin) {
            if ($isLogisticsFound) {
                $hubAreas = json_decode($request->input('hub_areas', '[]'), true);
                if (! is_array($hubAreas) || count($hubAreas) < 1) {
                    $validator->errors()->add('hub_areas', 'Add at least one province and municipality your company covers.');
                } else {
                    foreach ($hubAreas as $area) {
                        if (!is_array($area) || empty($area['region']) || !is_string($area['region']) || empty($area['province']) || empty($area['municipality'])) {
                            $validator->errors()->add('hub_areas', 'Each coverage area needs both a province and a municipality.');
                            break;
                        }
                    }
                }
            }

            // Hub staff and riders both join at their municipality hub or an available
            // hiring hub nearby — same picker, same validation either way.
            $hub = null;
            if (($isLogisticsJoin || $isRider) && $request->filled('business_name')) {
                if ($request->filled('logistics_hub_id')) {
                    $hub = LogisticsHub::where('company_name', $request->business_name)
                        ->where('is_operational', true)
                        ->where('id', $request->logistics_hub_id)
                        ->first();
                    if (! $hub) {
                        $validator->errors()->add('logistics_hub_id', 'Please choose a valid hub from the selected company.');
                    } elseif (! $hub->is_hiring) {
                        if (! Schema::hasColumn('logistics_hubs', 'rider_hiring_open')) {
                            $validator->errors()->add('logistics_hub_id', "The {$hub->municipality} hub is currently not hiring.");
                        }
                    }
                } elseif ($isLogisticsJoin && $request->filled('province') && $request->filled('municipality')) {
                    // Hub staff only: riders always submit an explicit logistics_hub_id from
                    // the picker, so this address-fallback path is specific to that flow.
                    $hub = LogisticsHub::where('company_name', $request->business_name)
                        ->where('is_operational', true)
                        ->whereRaw('LOWER(province) = ?', [mb_strtolower(trim($request->province))])
                        ->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($request->municipality))])
                        ->first();
                    if (! $hub) {
                        $validator->errors()->add('municipality', "{$request->business_name} doesn't have a hub in {$request->municipality}, {$request->province} yet — ask them to add it as a coverage area first, or choose a nearby available hub.");
                    } elseif (! $hub->is_hiring) {
                        if (! Schema::hasColumn('logistics_hubs', 'rider_hiring_open')) {
                            $validator->errors()->add('municipality', 'This hub is currently not hiring.');
                        }
                    }
                } elseif ($isRider) {
                    $validator->errors()->add('logistics_hub_id', 'Please choose a hub.');
                }
            }

            if ($hub && ($isRider || $isLogisticsJoin)) {
                try {
                    app(HubHiring::class)->assertIntake($hub, $isRider ? 'rider' : 'hub_staff');
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $key => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($key, $message);
                        }
                    }
                }
            }

            // A rider taking a company-provided vehicle can only pick a type that hub
            // actually has an approved, not-in-maintenance unit of right now — mirrors the
            // client-side disable in register-rider.blade.php, enforced here too so a
            // crafted request can't bypass it.
            if ($isRider && $hub && $request->input('vehicle_ownership') === 'company' && $request->filled('vehicle_type')
                && ! CompanyVehicle::typeAvailableAtHub($hub->id, $request->vehicle_type)) {
                $validator->errors()->add('vehicle_type', "The {$hub->municipality} hub doesn't currently have an available ".str_replace('_', ' ', $request->vehicle_type).' for company-provided riders. Please select another vehicle type, or bring your own.');
            }

            // Same-person check: only flag a duplicate when the ENTIRE identity matches —
            // full name (given + last + middle), birthday, sex, and suffix all the same.
            // Matching given/last name alone used to false-positive on two different
            // people who just happen to share a common name.
            if ($request->filled('given_names') && $request->filled('last_name')
                && $request->filled('birthday') && $request->filled('sex')) {
                $duplicate = $this->identityExists($request);
                if ($duplicate) {
                    $validator->errors()->add('given_names', 'An account under this exact name, birthday, and gender is already registered.');
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

        $emailProof = $request->session()->get('registration_verified_email', []);
        if ($isLogisticsFound && $request->filled('representative_email')) {
            $representativeProof=$request->session()->get('registration_verified_representative_email', []);
            if (($representativeProof['expires_at'] ?? 0)<now()->timestamp ||
                !hash_equals((string)($representativeProof['email'] ?? ''),strtolower((string)$request->representative_email))) {
                return response()->json(['success'=>false,'message'=>'Verify the representative email before continuing.',
                    'errors'=>['representative_email'=>['Verify the representative email before continuing.']]],422);
            }
        }
        if (($emailProof['expires_at'] ?? 0) < now()->timestamp ||
            !hash_equals((string)($emailProof['email'] ?? ''), strtolower((string)$request->email))) {
            return response()->json(['success'=>false, 'message'=>'Verify your email with Google or a code before continuing.',
                'errors'=>['email'=>['Verify your email with Google or a code before continuing.']]], 422);
        }
        // Google email verification does not change the account's password-based registration.
        if ($request->auth_method === 'google') {
            if (strtolower((string)session('google_email')) !== strtolower((string)$request->email) || !session('google_id')) {
                return response()->json(['success'=>false, 'message'=>'Please verify your Google account again.'], 422);
            }
            $request->merge(['google_id'=>session('google_id')]);
        } else {
            $request->merge(['google_id'=>null]);
        }

        // Riders skip the generic ID upload (their license covers it) — everyone
        // else must provide one, guaranteed by the validation rules above.
        try {
            $idPath = ! $isRider && $request->hasFile('id_file') ? $this->storeWithRetry($request->file('id_file'), 'id_files') : null;
            $selfiePath = $request->hasFile('selfie_file') ? $this->storeWithRetry($request->file('selfie_file'), 'selfie_files') : null;
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'We couldn\'t upload your documents due to a network issue. Please check your connection and try again.'], 502);
        }

        $userData = [
            'account_type' => $request->account_type,
            'auth_method' => $request->auth_method,
            'google_id' => $request->google_id,
            'username' => $request->username,
            'last_name' => $request->last_name,
            'given_names' => $request->given_names,
            'middle_name' => $request->boolean('no_middle_name') ? null : $request->middle_name,
            'suffix' => $request->suffix ?: null,
            'sex' => $request->sex,
            'birthday' => $request->birthday,
            'age' => $request->age,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'region' => $request->region,
            'seller_type' => $isSeller ? $request->seller_type : null,
            'tin' => ($isSeller || $isLogisticsFound) ? $request->tin : null,
            'position' => $isLogisticsFound ? $request->position : null,
            'representative_email' => $isLogisticsFound ? $request->representative_email : null,
            'representative_contact_no' => $isLogisticsFound ? $request->representative_contact_no : null,
            'sec_registration_no' => $isLogisticsFound ? $request->sec_registration_no : null,
            'postal_code' => app(\App\Services\RegistrationPostalCode::class)->lookup($request->municipality_psgc_code, $request->municipality) ?? ($request->postal_code ?: null),
            'service_regions' => $isLogisticsFound ? array_values(array_unique(array_column(json_decode($request->hub_areas, true), 'region'))) : null,
            'province' => $request->province,
            'municipality' => $request->municipality,
            'barangay' => $request->barangay,
            'house_no' => $request->house_no,
            'street' => $request->street,
            'password' => $request->password,
            'id_file' => $idPath,
            'id_type_id' => $request->id_type_id,
            'selfie_file' => $selfiePath,
            'status' => 'pending',
            'category_id' => $isSeller ? ($request->input('category_ids')[0] ?? null) : null,
            'category_other' => $isSeller ? $request->category_other : null,
            'business_name' => ($isSeller || $isRider || $isLogistics) ? $request->business_name : null,
            // Logistics staff go through the same public, admin-approved registration as
            // everyone else — is_logistics is what LogisticsMiddleware and login actually
            // gate on, account_type is kept in sync purely for display/UI consistency.
            'is_logistics' => $isLogistics,
            'logistics_role' => $isLogistics ? ($isLogisticsJoin ? 'hub_staff' : 'admin') : null,
            // Hub assignment: use explicitly selected hub or match by address
            'logistics_hub_id' => $isLogisticsJoin
                ? ($request->filled('logistics_hub_id')
                    ? (int) $request->logistics_hub_id
                    : optional(LogisticsHub::where('company_name', $request->business_name)
                        ->where('is_operational', true)
                        ->whereRaw('LOWER(province) = ?', [mb_strtolower(trim($request->province))])
                        ->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($request->municipality))])
                        ->first())->id)
                : null,
        ];

        try {
            if ($isSeller) {
                foreach (\App\Support\SellerDocuments::required($request->input('seller_type')) as $field) {
                    $userData[$field] = $this->storeWithRetry($request->file($field), 'seller_documents');
                }
            }
            if ($isLogisticsFound) {
                $userData['business_permit_file'] = $this->storeWithRetry($request->file('business_permit_file'), 'business_permits');
                foreach (\App\Support\LogisticsDocuments::LABELS as $field => $label) {
                    $userData[$field] = $this->storeWithRetry($request->file($field), 'business_certificates');
                }
                if ($isLogisticsFound && $request->hasFile('company_logo')) {
                    $userData['company_logo'] = $this->storeWithRetry($request->file('company_logo'), 'logistics_logos');
                }
            }

            if ($isSeller && $request->hasFile('shop_logo')) {
                $userData['profile_picture']=$this->storeWithRetry($request->file('shop_logo'),'shop_logos','profile_images');
            }
            if (($isRider || $isLogisticsJoin) && $request->hasFile('resume_file')) {
                $userData['resume_file'] = $this->storeWithRetry($request->file('resume_file'), 'resume_files');
            }

            if ($isRider) {
                // Already validated above to exist, belong to this company, and be hiring.
                $userData['logistics_hub_id'] = (int) $request->logistics_hub_id;
                $userData['vehicle_type'] = $request->vehicle_type;
                $userData['nbi_clearance_file'] = $this->storeWithRetry($request->file('nbi_clearance_file'), 'nbi_clearances');
                $userData['vehicle_ownership'] = $request->vehicle_ownership;
                $userData['license_number'] = $request->license_number;
                $userData['license_expiry'] = $request->license_expiry;
                $userData['license_file'] = $this->storeWithRetry($request->file('license_file'), 'license_files');

                // No separate ID step for riders — mirror the license into the generic
                // id_file/id_type_id columns so admin document review, which reads those
                // columns for every account type, still has something to show.
                $userData['id_file'] = $userData['license_file'];
                $userData['id_type_id'] = DB::table('id_types')->where('name', "Driver's License")->value('id');

                if ($request->input('vehicle_ownership') === 'own') {
                    $userData['vehicle_brand'] = $request->vehicle_brand;
                    $userData['vehicle_model'] = $request->vehicle_model;
                    $userData['plate_number'] = $request->plate_number;
                    if ($request->hasFile('or_file')) {
                        $userData['or_file'] = $this->storeWithRetry($request->file('or_file'), 'vehicle_docs');
                    }
                    if ($request->hasFile('cr_file')) {
                        $userData['cr_file'] = $this->storeWithRetry($request->file('cr_file'), 'vehicle_docs');
                    }
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'We couldn\'t upload your documents due to a network issue. Please check your connection and try again.'], 502);
        }

        // Reuse a safely matched rejected account and preserve its attempt history.
        try {
            app(HubHiring::class)->register($userData, $isRider ? 'rider' : ($isLogisticsJoin ? 'hub_staff' : null));
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(['success' => false, 'message' => 'An account already exists. Refresh and try again.', 'errors' => ['email' => ['An account already exists.']]], 422);
        }

        if ($isLogisticsFound) {
            $hubAreas = json_decode($request->input('hub_areas', '[]'), true) ?: [];

            // De-dupe identical rows from the picker, keeping each one's first
            // occurrence (and whichever is_regional flag it carried).
            $deduped = [];
            foreach ($hubAreas as $area) {
                $province = trim($area['province'] ?? '');
                $municipality = trim($area['municipality'] ?? '');
                if ($province === '' || $municipality === '') {
                    continue;
                }
                $key = mb_strtolower($province).'|'.mb_strtolower($municipality);
                if (! isset($deduped[$key])) {
                    $deduped[$key] = ['region' => trim($area['region']), 'province' => $province, 'municipality' => $municipality, 'is_regional' => ! empty($area['is_regional'])];
                }
            }

            // Exactly one regional (province-level) hub per province: honor an
            // explicit pick from the form if there is one, otherwise the first
            // municipality added for that province stands in as its regional
            // hub — see LogisticsHub::buildRoute() for what that's used for.
            $regionalPerProvince = [];
            foreach ($deduped as $row) {
                $provinceKey = mb_strtolower($row['province']);
                if ($row['is_regional'] && ! isset($regionalPerProvince[$provinceKey])) {
                    $regionalPerProvince[$provinceKey] = mb_strtolower($row['municipality']);
                }
            }
            foreach ($deduped as $row) {
                $provinceKey = mb_strtolower($row['province']);
                $regionalPerProvince[$provinceKey] ??= mb_strtolower($row['municipality']);
            }

            foreach ($deduped as $row) {
                LogisticsHub::firstOrCreate([
                    'company_name'=>$request->business_name,'province'=>$row['province'],'municipality'=>$row['municipality'],
                ],[
                    'region' => $row['region'],
                    'is_regional_hub' => $regionalPerProvince[mb_strtolower($row['province'])] === mb_strtolower($row['municipality']),
                ]);
            }
        }

        return response()->json(['success' => true]);
    }
}
