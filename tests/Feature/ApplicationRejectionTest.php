<?php

namespace Tests\Feature;

use App\Mail\ApplicationRejectedMail;
use App\Models\{LogisticsHub, User};
use App\Support\ApplicationCorrection;
use Illuminate\Support\Facades\{DB, Mail, Storage};
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class ApplicationRejectionTest extends TestCase
{
    use WorkflowSchema;
    private User $platform;
    private User $company;
    private LogisticsHub $hub;
    private int $serial=0;

    protected function setUp(): void
    {
        parent::setUp(); $this->createWorkflowSchema();
        foreach (['2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details'] as $migration) (require database_path('migrations/'.$migration.'.php'))->up();
        Storage::fake('public'); Storage::fake('sensitive_documents'); Mail::fake();
        DB::table('id_types')->insert(['id'=>1,'name'=>"Driver's License"]);
        DB::table('vehicle_types')->insert(['slug'=>'two_wheels','name'=>'Motorcycle']);
        $this->platform=User::create(['username'=>'platform_admin','account_type'=>'admin','is_admin'=>true,'status'=>'approved']);
        $this->hub=LogisticsHub::create(['company_name'=>'Alpha','province'=>'Laguna','municipality'=>'Santa Cruz','is_operational'=>true,'is_hiring'=>true]);
        $this->company=User::create(['username'=>'company_admin','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Alpha','status'=>'approved']);
    }

    private function applicant(string $role): User
    {
        $n=++$this->serial;
        return User::create(['username'=>'applicant_'.$n,'email'=>'applicant'.$n.'@gmail.com','password'=>'Applicant@2026','account_type'=>$role==='hub_staff'?'logistics':$role,
            'given_names'=>'Test','last_name'=>'Applicant','sex'=>'male','birthday'=>'1990-01-01','contact_no'=>'0917'.str_pad((string)$n,7,'0',STR_PAD_LEFT),
            'region'=>'CALABARZON','province'=>'Laguna','municipality'=>'Santa Cruz','barangay'=>'Central','id_type_id'=>1,
            'status'=>'pending','is_logistics'=>in_array($role,['logistics','hub_staff']),'logistics_role'=>$role==='hub_staff'?'hub_staff':($role==='logistics'?'admin':null),
            'business_name'=>in_array($role,['rider','hub_staff'])?'Alpha':($role==='logistics'?'Company '.$n:($role==='seller'?'Shop '.$n:null)),
            'logistics_hub_id'=>in_array($role,['rider','hub_staff'])?$this->hub->id:null,'seller_type'=>$role==='seller'?'individual':null,
            'vehicle_type'=>$role==='rider'?'two_wheels':null,'vehicle_ownership'=>$role==='rider'?'own':null])->fresh();
    }

    private function reject(User $user, string $decision='final_rejection'): void
    {
        $role=app(\App\Services\HubHiring::class)->role($user);
        $url=$role?route($role==='rider'?'logistics.riders.reject':'logistics.staff.reject',$user->id):route('admin.registrations.reject',$user);
        $this->actingAs($role?$this->company:$this->platform)->patch($url,['reason_preset'=>'other','reason_details'=>'Application requires a reviewer decision.','rejection_decision'=>$decision])->assertRedirect()->assertSessionHasNoErrors();
        $this->app['auth']->guard()->logout(); auth()->forgetGuards();
    }

    public function test_explicit_permission_sends_correction_email_and_allows_only_correction_access_for_every_role(): void
    {
        foreach (['buyer','seller','rider','hub_staff','logistics'] as $role) {
            $user=$this->applicant($role); $this->reject($user,'allow_resubmission');
            $this->assertTrue($user->fresh()->can_resubmit);
            Mail::assertSent(ApplicationRejectedMail::class,fn($mail)=>$mail->hasTo($user->email) && $mail->canResubmit && str_contains($mail->render(),'Sign in with your existing account'));
            $this->post(route('login.post'),['email'=>$user->email,'password'=>'Applicant@2026'])->assertRedirect(route($role==='seller'?'seller.application.edit':'application.correction.edit'));
            $this->get(route($role==='seller'?'seller.application.edit':'application.correction.edit'))->assertOk();
            $this->assertGuest(); $this->app['session']->flush();
        }
    }

    public function test_final_rejection_sends_no_correction_link_and_blocks_signin_and_direct_grants_for_every_role(): void
    {
        foreach (['buyer','seller','rider','hub_staff','logistics'] as $role) {
            $user=$this->applicant($role); $this->reject($user);
            $this->assertFalse($user->fresh()->can_resubmit);
            Mail::assertSent(ApplicationRejectedMail::class,fn($mail)=>$mail->hasTo($user->email) && !$mail->canResubmit && !str_contains($mail->render(),'Open your application'));
            $this->post(route('login.post'),['email'=>$user->email,'password'=>'Applicant@2026'])->assertSessionHasErrors('email')->assertSessionMissing('application_correction')->assertSessionMissing('seller_application_correction');
            $grant=$role==='seller'?['seller_application_correction'=>['id'=>$user->id,'expires_at'=>now()->addMinutes(30)->timestamp]]:['application_correction'=>ApplicationCorrection::grant($user->fresh())];
            $this->withSession($grant)->get(route($role==='seller'?'seller.application.edit':'application.correction.edit'))->assertForbidden();
            $this->withSession($grant)->post(route($role==='seller'?'seller.application.update':'application.correction.update'),['can_resubmit'=>true])->assertForbidden();
            $this->app['session']->flush();
        }
    }

    public function test_revoking_permission_invalidates_existing_correction_session_and_public_reregistration(): void
    {
        $user=$this->applicant('buyer'); $this->reject($user,'allow_resubmission');
        $grant=['application_correction'=>ApplicationCorrection::grant($user->fresh())];
        $this->withSession($grant)->get(route('application.correction.edit'))->assertOk();
        $this->reject($user->fresh(),'final_rejection');
        $this->withSession($grant)->get(route('application.correction.edit'))->assertForbidden();
        $this->withSession($grant)->post(route('application.correction.update'),[])->assertForbidden();
        $this->postJson('/register',['account_type'=>'buyer','email'=>$user->email,'username'=>$user->username,'password'=>'Applicant@2026','can_resubmit'=>true])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame('rejected',$user->fresh()->status);
        $this->assertFalse($user->fresh()->can_resubmit);
    }

    public function test_foreign_company_and_forged_decision_cannot_grant_permission(): void
    {
        $user=$this->applicant('rider');
        $other=User::create(['username'=>'foreign_admin','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Other','status'=>'approved']);
        $data=['reason_preset'=>'other','reason_details'=>'Review requires correction.','rejection_decision'=>'allow_resubmission'];
        $this->actingAs($other)->patch(route('logistics.riders.reject',$user->id),$data)->assertNotFound();
        $this->actingAs($this->company)->patchJson(route('logistics.riders.reject',$user->id),array_replace($data,['rejection_decision'=>'forged']))->assertUnprocessable()->assertJsonValidationErrors('rejection_decision');
        $this->assertSame('pending',$user->fresh()->status); Mail::assertNothingSent();
    }

    public function test_google_signin_honors_resubmission_permission_and_revocation(): void
    {
        $user=$this->applicant('buyer');
        $googleUser=(new \Laravel\Socialite\Two\User())->map(['id'=>'google-applicant','email'=>$user->email,'name'=>'Test Applicant']);
        $provider=\Mockery::mock();
        $provider->shouldReceive('setHttpClient')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->reject($user,'allow_resubmission');
        $this->withSession(['oauth_intent'=>'login'])->get('/auth/google/callback?code=test')->assertRedirect(route('application.correction.edit'));
        $this->get(route('application.correction.edit'))->assertOk(); $this->assertGuest();
        $this->reject($user->fresh(),'final_rejection');
        $this->withSession(['oauth_intent'=>'login'])->get('/auth/google/callback?code=test')->assertRedirect(route('login'))->assertSessionHasErrors('email')->assertSessionMissing('application_correction');
        $this->assertGuest();
    }
}
