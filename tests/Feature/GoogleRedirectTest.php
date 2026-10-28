<?php

namespace Tests\Feature;

use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class GoogleRedirectTest extends TestCase
{
    public function test_google_redirect_uses_the_configured_callback_and_preserves_registration_role(): void
    {
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
        foreach (['http://localhost:8000', 'http://127.0.0.1:8000', 'https://pocketfindsph.shop'] as $origin) {
            config(['services.google.redirect' => $origin.'/auth/google/callback']);
            Socialite::forgetDrivers();
            $response = $this->get($origin.'/auth/google/redirect?type=seller');
            $response->assertRedirect()->assertSessionHas('oauth_account_type', 'seller')->assertSessionHas('oauth_intent', 'register');
            parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
            $this->assertSame($origin.'/auth/google/callback', $query['redirect_uri']);
            $this->assertNotEmpty($query['state']);
            $response->assertSessionHas('state', $query['state']);
        }
    }

    public function test_google_login_uses_the_public_callback_behind_cloudflare(): void
    {
        config(['services.google.redirect' => 'https://pocketfindsph.shop/auth/google/callback']);
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
        Socialite::forgetDrivers();
        $response = $this->withHeaders(['Host' => 'pocketfindsph.shop', 'X-Forwarded-Proto' => 'https'])
            ->get('/auth/google/login');
        $response->assertRedirect()->assertSessionHas('oauth_intent', 'login');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('https://pocketfindsph.shop/auth/google/callback', $query['redirect_uri']);
    }

    public function test_google_login_starts_on_the_registered_callback_origin(): void
    {
        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost:8000/auth/google/callback',
        ]);
        Socialite::forgetDrivers();
        $response = $this->get('http://pocketfindsph.shop/auth/google/login');
        $response->assertRedirect('http://localhost:8000/auth/google/login');
        $response->assertSessionMissing('state');
    }

    public function test_google_registration_moves_to_callback_origin_with_its_role(): void
    {
        config(['services.google.redirect' => 'http://localhost:8000/auth/google/callback']);
        $this->get('https://pocketfindsph.shop/auth/google/redirect?type=seller')
            ->assertRedirect('http://localhost:8000/auth/google/redirect?type=seller')
            ->assertSessionMissing('state');
    }

    public function test_callback_without_a_session_returns_to_login(): void
    {
        $this->get('/auth/google/callback?error=access_denied')
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_cancelled_consent_returns_to_the_correct_registration_method(): void
    {
        $this->withSession(['oauth_intent' => 'register', 'oauth_account_type' => 'seller'])
            ->get('/auth/google/callback?error=access_denied')
            ->assertRedirect(route('register.method', ['type' => 'seller']))->assertSessionHasErrors('email');
    }

    public function test_invalid_oauth_state_is_rejected_without_contacting_google(): void
    {
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
        Socialite::forgetDrivers();
        $this->withSession(['oauth_intent' => 'login', 'state' => 'expected'])
            ->get('/auth/google/callback?code=fake&state=incorrect')
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
