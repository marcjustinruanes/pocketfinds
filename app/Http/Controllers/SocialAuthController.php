<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Client;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        $type = request('type', 'buyer');
        session(['oauth_account_type' => $type, 'oauth_intent' => 'register']);
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function redirectToGoogleLogin()
    {
        session(['oauth_intent' => 'login']);
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')
            ->setHttpClient(new Client(['verify' => false]))
            ->stateless()
            ->user();

        $intent = session('oauth_intent', 'register');
        $existing = User::where('email', $googleUser->getEmail())
                        ->orWhere('google_id', $googleUser->getId())
                        ->first();

        // --- SIGN IN via Google ---
        if ($intent === 'login') {
            if (!$existing) {
                return redirect()->route('login')->withErrors([
                    'email' => 'No account found with this Google email. Please register first.',
                ]);
            }

            if ($existing->status === 'pending') {
                return redirect()->route('login')->withErrors([
                    'email' => 'Your account is still pending admin approval. Please wait for confirmation.',
                ]);
            }

            if ($existing->status === 'rejected') {
                return redirect()->route('login')->withErrors([
                    'email' => 'Your account has been rejected. Please contact support for assistance.',
                ]);
            }

            Auth::login($existing, true);
            request()->session()->regenerate();

            if ($existing->is_admin) return redirect()->route('admin.dashboard');
            if ($existing->account_type === 'buyer') return redirect()->route('buyer.dashboard');
            return redirect()->intended('/');
        }

        // --- REGISTER via Google ---
        if ($existing) {
            return redirect()->route('login')->withErrors([
                'email' => 'An account with this Google email already exists. Please sign in instead.',
            ]);
        }

        session([
            'google_id'     => $googleUser->getId(),
            'google_name'   => $googleUser->getName(),
            'google_email'  => $googleUser->getEmail(),
            'google_avatar' => $googleUser->getAvatar(),
        ]);

        $type = session('oauth_account_type', 'buyer');
        return redirect()->route('register.google', ['type' => $type]);
    }
}
