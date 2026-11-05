<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class RegistrationGoogleEmailTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        config(['services.google.redirect'=>'http://localhost/auth/google/callback']);
    }

    private function proof(string $nonce, int $expires): string
    {
        return Crypt::encryptString(json_encode(['purpose'=>'registration-email', 'email'=>'newbuyer@gmail.com', 'nonce'=>$nonce, 'expires_at'=>$expires]));
    }

    public function test_fresh_forms_do_not_reuse_a_previous_google_verified_email(): void
    {
        foreach (['buyer','seller','rider','logistics','logistics-staff'] as $type) {
            $response=$this->withSession(['registration_verified_email'=>[
                'method'=>'google','email'=>'previous@gmail.com','expires_at'=>now()->addMinutes(10)->timestamp,
            ]])->get(route('register.'.$type))->assertOk();
            preg_match('/id="registrationGoogleResult">(.*?)<\/script>/s', $response->getContent(), $matches);
            $result=json_decode($matches[1],true);
            $this->assertNull($result['email']);
            $this->assertFalse($result['returning']);
        }
    }

    public function test_representative_and_company_email_codes_keep_independent_proof(): void
    {
        \Illuminate\Support\Facades\Cache::put('representative_otp_'.md5('rep@gmail.com'),'123456',now()->addMinutes(5));
        \Illuminate\Support\Facades\Cache::put('otp_'.md5('company@gmail.com'),'654321',now()->addMinutes(5));
        $this->postJson('/register/verify-otp',['email'=>'rep@gmail.com','otp'=>'123456'])
            ->assertJson(['success'=>false]);
        $this->postJson('/register/verify-otp',['email'=>'rep@gmail.com','otp'=>'123456','purpose'=>'representative'])
            ->assertJson(['success'=>true])->assertSessionHas('registration_verified_representative_email.email','rep@gmail.com');
        $this->postJson('/register/verify-otp',['email'=>'company@gmail.com','otp'=>'654321'])
            ->assertJson(['success'=>true])->assertSessionHas('registration_verified_email.email','company@gmail.com')
            ->assertSessionHas('registration_verified_representative_email.email','rep@gmail.com');
    }

    public function test_representative_google_return_verifies_its_field_without_overwriting_company_email(): void
    {
        $start=$this->withSession(['registration_verified_email'=>['email'=>'company@gmail.com','expires_at'=>now()->addHour()->timestamp]])
            ->postJson(route('register.google.start'),['type'=>'logistics','same_tab'=>true,'purpose'=>'representative'])->assertOk();
        parse_str(parse_url($start->json('url'),PHP_URL_QUERY),$query);
        $this->get('/register/google/return?'.http_build_query(['proof'=>$this->proof($query['nonce'],now()->addMinute()->timestamp)]))
            ->assertRedirect(route('register.logistics',['google_return'=>1,'email_target'=>'representative']))
            ->assertSessionHas('registration_verified_representative_email.email','newbuyer@gmail.com')
            ->assertSessionHas('registration_verified_email.email','company@gmail.com');
        $response=$this->get(route('register.logistics',['google_return'=>1,'email_target'=>'representative']))->assertOk();
        preg_match('/id="registrationGoogleResult">(.*?)<\/script>/s',$response->getContent(),$matches);
        $result=json_decode($matches[1],true);
        $this->assertSame('newbuyer@gmail.com',$result['representative_email']);
        $this->assertNull($result['email']);
    }

    public function test_every_role_skips_method_selection_and_has_google_email_verification(): void
    {
        foreach (['buyer','seller','rider','logistics','logistics-staff'] as $type) {
            $this->get(route('register.method', ['type'=>$type]))->assertRedirect(route('register.'.$type));
            $this->get(route('register.'.$type))->assertOk()->assertSee('id="registrationGoogleEmail"', false)
                ->assertSee('data-type="'.$type.'"', false)->assertDontSee('How would you like to sign up?');
        }
    }

    public function test_verified_email_is_session_bound_expires_and_cannot_be_replayed(): void
    {
        $start = $this->postJson(route('register.google.start'), ['type'=>'seller'])->assertOk();
        parse_str(parse_url($start->json('url'), PHP_URL_QUERY), $query);
        $this->assertSame('1', (string)$query['verify_email']);
        $nonce = $query['nonce'];
        $this->postJson(route('register.google.complete'), ['proof'=>$this->proof(str_repeat('a',64), now()->addMinute()->timestamp)])->assertUnprocessable();
        $this->postJson(route('register.google.complete'), ['proof'=>$this->proof($nonce, now()->subMinute()->timestamp)])->assertUnprocessable();
        $proof = $this->proof($nonce, now()->addMinute()->timestamp);
        $this->postJson(route('register.google.complete'), ['proof'=>$proof])->assertOk()->assertJson(['email'=>'newbuyer@gmail.com'])
            ->assertSessionHas('registration_verified_email.email', 'newbuyer@gmail.com');
        $this->postJson(route('register.google.complete'), ['proof'=>$proof])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_callback_verifies_email_without_signing_in_or_creating_an_account(): void
    {
        $google = new \Laravel\Socialite\Two\User;
        $google->map(['id'=>'google-id', 'email'=>'newbuyer@gmail.com']);
        $google->user = ['email_verified'=>true];
        $provider = \Mockery::mock();
        $provider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $this->withSession(['oauth_intent'=>'verify-email', 'oauth_email_nonce'=>str_repeat('b',64)])
            ->get('/auth/google/callback?code=test')->assertOk()->assertSee('pocketfinds-email-verification');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_otp_only_verifies_after_a_correct_code_and_mail_failure_never_verifies(): void
    {
        \Illuminate\Support\Facades\Mail::shouldReceive('raw')->once()->andThrow(new \Exception('mail unavailable'));
        $this->postJson('/register/send-otp', ['email'=>'newbuyer@gmail.com'])->assertJson(['success'=>false])->assertSessionMissing('registration_verified_email');
        \Illuminate\Support\Facades\Cache::put('otp_'.md5('newbuyer@gmail.com'), '123456', now()->addMinutes(5));
        $this->postJson('/register/verify-otp', ['email'=>'newbuyer@gmail.com', 'otp'=>'999999'])->assertJson(['success'=>false])->assertSessionMissing('registration_verified_email');
        $this->postJson('/register/verify-otp', ['email'=>'newbuyer@gmail.com', 'otp'=>'123456'])->assertJson(['success'=>true])->assertSessionHas('registration_verified_email.email', 'newbuyer@gmail.com');
    }

    public function test_cancelled_verification_returns_an_error_to_the_open_form(): void
    {
        $this->withSession(['oauth_intent'=>'verify-email'])->get('/auth/google/callback?error=access_denied')
            ->assertOk()->assertSee('Google sign-in was cancelled')->assertSee('pocketfinds-email-verification');
    }

    public function test_same_tab_callback_returns_to_original_host_and_verifies_the_selected_email(): void
    {
        $start = $this->postJson('http://127.0.0.1/register/google/start', ['type'=>'seller', 'same_tab'=>true])->assertOk();
        parse_str(parse_url($start->json('url'), PHP_URL_QUERY), $query);
        $this->assertSame('1', (string)$query['same_tab']);
        $google = new \Laravel\Socialite\Two\User;
        $google->map(['id'=>'google-id', 'email'=>'newbuyer@gmail.com']);
        $google->user = ['email_verified'=>true];
        $provider = \Mockery::mock();
        $provider->shouldReceive('setHttpClient')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $callback = $this->withSession(['oauth_intent'=>'verify-email', 'oauth_email_nonce'=>$query['nonce'], 'oauth_email_same_tab'=>true])
            ->get('http://localhost/auth/google/callback?code=test')->assertRedirect();
        $return = $callback->headers->get('Location');
        $this->assertStringStartsWith('http://127.0.0.1/register/google/return?proof=', $return);
        $this->get($return)->assertRedirect(route('register.seller', ['google_return'=>1]))
            ->assertSessionHas('registration_verified_email.email', 'newbuyer@gmail.com')
            ->assertSessionHas('registration_verified_email.method', 'google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_same_tab_return_rejects_a_proof_from_another_registration_session(): void
    {
        $this->withSession(['registration_google_type'=>'rider','registration_google_nonce'=>str_repeat('a',64)])
            ->get('/register/google/return?'.http_build_query(['proof'=>$this->proof(str_repeat('b',64), now()->addMinute()->timestamp)]))
            ->assertRedirect(route('register.rider', ['google_return'=>1]))->assertSessionHasErrors('email')
            ->assertSessionMissing('registration_verified_email');
    }

    public function test_google_return_keeps_the_selected_email_after_flash_data_is_gone(): void
    {
        $this->withSession(['registration_verified_email'=>['email'=>'chosen@gmail.com','method'=>'google','expires_at'=>now()->addHour()->timestamp]]);
        foreach (['buyer','seller','rider','logistics','logistics-staff'] as $role) {
            $page=$this->get(route('register.'.$role, ['google_return'=>1]))->assertOk();
            preg_match('/id="registrationGoogleResult">(.*?)<\/script>/s', $page->getContent(), $matches);
            $this->assertSame('chosen@gmail.com', json_decode($matches[1], true)['email']);
        }
        $page=$this->withSession(['registration_verified_email'=>['email'=>'expired@gmail.com','method'=>'google','expires_at'=>now()->subMinute()->timestamp]])
            ->get(route('register.buyer'))->assertOk();
        preg_match('/id="registrationGoogleResult">(.*?)<\/script>/s', $page->getContent(), $matches);
        $this->assertNull(json_decode($matches[1], true)['email']);
    }

    public function test_registered_google_email_returns_the_send_code_error_to_the_email_field(): void
    {
        \App\Models\User::create(['username'=>'registered_buyer', 'email'=>'newbuyer@gmail.com', 'given_names'=>'Existing', 'last_name'=>'Buyer', 'password'=>'FixturePassword123!', 'account_type'=>'buyer', 'status'=>'approved']);
        $nonce=str_repeat('c',64);
        $this->withSession(['registration_google_type'=>'buyer','registration_google_nonce'=>$nonce])
            ->get('/register/google/return?'.http_build_query(['proof'=>$this->proof($nonce,now()->addMinute()->timestamp)]))
            ->assertRedirect(route('register.buyer',['google_return'=>1]))
            ->assertSessionHasErrors(['email'=>'This email is already registered. Try signing in instead.'])
            ->assertSessionMissing('registration_verified_email');
        $page=$this->get(route('register.buyer',['google_return'=>1]))->assertOk();
        preg_match('/id="registrationGoogleResult">(.*?)<\/script>/s',$page->getContent(),$matches);
        $result=json_decode($matches[1],true);
        $this->assertSame('This email is already registered. Try signing in instead.',$result['error']);
        $this->assertNull($result['email']);
    }

    public function test_reload_reset_removes_verified_email_and_pending_google_proof(): void
    {
        $this->withSession(['registration_verified_email'=>['email'=>'previous@gmail.com','method'=>'google','expires_at'=>now()->addHour()->timestamp], 'registration_google_nonce'=>str_repeat('d',64)])
            ->postJson(route('register.google.reset'))->assertOk()->assertJson(['success'=>true])
            ->assertSessionMissing('registration_verified_email')->assertSessionMissing('registration_google_nonce');
    }
}
