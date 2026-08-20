<?php

namespace App\Http\Controllers;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Client;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        $type = request('type', 'buyer');
        session(['oauth_account_type' => $type]);

        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')
            ->setHttpClient(new Client(['verify' => false]))
            ->stateless()
            ->user();

        if (User::where('email', $googleUser->getEmail())->exists()) {
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
