<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LogisticsWorkspace;
use Illuminate\Support\Facades\Blade;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class LogisticsWorkspaceTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        $this->actingAs(User::create(['account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','username'=>'workspace_logistics','email'=>'workspace-logistics@example.test','given_names'=>'Ana','last_name'=>'Reyes','business_name'=>'Tiny Logistics','password'=>'Pass@2026','status'=>'approved']));
    }

    public function test_personnel_edit_reuses_actual_registration_screens(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        foreach (['rider', 'hub_staff'] as $role) {
            $member = new User(['account_type'=>$role === 'rider' ? 'rider' : 'logistics', 'logistics_role'=>$role === 'hub_staff' ? 'hub_staff' : null, 'given_names'=>'Existing', 'last_name'=>'Member', 'username'=>'existing_member', 'email'=>'existing@example.test', 'status'=>'approved', 'business_name'=>'Tiny Logistics']);
            $member->id = 777;
            $html = view($role === 'rider' ? 'auth.register-rider' : 'auth.register-logistics-staff', ['managedRegistration'=>true, 'editingAccount'=>$member, 'terms'=>null, 'logisticsCompanies'=>collect(), 'companyPolicies'=>collect(), 'companyHubs'=>collect()])->render();
            $this->assertStringContainsString('registration-flow.js', $html);
            $this->assertStringContainsString('personnel-registration-edit.js', $html);
            $this->assertStringContainsString('id="personnel-edit-data"', $html);
            $this->assertStringContainsString('existing_member', $html);
            $this->assertStringContainsString('id="panel-5"', $html);
            $this->assertStringContainsString('id="panel-2"', $html);
            $this->assertStringContainsString('id="panel-6"', $html);
            $this->assertStringContainsString('id="house_no"', $html);
        }
    }

    public function test_team_lists_share_status_filters_and_registration_review_layout(): void
    {
        view()->share('errors',new \Illuminate\Support\ViewErrorBag());
        foreach (['riders','staff'] as $page) {
            $teamRoute=app('router')->getRoutes()->getByName('logistics.'.$page);
            $teamRoute->bind(request());
            request()->setRouteResolver(fn()=>$teamRoute);
            $member=new User(['account_type'=>$page==='riders'?'rider':'logistics','is_logistics'=>$page==='staff','logistics_role'=>$page==='staff'?'hub_staff':null,'given_names'=>'Team','last_name'=>'Member','username'=>'team_member','email'=>'member@example.test','business_name'=>'Tiny Logistics','status'=>$page==='riders'?'pending':'interview','vehicle_type'=>'two_wheels','vehicle_ownership'=>'own']);
            $member->id=777;
            $member->setRelation('logisticsHub',null);
            $member->setRelation('category',null);
            $member->setRelation('barangayRoutes',collect());
            $records=new \Illuminate\Pagination\LengthAwarePaginator([$member],1,20);
            $html=view('logistics.'.$page,['riders'=>$records,'staff'=>$records,'status'=>'all','statusCounts'=>collect(['approved'=>1]),'hubs'=>collect(),'coverageInstalled'=>false])->render();
            $this->assertStringContainsString('logistics-team.css',$html);
            $this->assertStringContainsString('class="team-status-heading"',$html);
            $this->assertStringContainsString('Submitted documents',$html);
            $this->assertStringContainsString('data-personnel-registration-src',$html);
            $this->assertSame(1,substr_count($html,'id="create-personnel"'));
            $this->assertSame(1,substr_count($html,'data-modal-open="create-personnel"'));
            $this->assertStringNotContainsString("'logistics.layout')",$html);
            $this->assertStringNotContainsString('id="riderTabs"',$html);
            $this->assertStringContainsString('Edit account & hub',$html);
            if ($page==='riders') $this->assertStringContainsString(route('logistics.riders.approve',777),$html);
            else $this->assertStringContainsString('confirmInterviewModal-777',$html);
        }
    }

    public function test_admin_navigation_has_six_sections_and_all_tools_remain_reachable(): void
    {
        request()->setRouteResolver(fn()=>app('router')->getRoutes()->getByName('logistics.monitor'));
        $sidebar=view('logistics.partials.sidebar')->render();
        $this->assertSame(6,substr_count($sidebar,'class="nav-item '));
        $this->assertStringNotContainsString('data-navigation-group',$sidebar);
        $this->assertStringNotContainsString('My Account',$sidebar);
        $this->assertSame(1,substr_count($sidebar,'aria-current="page"'));
        $tabs=view('logistics.partials.workspace-tabs')->render();
        foreach(['logistics.requests','logistics.assignments','logistics.issues','logistics.history'] as $route) $this->assertStringContainsString(route($route, [], false),$tabs);
        $this->assertStringNotContainsString('>Hub transfers</a>',$tabs);
        $this->assertStringNotContainsString('>Live monitor</a>',$tabs);
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Assignments<\/a>/',$tabs);
        $this->assertSame('operations',LogisticsWorkspace::current('logistics.transfers.show'));
        $this->assertSame('team',LogisticsWorkspace::current('logistics.accounts.edit'));
        $this->assertSame('resources',LogisticsWorkspace::current('logistics.vehicles.review'));
        $this->assertSame('finance',LogisticsWorkspace::current('logistics.finance.payouts'));
        $this->assertSame('reports',LogisticsWorkspace::current('logistics.activity'));
        $this->assertNull(LogisticsWorkspace::current('logistics.account'));
        $destinations=[];
        foreach(LogisticsWorkspace::sections() as $section) foreach($section['links'] as [, $route]) { $this->assertNotNull(app('router')->getRoutes()->getByName($route));$destinations[]=$route; }
        $this->assertSame(count($destinations),count(array_unique($destinations)));
    }

    public function test_admin_shell_has_compact_controls_and_accessible_workspace_tabs(): void
    {
        request()->setRouteResolver(fn()=>app('router')->getRoutes()->getByName('logistics.requests'));
        $html=Blade::render("@extends('logistics.layout') @section('page-title','Pickup requests') @section('content')<div class='card'><div class='card-head'><h2>Pickup requests</h2></div><div class='card-pad'>Workspace preview</div></div>@endsection",['errors'=>new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('logistics-workspace.css',$html);
        $this->assertStringContainsString('data-sidebar-hover',$html);
        $this->assertStringContainsString('data-seller-clock',$html);
        $this->assertStringContainsString('aria-label="Messages"',$html);
        $this->assertStringContainsString('aria-label="Ask PocketFinds"',$html);
        $this->assertStringContainsString('data-messages-path="/logistics/messages"',$html);
        $this->assertStringContainsString('Pickup riders',$html);
        $this->assertStringContainsString('aria-label="My account"',$html);
        $this->assertStringNotContainsString('class="menu-toggle"',$html);
        $this->assertStringNotContainsString('class="topbar-search"',$html);
        $this->assertStringContainsString('aria-label="Operations sections"',$html);
        if(getenv('LOGISTICS_WORKSPACE_PREVIEW')==='1')file_put_contents(storage_path('framework/testing/logistics-workspace.html'),$html);
    }
    public function test_logistics_conversations_use_the_shared_composer_and_embedded_layout(): void
    {
        $contact=User::create(['account_type'=>'seller','username'=>'workspace_rider','email'=>'workspace-rider@example.test','given_names'=>'Leo','last_name'=>'Cruz','business_name'=>'Tiny Logistics','password'=>'Pass@2026','status'=>'approved']);
        $message=\App\Models\Message::create(['sender_id'=>$contact->id,'receiver_id'=>auth()->id(),'body'=>'Pickup ready','read'=>false,'attachment_path'=>'message_attachments/sample.pdf','attachment_name'=>'QUESTIONNAIRE NILA REYMER.pdf','attachment_type'=>'document','attachment_mime'=>'application/pdf']);
        $contact->last_message=$message;
        $contact->unread_count=1;
        request()->merge(['embedded'=>1]);
        $html=view('logistics.messages',['activeUser'=>$contact,'messages'=>collect([$message]),'admins'=>collect(),'couriers'=>collect(),'sellers'=>collect([$contact])])->render();
        $this->assertStringContainsString('buyer-messaging.css',$html);
        $this->assertStringContainsString('class="buyer buyer-pages chat-embedded"',$html);
        $this->assertStringContainsString('Search conversations',$html);
        $this->assertStringContainsString('aria-label="Send message"',$html);
        $this->assertStringContainsString('Pickup ready',$html);
        $this->assertStringContainsString('class="chat-doc-badge"',$html);
        $this->assertStringContainsString('QUESTIONNAIRE NILA REYMER.pdf',$html);
        $this->assertStringContainsString(route('logistics.messages.send'),$html);
        $this->assertStringContainsString(route('logistics.messages.poll'),$html);
        $this->assertStringContainsString(route('logistics.messages.thread',$contact->id),$html);
        $this->assertStringNotContainsString('title="Products"',$html);
        $this->assertStringNotContainsString('id="logisticsShell"',$html);
        if(getenv('LOGISTICS_WORKSPACE_PREVIEW')==='1')file_put_contents(storage_path('framework/testing/logistics-messages.html'),$html);
    }

    public function test_hub_staff_share_the_compact_shell_with_only_hub_tools(): void
    {
        $hub=\App\Models\LogisticsHub::create(['company_name'=>'Tiny Logistics','municipality'=>'Pila','province'=>'Laguna','is_operational'=>true]);
        $this->actingAs(User::create(['account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'hub_staff','logistics_hub_id'=>$hub->id,'username'=>'workspace_hub','email'=>'workspace-hub@example.test','given_names'=>'Mia','last_name'=>'Reyes','business_name'=>'Tiny Logistics','password'=>'Pass@2026','status'=>'approved']));
        request()->setRouteResolver(fn()=>app('router')->getRoutes()->getByName('logistics.scan'));
        $sidebar=view('logistics.partials.sidebar',['unassigned'=>2])->render();
        $this->assertSame(4,substr_count($sidebar,'class="nav-item '));
        $this->assertStringNotContainsString('data-navigation-group',$sidebar);
        $this->assertStringContainsString('Hub portal',$sidebar);
        $this->assertStringContainsString('Pila Hub',$sidebar);
        $tabs=view('logistics.partials.workspace-tabs')->render();
        foreach(['logistics.scan','logistics.transfers','logistics.assignments','logistics.monitor','logistics.issues','logistics.history'] as $route)$this->assertStringContainsString(route($route),$tabs);
        foreach(LogisticsWorkspace::sections() as $section) foreach($section['links'] as [, $route])$this->assertNotNull(app('router')->getRoutes()->getByName($route));
        foreach(['logistics.requests','logistics.riders','logistics.finance','logistics.hubs','logistics.accounts'] as $route)$this->assertStringNotContainsString('href="'.route($route).'"',$sidebar.$tabs);
        $html=Blade::render("@extends('logistics.layout') @section('page-title','Scan parcels') @section('content')<div class='card'><div class='card-pad'>Hub workspace</div></div>@endsection",['errors'=>new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('data-sidebar-hover',$html);
        $this->assertStringContainsString('logistics-workspace.css',$html);
        $this->assertStringContainsString('aria-label="Ask PocketFinds"',$html);
        $this->assertStringContainsString('data-messages-path="/logistics/messages"',$html);
        $this->assertStringNotContainsString('class="menu-toggle"',$html);
        if(getenv('LOGISTICS_WORKSPACE_PREVIEW')==='1')file_put_contents(storage_path('framework/testing/hub-workspace.html'),$html);
    }

}
