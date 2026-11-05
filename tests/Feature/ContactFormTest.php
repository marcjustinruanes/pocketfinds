<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('support_email');
        });
        DB::table('settings')->insert(['id' => 1, 'support_email' => 'contact@example.test']);
    }

    public function test_contact_message_uses_configured_mailbox_and_visitors_reply_address(): void
    {
        $this->postJson(route('support.contact'), [
            'email' => 'buyer@example.test',
            'message' => 'My question about an order.',
            'context' => 'Website contact form',
        ])->assertOk()->assertJson(['success' => true]);

        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('contact@example.test', $message->getTo()[0]->getAddress());
        $this->assertSame('buyer@example.test', $message->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('My question about an order.', $message->getHtmlBody());
    }

    public function test_invalid_contact_message_is_not_sent(): void
    {
        $this->postJson(route('support.contact'), [
            'email' => 'invalid', 'message' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'message']);
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_footer_contact_uses_pocketfinds_form_instead_of_exposing_the_mailbox(): void
    {
        $this->view('components.marketplace-footer', ['landing' => true])
            ->assertSee('data-open-contact', false)
            ->assertSee('Contact PocketFinds')
            ->assertSee(route('support.contact'))
            ->assertDontSee('contact@example.test')
            ->assertDontSee('mailto:', false);
    }
}
