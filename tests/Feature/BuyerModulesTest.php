<?php
namespace Tests\Feature;

use App\Models\BuyerAddress;
use App\Models\GcashPurchase;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\CancellationRefund;
use App\Services\ShopCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class BuyerModulesTest extends TestCase
{
    use WorkflowSchema;
    private User $buyer;
    private User $seller;
    protected function setUp(): void
    {
        parent::setUp(); $this->createWorkflowSchema();
        foreach(['2026_10_14_000004_add_gcash_purchases','2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details','2026_10_17_000001_add_buyer_commerce_features','2026_11_03_100000_add_buyer_module_tools'] as $file)(require database_path('migrations/'.$file.'.php'))->up();
        Storage::fake('profile_images'); Cache::flush(); Http::preventStrayRequests();
        \App\Models\PaymentMethod::create(['id'=>1,'name'=>'GCash','type'=>'ewallet','is_active'=>true]);
        $this->buyer=$this->person('buyer'); $this->seller=$this->person('seller');
        if(!\Illuminate\Support\Facades\Schema::hasTable('shop_follows'))DB::statement('CREATE TABLE shop_follows (id TEXT PRIMARY KEY, buyer_id INTEGER, seller_id INTEGER)');
    }
    private function person(string $role): User
    {
        return User::create(['account_type'=>$role,'username'=>uniqid('user'),'email'=>uniqid().'@gmail.com','given_names'=>'Ana','last_name'=>'Reyes','password'=>'Pass@2026','status'=>'approved','business_name'=>$role==='seller'?'Test Shop':null]);
    }
    private function address(User $owner, bool $default=false): BuyerAddress
    {
        return BuyerAddress::create(['buyer_id'=>$owner->id,'recipient_name'=>'Ana Reyes','contact_no'=>'09171234567','region'=>'Region IV-A','province'=>'Laguna','municipality'=>'Pila','barangay'=>'A','postal_code'=>'4010','is_default'=>$default]);
    }
    public function test_login_details_require_current_password_and_a_unique_username(): void
    {
        $payload=['username'=>'new.buyer','current_password'=>'Wrong@2026','password'=>'Updated@2026','password_confirmation'=>'Updated@2026'];
        $original=$this->buyer->username;
        $this->actingAs($this->buyer)->post(route('buyer.account.password'),$payload)->assertSessionHasErrors('current_password');
        $this->assertSame($original,$this->buyer->fresh()->username);
        $payload['current_password']='Pass@2026';$payload['username']=$this->seller->username;
        $this->postJson(route('buyer.account.password'),$payload)->assertUnprocessable()->assertJsonValidationErrors('username');
        $payload['username']='new.buyer';
        $this->post(route('buyer.account.password'),$payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('new.buyer',$this->buyer->fresh()->username);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Updated@2026',$this->buyer->fresh()->password));
    }
    public function test_email_verification_in_place_is_bound_to_the_requested_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $email='updated-buyer@gmail.com';
        $this->actingAs($this->buyer)->postJson(route('buyer.commerce.email.code'), ['email'=>$email])
            ->assertOk()->assertJson(['email'=>$email]);
        $this->assertNotSame($email,$this->buyer->fresh()->email);
        Cache::put('buyer-email-change:'.$this->buyer->id,['email'=>$email,'hash'=>\Illuminate\Support\Facades\Hash::make('123456')],now()->addMinutes(5));
        $this->postJson(route('buyer.commerce.email.verify'),['email'=>'different@gmail.com','otp'=>'123456'])->assertUnprocessable();
        $this->assertNotSame($email,$this->buyer->fresh()->email);
        $this->postJson(route('buyer.commerce.email.verify'),['email'=>$email,'otp'=>'123456'])->assertOk()->assertJson(['email'=>$email]);
        $this->assertSame($email,$this->buyer->fresh()->email);
        $this->assertNotNull($this->buyer->fresh()->email_verified_at);
    }
    private function mockAccountGoogleEmail(string $email): void
    {
        $google=new \Laravel\Socialite\Two\User;
        $google->map(['id'=>'account-google-user','email'=>$email]);$google->user=['email_verified'=>true];
        $provider=\Mockery::mock();$provider->shouldReceive('setHttpClient')->once()->andReturnSelf();$provider->shouldReceive('user')->once()->andReturn($google);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
    public function test_google_verifies_a_new_account_email_without_changing_username_or_logged_in_user(): void
    {
        $username=$this->buyer->username;
        $this->mockAccountGoogleEmail('new-google-buyer@gmail.com');
        $this->actingAs($this->buyer)->withSession(['oauth_intent'=>'account-email','oauth_account_email_owner'=>$this->buyer->id,'oauth_account_email_expires'=>now()->addMinutes(10)->timestamp])
            ->get(route('google.callback',['code'=>'test']))->assertRedirect(route('buyer.account').'#account-security')->assertSessionHas('account_google_email','new-google-buyer@gmail.com');
        $this->assertAuthenticatedAs($this->buyer);
        $this->assertSame('new-google-buyer@gmail.com',$this->buyer->fresh()->email);
        $this->assertSame($username,$this->buyer->fresh()->username);
    }
    public function test_google_account_email_cannot_take_an_email_registered_to_another_account(): void
    {
        $email=$this->buyer->email;$this->mockAccountGoogleEmail($this->seller->email);
        $this->actingAs($this->buyer)->withSession(['oauth_intent'=>'account-email','oauth_account_email_owner'=>$this->buyer->id,'oauth_account_email_expires'=>now()->addMinutes(10)->timestamp])
            ->get(route('google.callback',['code'=>'test']))->assertSessionHasErrors('email');
        $this->assertSame($email,$this->buyer->fresh()->email);
    }
    public function test_buyer_and_seller_can_upload_real_profile_images_but_not_documents(): void
    {
        foreach([$this->buyer,$this->seller] as $u){
            $this->actingAs($u)->post(route('account.photo'),['profile_picture'=>UploadedFile::fake()->image('photo.jpg')])->assertRedirect()->assertSessionHasNoErrors();
            Storage::disk('profile_images')->assertExists($u->fresh()->profile_picture);
            $this->postJson(route('account.photo'),['profile_picture'=>UploadedFile::fake()->create('bad.pdf',10,'application/pdf')])->assertUnprocessable();
        }
    }
    public function test_editing_delivery_address_keeps_existing_order_snapshot_and_enforces_ownership(): void
    {
        $address=$this->address($this->buyer,true); $snapshot=$address->toShippingArray();
        $order=Order::create(['order_number'=>'ADDRESS-1','buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'placed','items'=>[],'shipping_address'=>$snapshot,'subtotal'=>10,'total'=>10]);
        $data=$address->toArray();$data['street']='New street';
        $this->actingAs($this->buyer)->patchJson(route('buyer.addresses.update',$address),$data)->assertOk();
        $this->assertSame('New street',$address->fresh()->street);$this->assertSame($snapshot,$order->fresh()->shipping_address);
        $this->actingAs($this->person('buyer'))->patchJson(route('buyer.addresses.update',$address),$data)->assertForbidden();
    }
    public function test_default_address_can_be_switched_and_previous_one_deleted(): void
    {
        $a=$this->address($this->buyer,true);$b=$this->address($this->buyer);
        $this->actingAs($this->buyer)->deleteJson(route('buyer.addresses.destroy',$a))->assertUnprocessable();
        $this->post(route('buyer.addresses.default',$b))->assertRedirect();
        $this->assertSame(1,BuyerAddress::where('buyer_id',$this->buyer->id)->where('is_default',true)->count());
        $this->deleteJson(route('buyer.addresses.destroy',$a))->assertOk();
        $this->actingAs($this->person('buyer'))->post(route('buyer.addresses.default',$b))->assertForbidden();
    }
    public function test_category_depth_ownership_and_parent_filtering(): void
    {
        $product=Product::create(['seller_id'=>$this->seller->id,'name'=>'Basket','price'=>100,'stock'=>10,'status'=>'active']);
        $this->actingAs($this->seller)->post(route('seller.shop-categories.save'),['name'=>'Home'])->assertRedirect()->assertSessionHasNoErrors();
        $parent=DB::table('shop_categories')->value('id');
        $this->post(route('seller.shop-categories.save'),['name'=>'Baskets','parent_id'=>$parent])->assertRedirect()->assertSessionHasNoErrors();
        $child=DB::table('shop_categories')->where('parent_id',$parent)->value('id');
        $this->postJson(route('seller.shop-categories.save'),['name'=>'Third level','parent_id'=>$child])->assertUnprocessable();
        $this->post(route('seller.shop-categories.assign',$product),['categories'=>[$child]])->assertRedirect();
        $items=[['id'=>$product->id],['id'=>'other']];
        $this->assertCount(1,app(ShopCatalog::class)->filter($items,$this->seller->id,(string)$parent));
        $this->actingAs($this->person('seller'))->post(route('seller.shop-categories.assign',$product),['categories'=>[$child]])->assertForbidden();
        $this->actingAs($this->seller)->delete(route('seller.shop-categories.delete',$parent))->assertRedirect();
        $this->assertSame(0,DB::table('shop_category_products')->count());$this->assertNotNull($product->fresh());
    }
    public function test_order_details_show_variant_thumbnail_without_column_headings(): void
    {
        $product = Product::create(['seller_id'=>$this->seller->id, 'name'=>'Pink bag', 'price'=>100, 'stock'=>5, 'status'=>'active', 'variations'=>[['name'=>'Color', 'options'=>[['value'=>'Pink', 'image'=>'products/pink.jpg']]]]]);
        $order = Order::create(['order_number'=>'IMAGE-1', 'buyer_id'=>$this->buyer->id, 'seller_id'=>$this->seller->id, 'status'=>'placed', 'subtotal'=>200, 'total'=>200, 'items'=>[['product_id'=>$product->id, 'name'=>'Pink bag', 'variation_group'=>'Color', 'variation_value'=>'Pink', 'price'=>100, 'qty'=>2]]]);
        $this->assertStringEndsWith('/products/pink.jpg', $order->itemsWithImages()[0]['img']);
        $this->actingAs($this->buyer)->get(route('buyer.orders'))->assertOk()->assertSee('products/pink.jpg')->assertSee('Color: Pink')->assertDontSee('Count × original price')->assertDontSee('Product / variant');
        $order->items = [array_merge($order->items[0], ['img'=>'https://example.com/saved-pink.jpg'])];
        $this->assertSame('https://example.com/saved-pink.jpg', $order->itemsWithImages()[0]['img']);
    }

    public function test_review_variation_is_read_from_order_even_if_product_changes(): void
    {
        $order=Order::create(['order_number'=>'REVIEW-1','buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'completed','subtotal'=>100,'total'=>100,'items'=>[['product_id'=>'old-product','variation_group'=>'Color / Size','variation_value'=>'Pink / M','price'=>100,'qty'=>1]]]);
        $review=Review::create(['order_id'=>$order->id,'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'product_id'=>'old-product','item_key'=>'0','rating'=>5]);
        $this->assertSame('Color / Size: Pink / M',$review->variationLabel());
    }
    public function test_review_modal_uses_the_order_details_product_summary(): void
    {
        $product=Product::create(['seller_id'=>$this->seller->id,'name'=>'Review summary product','price'=>100,'stock'=>5,'status'=>'active','image'=>'products/review.jpg']);
        $order=Order::create(['order_number'=>'REVIEW-SUMMARY','buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'completed','subtotal'=>100,'total'=>100,'items'=>[['product_id'=>$product->id,'name'=>$product->name,'variation_group'=>'Size','variation_value'=>'M','price'=>100,'qty'=>1]]]);
        $this->actingAs($this->buyer);
        $html=view('buyer.partials.order-reviews',['order'=>$order])->render();
        $this->assertStringContainsString('order-summary-product-image',$html);
        $this->assertStringContainsString('products/review.jpg',$html);
        $this->assertStringContainsString('Size: M',$html);
        $this->assertStringContainsString('aria-label="5 stars"',$html);
        $this->assertStringContainsString('name="item_key" value="0"',$html);
    }
    public function test_paid_cancellation_creates_one_refund_and_only_own_seller_can_record_repayment(): void
    {
        $purchase=GcashPurchase::create(['id'=>(string)Str::uuid(),'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'checkout_id'=>(string)Str::uuid(),'request_hash'=>str_repeat('a',64),'payment_method_id'=>1,'snapshot'=>['items'=>[]],'amount_due'=>150,'verified_amount'=>150,'verified_at'=>now(),'payment_reference'=>'PAID12345','payment_state'=>'order_placed','reservation_state'=>'transferred']);
        $order=Order::create(['order_number'=>'REFUND-1','buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'placed','gcash_purchase_id'=>$purchase->id,'subtotal'=>100,'total'=>150,'items'=>[]]);
        $this->mock(\App\Services\GcashWorkflow::class,function($mock){
            $mock->shouldReceive('releaseOrder')->once();
            $mock->shouldReceive('assertOrderOperation')->andReturnNull();
        });
        DB::transaction(function()use($order){
            $this->assertTrue($order->cancelAndRestock('Changed my mind',null,$this->buyer->id));
            $this->assertFalse($order->cancelAndRestock('Changed my mind',null,$this->buyer->id));
        });
        $this->assertSame(1,DB::table('cancellation_refunds')->count());$refund=DB::table('cancellation_refunds')->first();$this->assertEquals(150,$refund->amount);
        $data=['repayment_reference'=>'REPAY12345','confirm_repaid'=>1];
        $this->actingAs($this->person('seller'))->post(route('account.cancellation-refunds.complete',$refund->id),$data)->assertNotFound();
        $this->actingAs($this->buyer)->post(route('account.cancellation-refunds.complete',$refund->id),$data)->assertForbidden();
        $this->actingAs($this->seller)->post(route('account.cancellation-refunds.complete',$refund->id),$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed',DB::table('cancellation_refunds')->value('status'));
    }
    public function test_cod_cancellation_does_not_create_a_refund(): void
    {
        $order=Order::create(['order_number'=>'COD-1','buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'cancelled','subtotal'=>100,'total'=>100,'items'=>[]]);
        DB::transaction(fn()=>app(CancellationRefund::class)->initiate($order));$this->assertSame(0,DB::table('cancellation_refunds')->count());
    }
    public function test_retiring_existing_shipping_vouchers_preserves_discounts_and_claim_history(): void
    {
        $common=['seller_id'=>$this->seller->id,'discount_type'=>'fixed','amount'=>20,'minimum_spend'=>0,'starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'usage_limit'=>10,'per_buyer_limit'=>1,'is_active'=>true];
        $shipping=DB::table('commerce_vouchers')->insertGetId($common+['code'=>'SHIP20','kind'=>'shipping']);
        $discount=DB::table('commerce_vouchers')->insertGetId($common+['code'=>'OFF20','kind'=>'store']);
        DB::table('commerce_claims')->insert(['buyer_id'=>$this->buyer->id,'voucher_id'=>$shipping]);
        (require database_path('migrations/2026_11_07_100000_disable_existing_free_shipping_vouchers.php'))->up();
        $this->assertFalse((bool)DB::table('commerce_vouchers')->where('id',$shipping)->value('is_active'));
        $this->assertTrue((bool)DB::table('commerce_vouchers')->where('id',$discount)->value('is_active'));
        $this->assertDatabaseHas('commerce_claims',['buyer_id'=>$this->buyer->id,'voucher_id'=>$shipping]);
    }
    public function test_following_and_management_screens_are_reachable_and_scoped(): void
    {
        \App\Models\ShopFollow::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id]);
        $this->actingAs($this->buyer)->get(route('buyer.following'))->assertRedirect(route('buyer.account').'#following-shops');
        $this->get(route('buyer.account'))->assertOk()->assertSee('following-shops')->assertSee('Test Shop')->assertSee('Unfollow');
        $this->get(route('account.cancellation-refunds'))->assertOk()->assertSee('No cancellation refunds.');
        $this->actingAs($this->person('buyer'))->get(route('buyer.account'))->assertOk()->assertSee('You aren’t following any shops yet.');
        $this->actingAs($this->seller)->get(route('seller.shop-categories.index'))->assertOk()->assertSee('Add category');
        $this->get(route('account.cancellation-refunds'))->assertRedirect(route('seller.orders',['status'=>'refunds']));
    }
    public function test_product_and_shop_show_claimable_vouchers_and_flash_details(): void
    {
        $id=DB::table('commerce_vouchers')->insertGetId(['code'=>'SHOP50','kind'=>'store','seller_id'=>$this->seller->id,'discount_type'=>'fixed','amount'=>50,'minimum_spend'=>100,'starts_at'=>now()->subHour(),'ends_at'=>now()->addDay(),'usage_limit'=>10,'per_buyer_limit'=>1]);
        $this->actingAs($this->buyer);
        $this->view('partials.shop-catalog-tools',['seller'=>$this->seller,'buyerMode'=>true])->assertSee('SHOP50')->assertSee('Claim');
        $product=['seller_id'=>$this->seller->id,'flash_sale'=>['ends_at'=>now()->addHour()->toIso8601String(),'remaining'=>7]];
        $this->view('partials.product-promotions',compact('product'))->assertSee('7 flash-sale units remaining')->assertSee('data-flash-countdown',false)->assertSee('SHOP50');
        $this->post(route('buyer.commerce.claim',$id))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('buyer.commerce.claim',$id))->assertRedirect();
        $this->assertSame(1,DB::table('commerce_claims')->where('buyer_id',$this->buyer->id)->where('voucher_id',$id)->count());
        $this->view('partials.shop-catalog-tools',['seller'=>$this->seller,'buyerMode'=>true])->assertSee('Claimed');
    }
    public function test_product_cannot_be_assigned_to_another_sellers_category_or_more_than_three(): void
    {
        $product=Product::create(['seller_id'=>$this->seller->id,'name'=>'Basket','price'=>100,'stock'=>10,'status'=>'active']);
        $other=$this->person('seller');
        $foreign=DB::table('shop_categories')->insertGetId(['seller_id'=>$other->id,'name'=>'Private']);
        $this->actingAs($this->seller)->postJson(route('seller.shop-categories.assign',$product),['categories'=>[$foreign]])->assertForbidden();
        $this->postJson(route('seller.shop-categories.assign',$product),['categories'=>[1,2,3,4]])->assertUnprocessable()->assertJsonValidationErrors('categories');
    }
    public function test_delivery_address_can_save_without_a_postal_code_and_wishlist_supports_ajax(): void
    {
        $address=$this->address($this->buyer,true);$data=$address->toArray();$data['postal_code']='';
        $this->actingAs($this->buyer)->patchJson(route('buyer.addresses.update',$address),$data)->assertOk();
        $this->assertNull($address->fresh()->postal_code);
        $product=Product::create(['seller_id'=>$this->seller->id,'name'=>'Basket','price'=>100,'stock'=>10,'status'=>'active']);
        $this->postJson(route('buyer.commerce.wishlist',$product->id),['saved'=>true])->assertOk()->assertJson(['saved'=>true]);
        $this->postJson(route('buyer.commerce.wishlist',$product->id),['saved'=>true])->assertOk();
        $this->assertSame(1,DB::table('commerce_wishlist')->where('buyer_id',$this->buyer->id)->count());
        $this->postJson(route('buyer.commerce.wishlist',$product->id),['saved'=>false])->assertOk()->assertJson(['saved'=>false]);
    }
}
