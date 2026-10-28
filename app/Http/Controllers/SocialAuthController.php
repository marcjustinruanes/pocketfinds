<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Client;

class SocialAuthController extends Controller
{
    private function googleProvider()
    {
        // Use the exact callback registered for this OAuth client in Google Cloud.
        return Socialite::driver('google');
    }

    private function googleStartRedirect(string $route, array $parameters = [])
    {
        $callback = parse_url(config('services.google.redirect'));
        $origin = $callback['scheme'].'://'.$callback['host'].(isset($callback['port']) ? ':'.$callback['port'] : '');

        // Establish the OAuth session where Google will return its callback.
        if (request()->getSchemeAndHttpHost() !== $origin) {
            return redirect()->away($origin.route($route, $parameters, false));
        }

        return null;
    }

    public function redirectToGoogle()
    {
        $type = request('type', 'buyer');
        if ($redirect = $this->googleStartRedirect('google.redirect', ['type' => $type])) {
            return $redirect;
        }
        session(['oauth_account_type' => $type, 'oauth_intent' => 'register']);
        return $this->googleProvider()->redirect();
    }

    public function redirectToGoogleLogin()
    {
        if ($redirect = $this->googleStartRedirect('google.login')) {
            return $redirect;
        }
        session()->forget('oauth_account_type');
        session(['oauth_intent' => 'login']);
        return $this->googleProvider()->redirect();
    }

    public function handleGoogleCallback()
    {
        $intent = session('oauth_intent', 'login');

        // Google redirects here without a usable `code` if the user cancels
        // consent, the code was already used (e.g. a duplicate/back-button
        // hit), or the request otherwise didn't come from a fresh OAuth
        // handshake — bail out to a friendly page instead of a raw 500.
        if (request()->filled('error') || !request()->filled('code')) {
            return $this->googleFailureRedirect($intent, 'Google sign-in was cancelled or could not be completed. Please try again.');
        }

        try {
            $googleUser = $this->googleProvider()
                ->setHttpClient(new Client(['verify' => false]))
                ->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            return $this->googleFailureRedirect($intent, 'Your Google sign-in session expired. Please try again.');
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
                    'email' => 'Your account application was rejected.'.($existing->status_reason ? ' Reason: '.$existing->status_reason : ''),
                ])->with('accountStatus', 'rejected');
            }

            Auth::login($existing, true);
            request()->session()->regenerate();

            if ($existing->is_admin) return redirect()->route('admin.dashboard');
            if ($existing->is_logistics) return redirect()->route('logistics.dashboard');
            if ($existing->account_type === 'buyer') return redirect()->route('buyer.dashboard');
            if ($existing->account_type === 'seller') return redirect()->route('seller.dashboard');
            if ($existing->account_type === 'rider') return redirect()->route('rider.dashboard');
            return redirect()->intended('/');
        }

        // --- REGISTER via Google ---
        if ($existing && $existing->status !== 'rejected') {
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

        // Every role has its own dedicated registration page. 'logistics-staff' (hub staff
        // joining an existing company) was missing here — it silently fell through to the
        // buyer page, since a bare match() has no idea that type even exists.
        return match ($type) {
            'seller'          => redirect()->route('register.seller', ['google' => 1]),
            'rider'           => redirect()->route('register.rider', ['google' => 1]),
            'logistics'       => redirect()->route('register.logistics', ['google' => 1]),
            'logistics-staff' => redirect()->route('register.logistics-staff', ['google' => 1]),
            default           => redirect()->route('register.buyer', ['google' => 1]),
        };
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
