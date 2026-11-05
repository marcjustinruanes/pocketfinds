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
    public function test_review_variation_is_read_from_order_even_if_product_changes(): void
    {
        $order=Order::create(['order_number'=>'REVIEW-1','buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'status'=>'completed','subtotal'=>100,'total'=>100,'items'=>[['product_id'=>'old-product','variation_group'=>'Color / Size','variation_value'=>'Pink / M','price'=>100,'qty'=>1]]]);
        $review=Review::create(['order_id'=>$order->id,'buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id,'product_id'=>'old-product','item_key'=>'0','rating'=>5]);
        $this->assertSame('Color / Size: Pink / M',$review->variationLabel());
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
    public function test_following_and_management_screens_are_reachable_and_scoped(): void
    {
        \App\Models\ShopFollow::create(['buyer_id'=>$this->buyer->id,'seller_id'=>$this->seller->id]);
        $this->actingAs($this->buyer)->get(route('buyer.following'))->assertRedirect(route('buyer.account').'#following-shops');
        $this->get(route('buyer.account'))->assertOk()->assertSee('following-shops')->assertSee('Test Shop')->assertSee('Unfollow');
        $this->get(route('account.cancellation-refunds'))->assertOk()->assertSee('No cancellation refunds.');
        $this->actingAs($this->person('buyer'))->get(route('buyer.account'))->assertOk()->assertSee('You aren’t following any shops yet.');
        $this->actingAs($this->seller)->get(route('seller.shop-categories.index'))->assertOk()->assertSee('Add category');
        $this->get(route('account.cancellation-refunds'))->assertOk();
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
