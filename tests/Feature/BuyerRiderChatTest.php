<?php

namespace Tests\Feature;

use App\Http\Controllers\BuyerController;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class BuyerRiderChatTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        foreach (['buyer', 'rider', 'seller', 'buyer'] as $index => $role) {
            DB::table('users')->insert(['id' => $index + 1, 'account_type' => $role,
                'username' => 'chat'.$index, 'status' => 'approved', 'given_names' => $role]);
        }
        $this->actingAs(User::findOrFail(1));
    }

    public function test_buyer_receives_opens_and_replies_to_rider_chat(): void
    {
        $incoming = Message::create(['sender_id' => 2, 'receiver_id' => 1, 'body' => 'Delivery update', 'read' => false]);
        $controller = app(BuyerController::class);
        $inbox = $controller->messages(Request::create('/buyer/messages'));
        $this->assertTrue($inbox->getData()['conversations']->has(2));
        $thread = $controller->messages(Request::create('/buyer/messages', 'GET', ['seller' => 'chat1']));
        $this->assertSame(2, $thread->getData()['seller']->id);
        $this->assertTrue($incoming->fresh()->read);
        $reply = $controller->messagesSend(Request::create('/buyer/messages', 'POST', ['receiver_id' => 2, 'body' => 'Thank you']));
        $this->assertTrue($reply->getData(true)['ok']);
        $poll = $controller->messagesPoll(Request::create('/buyer/messages/poll', 'GET', ['receiver_id' => 2]));
        $this->assertCount(2, $poll->getData(true)['messages']);
    }

    public function test_another_buyer_cannot_open_or_reply_to_the_rider_thread(): void
    {
        Message::create(['sender_id' => 2, 'receiver_id' => 4, 'body' => 'Private update']);
        $this->expectException(HttpException::class);
        app(BuyerController::class)->messagesPoll(Request::create('/', 'GET', ['receiver_id' => 2]));
    }

    public function test_buyer_can_still_start_a_seller_conversation(): void
    {
        $reply = app(BuyerController::class)->messagesSend(Request::create('/', 'POST', ['receiver_id' => 3, 'body' => 'Product question']));
        $this->assertTrue($reply->getData(true)['ok']);
    }
}
