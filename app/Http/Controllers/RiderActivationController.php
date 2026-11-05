<?php

namespace App\Http\Controllers;

use App\Services\RiderActivation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class RiderActivationController extends Controller
{
    public function show(string $token, RiderActivation $activation)
    {
        $invitation = $activation->valid($token);
        if ($invitation) {
            $rider = \App\Models\User::find($invitation->user_id);
            if ($rider && $rider->account_type === 'rider' && !$rider->is_admin && $rider->status === 'rejected'
                && $rider->email === $invitation->email && $rider->business_name === $invitation->company_name) {
                abort_unless($rider->can_resubmit,410,'This application was rejected and cannot be resubmitted.');
                // The emailed single-use invitation proves ownership for riders who
                // were rejected before they had a chance to choose a password.
                abort_if(request()->user(), 403);
                request()->session()->regenerate();
                request()->session()->put('application_correction', \App\Support\ApplicationCorrection::grant($rider));
                return redirect()->route('application.correction.edit')->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
            }
        }
        return response()->view('auth.rider-activation', compact('token', 'invitation'), $invitation ? 200 : 410)
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, string $token, RiderActivation $activation)
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:128', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed']]);
        $activation->activate($token, $data['password']);
        return redirect()->route('login')->with('success', 'Your rider account is active. You can now sign in with your new password.');
    }
}
