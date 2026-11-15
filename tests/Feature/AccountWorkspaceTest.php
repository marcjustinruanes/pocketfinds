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
                ->assertSee('account-profile-photo.js',false);
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
