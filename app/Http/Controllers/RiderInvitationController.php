<?php

namespace App\Http\Controllers;

use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\MediaLifecycle;
use App\Services\RiderInvitations;
use App\Support\AccountInput;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RiderInvitationController extends Controller
{
    public function create(Request $request)
    {
        return view('logistics.invite-rider', ['hubs' => LogisticsHub::where('company_name', $request->user()->business_name)->where('is_operational', true)->get()]);
    }

    public function store(Request $request, RiderInvitations $invitations)
    {
        $request->merge(['email' => is_string($request->input('email')) ? mb_strtolower(trim($request->email)) : $request->input('email'),
            'contact_no' => AccountInput::phone($request->input('contact_no')),
            'license_number' => is_string($request->input('license_number')) ? strtoupper(trim($request->license_number)) : $request->input('license_number'),
            'plate_number' => is_string($request->input('plate_number')) ? strtoupper(trim($request->plate_number)) : $request->input('plate_number')]);
        $document = ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        $data = $request->validate([
            'given_names' => AccountInput::name(), 'last_name' => AccountInput::name(),
            'username' => ['sometimes', 'string', 'min:8', 'max:30', 'alpha_dash', 'unique:users,username'],
            'status' => ['prohibited'], 'password' => ['prohibited'], 'password_confirmation' => ['prohibited'],
            'birthday' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'email' => ['required', 'email', 'max:255', function ($attribute, $value, $fail) {
                if (User::whereRaw('LOWER(email) = ?', [mb_strtolower($value)])->exists()) {
                    $fail('This email is already registered.');
                }
            }],
            'contact_no' => ['required', 'regex:/\A09[0-9]{9}\z/', 'unique:users,contact_no'],
            'license_number' => ['required', 'regex:/\A[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}\z/', 'unique:users,license_number'],
            'license_expiry' => ['required', 'date', 'after_or_equal:today'],
            'vehicle_type' => ['required', Rule::in(['two_wheels', 'four_wheels'])],
            'plate_number' => ['required', 'string', 'max:20', 'unique:users,plate_number'],
            'license_file' => $document, 'or_cr_file' => $document,
            'logistics_hub_id' => ['required', 'integer', Rule::exists('logistics_hubs', 'id')->where('company_name', $request->user()->business_name)->where('is_operational', true)],
        ]);
        $rider = DB::transaction(function () use ($request, $data, $invitations) {
            $hub = LogisticsHub::whereKey($data['logistics_hub_id'])->lockForUpdate()->firstOrFail();
            $data['license_file'] = app(MediaLifecycle::class)->store($request->file('license_file'), 'rider-documents', 'public');
            $data['or_cr_file'] = app(MediaLifecycle::class)->store($request->file('or_cr_file'), 'rider-documents', 'public');
            $rider = User::create($data + ['account_type' => 'rider', 'status' => 'pending_activation', 'username' => 'pf_'.Str::lower(Str::random(20)),
                'business_name' => $request->user()->business_name, 'province' => $hub->province, 'municipality' => $hub->municipality,
                'barangay' => '', 'age' => Carbon::parse($data['birthday'])->age, 'vehicle_ownership' => 'own',
                'id_file' => $data['license_file'], 'or_file' => $data['or_cr_file'], 'cr_file' => $data['or_cr_file']]);
            $invitations->send($rider);

            return $rider;
        });
        $request->attributes->set('logistics_record_id', $rider->id);

        return $request->expectsJson() ? response()->json(['success' => true, 'id' => $rider->id, 'status' => $rider->status])
            : redirect()->route('logistics.riders', ['status' => 'pending_activation'])->with('success', 'Rider created. An activation invitation has been emailed.');
    }

    public function resend(Request $request, int $user, RiderInvitations $invitations)
    {
        $user = User::findOrFail($user);
        abort_unless($user->account_type === 'rider' && $user->business_name === $request->user()->business_name, 404);
        DB::transaction(function () use ($user, $invitations) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $invitations->send($user);
        });

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('success', 'A new activation invitation has been emailed.');
    }

    public function activation(User $user, string $token, RiderInvitations $invitations)
    {
        $invitations->check($user, $token);

        return view('auth.activate-rider', compact('user', 'token'));
    }

    public function activate(Request $request, User $user, string $token, RiderInvitations $invitations)
    {
        $data = $request->validate(['password' => AccountInput::password()]);
        $invitations->activate($user, $token, $data['password']);

        return $request->expectsJson() ? response()->json(['success' => true]) : redirect()->route('login')->with('success', 'Your rider account is active. You can now sign in.');
    }
}
