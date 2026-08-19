<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        $type = request('type', 'buyer');
        session(['oauth_account_type' => $type]);

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->user();

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
