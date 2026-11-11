<?php

namespace Tests\Feature;

use App\Models\{CartItem,GcashPurchase,Order,PaymentMethod,Product,Setting,User};
use App\Services\GcashWorkflow;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class AdminGcashFlowTest extends TestCase
{
    use WorkflowSchema;
    private User $buyer;
    private User $seller;
    private User $admin;
    private Product $product;
    private CartItem $cart;
    private GcashWorkflow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_14_000004_add_gcash_purchases.php'))->up();
        (require database_path('migrations/2026_11_08_120000_add_admin_gcash_review_flow.php'))->up();
        $this->buyer=$this->person('buyer');$this->seller=$this->person('seller');$this->admin=$this->person('admin');
        Setting::current()->update(['platform_gcash_name'=>'PocketFinds','platform_gcash_number'=>'09203783822']);
        PaymentMethod::create(['id'=>1,'name'=>'GCash','type'=>'ewallet','is_active'=>true]);
        $this->product=Product::create(['seller_id'=>$this->seller->id,'name'=>'Platform payment item','price'=>100,'stock'=>10,'status'=>'active']);
        $this->cart=CartItem::create(['buyer_id'=>$this->buyer->id,'product_id'=>$this->product->id,'seller_slug'=>$this->seller->username,'name'=>$this->product->name,'price'=>100,'qty'=>2]);
        $this->flow=app(GcashWorkflow::class);
    }

    private function person(string $role): User
    {
        return User::create(['account_type'=>$role,'is_admin'=>$role==='admin','username'=>uniqid('account'),'email'=>uniqid().'@example.test','status'=>'approved','given_names'=>ucfirst($role),'last_name'=>'Test','barangay'=>'B','municipality'=>'Pila','province'=>'Laguna','street'=>'Street']);
    }

    private function purchase(?string $checkout=null): GcashPurchase
    {
        return $this->flow->create($this->buyer,[$this->cart->id],1,$checkout??(string)Str::uuid())[0]->fresh();
    }

    private function proof(GcashPurchase $purchase): GcashPurchase
    {
        return $this->flow->proof($this->buyer,$purchase->id,(string)Str::uuid(),'gcash-proofs/'.Str::uuid().'.png','image/png',100,hash('sha256','receipt'));
    }

    public function test_order_is_placed_immediately_and_retry_never_duplicates_stock_or_order(): void
    {
        $checkout=(string)Str::uuid();$purchase=$this->purchase($checkout);
        $this->assertTrue($purchase->usesAdminReview());
        $this->assertSame('awaiting_payment',$purchase->payment_state);
        $this->assertSame('transferred',$purchase->reservation_state);
        $this->assertNull($purchase->verified_at);
        $this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('cart_items',0);
        $this->assertSame(8,$this->product->fresh()->stock);
        $this->assertSame($purchase->id,$this->purchase($checkout)->id);
        $this->assertDatabaseCount('orders',1);
        $this->travel(2)->hours();$this->flow->expire($purchase->id);
        $this->assertSame(8,$this->product->fresh()->stock);
        $this->assertSame('transferred',$purchase->fresh()->reservation_state);
    }

    public function test_only_admin_can_verify_and_seller_fulfillment_waits_for_approval(): void
    {
        $purchase=$this->proof($this->purchase());$order=Order::firstOrFail();
        $this->actingAs($this->buyer)->get(route('buyer.orders',['payment'=>'gcash']))->assertOk()
            ->assertSee('Continue payment')->assertDontSee('View order details')
            ->assertSee('Order summary')->assertSee('Customer name')->assertSee('Products')
            ->assertDontSee('data-approved-order-content tabindex="-1" hidden',false);
        $this->actingAs($this->seller)->patchJson(route('seller.orders.confirm',$order))->assertUnprocessable();
        $this->assertSame('placed',$order->fresh()->status);
        $data=['revision'=>$purchase->revision,'accept'=>1,'amount'=>$purchase->amount_due,'reference'=>'PLATFORM-123456','received_proof'=>UploadedFile::fake()->create('received.pdf',10,'application/pdf')];
        $this->postJson(route('gcash.decide',$purchase->id),$data)->assertForbidden();
        $this->actingAs($this->buyer)->postJson(route('gcash.decide',$purchase->id),$data)->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('gcash.decide',$purchase->id),array_replace($data,['amount'=>'1.00','reference'=>'PLATFORM-SHORT1']))
            ->assertOk()->assertJsonPath('purchase.payment_state','underpaid');
        $purchase=$purchase->fresh();
        $this->assertSame('1.00',$purchase->verified_amount);
        $this->assertDatabaseHas('notifications',['user_id'=>$this->buyer->id,'title'=>'GCash payment short — top-up needed']);
        $purchase=$this->proof($purchase);
        $data=['revision'=>$purchase->revision,'accept'=>1,'amount'=>$purchase->amount_due,'reference'=>'PLATFORM-FULL123','received_proof'=>UploadedFile::fake()->create('received-full.pdf',10,'application/pdf')];
        $this->actingAs($this->admin)->postJson(route('gcash.decide',$purchase->id),$data)->assertOk()->assertJsonPath('purchase.payment_state','order_placed');
        $this->assertEquals($this->admin->id,$purchase->fresh()->verified_by);
        $this->actingAs($this->buyer)->get(route('buyer.orders',['payment'=>'gcash']))->assertOk()
            ->assertSee('Payment approved')->assertSee('View order details')
            ->assertSee('data-approved-order-content tabindex="-1" hidden',false);
        $this->postJson(route('gcash.decide',$purchase->id),$data)->assertUnprocessable();
        $this->assertDatabaseHas('notifications',['user_id'=>$this->seller->id,'title'=>'Admin confirmed GCash payment']);
        $this->actingAs($this->seller)->patch(route('seller.orders.confirm',$order))->assertRedirect();
        $this->assertSame('confirmed',$order->fresh()->status);
        $this->assertSame(8,$this->product->fresh()->stock);
    }

    public function test_admin_received_evidence_is_required_and_refund_requires_buyer_details_and_proof(): void
    {
        Storage::fake('local');
        $purchase=$this->proof($this->purchase());
        $decision=['revision'=>$purchase->revision,'accept'=>1,'amount'=>number_format((float)$purchase->amount_due+50,2,'.',''),'reference'=>'EXCESS-123456'];
        $this->actingAs($this->admin)->postJson(route('gcash.decide',$purchase->id),$decision)->assertUnprocessable();
        $this->assertSame('proof_submitted',$purchase->fresh()->payment_state);
        $decision['received_proof']=UploadedFile::fake()->create('received.pdf',10,'application/pdf');
        $this->postJson(route('gcash.decide',$purchase->id),$decision)->assertOk();
        $this->assertSame('order_placed',$purchase->fresh()->payment_state);
        $this->assertEquals($purchase->amount_due,$purchase->fresh()->verified_amount);
        $evidence=DB::table('gcash_purchase_history')->where('purchase_id',$purchase->id)->where('event','admin_received_proof')->first();
        $this->assertNotNull($evidence);
        $details=['name'=>'Buyer Test','number'=>'09123456789'];
        $this->actingAs($this->seller)->postJson(route('gcash.refundDetails',$purchase->id),$details)->assertForbidden();
        $this->actingAs($this->buyer)->get(route('gcash.adminEvidence',[$purchase->id,$evidence->id]))->assertOk();
        $this->post(route('gcash.refundDetails',$purchase->id),$details)->assertRedirect();
        $refund=['repayment_reference'=>'REFUND-123456','confirm_repaid'=>1];
        $this->actingAs($this->admin)->postJson(route('gcash.refundOverpayment',$purchase->id),$refund)->assertUnprocessable();
        $refund['refund_proof']=UploadedFile::fake()->create('refund.pdf',10,'application/pdf');
        $this->post(route('gcash.refundOverpayment',$purchase->id),$refund)->assertRedirect();
        $this->assertDatabaseHas('gcash_purchase_history',['purchase_id'=>$purchase->id,'event'=>'admin_refund_proof']);
        $this->assertDatabaseHas('gcash_purchase_history',['purchase_id'=>$purchase->id,'event'=>'overpayment_refunded']);
        $this->postJson(route('gcash.refundOverpayment',$purchase->id),$refund)->assertUnprocessable();
    }

    public function test_rejection_allows_new_proof_and_platform_destination_is_immutable(): void
    {
        $purchase=$this->proof($this->purchase());
        $purchase=$this->flow->decide($this->admin,$purchase->id,$purchase->revision,false,null,'No matching transaction');
        $this->assertSame('rejected',$purchase->payment_state);
        $this->actingAs($this->seller)->postJson(route('gcash.instructions',$purchase->id),['revision'=>$purchase->revision,'name'=>'Other','number'=>'09123456789'])->assertForbidden();
        Setting::current()->update(['platform_gcash_number'=>'09123456789']);
        $this->assertSame('09203783822',$purchase->fresh()->instructions['number']);
        $purchase=$this->proof($purchase);
        $this->assertSame('proof_submitted',$purchase->payment_state);
        $this->assertDatabaseCount('orders',1);
    }

    public function test_admin_and_buyer_pages_render_and_seller_has_no_review_form(): void
    {
        $purchase=$this->proof($this->purchase());
        $this->actingAs($this->buyer)->get(route('gcash.show',$purchase->id))->assertOk()->assertSee('Waiting for admin payment approval')->assertSee('PocketFinds')->assertSee('09203783822')->assertDontSee('Ready to place your order');
        $this->actingAs($this->admin)->get(route('gcash.index'))->assertOk()->assertSee('Platform GCash account')->assertSee('Pending review');
        $this->get(route('gcash.show',$purchase->id))->assertOk()->assertSee('Verify Payment')->assertSee('Notify buyer')->assertDontSee('Receipt is unclear or incorrect')->assertSee('Proof of returned payment');
        $this->actingAs($this->seller)->get(route('gcash.show',[$purchase->id,'embedded'=>1]))->assertOk()->assertDontSee('Verify Payment')->assertDontSee('Notify buyer')->assertSee('Admin verification');
        $this->get(route('seller.orders'))->assertOk()->assertSee('Awaiting admin payment approval')->assertDontSee('> Confirm Order</button>',false);
        $this->postJson(route('gcash.platformAccount'),['platform_gcash_name'=>'Other','platform_gcash_number'=>'09123456789'])->assertForbidden();
    }

    public function test_admin_can_attach_return_payment_proof_when_rejecting_and_buyer_can_view_it(): void
    {
        Storage::fake('local');
        $purchase=$this->proof($this->purchase());
        $data=['revision'=>$purchase->revision,'accept'=>0,'decision'=>'other','reason'=>'Payment was returned to the buyer.','payment_returned'=>1,'refund_proof'=>UploadedFile::fake()->create('returned-payment.pdf',10,'application/pdf')];
        $this->actingAs($this->admin)->postJson(route('gcash.decide',$purchase->id),$data)->assertOk()->assertJsonPath('purchase.payment_state','rejected');
        $evidence=DB::table('gcash_purchase_history')->where('purchase_id',$purchase->id)->where('event','admin_rejection_refund_proof')->first();
        $this->assertNotNull($evidence);
        $this->assertStringContainsString('refund proof is attached',DB::table('gcash_purchase_history')->where('purchase_id',$purchase->id)->where('event','admin_rejected')->value('reason'));
        $this->assertDatabaseHas('notifications',['user_id'=>$this->buyer->id,'title'=>'GCash payment proof rejected']);
        $this->actingAs($this->buyer)->get(route('gcash.adminEvidence',[$purchase->id,$evidence->id]))->assertOk();
    }

    public function test_admin_can_notify_buyer_about_underpayment_using_the_uploaded_receipt_without_an_amount_field(): void
    {
        Storage::fake('local');
        // Platform administrator accounts are authorized by is_admin, even when
        // their account_type remains buyer from the account they originally registered.
        $this->admin->update(['account_type'=>'buyer']);
        $purchase=$this->proof($this->purchase());
        $data=['revision'=>$purchase->revision,'accept'=>0,'decision'=>'underpaid','reason'=>'The receipt shows less than the order total. Please pay the remaining balance.','received_proof'=>UploadedFile::fake()->create('actual-received.pdf',10,'application/pdf')];
        $this->actingAs($this->admin)->get(route('gcash.show',[$purchase->id,'embedded'=>1]))->assertOk()->assertSee('Notify buyer');
        $this->actingAs($this->admin)->postJson(route('gcash.decide',$purchase->id),$data)->assertOk()->assertJsonPath('purchase.payment_state','underpaid');
        $this->assertNull($purchase->fresh()->verified_amount);
        $this->assertDatabaseHas('notifications',['user_id'=>$this->buyer->id,'message'=>'The receipt shows less than the order total. Please pay the remaining balance.']);
        $this->assertDatabaseHas('gcash_purchase_history',['purchase_id'=>$purchase->id,'event'=>'admin_short_payment','reason'=>$data['reason']]);
        $proof=DB::table('gcash_purchase_history')->where('purchase_id',$purchase->id)->where('event','admin_received_proof')->first();
        $this->assertNotNull($proof);
    }

    public function test_seller_can_confirm_after_proof_based_admin_approval_without_a_typed_reference(): void
    {
        Storage::fake('local');
        $this->admin->update(['account_type'=>'buyer']);
        $purchase=$this->proof($this->purchase());
        $order=Order::where('gcash_purchase_id',$purchase->id)->firstOrFail();
        $this->assertFalse(Order::visibleToSeller()->whereKey($order->id)->exists());
        $this->assertDatabaseMissing('notifications',['user_id'=>$this->seller->id,'reference_id'=>$purchase->id]);
        $this->actingAs($this->seller)->patchJson(route('seller.orders.confirm',$order))->assertUnprocessable();
        $this->actingAs($this->admin)->postJson(route('gcash.decide',$purchase->id),[
            'revision'=>$purchase->revision,'accept'=>1,
            'received_proof'=>UploadedFile::fake()->create('confirmed-payment.pdf',10,'application/pdf'),
        ])->assertOk()->assertJsonPath('purchase.payment_state','order_placed');
        $this->assertNull($purchase->fresh()->payment_reference);
        $this->assertDatabaseHas('notifications',['user_id'=>$this->seller->id,'title'=>'Admin confirmed GCash payment','reference_id'=>$purchase->id]);
        $this->assertFalse($order->fresh()->awaitingAdminPayment());
        $this->assertTrue(Order::visibleToSeller()->whereKey($order->id)->exists());
        $this->actingAs($this->seller)->patchJson(route('seller.orders.confirm',$order))->assertRedirect();
        $this->assertSame('confirmed',$order->fresh()->status);
    }

    public function test_missing_platform_account_blocks_checkout_without_reserving_any_stock(): void
    {
        Setting::current()->update(['platform_gcash_number'=>null]);
        $this->actingAs($this->buyer)->postJson(route('gcash.create'),['items'=>[$this->cart->id],'payment_method'=>1,'checkout_id'=>(string)Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('orders',0);$this->assertDatabaseCount('gcash_purchases',0);
        $this->assertSame(10,$this->product->fresh()->stock);
    }

    private function refundsSchema(): void
    {
        Schema::create('cancellation_refunds',function(Blueprint $table){
            $table->id();$table->string('order_id')->unique();$table->integer('buyer_id');$table->integer('seller_id');
            $table->decimal('amount',12,2);$table->string('method');$table->string('status');$table->string('payment_reference')->nullable();
            $table->string('repayment_reference')->nullable();$table->timestamp('completed_at')->nullable();$table->timestamps();
        });
    }

    public function test_seller_cancellation_requires_admin_refund_proof_and_notifies_buyer(): void
    {
        Storage::fake('local');
        $this->refundsSchema();
        $this->admin->update(['account_type'=>'buyer']);
        $purchase=$this->proof($this->purchase());
        $this->actingAs($this->admin)->postJson(route('gcash.decide',$purchase->id),[
            'revision'=>$purchase->revision,'accept'=>1,
            'received_proof'=>UploadedFile::fake()->create('received.pdf',10,'application/pdf'),
        ])->assertOk();
        $order=Order::where('gcash_purchase_id',$purchase->id)->firstOrFail();
        $this->actingAs($this->seller)->patch(route('seller.orders.cancel',$order),['cancellation_reason'=>'Out of stock'])->assertRedirect();
        $this->assertSame('cancelled',$order->fresh()->status);
        $refund=DB::table('cancellation_refunds')->where('order_id',$order->id)->first();
        $this->assertNotNull($refund);
        $this->assertSame('pending',$refund->status);
        $data=['repayment_reference'=>'ADMIN-RETURN-123','confirm_repaid'=>1];
        $this->actingAs($this->seller)->postJson(route('account.cancellation-refunds.complete',$refund->id),$data)->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('account.cancellation-refunds.complete',$refund->id),$data)->assertUnprocessable();
        $this->assertDatabaseHas('cancellation_refunds',['id'=>$refund->id,'status'=>'pending']);
        $data['refund_proof']=UploadedFile::fake()->create('payment-returned.pdf',10,'application/pdf');
        $this->postJson(route('account.cancellation-refunds.complete',$refund->id),$data)->assertOk();
        $this->assertDatabaseHas('cancellation_refunds',['id'=>$refund->id,'status'=>'completed']);
        $proof=DB::table('gcash_purchase_history')->where('purchase_id',$purchase->id)->where('event','admin_cancellation_refund_proof')->first();
        $this->assertNotNull($proof);
        $this->assertDatabaseHas('notifications',['user_id'=>$this->buyer->id,'title'=>'Cancellation refund repaid']);
        $this->actingAs($this->buyer)->get(route('gcash.adminEvidence',[$purchase->id,$proof->id]))->assertOk();
    }

    public function test_unpaid_cancellation_restores_stock_and_cannot_be_approved(): void
    {
        $this->refundsSchema();$purchase=$this->proof($this->purchase());$order=Order::firstOrFail();
        $this->actingAs($this->buyer)->patchJson(route('buyer.orders.cancel',$order),['cancellation_reason'=>'Changed my mind'])->assertOk();
        $this->assertSame(10,$this->product->fresh()->stock);$this->assertDatabaseCount('cancellation_refunds',0);
        $this->actingAs($this->admin)->postJson(route('gcash.decide',$purchase->id),['revision'=>$purchase->fresh()->revision,'accept'=>1,'amount'=>'200.00','reference'=>'CANCELLED-123'])->assertUnprocessable();
    }

    public function test_paid_cancellation_is_repaid_by_admin_and_never_the_seller(): void
    {
        Storage::fake('local');
        $this->refundsSchema();$purchase=$this->proof($this->purchase());
        $this->flow->decide($this->admin,$purchase->id,$purchase->revision,true,'200.00',null,'PAID-123456');
        $order=Order::firstOrFail();
        $this->actingAs($this->buyer)->patchJson(route('buyer.orders.cancel',$order),['cancellation_reason'=>'Changed my mind'])->assertOk();
        $refund=DB::table('cancellation_refunds')->first();$this->assertNotNull($refund);
        $sellerNotice=DB::table('notifications')->where('user_id',$this->seller->id)->where('title','Order Cancelled')->where('reference_id',$order->id)->first();
        $this->assertNotNull($sellerNotice);
        $this->assertStringContainsString('Admin has been notified to refund',$sellerNotice->message);
        $this->actingAs($this->seller)->get(route('seller.notifications.open',$sellerNotice->id))->assertRedirect(route('seller.orders',['q'=>$order->order_number]));
        $this->assertDatabaseHas('notifications',['id'=>$sellerNotice->id,'is_read'=>true]);
        $data=['repayment_reference'=>'REPAY-123456','confirm_repaid'=>1];
        $this->actingAs($this->seller)->post(route('account.cancellation-refunds.complete',$refund->id),$data)->assertForbidden();
        $data['refund_proof']=UploadedFile::fake()->create('cancellation-refund.pdf',10,'application/pdf');
        $this->actingAs($this->admin)->post(route('account.cancellation-refunds.complete',$refund->id),$data)->assertRedirect();
        $this->assertDatabaseHas('cancellation_refunds',['id'=>$refund->id,'status'=>'completed','repayment_reference'=>null]);
        $this->assertSame(10,$this->product->fresh()->stock);
    }

    public function test_commerce_checkout_with_vouchers_creates_one_order_and_survives_retry(): void
    {
        foreach(['2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details','2026_10_17_000001_add_buyer_commerce_features'] as $file)(require database_path('migrations/'.$file.'.php'))->up();
        $this->product->update(['weight_grams'=>400]);
        $address=\App\Models\BuyerAddress::create(['buyer_id'=>$this->buyer->id,'region'=>'Test Region','postal_code'=>'0123','province'=>'Laguna','municipality'=>'Pila','barangay'=>'B','street'=>'Street','recipient_name'=>'Buyer Test','contact_no'=>'09171234567','is_default'=>true]);
        DB::table('commerce_shipping_rates')->insert(['seller_id'=>$this->seller->id,'region'=>'Test Region','first_kg'=>60,'additional_kg'=>20]);
        $data=['items'=>[$this->cart->id],'delivery_address_id'=>$address->id,'payment_method'=>1,'checkout_id'=>(string)Str::uuid()];
        $quote=app(\App\Services\BuyerCommerce::class)->quote($this->buyer,[$this->cart->id],$address->id);
        $data['quote_hash']=hash('sha256',json_encode($quote));
        $this->actingAs($this->buyer)->post(route('buyer.commerce.place'),$data)->assertRedirect(route('gcash.index'))->assertSessionHasNoErrors();
        $this->post(route('buyer.commerce.place'),$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('gcash_purchases',1);
        $purchase=GcashPurchase::firstOrFail();$this->assertSame('awaiting_payment',$purchase->payment_state);
        $this->assertSame(8,$this->product->fresh()->stock);$this->assertDatabaseCount('cart_items',0);
        $purchase=$this->proof($purchase);
        $this->flow->decide($this->admin,$purchase->id,$purchase->revision,true,$purchase->amount_due,null,'COMMERCE-123456');
        $this->assertSame('order_placed',$purchase->fresh()->payment_state);
    }
}
