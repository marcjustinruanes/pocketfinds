<?php
namespace Tests\Feature;

use App\Models\{LogisticsHub, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, Http, Storage};
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class AccountWorkspaceTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        foreach (['2026_10_14_000004_add_gcash_purchases','2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details','2026_10_17_000001_add_buyer_commerce_features','2026_11_03_100000_add_buyer_module_tools'] as $file) {
            (require database_path('migrations/'.$file.'.php'))->up();
        }
        Storage::fake('profile_images');
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_platform_navigation_combines_tools_without_duplicate_sidebar_entries(): void
    {
        $sections = \App\Support\PlatformWorkspace::sections();
        $this->assertCount(7, $sections);
        $routes = collect($sections)->flatMap(fn ($section) => collect($section['items'])->pluck(1));
        $this->assertCount($routes->count(), $routes->unique());
        foreach ($routes as $route) $this->assertTrue(\Illuminate\Support\Facades\Route::has($route), $route);
        foreach (['admin.registrations','admin.update-requests','admin.vehicles','admin.complaints','admin.policies','admin.commission','admin.faqs'] as $route) {
            $this->assertContains($route, $routes);
        }
        $requestRoute = new \Illuminate\Routing\Route('GET', '/admin/users', fn () => null);
        $requestRoute->name('admin.users');
        request()->setRouteResolver(fn () => $requestRoute);
        $tabs = view('admin.partials.workspace-tabs')->render();
        $this->assertStringContainsString('Accounts sections', $tabs);
        $this->assertStringContainsString(route('admin.registrations', [], false), $tabs);
        $this->assertStringContainsString('aria-current="page"', $tabs);
        $this->actingAs(User::create(['account_type'=>'admin','is_admin'=>true,'username'=>'navigation_admin','email'=>'navigation@example.test','given_names'=>'Platform','last_name'=>'Admin','password'=>'Pass@2026','status'=>'approved']));
        $sidebar = view('admin.partials.sidebar')->render();
        $this->assertSame(7, substr_count($sidebar, 'class="nav-item '));
        $this->assertStringNotContainsString('class="count"', $sidebar);
        $this->assertStringNotContainsString('navigation-group', $sidebar);
    }

    public function test_admin_account_tables_render_type_header_dropdowns(): void
    {
        $this->actingAs(User::create(['account_type'=>'admin','is_admin'=>true,'username'=>'table_admin','email'=>'table@example.test','given_names'=>'Platform','last_name'=>'Admin','password'=>'Pass@2026','status'=>'approved']));
        $account = User::create(['account_type'=>'buyer','username'=>'review_buyer','email'=>'review@example.test','given_names'=>'Review','last_name'=>'Buyer','password'=>'Pass@2026','status'=>'pending','auth_method'=>'email']);
        $requestItem = (object) ['id'=>1,'user'=>$account,'status'=>'pending','requested_changes'=>['contact_no'=>'09171234567'],'requested_documents'=>[],'created_at'=>now(),'reviewed_at'=>null];
        foreach (['users','registrations','update-requests'] as $page) {
            $html = view('admin.'.$page, ['users'=>collect([$account]), 'requests'=>collect([$requestItem]), 'pendingCount'=>0, 'approvedCount'=>0, 'rejectedCount'=>0])->render();
            $this->assertStringContainsString('data-platform-filter="accountType"', $html);
            $this->assertStringContainsString('Filter by type', $html);
            $this->assertStringContainsString('platform-manage-heading">Manage', $html);
            $this->assertLessThan(strpos($html, 'card platform-accounts-card'), strpos($html, 'platform-account-toolbar'));
            if ($page==='users') $this->assertStringContainsString('Filter by status', $html);
            if ($page==='registrations') $this->assertStringNotContainsString('AI seller review', $html);
            $this->assertStringNotContainsString('data-tabs', $html);
            if ($page==='users') $this->assertStringNotContainsString('All Users', $html);
            if ($page==='registrations') $this->assertStringContainsString('Filter by status', $html);
        }
    }

    public function test_admin_messages_use_shared_workspace_and_json_polling(): void
    {
        $admin = User::create(['account_type'=>'admin','is_admin'=>true,'username'=>'chat_admin','email'=>'chatadmin@example.test','given_names'=>'Chat','last_name'=>'Admin','password'=>'Pass@2026','status'=>'approved']);
        $contact = User::create(['account_type'=>'buyer','username'=>'chat_contact','email'=>'contact@example.test','given_names'=>'Chat','last_name'=>'Buyer','password'=>'Pass@2026','status'=>'approved']);
        $this->actingAs($admin);
        $message = \App\Models\Message::create(['sender_id'=>$contact->id,'receiver_id'=>$admin->id,'body'=>'Hello support','read'=>false]);
        $contact->last_message = $message;
        $contact->unread_count = 1;
        $html = view('admin.messages',['users'=>collect([$contact]),'selectedUser'=>$contact,'messages'=>collect([$message]),'pickerProducts'=>collect()])->render();
        $this->assertStringContainsString('css/buyer-messaging.css', $html);
        $this->assertStringContainsString('data-messages-path="/admin/messages"', $html);
        $this->assertStringContainsString('Ask PocketFinds', $html);
        $this->assertStringContainsString(route('admin.messages.user',$contact), $html);
        $this->assertStringContainsString('Hello support', $html);
        $this->assertLessThan(strpos($html, 'pf-notification-menu'), strpos($html, 'platform-announcements-link'));
        $this->getJson(route('admin.messages.poll',['receiver_id'=>$contact->id]))->assertOk()->assertJsonPath('messages.0.body','Hello support');
    }

    public function test_all_roles_use_verified_single_use_password_links(): void
    {
        (require database_path('migrations/2026_11_01_110000_create_password_reset_tokens_table.php'))->up();
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Mail::fake();
        $hub = LogisticsHub::create(['company_name'=>'Security Logistics','province'=>'Laguna','municipality'=>'Pila','is_operational'=>true]);
        foreach (['admin','rider','logistics'] as $role) {
            $user = User::create(['account_type'=>$role,'is_admin'=>$role==='admin','is_logistics'=>$role==='logistics','logistics_role'=>$role==='logistics'?'admin':null,'business_name'=>'Security Logistics','logistics_hub_id'=>$role==='admin'?null:$hub->id,'username'=>'security_'.$role,'email'=>$role.'security@example.test','given_names'=>'Security','last_name'=>'Account','password'=>'Original@2026','status'=>'approved']);
            $this->actingAs($user)->post(route($role.'.account.password-link'))->assertSessionHasNoErrors();
            $token = null;
            \Illuminate\Support\Facades\Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class, function ($notification) use (&$token) { $token=$notification->token; return true; });
            $data=['username'=>'updated_'.$role,'password'=>'Changed@2026','password_confirmation'=>'Changed@2026'];
            $this->post(route($role.'.account.password-change'),$data)->assertSessionHasErrors('password_link');
            $this->get(route('password.reset',['token'=>$token,'email'=>$user->email]))->assertRedirect(route($role.'.account').'#account-security');
            $this->post(route($role.'.account.password-change'),$data)->assertSessionHasNoErrors()->assertSessionHas('password_success');
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Changed@2026',$user->fresh()->password));
            $this->post(route($role.'.account.password-change'),$data)->assertSessionHasErrors('password_link');
            Cache::flush(); // Exercise email verification independently of password-route throttles.
            $this->postJson(route('account.email.code'),['email'=>'new'.$role.'@gmail.com'])->assertOk()->assertJson(['email'=>'new'.$role.'@gmail.com']);
            $this->assertSame($role.'security@example.test',$user->fresh()->email);
            session()->flush();
        }
    }

    public function test_marketplace_separates_vouchers_and_moves_vehicles_to_compliance(): void
    {
        $this->actingAs(User::create(['account_type'=>'admin','is_admin'=>true,'username'=>'resources_admin','email'=>'resources@example.test','given_names'=>'Resource','last_name'=>'Admin','password'=>'Pass@2026','status'=>'approved']));
        $sections = collect(\App\Support\PlatformWorkspace::sections())->keyBy('label');
        $this->assertSame(['admin.products','admin.vouchers'], collect($sections['Marketplace']['items'])->pluck(1)->all());
        $this->assertContains('admin.vehicles', collect($sections['Compliance']['items'])->pluck(1));
        $voucher=(object)['id'=>1,'code'=>'TESTVOUCHER','kind'=>'platform','discount_type'=>'percent','amount'=>20,'minimum_spend'=>100,'cap'=>50,'starts_at'=>now()->subDay(),'ends_at'=>now()->addDay(),'is_active'=>true,'usage_limit'=>100,'per_buyer_limit'=>1];
        foreach (['products','vehicles','vouchers'] as $page) {
            $html=view('admin.'.$page,['products'=>collect(),'vehicles'=>collect(),'vouchers'=>collect([$voucher]),'categories'=>collect(),'errors'=>new \Illuminate\Support\ViewErrorBag()])->render();
            $this->assertStringContainsString('data-platform-filter-table', $html);
            $this->assertStringContainsString('Filter by status', $html);
            if ($page==='products') $this->assertStringNotContainsString('id="platform-vouchers"', $html);
            if ($page==='vouchers') $this->assertStringContainsString('id="createPlatformVoucher"', $html);
        }
        $this->get(route('admin.commerce.index'))->assertRedirect(route('admin.vouchers'));
        $this->get(route('admin.vouchers'))->assertOk()->assertSee('Create voucher')->assertSee('vouchersTable');
    }

    public function test_admin_user_modals_match_current_role_registration_fields(): void
    {
        foreach (['buyer','seller','logistics','hub_staff','rider'] as $role) {
            $user = new User(['account_type'=>$role==='hub_staff'?'logistics':$role,'is_logistics'=>in_array($role,['logistics','hub_staff']),'logistics_role'=>$role==='hub_staff'?'hub_staff':($role==='logistics'?'admin':null),'given_names'=>'Registered','middle_name'=>'Middle','last_name'=>'Account','suffix'=>'Jr.','username'=>'registered_'.$role,'email'=>$role.'@example.test','seller_type'=>'sole_proprietorship','tin'=>'123-456-789-000','business_name'=>'Registration Company','position'=>'Director','representative_email'=>'representative@example.test','sec_registration_no'=>'SEC12345','vehicle_type'=>'two_wheels','vehicle_ownership'=>'own','license_number'=>'N01-26-123456','license_expiry'=>'2030-01-01','postal_code'=>'4010']);
            $user->setRelation('logisticsHub',new LogisticsHub(['municipality'=>'Pila','province'=>'Laguna']));
            $user->setRelation('category',null);
            $html=view('admin.partials.user-profile-body',['user'=>$user])->render();
            $this->assertStringContainsString('First name',$html);
            $this->assertStringContainsString('Middle name',$html);
            $this->assertStringContainsString('Postal code',$html);
            $this->assertStringContainsString('Submitted documents',$html);
            if ($role==='seller') {
                $this->assertStringContainsString('Sole Proprietorship',$html);
                $this->assertStringContainsString('DTI Certificate',$html);
                $this->assertStringContainsString('BIR Certificate',$html);
            }
            if ($role==='logistics') {
                $this->assertStringContainsString('representative@example.test',$html);
                $this->assertStringContainsString('SEC12345',$html);
                $this->assertStringContainsString('Business permit',$html);
            }
            if (in_array($role,['rider','hub_staff'])) $this->assertStringContainsString('Pila, Laguna',$html);
            if ($role==='rider') {
                $this->assertStringContainsString('NBI clearance',$html);
                $this->assertStringContainsString('Certificate of registration (CR)',$html);
                $this->assertStringContainsString('2030',$html);
            }
            if ($role==='hub_staff') $this->assertStringNotContainsString('Business permit',$html);
        }
    }

    public function test_admin_cases_use_separate_tables_and_legacy_seller_reviews_redirect(): void
    {
        $this->actingAs(User::create(['account_type'=>'admin','is_admin'=>true,'username'=>'cases_admin','email'=>'cases@example.test','given_names'=>'Case','last_name'=>'Admin','password'=>'Pass@2026','status'=>'approved']));
        $this->get(route('admin.compliance'))->assertRedirect(route('admin.users'));
        $this->get(route('admin.complaints'))->assertOk()->assertSee('id="dispTable"',false)->assertDontSee('id="returnsTable"',false)->assertSee('Filter by status');
        $this->get(route('admin.complaints',['view'=>'returns']))->assertOk()->assertSee('id="returnsTable"',false)->assertDontSee('id="dispTable"',false)->assertSee('Filter by status');
        $links=collect(\App\Support\PlatformWorkspace::sections())->flatMap(fn($section)=>collect($section['items'])->pluck(1));
        $this->assertNotContains('admin.compliance',$links);
    }

    public function test_settings_policy_and_faq_pages_render_the_updated_workspace(): void
    {
        $this->actingAs(User::create(['account_type'=>'admin','is_admin'=>true,'username'=>'settings_admin','email'=>'settings@example.test','given_names'=>'Settings','last_name'=>'Admin','password'=>'Pass@2026','status'=>'approved']));
        if (!\Illuminate\Support\Facades\Schema::hasColumn('categories','image_sha256')) \Illuminate\Support\Facades\Schema::table('categories',fn($table)=>$table->string('image_sha256')->nullable());
        $errors=new \Illuminate\Support\ViewErrorBag();
        view()->share('errors',$errors);
        $settings=view('admin.settings',['setting'=>\App\Models\Setting::current(),'commissionEnrollment'=>null,'commissionEnrollmentEvents'=>null,'errors'=>$errors])->render();
        $this->assertStringContainsString('platform-settings.css',$settings);
        $this->assertStringContainsString('<button type="button" class="settings-nav-item',$settings);
        $policies=view('admin.policies',['terms'=>collect(),'termsHistory'=>collect(),'companies'=>collect(),'errors'=>$errors])->render();
        $this->assertSame(4,substr_count($policies,'class="platform-policy-record"'));
        $faqs=view('admin.faqs',['audience'=>'buyer','editing'=>null,'faqs'=>new \Illuminate\Pagination\LengthAwarePaginator([],0,20),'errors'=>$errors])->render();
        $this->assertStringContainsString('data-modal-open="faqEditorModal"',$faqs);
        $this->assertStringContainsString('aria-labelledby="faqEditorTitle"',$faqs);
        $editing=new \App\Models\SupportFaq(['id'=>1,'audience'=>'buyer','question'=>'How do I order?','answer'=>'Choose a product.','keywords'=>['order'],'sort_order'=>0,'is_published'=>true]);
        $editing->id=1;
        $html=view('admin.faqs',['audience'=>'buyer','editing'=>$editing,'faqs'=>new \Illuminate\Pagination\LengthAwarePaginator([],0,20),'errors'=>$errors])->render();
        $this->assertStringContainsString('class="modal-overlay open" id="faqEditorModal"',$html);
        $this->assertStringContainsString('How do I order?',$html);
    }

    public function test_all_account_roles_render_the_buyer_account_workspace(): void
    {
        $hub=LogisticsHub::create(['company_name'=>'Account Logistics','province'=>'Laguna','municipality'=>'Pila','is_operational'=>true]);
        foreach (['buyer','seller','admin','rider','logistics','hub_staff'] as $role) {
            $accountRole=$role==='hub_staff'?'logistics':$role;
            $user=User::create(['account_type'=>$accountRole,'username'=>'account_'.$role,'email'=>$role.'@example.test',
                'given_names'=>'Account','last_name'=>'Example','password'=>'Pass@2026','status'=>'approved',
                'is_admin'=>$role==='admin','is_logistics'=>$accountRole==='logistics',
                'logistics_role'=>$accountRole==='logistics'?($role==='hub_staff'?'hub_staff':'admin'):null,
                'logistics_hub_id'=>in_array($accountRole,['logistics','rider'])?$hub->id:null,'business_name'=>'Account Logistics',
                'sex'=>'female','birthday'=>'1995-01-02','province'=>'Laguna','municipality'=>'Pila','barangay'=>'Central','region'=>'CALABARZON','contact_no'=>'09171234567']);
            $response=$this->actingAs($user)->get(route($accountRole.'.account'));
            $response->assertOk()->assertSee('id="accountLayout"',false)->assertSee('css/buyer-account.css',false)
                ->assertSee('data-account-section="profile-information"',false)->assertSee('openBuyerProfilePhotoModal',false)
                ->assertSee('account-profile-photo.js',false)
                ->assertSee('Email change-password link')->assertSee('accountEmailCodeForm',false)
                ->assertSee(route($accountRole.'.account.password-link'),false)
                ->assertDontSee('name="current_password"',false);
            if ($role!=='buyer') {
                $response->assertSee('data-account-role="'.$accountRole.'"',false)->assertSee('role-account.css',false);
                if (getenv('ACCOUNT_BROWSER_FIXTURES')) file_put_contents(storage_path('framework/testing/account-'.$role.'.html'),$response->getContent());
            }
        }
    }

    public function test_seller_security_and_document_submission_match_the_shared_account_flow(): void
    {
        Storage::fake('sensitive_documents');
        \Illuminate\Support\Facades\Mail::fake();
        $seller=User::create(['account_type'=>'seller','username'=>'updated_seller','email'=>'old@gmail.com','given_names'=>'Shop','last_name'=>'Owner','password'=>'Pass@2026','status'=>'approved']);
        $this->actingAs($seller)->get(route('seller.account'))->assertOk()->assertSee('Email change-password link')
            ->assertSee('accountEmailCodeForm')->assertSee('accountSelfieFile')->assertSee('Shop availability')
            ->assertDontSee('Shipping regions and rates')->assertDontSee('Checkout charges PHP 50')->assertDontSee('Manage flash sales');
        $this->postJson(route('account.email.code'),['email'=>'new@gmail.com'])->assertOk()->assertJson(['email'=>'new@gmail.com']);
        Cache::put('buyer-email-change:'.$seller->id,['email'=>'new@gmail.com','hash'=>\Illuminate\Support\Facades\Hash::make('123456'),'attempts'=>0],now()->addMinutes(5));
        $this->postJson(route('account.email.verify'),['email'=>'different@gmail.com','otp'=>'123456'])->assertUnprocessable();
        $this->assertSame('old@gmail.com',$seller->fresh()->email);
        $this->postJson(route('account.email.verify'),['email'=>'new@gmail.com','otp'=>'123456'])->assertOk();
        $this->assertSame('new@gmail.com',$seller->fresh()->email);
        \Illuminate\Support\Facades\DB::table('id_types')->insertOrIgnore(['id'=>1,'name'=>'Passport']);
        $this->postJson(route('seller.account.documents'),['id_type_id'=>1,'id_file'=>UploadedFile::fake()->image('id.jpg')])->assertUnprocessable()->assertJsonValidationErrors('selfie_file');
        $this->postJson(route('seller.account.documents'),['selfie_file'=>UploadedFile::fake()->image('selfie.jpg')])->assertUnprocessable()->assertJsonValidationErrors('id_file');
        $this->post(route('seller.account.documents'),['id_type_id'=>1,'id_file'=>UploadedFile::fake()->image('id.jpg'),'selfie_file'=>UploadedFile::fake()->image('selfie.jpg')])->assertSessionHasNoErrors()->assertSessionHas('docs_success');
        $pending=\App\Models\AccountUpdateRequest::where('user_id',$seller->id)->firstOrFail();
        $this->assertCount(2,$pending->requested_documents);
        foreach($pending->requested_documents as $path) Storage::disk('sensitive_documents')->assertExists($path);
        $this->assertNull($seller->fresh()->selfie_file);
        $this->assertNull($seller->fresh()->id_file);
        $pending->apply();
        $this->assertSame($pending->requested_documents['selfie_file'],$seller->fresh()->selfie_file);
    }

    public function test_shared_photo_manager_saves_only_the_signed_in_approved_account(): void
    {
        $hub=LogisticsHub::create(['company_name'=>'Photo Logistics','province'=>'Laguna','municipality'=>'Pila','is_operational'=>true]);
        foreach (['buyer','seller','admin','rider','logistics'] as $role) {
            $user=User::create(['account_type'=>$role,'username'=>'photo_'.$role,'email'=>'photo_'.$role.'@example.test',
                'given_names'=>'Photo','last_name'=>'Example','password'=>'Pass@2026','status'=>'approved','is_admin'=>$role==='admin',
                'business_name'=>in_array($role,['logistics','rider'])?$hub->company_name:null,'is_logistics'=>$role==='logistics',
                'logistics_role'=>$role==='logistics'?'admin':null,'logistics_hub_id'=>in_array($role,['logistics','rider'])?$hub->id:null]);
            $this->actingAs($user)->post(route('account.photo'),['profile_picture'=>UploadedFile::fake()->image('avatar.jpg')])
                ->assertRedirect()->assertSessionHasNoErrors();
            Storage::disk('profile_images')->assertExists($user->fresh()->profile_picture);
            $this->postJson(route('account.photo'),['profile_picture'=>UploadedFile::fake()->create('file.pdf',2,'application/pdf')])
                ->assertUnprocessable();
        }
        $user->update(['status'=>'suspended']);
        $this->postJson(route('account.photo'),['profile_picture'=>UploadedFile::fake()->image('blocked.jpg')])->assertForbidden();
    }
}
