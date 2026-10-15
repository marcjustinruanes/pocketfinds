<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\GcashPurchase;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Voucher;
use App\Services\GcashWorkflow;
use App\Services\MediaLifecycle;
use App\Support\OrderTimeline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class GcashPurchaseTest extends TestCase
{
    use WorkflowSchema;

    private User $buyer;

    private User $seller;

    private Product $product;

    private CartItem $cart;

    private GcashWorkflow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_14_000004_add_gcash_purchases.php'))->up();
        Storage::fake('local');
        $this->buyer = $this->person('buyer');
        $this->seller = $this->person('seller');
        $this->seller->update(['shipping_fee' => 20]);
        PaymentMethod::create(['id' => 1, 'name' => 'GCash', 'type' => 'ewallet', 'is_active' => true]);
        $this->product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Item', 'price' => 100, 'stock' => 10, 'status' => 'active']);
        $this->cart = $this->item($this->product, 2);
        $this->flow = app(GcashWorkflow::class);
    }

    private function person(string $role): User
    {
        return User::create(['account_type' => $role, 'username' => uniqid('account'), 'email' => uniqid().'@example.test', 'status' => 'approved', 'given_names' => 'Fixture', 'barangay' => 'B', 'municipality' => 'Pila', 'province' => 'Laguna', 'street' => 'Street']);
    }

    private function item(Product $p, int $qty): CartItem
    {
        return CartItem::create(['buyer_id' => $this->buyer->id, 'product_id' => $p->id, 'seller_slug' => $this->seller->username, 'name' => $p->name, 'price' => 100, 'qty' => $qty]);
    }

    private function draft(?string $voucher = null): GcashPurchase
    {
        return $this->flow->create($this->buyer, [$this->cart->id], 1, (string) Str::uuid(), $voucher)[0]->fresh();
    }

    private function proof(GcashPurchase $p): GcashPurchase
    {
        $p = $this->flow->instructions($this->seller, $p->id, $p->revision, 'Seller', '09123456789');

        return $this->flow->proof($this->buyer, $p->id, (string) Str::uuid(), 'gcash-proofs/'.Str::uuid().'.png', 'image/png', 100, hash('sha256', 'receipt'));
    }

    private function verified(?GcashPurchase $p = null, string $ref = 'GCASH-123456'): GcashPurchase
    {
        $p = $this->proof($p ?? $this->draft());

        return $this->flow->decide($this->seller, $p->id, $p->revision, true, $p->amount_due, null, $ref);
    }

    private function invalid(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_reservation_snapshot_server_total_deadline_and_checkout_retry(): void
    {
        $nonce = (string) Str::uuid();
        $p = $this->flow->create($this->buyer, [$this->cart->id], 1, $nonce)[0]->fresh();
        $this->assertSame('220.00', $p->amount_due);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertSame(900, $p->expires_at->timestamp - $p->activated_at->timestamp);
        $retry = $this->flow->create($this->buyer, [$this->cart->id], 1, $nonce)[0];
        $this->assertSame($p->id, $retry->id);
        $this->assertDatabaseCount('gcash_stock_allocations', 1);
        $this->invalid(fn () => $p->update(['amount_due' => 1]));
    }

    public function test_deadline_starts_after_outer_commit_not_transaction_begin(): void
    {
        DB::beginTransaction();
        $p = $this->flow->create($this->buyer, [$this->cart->id], 1, (string) Str::uuid())[0];
        $this->assertNull($p->fresh()->expires_at);
        $this->travel(5)->minutes();
        DB::commit();
        $p->refresh();
        $this->assertSame(now()->timestamp, $p->activated_at->timestamp);
        $this->assertSame(now()->addMinutes(15)->timestamp, $p->expires_at->timestamp);
    }

    public function test_rollback_preserves_original_inventory_and_no_purchase(): void
    {
        DB::beginTransaction();
        $this->draft();
        DB::rollBack();
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('gcash_purchases', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_insufficient_stock_and_duplicate_cart_options_cannot_overreserve(): void
    {
        $this->cart->update(['qty' => 11]);
        $this->invalid(fn () => $this->draft());
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->cart->update(['qty' => 6]);
        $second = $this->item($this->product, 6);
        $this->invalid(fn () => $this->flow->create($this->buyer, [$this->cart->id, $second->id], 1, (string) Str::uuid()));
        $this->assertDatabaseCount('gcash_purchases', 0);
    }

    public function test_sequential_competing_buyers_cannot_take_reserved_units(): void
    {
        $this->cart->update(['qty' => 8]);
        $this->draft();
        $other = $this->person('buyer');
        $cart = CartItem::create(['buyer_id' => $other->id, 'product_id' => $this->product->id, 'seller_slug' => $this->seller->username, 'price' => 100, 'qty' => 3]);
        $this->invalid(fn () => $this->flow->create($other, [$cart->id], 1, (string) Str::uuid()));
        $this->assertSame(2, $this->product->fresh()->stock);
    }

    public function test_variation_release_is_exact_and_repeated_expiry_does_not_restore_twice(): void
    {
        $this->product->update(['variations' => [['name' => 'Size', 'options' => [['value' => 'S', 'price' => 100, 'stock' => 5], ['value' => 'L', 'price' => 100, 'stock' => 7]]]]]);
        $this->cart->update(['variation_group' => 'Size', 'variation_value' => 'S']);
        $p = $this->draft();
        $this->assertSame(3, $this->product->fresh()->availableStock('Size', 'S'));
        $this->travel(15)->minutes();
        $this->assertTrue($this->flow->expire($p->id));
        $this->assertFalse($this->flow->expire($p->id));
        $this->assertSame(5, $this->product->fresh()->availableStock('Size', 'S'));
        $this->assertSame(7, $this->product->fresh()->availableStock('Size', 'L'));
    }

    public function test_proof_is_not_payment_and_does_not_extend_deadline(): void
    {
        $p = $this->draft();
        $expires = $p->expires_at->timestamp;
        $this->travel(4)->minutes();
        $p = $this->proof($p);
        $this->assertSame($expires, $p->expires_at->timestamp);
        $this->assertSame('proof_submitted', $p->payment_state);
        $this->invalid(fn () => $this->flow->place($this->buyer, $p->id, $p->revision));
    }

    public function test_seller_verification_exact_amount_and_only_one_linked_order(): void
    {
        $p = $this->verified();
        $this->travel(30)->minutes();
        $order = $this->flow->place($this->buyer, $p->id, $p->revision);
        $again = $this->flow->place($this->buyer, $p->id, $p->revision);
        $this->assertSame($order->id, $again->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->cart = $this->item($this->product, 2);
        $other = $this->draft();
        $this->invalid(fn () => $this->flow->place($this->buyer, $other->id, $other->revision));
    }

    public function test_verification_rejects_underpayment_and_reused_gcash_reference(): void
    {
        $p = $this->proof($this->draft());
        $this->invalid(fn () => $this->flow->decide($this->seller, $p->id, $p->revision, true, '219.99', null, 'REF-123456'));
        $this->flow->decide($this->seller, $p->id, $p->revision, true, '220', null, 'REF-123456');
        $second = $this->proof($this->draft());
        $this->invalid(fn () => $this->flow->decide($this->seller, $second->id, $second->revision, true, '220', null, 'REF-123456'));
    }

    public function test_buyer_foreign_seller_and_suspended_accounts_cannot_verify(): void
    {
        $p = $this->proof($this->draft());
        foreach ([$this->buyer, $this->person('seller')] as $actor) {
            $this->actingAs($actor)->postJson(route('gcash.decide', $p->id), ['revision' => $p->revision, 'accept' => true, 'amount' => '220', 'reference' => 'REF-123456'])->assertForbidden();
        }
        $this->seller->update(['status' => 'suspended']);
        $this->actingAs($this->seller)->postJson(route('gcash.decide', $p->id), ['revision' => $p->revision, 'accept' => true, 'amount' => '220', 'reference' => 'REF-123456'])->assertForbidden();
    }

    public function test_expired_payment_reports_and_proofs_require_explicit_resolution(): void
    {
        $p = $this->draft();
        $this->travel(16)->minutes();
        $p = $this->flow->proof($this->buyer, $p->id, (string) Str::uuid(), 'gcash-proofs/'.Str::uuid().'.png', 'image/png', 100, hash('sha256', 'late'));
        $this->assertSame('manual_resolution', $p->payment_state);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->invalid(fn () => $this->flow->place($this->buyer, $p->id, $p->revision));
        $p = $this->flow->resolve($this->seller, $p->id, $p->revision, '220', 'Checked actual receipt', true, 'LATE-123456');
        $this->assertSame('paid', $p->reservation_state);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->flow->place($this->buyer, $p->id, $p->revision);
    }

    public function test_reported_money_without_receipt_is_preserved_and_not_refunded(): void
    {
        $p = $this->draft();
        $this->travel(16)->minutes();
        $p = $this->flow->reportPaid($this->buyer, $p->id, 'I sent money');
        $p = $this->flow->resolve($this->seller, $p->id, $p->revision, '220', 'Cannot fulfill; repayment unresolved', false, 'LATE-654321');
        $this->assertSame('manual_resolution', $p->payment_state);
        $this->assertSame('expired', $p->reservation_state);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expiration_before_verification_cannot_double_release_or_verify(): void
    {
        $p = $this->proof($this->draft());
        $this->travel(16)->minutes();
        $this->invalid(fn () => $this->flow->decide($this->seller, $p->id, $p->revision, true, '220', null, 'EXPIRED-123456'));
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame('manual_resolution', $p->fresh()->payment_state);
        $this->assertFalse($this->flow->expire($p->id));
    }

    public function test_rejection_requires_reason_and_new_proof_without_extending_hold(): void
    {
        $p = $this->proof($this->draft());
        $this->invalid(fn () => $this->flow->decide($this->seller, $p->id, $p->revision, false));
        $p = $this->flow->decide($this->seller, $p->id, $p->revision, false, null, 'Receipt does not match');
        $this->invalid(fn () => $this->flow->place($this->buyer, $p->id, $p->revision));
        $p = $this->flow->proof($this->buyer, $p->id, (string) Str::uuid(), 'gcash-proofs/'.Str::uuid().'.png', 'image/png', 100, hash('sha256', 'new'));
        $this->assertSame('proof_submitted', $p->payment_state);
    }

    public function test_listing_stock_variations_archive_delete_block_but_restock_allowed(): void
    {
        $p = $this->draft();
        foreach ([['name' => 'Changed'], ['stock' => 40], ['variations' => []], ['status' => 'archived']] as $changes) {
            $this->invalid(fn () => $this->product->fresh()->update($changes));
        }$this->invalid(fn () => $this->product->fresh()->delete());
        DB::transaction(fn () => $this->product->fresh()->deductStock(-3));
        $this->assertSame(11, $this->product->fresh()->stock);
        $this->flow->abandon($this->buyer, $p->id, $p->revision);
        $this->product->fresh()->update(['name' => 'Allowed after release']);
        $this->assertSame(13, $this->product->fresh()->stock);
    }

    public function test_admin_rejection_keeps_evidence_and_enters_manual_resolution(): void
    {
        $p = $this->verified();
        $this->product->fresh()->update(['status' => 'rejected', 'rejection_note' => 'Review']);
        $this->assertSame('manual_resolution', $p->fresh()->payment_state);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('gcash_proofs', 1);
        $this->invalid(fn () => $this->flow->place($this->buyer, $p->id, $p->fresh()->revision));
    }

    public function test_paid_order_confirmation_does_not_deduct_again_and_cancellation_restores_once(): void
    {
        $p = $this->verified();
        $order = $this->flow->place($this->buyer, $p->id, $p->revision);
        $this->actingAs($this->seller)->patch(route('seller.orders.confirm', $order->id))->assertRedirect();
        $this->assertSame(8, $this->product->fresh()->stock);
        DB::transaction(fn () => $order->fresh()->cancelAndRestock('Buyer request', null, $this->buyer->id));
        DB::transaction(fn () => $order->fresh()->cancelAndRestock('Again', null, $this->buyer->id));
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame('manual_resolution', $p->fresh()->payment_state);
    }

    public function test_placed_prepaid_order_blocks_listing_until_safe_delivery_or_release(): void
    {
        $p = $this->verified();
        $order = $this->flow->place($this->buyer, $p->id, $p->revision);
        $this->invalid(fn () => $this->product->fresh()->update(['name' => 'Blocked']));
        $order->update(['status' => 'delivered']);
        $this->product->fresh()->update(['name' => 'Allowed']);
        $this->assertSame('Allowed', $this->product->fresh()->name);
    }

    public function test_voucher_slots_deactivation_deletion_and_expiration(): void
    {
        $v = Voucher::create(['seller_id' => $this->seller->id, 'code' => 'SAVE', 'type' => 'discount', 'discount_amount' => 10, 'minimum_spend' => 0, 'usage_limit' => 1, 'used_count' => 0, 'is_active' => true]);
        $p = $this->draft('SAVE');
        $this->assertSame('210.00', $p->amount_due);
        $this->invalid(fn () => $this->draft('SAVE'));
        $this->invalid(fn () => $v->delete());
        $v->update(['is_active' => false]);
        $p = $this->verified($p);
        $this->flow->place($this->buyer, $p->id, $p->revision);
        $this->assertSame(1, $v->fresh()->used_count);
        $v->delete();
        $this->assertDatabaseCount('gcash_voucher_allocations', 1);
    }

    public function test_unused_voucher_slot_released_exactly_once(): void
    {
        $v = Voucher::create(['seller_id' => $this->seller->id, 'code' => 'FREE', 'type' => 'free_shipping', 'usage_limit' => 1, 'used_count' => 0, 'minimum_spend' => 0, 'is_active' => true]);
        $p = $this->draft('FREE');
        $this->travel(16)->minutes();
        $this->flow->sweep();
        $this->flow->sweep();
        $this->assertSame(0, $v->fresh()->used_count);
        $this->assertTrue($v->fresh()->isUsable());
    }

    public function test_receipt_upload_is_private_and_retry_notifications_not_duplicated(): void
    {
        $p = $this->draft();
        $p = $this->flow->instructions($this->seller, $p->id, $p->revision, 'Seller', '09123456789');
        $file = UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        $nonce = (string) Str::uuid();
        $this->actingAs($this->buyer)->postJson(route('gcash.proof', $p->id), ['submission_id' => $nonce, 'receipt' => $file])->assertOk();
        $notifications = DB::table('notifications')->count();
        $history = DB::table('gcash_purchase_history')->count();
        $this->postJson(route('gcash.proof', $p->id), ['submission_id' => $nonce, 'receipt' => $file])->assertOk();
        $this->assertSame($notifications, DB::table('notifications')->count());
        $this->assertSame($history, DB::table('gcash_purchase_history')->count());
        $proof = DB::table('gcash_proofs')->first();
        $this->get(route('gcash.receipt', [$p->id, $proof->id]))->assertOk();
        $this->actingAs($this->person('buyer'))->get(route('gcash.receipt', [$p->id, $proof->id]))->assertForbidden();
    }

    public function test_invalid_receipts_foreign_cart_and_generic_gcash_checkout_are_denied(): void
    {
        $p = $this->draft();
        $this->actingAs($this->buyer)->postJson(route('gcash.proof', $p->id), ['submission_id' => (string) Str::uuid(), 'receipt' => UploadedFile::fake()->create('bad.exe', 1)])->assertUnprocessable();
        $this->actingAs($this->person('buyer'))->postJson(route('gcash.create'), ['items' => [$this->cart->id], 'checkout_id' => (string) Str::uuid(), 'payment_method' => 1])->assertUnprocessable();
        $this->actingAs($this->buyer)->postJson(route('buyer.checkout'), ['items' => [$this->cart->id], 'payment_method' => 1])->assertUnprocessable();
    }

    public function test_stale_revision_snapshot_integrity_and_cart_changes_do_not_authorize_new_amount(): void
    {
        $p = $this->verified();
        $this->cart->update(['qty' => 9]);
        $order = $this->flow->place($this->buyer, $p->id, $p->revision);
        $this->assertSame('220.00', $order->total);
        $this->assertSame(2, $order->items[0]['qty']);
        $this->assertDatabaseHas('cart_items', ['id' => $this->cart->id, 'qty' => 9]);
    }

    public function test_buyer_purchase_detail_renders_the_gcash_form_submission_script(): void
    {
        $purchase = $this->draft();
        $this->flow->instructions($this->seller, $purchase->id, $purchase->revision, 'Fixture Seller', '09123456789');

        $response = $this->actingAs($this->buyer)->get(route('gcash.show', $purchase->id))->assertOk();
        $response->assertSee('Submit Payment Proof');
        $response->assertSee('js/gcash.js', false);
        $response->assertSee('id="gcash-confirm"', false);
        $response->assertSee('id="gcash-receipt"', false);
    }

    public function test_payment_navigation_explains_the_next_step_without_skipping_verification(): void
    {
        $purchase = $this->draft();
        $this->actingAs($this->buyer)->get(route('gcash.show', $purchase->id))->assertOk()
            ->assertSee('Ask the seller for payment details')
            ->assertSee('aria-label="Payment progress"', false)
            ->assertSee('All GCash purchases')->assertSee('GCash payments')
            ->assertDontSee('action="'.route('gcash.place', $purchase->id).'"', false);

        $purchase = $this->verified($purchase);
        $this->get(route('gcash.show', $purchase->id))->assertOk()
            ->assertSee('Ready to place your order')
            ->assertSee('Your order will be created only when you select Place Order.')
            ->assertSee('href="#place-order"', false);
        $this->assertDatabaseCount('orders', 0);

        $admin = $this->person('admin');
        $admin->update(['is_admin' => true]);
        $this->actingAs($admin)->get(route('gcash.show', $purchase->id))->assertOk()
            ->assertSee('Read-only payment review')->assertDontSee('class="gcash-form"', false);

        $this->flow->place($this->buyer, $purchase->id, $purchase->revision);
        $this->actingAs($this->buyer)->get(route('gcash.show', $purchase->id))->assertOk()
            ->assertSee('Your order has been placed')->assertSee('View linked order')
            ->assertDontSee('action="'.route('gcash.place', $purchase->id).'"', false);
    }

    public function test_expired_payment_navigation_prioritizes_help_without_an_active_payment_step(): void
    {
        $purchase = $this->draft();
        $this->travel(16)->minutes();

        $this->actingAs($this->buyer)->get(route('gcash.show', $purchase->id))->assertOk()
            ->assertSee('This payment needs attention')->assertSee('Do not pay again.')
            ->assertSee('href="#payment-help"', false)
            ->assertDontSee('aria-current="step"', false)
            ->assertDontSee('action="'.route('gcash.place', $purchase->id).'"', false);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_opens_existing_conversation_and_purchase_pages_render(): void
    {
        $this->actingAs($this->buyer)->post(route('buyer.checkout'), ['items' => [$this->cart->id], 'payment_method' => 1, 'checkout_id' => (string) Str::uuid()])->assertRedirect();
        $p = GcashPurchase::firstOrFail();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('messages', 1);
        $this->get(route('gcash.index'))->assertOk()->assertSee($p->id);
        $this->get(route('gcash.show', $p->id))->assertOk()->assertSee('Amount payable')->assertDontSee('Place Order</button>', false);
        $html = $this->get(route('gcash.show', $p->id))->getContent();
        preg_match_all('#<script>(.*?)</script>#s', $html, $scripts);
        file_put_contents(storage_path('app/private/gcash-inline.js'), implode("\n", $scripts[1]));
        $this->get(route('buyer.messages', ['seller' => $this->seller->username, 'purchase' => $p->id]))->assertOk()->assertSee('Open linked purchase')
            ->assertSee('css/messaging.css')->assertSee('aria-label="Message"', false)->assertSee('Shift+Enter')->assertSee('Back to conversations');
        $this->actingAs($this->seller)->get(route('gcash.show', $p->id))->assertOk()->assertSee('Send payment instructions');
        $this->get(route('gcash.index'))->assertOk()->assertSee('Item')->assertSee('GCash payments');
        $this->get(route('seller.messages', ['buyer' => $this->buyer->id, 'purchase' => $p->id]))->assertOk()
            ->assertSee('data-has-conversation="true"', false)->assertSee('Back to conversations')->assertSee('Shift+Enter')->assertSee('id="chatFeedback"', false);
    }

    public function test_instruction_validation_rejects_invalid_names_and_literal_phone_spaces_without_writes(): void
    {
        $purchase = $this->draft();
        $revision = $purchase->revision;
        $history = DB::table('gcash_purchase_history')->count();
        foreach (['Seller!', 'Seller\'s', 'Seller😀', 'Seller  Two', "Seller\tTwo"] as $name) {
            $this->actingAs($this->seller)->postJson(route('gcash.instructions', $purchase->id), ['revision' => $revision, 'name' => $name, 'number' => '09123456789'])->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        foreach ([' 09123456789', '09123456789 ', '09123 456789', '0912345678', '091234567890', '0912345678A', '+639123456789', '08123456789'] as $number) {
            $this->postJson(route('gcash.instructions', $purchase->id), ['revision' => $revision, 'name' => 'Seller 2', 'number' => $number])->assertUnprocessable()->assertJsonValidationErrors('number');
        }
        $this->assertNull($purchase->fresh()->instructions);
        $this->assertSame($revision, $purchase->fresh()->revision);
        $this->assertSame($history, DB::table('gcash_purchase_history')->count());
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->postJson(route('gcash.instructions', $purchase->id), ['revision' => $revision, 'name' => '  Juan Dela Cruz 2  ', 'number' => '09123456789'])->assertOk()->assertJsonPath('purchase.instructions.name', 'Juan Dela Cruz 2');
    }

    public function test_gcash_messages_use_snapshot_product_names_in_both_threads_and_polling_without_rewriting_history(): void
    {
        $purchase = $this->draft();
        $message = DB::table('messages')->where('gcash_purchase_id', $purchase->id)->first();
        $this->assertStringContainsString('GCash payment for Item', $message->body);
        $this->assertStringNotContainsString($purchase->id, $message->body);
        $legacy = 'GCash purchase '.$purchase->id.' - amount due PHP 220.00. Stock reserved for 15 minutes. Awaiting seller payment instructions.';
        DB::table('messages')->where('id', $message->id)->update(['body' => $legacy]);
        // Fixture-only catalog change: presentation must use the immutable snapshot.
        DB::table('products')->where('id', $this->product->id)->update(['name' => 'Renamed catalog item']);
        foreach ([[$this->buyer, 'buyer', 'seller', $this->seller->username, $this->seller->id], [$this->seller, 'seller', 'buyer', $this->buyer->id, $this->buyer->id]] as [$actor, $role, $contact, $key, $receiver]) {
            $this->actingAs($actor)->get(route($role.'.messages', [$contact => $key]))->assertOk()
                ->assertSee('GCash payment for Item')->assertDontSee('GCash purchase '.$purchase->id)->assertDontSee('Renamed catalog item - amount due');
            $this->getJson(route($role.'.messages.poll', ['receiver_id' => $receiver]))->assertOk()
                ->assertJsonPath('messages.0.body', 'GCash payment for Item - amount due PHP 220.00. Stock reserved for 15 minutes. Awaiting seller payment instructions.');
        }
        $this->assertSame($legacy, DB::table('messages')->where('id', $message->id)->value('body'));
    }

    public function test_buyer_order_detail_uses_product_heading_and_collapsed_delivery_progress(): void
    {
        $purchase = $this->verified();
        $order = $this->flow->place($this->buyer, $purchase->id, $purchase->revision);
        $this->actingAs($this->buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee('css/order-details.css')->assertSee('data-order-summary', false)
            ->assertSee('id="orderDetailsTitle'.$order->id.'">Item</h3>', false)
            ->assertSee('Order reference:')->assertSee($order->order_number)
            ->assertSee('<details class="order-tracking">', false)
            ->assertSee('Scrollable order information')->assertSee('>Done</button>', false);
        $this->assertSame('placed', $order->fresh()->status);
    }

    public function test_delivery_timeline_never_invents_intermediate_events_or_timestamps(): void
    {
        foreach (Order::STATUSES as $status) {
            $order = new Order(['status' => $status]);
            $order->forceFill(['created_at' => now()]);
            $order->setRelation('shipment', null);
            $timeline = OrderTimeline::forOrder($order, []);
            $this->assertCount(6, $timeline['rows']);
            $this->assertCount(1, array_filter($timeline['rows'], fn ($row) => $row['timestamp'] !== null));
            if (in_array($status, ['cancelled', 'returned', 'delivery_failed'])) {
                $this->assertTrue($timeline['stopped']);
                $this->assertCount(0, array_filter($timeline['rows'], fn ($row) => $row['state'] === 'current'));
            }
        }
        $order->status = 'picked_up';
        $timeline = OrderTimeline::forOrder($order, [(object) ['status' => 'confirmed', 'created_at' => '2026-10-15 09:10:00']]);
        $this->assertSame('completed', $timeline['rows'][1]['state']);
        $this->assertSame('09:10', $timeline['rows'][1]['timestamp']->format('H:i'));
        $this->assertSame('unrecorded', $timeline['rows'][2]['state']);
        $this->assertNull($timeline['rows'][2]['timestamp']);
        $this->assertSame('current', $timeline['rows'][3]['state']);
        $this->assertSame('pending', $timeline['rows'][4]['state']);
        $this->assertSame('future', $timeline['rows'][5]['state']);
        $order->status = 'completed';
        $timeline = OrderTimeline::forOrder($order, [(object) ['status' => 'completed', 'created_at' => '2026-10-15 10:15:00']]);
        $this->assertSame('Order completed', $timeline['rows'][5]['title']);
        $this->assertSame('10:15', $timeline['rows'][5]['timestamp']->format('H:i'));
    }

    public function test_json_cancellation_preserves_authorization_stock_and_payment_history_on_retries(): void
    {
        $purchase = $this->verified();
        $order = $this->flow->place($this->buyer, $purchase->id, $purchase->revision);
        $payload = ['cancellation_reason' => 'Changed my mind'];
        $this->actingAs($this->person('buyer'))->patchJson(route('buyer.orders.cancel', $order), $payload)->assertForbidden();
        $this->actingAs($this->buyer)->patchJson(route('buyer.orders.cancel', $order), [])->assertUnprocessable();
        $this->assertSame('placed', $order->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->patchJson(route('buyer.orders.cancel', $order), $payload)->assertOk()->assertJsonPath('cancelled', true);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame('manual_resolution', $purchase->fresh()->payment_state);
        $this->assertSame('GCASH-123456', $purchase->fresh()->payment_reference);
        $this->patchJson(route('buyer.orders.cancel', $order), $payload)->assertUnprocessable();
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'cancelled')->count());
    }

    public function test_ineligible_order_hides_cancellation_and_rejects_crafted_json_requests(): void
    {
        $purchase = $this->verified();
        $order = $this->flow->place($this->buyer, $purchase->id, $purchase->revision);
        foreach (['ready_for_pickup', 'delivered'] as $status) {
            DB::table('orders')->where('id', $order->id)->update(['status' => $status]);
            $this->actingAs($this->buyer)->get(route('buyer.orders'))->assertOk()->assertDontSee('class="order-cancel-form"', false);
            $this->patchJson(route('buyer.orders.cancel', $order), ['cancellation_reason' => 'Other'])->assertUnprocessable();
            $this->assertSame($status, $order->fresh()->status);
            $this->assertSame(8, $this->product->fresh()->stock);
        }
    }

    public function test_legacy_instruction_names_remain_unchanged_and_can_be_retried(): void
    {
        $purchase = $this->draft();
        $purchase->update(['instructions' => ['name' => "Seller's Account", 'number' => '09123456789', 'amount' => $purchase->amount_due]]);
        $this->actingAs($this->seller)->postJson(route('gcash.instructions', $purchase->id), ['revision' => $purchase->revision, 'name' => "Seller's Account", 'number' => '09123456789'])->assertOk()->assertJsonPath('purchase.instructions.name', "Seller's Account");
    }

    public function test_reference_validation_returns_safe_field_errors_and_preserves_payment_state(): void
    {
        $purchase = $this->proof($this->draft());
        foreach (['short', str_repeat('A', 65), 'ABC 123', '<script>'] as $reference) {
            $this->actingAs($this->seller)->postJson(route('gcash.decide', $purchase->id), ['revision' => $purchase->revision, 'accept' => true, 'amount' => '220', 'reference' => $reference])->assertUnprocessable()->assertJsonValidationErrors('reference');
        }
        $this->assertSame('proof_submitted', $purchase->fresh()->payment_state);
        $this->postJson(route('gcash.decide', $purchase->id), ['revision' => $purchase->revision, 'accept' => true, 'amount' => '220', 'reference' => '  test-123456  '])->assertOk()->assertJsonPath('purchase.payment_reference', 'TEST-123456');
        $other = $this->proof($this->draft());
        $this->postJson(route('gcash.decide', $other->id), ['revision' => $other->revision, 'accept' => true, 'amount' => '220', 'reference' => 'TEST-123456'])->assertUnprocessable()->assertJsonValidationErrors('reference')->assertDontSee($purchase->id);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_receipt_upload_rejects_unsupported_content_and_oversized_files(): void
    {
        $purchase = $this->draft();
        $purchase = $this->flow->instructions($this->seller, $purchase->id, $purchase->revision, 'Seller', '09123456789');
        foreach ([UploadedFile::fake()->create('receipt.txt', 10, 'text/plain'), UploadedFile::fake()->create('receipt.pdf', 5121, 'application/pdf')] as $file) {
            $this->actingAs($this->buyer)->postJson(route('gcash.proof', $purchase->id), ['submission_id' => (string) Str::uuid(), 'receipt' => $file])->assertUnprocessable()->assertJsonValidationErrors('receipt');
        }
        $this->assertDatabaseCount('gcash_proofs', 0);
        $this->assertSame('awaiting_payment', $purchase->fresh()->payment_state);
    }

    public function test_sweep_command_and_expiration_notification_are_idempotent(): void
    {
        $p = $this->draft();
        $this->travel(16)->minutes();
        $this->artisan('gcash:expire')->assertExitCode(0);
        $count = DB::table('notifications')->count();
        $this->artisan('gcash:expire')->assertExitCode(0);
        $this->assertSame($count, DB::table('notifications')->count());
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_changed_shipping_requires_explicit_resolution_without_new_payment_authorization(): void
    {
        $p = $this->verified();
        $this->seller->update(['shipping_fee' => 30]);
        $this->actingAs($this->buyer)->get(route('gcash.show', $p->id))->assertOk()->assertDontSee('Place Order</button>', false)->assertSee('Shipping amount changed');
        $this->invalid(fn () => $this->flow->place($this->buyer, $p->id, $p->revision));
        $p = $this->flow->requestResolution($this->buyer, $p->id, $p->revision, 'Shipping changed after payment');
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame('220.00', $p->amount_due);
        $this->invalid(fn () => $this->flow->resolve($this->seller, $p->id, $p->revision, '220', 'Request original amount', true, 'GCASH-123456'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_abandoned_voucher_and_product_can_be_deleted_without_erasing_allocation_history(): void
    {
        $v = Voucher::create(['seller_id' => $this->seller->id, 'code' => 'KEEP', 'type' => 'discount', 'discount_amount' => 10, 'minimum_spend' => 0, 'usage_limit' => 1, 'used_count' => 0, 'is_active' => true]);
        $p = $this->draft('KEEP');
        $this->flow->abandon($this->buyer, $p->id, $p->revision);
        $productId = $this->product->id;
        $voucherId = $v->id;
        $this->product->fresh()->delete();
        $v->delete();
        $this->assertDatabaseHas('gcash_stock_allocations', ['product_reference' => $productId, 'product_id' => null, 'state' => 'released']);
        $this->assertDatabaseHas('gcash_voucher_allocations', ['voucher_reference' => $voucherId, 'voucher_id' => null, 'state' => 'released']);
    }

    public function test_stale_decisions_and_partial_allocations_are_rejected(): void
    {
        $p = $this->proof($this->draft());
        $this->invalid(fn () => $this->flow->decide($this->seller, $p->id, $p->revision - 1, true, '220', null, 'REF-555555'));
        $p = $this->flow->decide($this->seller, $p->id, $p->revision, true, '220', null, 'REF-555555');
        DB::table('gcash_stock_allocations')->where('purchase_id', $p->id)->update(['quantity' => 1]);
        $this->invalid(fn () => $this->flow->place($this->buyer, $p->id, $p->revision));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_seller_listing_and_voucher_endpoints_show_guard_errors(): void
    {
        $p = $this->draft();
        $this->actingAs($this->seller)->deleteJson(route('seller.inventory.destroy', $this->product->id))->assertUnprocessable();
        $this->actingAs($this->person('buyer'))->getJson(route('gcash.show', $p->id))->assertForbidden();
        foreach (['rider', 'logistics', 'admin'] as $role) {
            $actor = $this->person('buyer');
            DB::table('users')->where('id', $actor->id)->update(['account_type' => $role]); // Raw fixture: do not bypass recruitment to manufacture approved personnel.
            $this->actingAs($actor->fresh())->getJson(route('gcash.index'))->assertForbidden();
        }
    }

    public function test_platform_admin_can_review_but_never_verify_payment(): void
    {
        $p = $this->proof($this->draft());
        $admin = $this->person('admin');
        $admin->update(['is_admin' => true]);
        $this->actingAs($admin)->get(route('gcash.index'))->assertOk()->assertSee($p->id);
        $this->get(route('gcash.show', $p->id))->assertOk()->assertDontSee('Confirm actual payment received');
        $this->postJson(route('gcash.decide', $p->id), ['revision' => $p->revision, 'accept' => true, 'amount' => '220', 'reference' => 'ADMIN-123456'])->assertForbidden();
    }

    public function test_changed_cart_variation_is_retained_and_suspended_seller_cannot_confirm_prepaid_order(): void
    {
        $this->product->update(['variations' => [['name' => 'Size', 'options' => [['value' => 'S', 'price' => 100, 'stock' => 5], ['value' => 'L', 'price' => 100, 'stock' => 5]]]]]);
        $this->cart->update(['variation_group' => 'Size', 'variation_value' => 'S']);
        $p = $this->verified();
        $this->cart->update(['variation_value' => 'L']);
        $order = $this->flow->place($this->buyer, $p->id, $p->revision);
        $this->assertDatabaseHas('cart_items', ['id' => $this->cart->id, 'variation_value' => 'L']);
        $this->seller->update(['status' => 'suspended']);
        $this->actingAs($this->seller)->patchJson(route('seller.orders.confirm', $order->id))->assertForbidden();
        $this->assertSame('placed', $order->fresh()->status);
    }

    public function test_payment_notifications_open_only_the_recipient_purchase(): void
    {
        $p = $this->draft();
        foreach ([$this->buyer, $this->seller] as $actor) {
            $row = DB::table('notifications')->where('user_id', $actor->id)->where('reference_id', $p->id)->first();
            $this->actingAs($actor)->get(route($actor->account_type.'.notifications.open', $row->id))->assertRedirect(route('gcash.show', $p->id));
        }
        $this->actingAs($this->person('buyer'))->get(route('buyer.notifications.open', $row->id))->assertNotFound();
    }

    public function test_rejected_listing_blocks_pre_handoff_order_progress_and_preserves_manual_repayment(): void
    {
        $p = $this->verified();
        $order = $this->flow->place($this->buyer, $p->id, $p->revision);
        $order->update(['status' => 'confirmed']);
        $this->product->fresh()->update(['status' => 'rejected']);
        $this->invalid(fn () => $order->fresh()->update(['status' => 'preparing']));
        $this->assertSame('confirmed', $order->fresh()->status);
        DB::transaction(fn () => $order->fresh()->cancelAndRestock('Listing rejected', null, $this->seller->id));
        $p->refresh();
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame('released', $p->reservation_state);
        $this->invalid(fn () => $this->flow->resolve($this->seller, $p->id, $p->revision, '220', 'Do not create second order', true, 'GCASH-123456'));
        $p = $this->flow->resolve($this->seller, $p->id, $p->revision, '220', 'Repayment still unresolved', false, 'GCASH-123456');
        $this->assertSame('manual_resolution', $p->payment_state);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_audit_failure_rolls_back_reservation_and_returns_sanitized_error(): void
    {
        DB::statement("CREATE TRIGGER fail_gcash_audit BEFORE INSERT ON gcash_purchase_history BEGIN SELECT RAISE(ABORT, 'fixture audit failure'); END");
        $response = $this->actingAs($this->buyer)->postJson(route('gcash.create'), ['items' => [$this->cart->id], 'checkout_id' => (string) Str::uuid(), 'payment_method' => 1]);
        $response->assertStatus(503)->assertJsonMissingPath('exception');
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('gcash_purchases', 0);
        $this->assertDatabaseCount('gcash_stock_allocations', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_extreme_variation_price_cannot_overflow_into_a_paid_zero_total(): void
    {
        $this->product->update(['variations' => [['name' => 'Size', 'options' => [['value' => 'S', 'price' => 1e20, 'stock' => 5]]]]]);
        $this->cart->update(['variation_group' => 'Size', 'variation_value' => 'S', 'price' => 1e20]);
        $this->invalid(fn () => $this->draft());
        $this->assertSame(5, $this->product->fresh()->availableStock('Size', 'S'));
        $this->assertDatabaseCount('gcash_purchases', 0);
    }

    public function test_direct_purchase_cannot_bypass_existing_address_completeness_rule(): void
    {
        $this->buyer->update(['municipality' => 'Not provided']);
        $this->invalid(fn () => $this->draft());
        $this->assertDatabaseCount('gcash_purchases', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_additive_migration_preserves_existing_data_and_refuses_history_destruction(): void
    {
        $this->assertSame(10, $this->product->fresh()->stock);
        $migration = require database_path('migrations/2026_10_14_000004_add_gcash_purchases.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    private function pickupFixture(): array
    {
        $purchase = $this->verified(null, 'PICKUP-'.Str::random(12));
        $order = $this->flow->place($this->buyer, $purchase->id, $purchase->revision);
        $order->update(['status' => 'ready_for_pickup']);
        $rider = $this->person('buyer');
        DB::table('users')->where('id', $rider->id)->update(['account_type' => 'rider', 'business_name' => 'Alpha']);
        $shipment = Shipment::create(['order_id' => $order->id, 'tracking_number' => (string) Str::uuid(), 'shipping_status' => 'ready_for_pickup', 'pickup_rider_id' => $rider->id, 'pickup_approved_at' => now(), 'logistics_company' => 'Alpha', 'origin_hub' => 'Pila', 'destination_hub' => 'Pila', 'origin_province' => 'Laguna', 'destination_province' => 'Laguna']);

        return [$purchase, $order, $shipment, $rider->fresh()];
    }

    public function test_suspended_seller_cannot_mutate_prepaid_order_at_each_stage(): void
    {
        [$purchase, $order, $shipment] = $this->pickupFixture();
        LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        $this->seller->update(['status' => 'suspended']);
        $this->actingAs($this->seller);
        foreach ([['confirmed', 'preparing', []], ['preparing', 'ready', ['logistics_company' => 'Alpha']], ['ready_for_pickup', 'confirm-pickup', []], ['ready_for_pickup', 'schedule-pickup', ['scheduled_pickup_at' => now()->addDay()->toDateTimeString()]], ['confirmed', 'cancel', ['cancellation_reason' => 'Other']]] as [$status, $route, $payload]) {
            DB::table('orders')->where('id', $order->id)->update(['status' => $status]);
            DB::table('shipments')->where('id', $shipment->id)->update(['rider_confirmed_pickup_at' => now()]);
            $this->patchJson(route('seller.orders.'.$route, $order->id), $payload)->assertForbidden();
            $this->assertSame($status, $order->fresh()->status);
        }
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_rejection_before_and_between_pickup_confirmations_blocks_handoff(): void
    {
        [$purchase, $order, $shipment, $rider] = $this->pickupFixture();
        $this->product->fresh()->update(['status' => 'rejected']);
        $this->actingAs($rider)->patchJson(route('rider.my-pickups.confirm', $shipment->id))->assertUnprocessable();
        $this->assertNull($shipment->fresh()->rider_confirmed_pickup_at);
        DB::table('shipments')->where('id', $shipment->id)->update(['rider_confirmed_pickup_at' => now()]);
        $this->actingAs($this->seller)->patchJson(route('seller.orders.confirm-pickup', $order->id))->assertUnprocessable();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertNull($shipment->fresh()->seller_confirmed_pickup_at);
    }

    public function test_legitimate_post_handoff_return_releases_prepaid_stock_once(): void
    {
        [$purchase, $order, $shipment] = $this->pickupFixture();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'delivery_failed']);
        DB::table('shipments')->where('id', $shipment->id)->update(['shipping_status' => 'delivery_failed', 'rider_confirmed_pickup_at' => now(), 'seller_confirmed_pickup_at' => now()]);
        $this->product->fresh()->update(['status' => 'rejected']);
        $logistics = $this->person('buyer');
        DB::table('users')->where('id', $logistics->id)->update(['account_type' => 'logistics', 'is_logistics' => true, 'logistics_role' => 'admin', 'business_name' => 'Alpha']);
        $this->actingAs($logistics->fresh())->patchJson(route('logistics.status.update', $shipment->id), ['status' => 'returned'])->assertRedirect();
        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->patchJson(route('logistics.status.update', $shipment->id), ['status' => 'returned'])->assertRedirect();
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame('manual_resolution', $purchase->fresh()->payment_state);
    }

    public function test_private_cleanup_on_rejected_upload_and_failed_transaction(): void
    {
        $p = $this->verified();
        $file = UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        $this->actingAs($this->buyer)->postJson(route('gcash.proof', $p->id), ['submission_id' => (string) Str::uuid(), 'receipt' => $file])->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->allFiles('gcash-proofs'));
        $p = $this->draft();
        $p = $this->flow->instructions($this->seller, $p->id, $p->revision, 'Seller', '09123456789');
        DB::statement("CREATE TRIGGER fail_proof_history BEFORE INSERT ON gcash_purchase_history WHEN NEW.event = 'proof_submitted' BEGIN SELECT RAISE(ABORT, 'test audit outage'); END");
        $this->postJson(route('gcash.proof', $p->id), ['submission_id' => (string) Str::uuid(), 'receipt' => $file])->assertStatus(503);
        $this->assertSame([], Storage::disk('local')->allFiles('gcash-proofs'));
        $this->assertDatabaseCount('gcash_proofs', 1);
    }

    public function test_private_cleanup_preserves_referenced_receipt_and_rejects_other_local_paths(): void
    {
        $p = $this->proof($this->draft());
        $row = DB::table('gcash_proofs')->where('purchase_id', $p->id)->first();
        Storage::disk('local')->put($row->path, 'evidence');
        Storage::disk('local')->put('unrelated.txt', 'retain');
        DB::transaction(function () use ($row) {
            app(MediaLifecycle::class)->deleteAfterCommit($row->path, 'local');
            app(MediaLifecycle::class)->deleteAfterCommit('unrelated.txt', 'local');
        });
        Storage::disk('local')->assertExists($row->path);
        Storage::disk('local')->assertExists('unrelated.txt');
    }

    public function test_cart_price_review_requires_explicit_matching_acknowledgement(): void
    {
        $this->product->update(['price' => 120]);
        $this->invalid(fn () => $this->draft());
        $preview = $this->actingAs($this->buyer)->postJson(route('gcash.refreshPrices'), ['items' => [$this->cart->id]])->assertOk()->json();
        $this->assertSame(100.0, (float) $this->cart->fresh()->price);
        $this->assertSame('120.00', $preview['changes'][0]['new_price']);
        $this->postJson(route('gcash.refreshPrices'), ['items' => [$this->cart->id], 'acknowledge' => true, 'acknowledgement' => $preview['acknowledgement']])->assertOk();
        $this->assertSame('260.00', $this->draft()->amount_due);
    }

    public function test_cart_price_acknowledgement_rejects_changed_prices_and_foreign_cart(): void
    {
        $this->product->update(['price' => 120]);
        $preview = $this->flow->refreshCartPrices($this->buyer, [$this->cart->id]);
        $this->product->update(['price' => 130]);
        $this->invalid(fn () => $this->flow->refreshCartPrices($this->buyer, [$this->cart->id], $preview['acknowledgement']));
        $this->invalid(fn () => $this->flow->refreshCartPrices($this->person('buyer'), [$this->cart->id]));
        $this->assertSame(100.0, (float) $this->cart->fresh()->price);
    }

    public function test_reacquisition_revalidates_each_voucher_term(): void
    {
        $v = Voucher::create(['seller_id' => $this->seller->id, 'code' => 'VALID', 'type' => 'amount', 'discount_amount' => 10, 'minimum_spend' => 0, 'usage_limit' => 10, 'is_active' => true]);
        $p = $this->proof($this->draft('VALID'));
        $this->travel(16)->minutes();
        $this->flow->expire($p->id);
        foreach ([['minimum_spend' => 300], ['discount_amount' => 5], ['type' => 'free_shipping'], ['type' => 'unknown'], ['seller_id' => $this->person('seller')->id], ['used_count' => 10], ['is_active' => false]] as $changed) {
            $old = $v->fresh()->getAttributes();
            $v->fresh()->update($changed);
            $this->invalid(fn () => $this->flow->resolve($this->seller, $p->id, $p->fresh()->revision, '210', 'Verify original discount', true, 'VOUCHER-123456'));
            DB::table('vouchers')->where('id', $v->id)->update($old);
            $this->assertSame('manual_resolution', $p->fresh()->payment_state);
            $this->assertSame(10, $this->product->fresh()->stock);
        }
        $p = $this->flow->resolve($this->seller, $p->id, $p->fresh()->revision, '210', 'Original terms available', true, 'VOUCHER-123456');
        $this->assertSame('verified', $p->payment_state);
    }

    public function test_failed_post_commit_activation_retry_is_idempotent_and_keeps_deadline(): void
    {
        DB::statement("CREATE TRIGGER fail_activation BEFORE INSERT ON messages BEGIN SELECT RAISE(ABORT, 'test messaging outage'); END");
        $nonce = (string) Str::uuid();
        $p = $this->flow->create($this->buyer, [$this->cart->id], 1, $nonce)[0]->fresh();
        $this->assertSame('initializing', $p->reservation_state);
        $deadline = $p->expires_at->toISOString();
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->travel(3)->minutes();
        DB::statement('DROP TRIGGER fail_activation');
        $retry = $this->flow->create($this->buyer, [$this->cart->id], 1, $nonce)[0]->fresh();
        $this->assertSame($p->id, $retry->id);
        $this->assertSame('held', $retry->reservation_state);
        $this->assertSame($deadline, $retry->expires_at->toISOString());
        $this->flow->activate($p->id);
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_interrupted_process_activation_uses_conservative_original_time(): void
    {
        $p = $this->draft();
        DB::table('gcash_purchases')->where('id', $p->id)->update(['reservation_state' => 'initializing', 'activated_at' => null, 'expires_at' => null]);
        $this->travel(4)->minutes();
        $this->flow->activate($p->id);
        $p->refresh();
        $this->assertSame($p->created_at->copy()->addMinutes(15)->toISOString(), $p->expires_at->toISOString());
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_multi_seller_partial_activation_recovers_only_initializing_purchase(): void
    {
        $secondSeller = $this->person('seller');
        $secondProduct = Product::create(['seller_id' => $secondSeller->id, 'name' => 'Second seller', 'price' => 100, 'stock' => 5, 'status' => 'active']);
        $secondCart = CartItem::create(['buyer_id' => $this->buyer->id, 'product_id' => $secondProduct->id, 'seller_slug' => $secondSeller->username, 'name' => 'Second seller', 'price' => 100, 'qty' => 1]);
        DB::statement('CREATE TRIGGER fail_second_activation BEFORE INSERT ON messages WHEN NEW.receiver_id = '.$secondSeller->id." BEGIN SELECT RAISE(ABORT, 'second seller messaging outage'); END");
        $nonce = (string) Str::uuid();
        $rows = $this->flow->create($this->buyer, [$this->cart->id, $secondCart->id], 1, $nonce);
        $this->assertSame(['held', 'initializing'], array_map(fn ($p) => $p->fresh()->reservation_state, $rows));
        $firstDeadline = $rows[0]->fresh()->expires_at->toISOString();
        DB::statement('DROP TRIGGER fail_second_activation');
        $this->flow->create($this->buyer, [$this->cart->id, $secondCart->id], 1, $nonce);
        $this->assertSame('held', $rows[1]->fresh()->reservation_state);
        $this->assertSame($firstDeadline, $rows[0]->fresh()->expires_at->toISOString());
        $this->assertDatabaseCount('messages', 2);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertSame(4, $secondProduct->fresh()->stock);
        $counts = DB::table('gcash_purchase_history')->count();
        (require database_path('migrations/2026_10_14_000004_add_gcash_purchases.php'))->up();
        $this->assertSame($counts, DB::table('gcash_purchase_history')->count());
    }

    public function test_actual_first_pickup_confirmation_then_rejection_blocks_second(): void
    {
        [$purchase, $order, $shipment, $rider] = $this->pickupFixture();
        $this->actingAs($rider)->patchJson(route('rider.my-pickups.confirm', $shipment->id))->assertRedirect();
        $this->assertNotNull($shipment->fresh()->rider_confirmed_pickup_at);
        $this->product->fresh()->update(['status' => 'rejected']);
        $this->actingAs($this->seller)->patchJson(route('seller.orders.confirm-pickup', $order->id))->assertUnprocessable();
        $this->assertNull($shipment->fresh()->seller_confirmed_pickup_at);
        $this->assertSame('ready_for_pickup', $shipment->fresh()->shipping_status);
    }

    public function test_rejected_purchase_cannot_receive_logistics_pickup_approval(): void
    {
        [$purchase, $order, $shipment] = $this->pickupFixture();
        DB::table('shipments')->where('id', $shipment->id)->update(['pickup_approved_at' => null]);
        $this->product->fresh()->update(['status' => 'rejected']);
        $logistics = $this->person('buyer');
        DB::table('users')->where('id', $logistics->id)->update(['account_type' => 'logistics', 'is_logistics' => true, 'logistics_role' => 'admin', 'business_name' => 'Alpha']);
        $this->actingAs($logistics->fresh())->patchJson(route('logistics.requests.approve', $shipment->id))->assertUnprocessable();
        $this->assertNull($shipment->fresh()->pickup_approved_at);
    }

    public function test_cart_price_review_control_renders_with_valid_javascript(): void
    {
        $html = $this->actingAs($this->buyer)->get(route('buyer.cart'))->assertOk()->assertSee('Review current prices for GCash')->getContent();
        // Essential layout must survive a missing/cached cart stylesheet response.
        $this->assertMatchesRegularExpression('/<style id="buyer-cart-page-styles">[\s\S]*?\.cart-checkout-dock\[hidden\][\s\S]*?<\/style>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<link[^>]+href="[^"]*cart-page\.css/', $html);
        $this->assertMatchesRegularExpression('/class="cart-checkout-dock"[^>]*\bhidden\b/', $html);
        preg_match_all('#<script(?:\s[^>]*)?>(.*?)</script>#s', $html, $scripts);
        file_put_contents(storage_path('app/private/gcash-cart-inline.js'), implode("\n", $scripts[1]));
    }

    public function test_address_removal_json_preserves_ownership_and_default_address_rules(): void
    {
        $saved = \App\Models\BuyerAddress::create([
            'buyer_id' => $this->buyer->id, 'label' => 'Fictional work',
            'recipient_name' => 'Test Recipient', 'province' => 'Test Province',
            'municipality' => 'Test City', 'barangay' => 'Test Barangay', 'is_default' => false,
        ]);
        $default = \App\Models\BuyerAddress::create([
            'buyer_id' => $this->buyer->id, 'label' => 'Fictional home',
            'recipient_name' => 'Test Recipient', 'province' => 'Test Province',
            'municipality' => 'Test City', 'barangay' => 'Test Barangay', 'is_default' => true,
        ]);
        $foreign = $this->person('buyer');
        $this->actingAs($foreign)->deleteJson(route('buyer.addresses.destroy', $saved))->assertForbidden();
        $this->assertDatabaseHas('buyer_addresses', ['id' => $saved->id]);
        $this->actingAs($this->buyer)->deleteJson(route('buyer.addresses.destroy', $default))->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseHas('buyer_addresses', ['id' => $default->id]);
        $this->deleteJson(route('buyer.addresses.destroy', $saved))->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('buyer_addresses', ['id' => $saved->id]);
        $this->deleteJson(route('buyer.addresses.destroy', $saved))->assertNotFound();
    }

    public function test_delivery_address_mobile_number_requires_eleven_ascii_digits_starting_with_09(): void
    {
        $payload = ['label' => 'Fictional', 'recipient_name' => 'Test Recipient', 'province' => 'Test Province', 'municipality' => 'Test City', 'barangay' => 'Test Barangay'];
        foreach (['0912345678', '091234567890', '08123456789', '09abc456789', '09123-45678', '+639123456789', '０９１２３４５６７８９'] as $number) {
            $this->actingAs($this->buyer)->postJson(route('buyer.addresses.store'), $payload + ['contact_no' => $number])->assertUnprocessable()->assertJsonValidationErrors('contact_no');
        }
        $this->postJson(route('buyer.addresses.store'), $payload + ['contact_no' => '09123456789'])->assertOk()->assertJsonPath('address.contact_no', '09123456789');
        $this->assertDatabaseHas('buyer_addresses', ['buyer_id' => $this->buyer->id, 'contact_no' => '09123456789']);
        // The format rule does not change the existing optional-field policy.
        $this->postJson(route('buyer.addresses.store'), $payload + ['contact_no' => ''])->assertOk();
    }

    public function test_unchanged_normal_cart_with_legacy_empty_options_is_removed_once(): void
    {
        $this->cart->update(['variation_group' => '', 'variation_value' => '']);
        $p = $this->verified();
        $order = $this->flow->place($this->buyer, $p->id, $p->revision);
        $this->assertDatabaseMissing('cart_items', ['id' => $this->cart->id]);
        $this->assertSame($order->id, $this->flow->place($this->buyer, $p->id, $p->revision)->id);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_all_gcash_routes_reject_wrong_roles_and_foreign_participants(): void
    {
        $purchase = $this->proof($this->draft());
        $proof = DB::table('gcash_proofs')->first();
        Storage::disk('local')->put($proof->path, 'private evidence');
        $upload = fn () => UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        $requests = [
            'create' => ['items' => [$this->cart->id], 'payment_method' => 1, 'checkout_id' => (string) Str::uuid()],
            'refreshPrices' => ['items' => [$this->cart->id]],
            'instructions' => ['revision' => $purchase->revision, 'name' => 'Seller', 'number' => '09123456789'],
            'proof' => ['submission_id' => (string) Str::uuid(), 'receipt' => $upload()],
            'reportPaid' => ['reason' => 'Manual report'],
            'decide' => ['revision' => $purchase->revision, 'accept' => true, 'amount' => '220', 'reference' => 'MATRIX-123456'],
            'resolve' => ['revision' => $purchase->revision, 'reacquire' => true, 'amount' => '220', 'reference' => 'MATRIX-123456', 'reason' => 'Review'],
            'place' => ['revision' => $purchase->revision],
            'abandon' => ['revision' => $purchase->revision],
            'requestResolution' => ['revision' => $purchase->revision, 'reason' => 'Review'],
        ];
        $actors = ['buyer' => $this->buyer, 'seller' => $this->seller, 'foreign_buyer' => $this->person('buyer'), 'foreign_seller' => $this->person('seller')];
        foreach (['admin', 'logistics_admin', 'hub_staff', 'rider'] as $role) {
            $actor = $this->person('buyer');
            // Raw test fixtures avoid recruitment transitions while modeling existing approved accounts.
            DB::table('users')->where('id', $actor->id)->update(['account_type' => $role === 'admin' ? 'admin' : ($role === 'rider' ? 'rider' : 'logistics'), 'is_admin' => $role === 'admin', 'is_logistics' => in_array($role, ['logistics_admin', 'hub_staff']), 'logistics_role' => in_array($role, ['logistics_admin', 'hub_staff']) ? $role : null]);
            $actors[$role] = $actor->fresh();
        }
        foreach ($actors as $role => $actor) {
            $this->actingAs($actor);
            $review = in_array($role, ['buyer', 'seller', 'admin']);
            $this->getJson(route('gcash.index'))->assertStatus(in_array($actor->account_type, ['buyer', 'seller', 'admin']) ? 200 : 403);
            $this->getJson(route('gcash.show', $purchase->id))->assertStatus($review ? 200 : 403);
            $this->get(route('gcash.receipt', [$purchase->id, $proof->id]))->assertStatus($review ? 200 : 403);
            foreach ($requests as $action => $payload) {
                $permitted = in_array($action, ['create', 'refreshPrices', 'proof', 'reportPaid', 'place', 'abandon']) ? $role === 'buyer' : (in_array($action, ['instructions', 'decide', 'resolve']) ? $role === 'seller' : in_array($role, ['buyer', 'seller']));
                if ($permitted) {
                    continue;
                } // Valid owner transitions are exercised by the workflow tests.
                $url = route('gcash.'.$action, in_array($action, ['create', 'refreshPrices']) ? [] : [$purchase->id]);
                $expected = in_array($action, ['create', 'refreshPrices']) && $role === 'foreign_buyer' ? 422 : 403;
                $this->postJson($url, $payload)->assertStatus($expected);
            }
        }
        foreach ([$this->buyer, $this->seller] as $actor) {
            DB::table('users')->where('id', $actor->id)->update(['status' => 'suspended']);
            $this->actingAs($actor->fresh());
            $this->getJson(route('gcash.show', $purchase->id))->assertForbidden();
            $this->get(route('gcash.receipt', [$purchase->id, $proof->id]))->assertForbidden();
        }
        auth()->logout();
        $this->getJson(route('gcash.index'))->assertUnauthorized();
        $this->getJson(route('gcash.show', $purchase->id))->assertUnauthorized();
        $this->getJson(route('gcash.receipt', [$purchase->id, $proof->id]))->assertUnauthorized();
        foreach ($requests as $action => $payload) {
            $this->postJson(route('gcash.'.$action, in_array($action, ['create', 'refreshPrices']) ? [] : [$purchase->id]), $payload)->assertUnauthorized();
        }
        $this->assertSame('proof_submitted', $purchase->fresh()->payment_state);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertDatabaseCount('gcash_proofs', 1);
        $this->assertFalse(is_file(public_path('storage/'.$proof->path)));
    }

    public function test_receipt_size_spoofing_and_cross_purchase_ids_are_rejected(): void
    {
        $purchase = $this->draft();
        $purchase = $this->flow->instructions($this->seller, $purchase->id, $purchase->revision, 'Seller', '09123456789');
        $this->actingAs($this->buyer);
        Storage::disk('local')->put('oversized.pdf', '%PDF-1.4'.str_repeat('x', 5121 * 1024));
        Storage::disk('local')->put('fake.png', 'This is not an image');
        foreach ([new UploadedFile(Storage::disk('local')->path('oversized.pdf'), 'oversized.pdf', test: true), new UploadedFile(Storage::disk('local')->path('fake.png'), 'fake.png', test: true)] as $file) {
            $this->postJson(route('gcash.proof', $purchase->id), ['submission_id' => (string) Str::uuid(), 'receipt' => $file])->assertUnprocessable();
        }
        $this->assertDatabaseCount('gcash_proofs', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('gcash-proofs'));
        $purchase = $this->proof($purchase);
        $proof = DB::table('gcash_proofs')->first();
        Storage::disk('local')->put($proof->path, 'private evidence');
        $other = $this->draft();
        $this->get(route('gcash.receipt', [$other->id, $proof->id]))->assertNotFound();
        $this->get('/storage/'.$proof->path)->assertForbidden();
        $this->get('/gcash-proofs/'.basename($proof->path))->assertNotFound();
        $this->get(route('gcash.receipt', [$purchase->id, $proof->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_checkout_policy_accepts_only_active_cod_gcash_and_exact_legacy_cod(): void
    {
        $cases = [
            ['name' => 'Cash on delivery', 'type' => 'cod', 'is_active' => true, 'kind' => 'cod'],
            ['name' => '  CASH ON DELIVERY  ', 'type' => 'other', 'is_active' => true, 'kind' => 'cod'],
            ['name' => ' gcash ', 'type' => 'ewallet', 'is_active' => true, 'kind' => 'gcash'],
            ['name' => 'Cash bonus', 'type' => 'other', 'is_active' => true, 'kind' => null],
            ['name' => 'Cash on delivery', 'type' => 'bank', 'is_active' => true, 'kind' => null],
            ['name' => 'GCash', 'type' => 'other', 'is_active' => true, 'kind' => null],
            ['name' => 'GCash', 'type' => 'cod', 'is_active' => true, 'kind' => null],
            ['name' => 'PayMaya', 'type' => 'ewallet', 'is_active' => true, 'kind' => null],
            ['name' => 'Bank transfer', 'type' => 'bank', 'is_active' => true, 'kind' => null],
            ['name' => 'Cash on delivery', 'type' => 'cod', 'is_active' => false, 'kind' => null],
            ['name' => 'GCash', 'type' => 'ewallet', 'is_active' => false, 'kind' => null],
        ];
        foreach ($cases as $case) {
            $kind = $case['kind'];
            unset($case['kind']);
            $method = PaymentMethod::create($case);
            $this->assertSame($kind, $method->buyerCheckoutKind());
            $this->assertSame($kind !== null, PaymentMethod::forBuyerCheckout()->whereKey($method->id)->exists());
            if ($kind === null) {
                $this->actingAs($this->buyer)->postJson(route('buyer.checkout'), ['items' => [$this->cart->id], 'payment_method' => $method->id, 'checkout_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
            }
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('gcash_purchases', 0);
        $this->assertDatabaseCount('gcash_stock_allocations', 0);
        $this->assertDatabaseCount('gcash_voucher_allocations', 0);
        $this->assertDatabaseCount('gcash_purchase_history', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(100.0, (float) $this->cart->fresh()->price);
        $this->actingAs($this->buyer)->postJson(route('buyer.checkout'), ['items' => [$this->cart->id], 'payment_method' => 999999])->assertUnprocessable();
    }

    public function test_cart_hides_disallowed_methods_and_disables_checkout_without_eligible_methods(): void
    {
        $cod = PaymentMethod::create(['name' => 'Cash on delivery', 'type' => 'other', 'is_active' => true]);
        $bank = PaymentMethod::create(['name' => 'Disallowed bank fixture', 'type' => 'bank', 'is_active' => true]);
        $wallet = PaymentMethod::create(['name' => 'Disallowed wallet fixture', 'type' => 'ewallet', 'is_active' => true]);
        $this->actingAs($this->buyer)->get(route('buyer.cart'))->assertOk()->assertSee('GCash')->assertSee('Cash on delivery')->assertDontSee($bank->name)->assertDontSee($wallet->name);
        $this->assertSame('other', $cod->fresh()->type);
        PaymentMethod::whereIn('id', [1, $cod->id])->update(['is_active' => false]);
        $html = $this->get(route('buyer.cart'))->assertOk()->assertSee('No payment methods available.')->getContent();
        $this->assertMatchesRegularExpression('/id="checkoutButton"[^>]*disabled/', $html);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_buyer_checkout_recovers_committed_gcash_after_method_metadata_changes(): void
    {
        $payload = ['items' => [$this->cart->id], 'payment_method' => 1, 'checkout_id' => (string) Str::uuid()];
        $this->actingAs($this->buyer)->postJson(route('buyer.checkout'), $payload)->assertRedirect();
        $purchase = GcashPurchase::firstOrFail();
        $deadline = $purchase->expires_at->toISOString();
        PaymentMethod::whereKey(1)->update(['name' => 'Renamed historical method', 'type' => 'bank', 'is_active' => false]);
        $this->postJson(route('buyer.checkout'), $payload)->assertRedirect();
        $this->assertDatabaseCount('gcash_purchases', 1);
        $this->assertSame($deadline, $purchase->fresh()->expires_at->toISOString());
        $this->assertSame(8, $this->product->fresh()->stock);
        $purchase = $this->verified($purchase, 'COMMITTED-RETRY-123');
        $order = $this->flow->place($this->buyer, $purchase->id, $purchase->revision);
        $this->assertSame('GCash', $order->payment_method);
        $this->assertSame(1, $order->payment_method_id);
        $this->postJson(route('buyer.checkout'), $payload)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('gcash_purchases', 1);
        $this->assertSame(8, $this->product->fresh()->stock);
        $payload['checkout_id'] = (string) Str::uuid();
        $this->postJson(route('buyer.checkout'), $payload)->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    }

    public function test_reference_display_is_authorized_private_and_distinct_from_purchase_uuid(): void
    {
        $purchase = $this->draft();
        $this->actingAs($this->buyer)->get(route('gcash.show', $purchase->id))->assertOk()->assertDontSee('Recorded GCash transaction reference');
        $purchase = $this->verified($purchase, 'DISPLAY-123456');
        $admin = $this->person('admin');
        $admin->update(['is_admin' => true]);
        foreach ([$this->buyer, $this->seller, $admin] as $actor) {
            $response = $this->actingAs($actor)->get(route('gcash.show', $purchase->id))->assertOk()->assertSee('Recorded GCash transaction reference')->assertSee('DISPLAY-123456')->assertSee($purchase->id);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->getJson(route('gcash.show', $purchase->id))->assertOk()->assertJsonPath('purchase.payment_reference', 'DISPLAY-123456');
            $this->get(route('gcash.index'))->assertOk()->assertDontSee('DISPLAY-123456');
        }
        $this->actingAs($admin)->postJson(route('gcash.decide', $purchase->id), ['revision' => $purchase->revision, 'accept' => true, 'amount' => '220', 'reference' => 'ADMIN-REWRITE'])->assertForbidden();
        foreach ([$this->person('buyer'), $this->person('seller')] as $actor) {
            $this->actingAs($actor)->get(route('gcash.show', $purchase->id))->assertForbidden()->assertDontSee('DISPLAY-123456');
            $this->getJson(route('gcash.show', $purchase->id))->assertForbidden()->assertDontSee('DISPLAY-123456');
        }
        DB::table('users')->where('id', $this->buyer->id)->update(['status' => 'suspended']);
        $this->actingAs($this->buyer->fresh())->get(route('gcash.show', $purchase->id))->assertForbidden()->assertDontSee('DISPLAY-123456');
        auth()->logout();
        $this->getJson(route('gcash.show', $purchase->id))->assertUnauthorized()->assertDontSee('DISPLAY-123456');
    }

    public function test_reference_display_escapes_malformed_fixture_and_preserves_manual_resolution_state(): void
    {
        $purchase = $this->verified(null, 'MANUAL-REF-123');
        $purchase = $this->flow->requestResolution($this->buyer, $purchase->id, $purchase->revision, 'Manual payment issue');
        $this->actingAs($this->buyer)->get(route('gcash.show', $purchase->id))->assertOk()->assertSee('MANUAL-REF-123')->assertSee('Manual resolution');
        $unsafe = '<script>alert("fixture")</script>';
        // Explicit malformed historical fixture; normal workflow validation remains strict.
        DB::table('gcash_purchases')->where('id', $purchase->id)->update(['payment_reference' => $unsafe]);
        $response = $this->get(route('gcash.show', $purchase->id))->assertOk();
        $response->assertSee(e($unsafe), false)->assertDontSee($unsafe, false);
        $this->assertSame('manual_resolution', $purchase->fresh()->payment_state);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }
}
