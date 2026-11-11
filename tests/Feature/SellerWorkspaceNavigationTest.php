<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class SellerWorkspaceNavigationTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        $this->actingAs(User::create(['account_type'=>'seller','username'=>'workspace-shop','email'=>'workspace@gmail.com','given_names'=>'Test','last_name'=>'Seller','password'=>'Pass@2026','status'=>'approved','business_name'=>'Workspace Shop']));
    }

    public function test_six_sidebar_sections_replace_individual_tool_links(): void
    {
        request()->setRouteResolver(fn () => Route::getRoutes()->getByName('seller.feedback'));
        $html = view('seller.partials.workspace-navigation')->render();
        $this->assertSame(6, substr_count($html, 'class="nav-item '));
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        foreach (['Dashboard','Orders','Products','Promotions','Finance','My Account'] as $label) $this->assertStringContainsString($label, $html);
        foreach (['Messages','Announcements','Shipping Payments','COD Payouts'] as $label) $this->assertStringNotContainsString($label, $html);
        $tabs = view('seller.partials.workspace-tabs')->render();
        foreach (['Inventory','Shop categories','Reviews'] as $label) $this->assertStringContainsString($label, $tabs);
    }

    public function test_finance_tools_remain_accessible_under_one_section(): void
    {
        request()->setRouteResolver(fn () => Route::getRoutes()->getByName('seller.shipping'));
        $html = view('seller.partials.workspace-tabs')->render();
        foreach (['Shipping payments','COD payouts','Reports'] as $label) $this->assertStringContainsString($label, $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    public function test_reviews_page_renders_database_date_strings(): void
    {
        \Illuminate\Support\Facades\DB::table('reviews')->insert([
            'id'=>'review-date-regression', 'seller_id'=>auth()->id(), 'buyer_id'=>auth()->id(),
            'rating'=>5, 'comment'=>'Great product', 'created_at'=>'2026-11-10 12:30:00',
        ]);

        $this->get(route('seller.feedback'))->assertOk()
            ->assertSee('Great product')->assertSee('Nov 10, 2026')
            ->assertSee('datetime="2026-11-10"', false);
    }

    public function test_monthly_rating_chart_keeps_all_five_series_and_zero_months(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-11 12:00:00'));
        foreach([['june',3,'2026-06-01 00:00:00'],['september',5,'2026-09-13 12:00:00'],['october',1,'2026-10-01 12:00:00'],['outside',2,'2026-05-31 12:00:00']] as [$id,$rating,$date]){
            \Illuminate\Support\Facades\DB::table('reviews')->insert(['id'=>$id,'seller_id'=>auth()->id(),'buyer_id'=>auth()->id(),'rating'=>$rating,'created_at'=>$date]);
        }
        $this->get(route('seller.feedback',['star'=>5]))->assertOk()->assertSee('Monthly ratings')->assertViewHas('reviewMonths',function($months){
            $this->assertCount(6,$months);
            $this->assertSame('2026-06',$months[0]['key']);
            $this->assertSame(1,$months[0]['counts'][3]);
            $this->assertSame([1=>0,2=>0,3=>0,4=>0,5=>0],$months[1]['counts']);
            $this->assertSame(1,$months[3]['counts'][5]);
            $this->assertSame(1,$months[4]['counts'][1]);
            $this->assertSame('2026-11',$months[5]['key']);
            return true;
        });
    }

    public function test_embedded_messages_render_without_the_seller_sidebar(): void
    {
        $this->get(route('seller.messages', ['embedded'=>1]))->assertOk()->assertSee('seller-message-embedded')->assertSee('chat-shell')->assertDontSee('id="sidebar"', false)->assertDontSee('sellerMessagesWidget');
    }

    public function test_seller_uses_the_shared_assistant_dock_with_workspace_shortcuts(): void
    {
        $html=view('buyer.partials.assistant',['assistantGuest'=>true,'assistantSeller'=>true])->render();
        $this->assertStringContainsString('aria-label="Ask PocketFinds"',$html);
        $this->assertStringContainsString(route('guest.assistant.send'),$html);
        $this->assertStringContainsString(route('seller.inventory'),$html);
        $this->assertStringContainsString(route('seller.orders'),$html);
        $this->assertStringNotContainsString('data-share-orders',$html);
    }

    public function test_order_categories_preserve_filters_and_mark_one_active_status(): void
    {
        request()->setRouteResolver(fn () => Route::getRoutes()->getByName('seller.orders'));
        request()->query->add(['q'=>'ORDER-123','from'=>'2026-11-01']);
        $html = view('seller.partials.order-categories', ['status'=>'preparing','statusCounts'=>collect(['placed'=>2,'confirmed'=>2,'preparing'=>3]),'returnRequests'=>collect()])->render();
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertStringContainsString('Preparing (5)', $html);
        $this->assertStringContainsString('All (7)', $html);
        $this->assertStringNotContainsString('status=confirmed', $html);
        $this->assertStringContainsString('q=ORDER-123', $html);
        $this->assertStringContainsString('from=2026-11-01', $html);
        $this->assertStringContainsString('status=preparing', $html);
        $this->assertStringContainsString('Return / Refund', $html);
        $this->assertStringNotContainsString('status=returned', $html);
        $this->assertStringNotContainsString('Cancellation refunds', $html);
    }

    public function test_refund_category_uses_the_combined_distinct_order_count(): void
    {
        request()->setRouteResolver(fn () => Route::getRoutes()->getByName('seller.orders'));
        $html=view('seller.partials.order-categories', ['status'=>'refunds','statusCounts'=>collect(['returned'=>2,'cancelled'=>3]),'returnRequests'=>collect(),'refundOrderCount'=>4])->render();
        $this->assertStringContainsString('Return / Refund (4)', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->get(route('seller.orders',['status'=>'returned']))->assertRedirect(route('seller.orders',['status'=>'refunds']));
        $this->get(route('account.cancellation-refunds'))->assertRedirect(route('seller.orders',['status'=>'refunds']));
    }

    public function test_marking_notifications_read_returns_json_without_a_flash_message(): void
    {
        $db=\Illuminate\Support\Facades\DB::table('notifications');
        $db->insert([
            ['id'=>'own-alert','user_id'=>auth()->id(),'title'=>'Order update','message'=>'Ready','is_read'=>false],
            ['id'=>'other-alert','user_id'=>auth()->id()+100,'title'=>'Other update','message'=>'Ready','is_read'=>false],
        ]);
        $this->postJson(route('account.notifications.read-all'))->assertOk()->assertJson(['read'=>true])->assertSessionMissing('success');
        $this->assertEquals(1, (clone $db)->where('id','own-alert')->value('is_read'));
        $this->assertEquals(0, (clone $db)->where('id','other-alert')->value('is_read'));
        $db->where('id','own-alert')->update(['is_read'=>false]);
        $this->postJson(route('seller.notifications.read'))->assertOk()->assertJson(['read'=>true]);
        $this->assertEquals(1, (clone $db)->where('id','own-alert')->value('is_read'));
    }

    public function test_waybill_button_is_only_visible_during_preparation(): void
    {
        $shipment=(object)['shipping_status'=>'in_transit','picked_up_at'=>now()];
        foreach(['placed','confirmed','preparing','ready_for_pickup','picked_up','out_for_delivery','delivered','completed','cancelled','returned'] as $status){
            $order=(new \App\Models\Order())->forceFill(['id'=>1,'order_number'=>'TEST-1','status'=>$status]);
            $html=view('seller.partials.order-actions',compact('order','shipment'))->render();
            $this->assertSame($status==='preparing',str_contains($html,'data-seller-summary-waybill'),$status);
        }
    }
}
