<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Notification, Password, Schema};
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('account_type')->default('buyer');
            $table->string('status')->default('approved');
            $table->rememberToken();
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_01_110000_create_password_reset_tokens_table.php'))->up();
        Notification::fake();
    }

    private function account(string $status = 'approved'): User
    {
        return User::create(['email' => 'buyer@example.com', 'password' => 'OldPassword@1', 'status' => $status]);
    }

    public function test_forgot_password_shows_a_real_form_and_sends_a_hashed_single_use_link(): void
    {
        $user = $this->account();
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link')->assertDontSee('data-saved-login', false);
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHas('status')->assertSessionHasNoErrors();
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token, $user) {
            $token = $notification->token;
            $this->assertStringContainsString('https://pocketfindsph.shop'.route('password.reset', $token, false), $notification->toMail($user)->actionUrl);
            return true;
        });
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('New password')->assertSee('Confirm new password');
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword@2', 'password_confirmation' => 'NewPassword@2'];
        $this->post(route('password.update'), $data)->assertRedirect(route('login'))->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewPassword@2', $user->fresh()->password));
        $this->assertNotEmpty($user->fresh()->remember_token);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertGuest();
        $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
    }

    public function test_unknown_email_and_resend_throttle_do_not_reveal_accounts(): void
    {
        $user = $this->account();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        $knownMessage = session('status');
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', $knownMessage);
        $this->post(route('password.email'), ['email' => 'unknown@example.com'])->assertSessionHas('status', $knownMessage);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_reset_email_always_uses_the_public_domain_for_localhost_requests(): void
    {
        $user = $this->account();
        config(['app.url'=>'http://127.0.0.1:8000']);
        $this->post('http://127.0.0.1:8000/password/email', ['email'=>$user->email])->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $mail=$notification->toMail($user);
            $this->assertStringStartsWith('https://pocketfindsph.shop/password/reset/', $mail->actionUrl);
            $this->assertSame('Confirm reset password',$mail->actionText);
            $this->assertStringNotContainsString('127.0.0.1',$mail->actionUrl);
            return true;
        });
    }

    public function test_account_password_link_is_sent_only_to_the_signed_in_buyers_email(): void
    {
        $user = $this->account()->fresh();
        $password = $user->password;
        $this->actingAs($user)->post(route('buyer.account.password-link'), ['email' => 'someone-else@example.com'])
            ->assertRedirect()->assertSessionHas('password_link_sent')->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'someone-else@example.com']);
        $this->post(route('buyer.account.password-link'))->assertSessionHasErrors('password_link');
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_inline_account_password_change_requires_the_email_link_and_uses_a_single_use_token(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->string('username')->nullable()->unique(); });
        $user = $this->account()->fresh();
        $user->update(['username'=>'original-buyer']);
        $this->actingAs($user)->post(route('buyer.account.password-link'))->assertSessionHasNoErrors();
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) { $token=$notification->token; return true; });
        $data=['username'=>'new-buyer','password'=>'Changed@2026','password_confirmation'=>'Changed@2026'];
        $this->post(route('buyer.account.password-change'),$data)->assertSessionHasErrors('password_link');
        $this->assertSame('original-buyer',$user->fresh()->username);
        $this->get(route('password.reset',['token'=>$token,'email'=>$user->email]))
            ->assertRedirect(route('buyer.account').'#account-security')->assertSessionHas('buyer_password_change_token',$token);
        $this->postJson(route('buyer.account.password-change'),array_replace($data,['password_confirmation'=>'Different@2026']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->post(route('buyer.account.password-change'),$data)->assertSessionHasNoErrors()->assertSessionHas('password_success');
        $this->assertSame('new-buyer',$user->fresh()->username);
        $this->assertSame('buyer@example.com',$user->fresh()->email);
        $this->assertTrue(Hash::check('Changed@2026',$user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens',0);
        $this->post(route('buyer.account.password-change'),$data)->assertSessionHasErrors('password_link');
    }

    public function test_reset_email_uses_text_branding_without_image_attachments_or_a_url_footer(): void
    {
        $user = $this->account();
        $mail = (new ResetPassword('test-token'))->toMail($user);
        $this->assertSame('Reset your PocketFinds password', $mail->subject);
        $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);
        $html = view($mail->view, array_merge($mail->viewData, ['message' => $message]))->render();
        $this->assertStringContainsString('Pocket<span', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('src="cid:', $html);
        $this->assertStringNotContainsString('logo.png', $html);
        $this->assertCount(0, $message->getSymfonyMessage()->getAttachments());
        $this->assertStringContainsString('href="'.e($mail->actionUrl).'"', $html);
        $this->assertStringNotContainsString('trouble clicking', $html);
        $this->assertStringNotContainsString('copy and paste', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_invalid_expired_and_wrong_email_tokens_cannot_change_password(): void
    {
        $user = $this->account();
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => 'wrong', 'password' => 'NewPassword@2', 'password_confirmation' => 'NewPassword@2'];
        $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
        $this->post(route('password.update'), array_replace($data, ['token' => $token, 'email' => 'other@example.com']))->assertSessionHasErrors('email');
        DB::table('password_reset_tokens')->update(['created_at' => now()->subMinutes(61)]);
        $this->post(route('password.update'), array_replace($data, ['token' => $token]))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPassword@1', $user->fresh()->password));
    }

    public function test_password_rules_and_confirmation_are_enforced_and_rejection_is_not_bypassed(): void
    {
        $user = $this->account('rejected');
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'short', 'password_confirmation' => 'short'];
        $this->post(route('password.update'), $data)->assertSessionHasErrors('password');
        $this->post(route('password.update'), array_replace($data, ['password' => 'NewPassword@2']))->assertSessionHasErrors('password');
        $this->post(route('password.update'), array_replace($data, ['password' => 'NewPassword@2', 'password_confirmation' => 'NewPassword@2']))->assertRedirect(route('login'));
        $this->assertSame('rejected', $user->fresh()->status);
        $this->assertGuest();
    }
}
