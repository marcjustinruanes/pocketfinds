<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function renderNavigation(string $role, string $routeName, ?string $viewOverride = null): DOMXPath
    {
        $hub = LogisticsHub::firstOrCreate(['company_name' => 'Navigation Company', 'province' => 'Laguna', 'municipality' => 'Pila'], ['is_operational' => true]);
        $user = User::create(['email' => uniqid().'@example.test', 'username' => uniqid('nav'), 'given_names' => 'Navigation', 'last_name' => 'Fixture',
            'account_type' => in_array($role, ['logistics_admin', 'hub_staff']) ? 'logistics' : $role,
            'is_admin' => $role === 'admin', 'is_logistics' => in_array($role, ['logistics_admin', 'hub_staff']),
            'logistics_role' => $role === 'logistics_admin' ? 'admin' : ($role === 'hub_staff' ? 'hub_staff' : null),
            'business_name' => $hub->company_name, 'logistics_hub_id' => $hub->id, 'status' => 'approved', 'password' => 'NavigationPassword123']);
        $this->actingAs($user);
        if ($role === 'seller') {
            DB::table('notifications')->insert(['id' => uniqid(), 'user_id' => $user->id, 'title' => 'Fixture', 'message' => 'Navigation notification', 'is_read' => false, 'created_at' => now()]);
        }
        $route = clone app('router')->getRoutes()->getByName($routeName);
        $request = Request::create('/'.str_replace(['{id}', '{userId}', '{user}', '{order}', '{slug}'], ['fixture', '1', '1', 'fixture', 'shop'], $route->uri()));
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
        $view = $role === 'buyer' ? 'buyer.partials.storefront-header' : (in_array($role, ['hub_staff', 'logistics_admin']) ? 'logistics.partials.sidebar' : $role.'.partials.sidebar');
        $html = view($viewOverride ?? $view, ['notifications' => collect(), 'cartCount' => 3, 'unreadMessages' => 2, 'pendingRegistrations' => 4, 'openDisputes' => 1,
            'pendingDeliveries' => 3, 'pendingRiders' => 2, 'pendingStaff' => 2, 'pendingVehicles' => 1, 'pendingHubTransferRequests' => 2, 'unassigned' => 2])->render();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);

        return new DOMXPath($document);
    }

    public function test_all_six_roles_have_the_approved_groups_and_no_empty_groups(): void
    {
        $groups = ['admin' => ['Accounts & Recruitment', 'Marketplace & Resources', 'Compliance', 'Finance & Reports', 'Communications', 'System'],
            'buyer' => ['Shopping', 'Orders', 'Communications', 'Support'], 'seller' => ['Orders', 'Store', 'Tracking & Reports', 'Communications'],
            'logistics_admin' => ['Operations', 'Personnel & Resources', 'Tracking & Reports', 'Administration'],
            'hub_staff' => ['Operations', 'Hub Resources', 'Tracking', 'Communications'],
            'rider' => ['Pickup Operations', 'Transfer Operations', 'Delivery Operations', 'Earnings & History', 'Communications']];
        foreach ($groups as $role => $expected) {
            $prefix = in_array($role, ['hub_staff', 'logistics_admin']) ? 'logistics' : $role;
            $xpath = $this->renderNavigation($role, $prefix.'.dashboard');
            $labels = [];
            foreach ($xpath->query('//button[@data-navigation-group]') as $button) {
                $labels[] = trim($button->getElementsByTagName('span')->item(0)->textContent);
                $panel = $xpath->query('//*[@id="'.$button->getAttribute('aria-controls').'"]')->item(0);
                $this->assertNotNull($panel);
                $this->assertGreaterThan(0, $panel->getElementsByTagName('a')->length);
                $this->assertSame('false', $button->getAttribute('aria-expanded'));
            }
            $this->assertSame($expected, $labels, $role);
            if ($role === 'seller') {
                $this->assertSame(1, $xpath->query('//span[@class="badge" and text()="1"]')->length);
            }
            if ($role === 'admin') {
                $this->assertSame(1, $xpath->query('//span[@class="count" and text()="4"]')->length);
            }
            foreach ($xpath->query('//a[@href]') as $link) {
                if (str_starts_with($link->getAttribute('href'), url('/'))) {
                    $this->assertStringContainsString('/'.$prefix.'/', $link->getAttribute('href'), $role);
                }
            }
            $this->assertGreaterThan(0, $xpath->query('//a[@aria-current="page"]')->length);
        }
    }

    public function test_hub_staff_cannot_see_company_admin_navigation_and_counts_are_retained(): void
    {
        $xpath = $this->renderNavigation('hub_staff', 'logistics.transfers.show');
        foreach (['requests', 'hub-requests', 'riders', 'staff', 'hubs', 'vehicles', 'accounts', 'activity', 'reports'] as $path) {
            $this->assertSame(0, $xpath->query('//a[@href="'.route('logistics.'.$path).'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//a[@href="'.route('logistics.hub.vehicles').'"]')->length);
        $this->assertSame(1, $xpath->query('//span[@class="count" and text()="2"]')->length);
    }

    public function test_buyer_navigation_does_not_offer_restricted_support_to_platform_admin(): void
    {
        $xpath = $this->renderNavigation('admin', 'buyer.dashboard', 'buyer.partials.storefront-header');
        $this->assertSame(0, $xpath->query('//button[@aria-controls="buyer-support"]')->length);
        $this->assertSame(0, $xpath->query('//a[@href="'.route('buyer.faqs').'"]')->length);
        $this->assertStringNotContainsString('pf-assistant-launch', view('buyer.partials.assistant')->render());
        $this->assertStringContainsString('pf-assistant-launch', view('buyer.partials.assistant', ['assistantGuest' => true])->render());
        $this->renderNavigation('buyer', 'buyer.dashboard');
        $this->assertStringContainsString('pf-assistant-launch', view('buyer.partials.assistant')->render());
    }

    public function test_contextual_pages_expand_their_parent_and_mark_the_parent_link(): void
    {
        foreach ([['admin', 'admin.ai.order', 'admin-system', 'admin.ai'], ['admin', 'admin.compliance', 'admin-compliance', 'admin.compliance'],
            ['seller', 'seller.orders.waybill', 'seller-orders', 'seller.orders'], ['logistics_admin', 'logistics.accounts.edit', 'logistics-administration', 'logistics.accounts'],
            ['hub_staff', 'logistics.transfers.show', 'hub-staff-operations', 'logistics.transfers'], ['rider', 'rider.deliveries.show', 'rider-delivery-operations', 'rider.deliveries'],
            ['buyer', 'buyer.announcements.show', 'buyer-communications', 'buyer.announcements'],
            ['buyer', 'buyer.product', 'buyer-shopping', 'buyer.browse'], ['buyer', 'buyer.shop', 'buyer-shopping', 'buyer.browse']] as [$role, $route, $group, $parent]) {
            $xpath = $this->renderNavigation($role, $route);
            $this->assertSame(1, $xpath->query('//a[@aria-current="page"]')->length);
            $this->assertSame(1, $xpath->query('//button[@aria-controls="'.$group.'" and @aria-expanded="true"]')->length);
            $this->assertSame(0, $xpath->query('//*[@id="'.$group.'" and @hidden]')->length);
            $this->assertSame(1, $xpath->query('//a[@href="'.route($parent).'" and @aria-current="page"]')->length);
        }
    }
}
