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
        $intent = session('oauth_intent', 'register');

        // Google redirects here without a usable `code` if the user cancels
        // consent, the code was already used (e.g. a duplicate/back-button
        // hit), or the request otherwise didn't come from a fresh OAuth
        // handshake — bail out to a friendly page instead of a raw 500.
        if (request()->filled('error') || !request()->filled('code')) {
            return $this->googleFailureRedirect($intent, 'Google sign-in was cancelled or could not be completed. Please try again.');
        }

        try {
            $googleUser = Socialite::driver('google')
                ->setHttpClient(new Client(['verify' => false]))
                ->stateless()
                ->user();
        } catch (\Throwable $e) {
            return $this->googleFailureRedirect($intent, 'Google sign-in failed. Please try again.');
        }

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
            if ($existing->account_type === 'seller') return redirect()->route('seller.dashboard');
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

        return redirect()->route('register', ['type' => $type, 'google' => 1]);
    }

    private function googleFailureRedirect(string $intent, string $message)
    {
        if ($intent === 'login') {
            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        $type = session('oauth_account_type', 'buyer');
        return redirect()->route('register.method', ['type' => $type])->withErrors(['email' => $message]);
    }
}
