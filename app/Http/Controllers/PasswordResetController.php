<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function request()
    {
        return response()->view('auth.password-reset', ['resetting' => false])
            ->header('Cache-Control', 'private, no-store');
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        try {
            Password::sendResetLink($data);
        } catch (\Throwable $error) {
            Log::warning('Password reset email could not be sent.', ['exception' => $error::class]);
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'We could not send the reset link. Please try again shortly.',
            ]);
        }

        // Keep the same response for unknown addresses and throttled requests.
        return back()->with('status', 'If an account uses that email, a password reset link will be sent. Check your inbox and spam folder. Please wait a minute before requesting another link.');
    }

    public function edit(Request $request, string $token)
    {
        $pending = $request->session()->get('buyer_password_change_pending', []);
        if ($request->user()?->account_type === 'buyer' &&
            (string)($pending['user_id'] ?? '') === (string)$request->user()->id &&
            ($pending['expires_at'] ?? 0) > now()->timestamp &&
            strcasecmp((string)$request->query('email'), $request->user()->email) === 0 &&
            Password::tokenExists($request->user(), $token)) {
            $request->session()->put('buyer_password_change_token', $token);
            return redirect()->route('buyer.account')->withFragment('account-security')->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
        }
        return response()->view('auth.password-reset', [
            'resetting' => true, 'token' => $token, 'email' => $request->query('email', ''),
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:128', PasswordRule::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        $status = Password::reset($data, function ($user, string $password) {
            $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'This reset link is invalid or has expired. Request a new password reset link.',
            ]);
        }

        return redirect()->route('login')->with('success', 'Your password has been reset. Sign in with your new password.');
    }
}
