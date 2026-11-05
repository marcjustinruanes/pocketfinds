<?php

namespace Tests\Feature;

use App\Mail\RiderActivationMail;
use App\Models\LogisticsHub;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class RiderActivationTest extends TestCase
{
    use WorkflowSchema;
    private User $admin;
    private LogisticsHub $hub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_31_140000_add_rider_account_activations.php'))->up();
        Storage::fake('public'); Storage::fake('sensitive_documents'); Mail::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://psgc.gitlab.io/api/regions/' => Http::response([['code'=>'040000000','name'=>'CALABARZON']]),
            'https://psgc.gitlab.io/api/provinces/' => Http::response([['code'=>'043400000','name'=>'Laguna','regionCode'=>'040000000']]),
            'https://psgc.gitlab.io/api/provinces/043400000/cities-municipalities/' => Http::response([['code'=>'043426000','name'=>'Santa Cruz']]),
            'https://psgc.gitlab.io/api/cities-municipalities/043426000/barangays/' => Http::response([['code'=>'043426001','name'=>'Central']]),
        ]);
        DB::table('id_types')->insert(['id'=>1,'name'=>"Driver's License"]);
        DB::table('vehicle_types')->insert(['slug'=>'two_wheels','name'=>'Motorcycle']);
        $this->hub = LogisticsHub::create(['company_name'=>'Alpha','province'=>'Laguna','municipality'=>'Santa Cruz','is_operational'=>true,'is_hiring'=>true]);
        $this->admin = User::create(['username'=>'company_admin','email'=>'admin@gmail.com','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Alpha','status'=>'approved','logistics_hub_id'=>$this->hub->id]);
    }

    private function payload(): array
    {
        $image = fn () => UploadedFile::fake()->createWithContent('document.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        return ['role'=>'rider','send_activation'=>'1','username'=>'invited_rider','email'=>'invited@gmail.com',
            'given_names'=>"O’Hara",'last_name'=>"D'Angelo",'sex'=>'male','birthday'=>'1998-01-01','contact_no'=>'09171234567',
            'region'=>'CALABARZON','barangay'=>'Central','logistics_hub_id'=>$this->hub->id,
            'license_number'=>'L01-23-456789','license_expiry'=>'2030-01-01','vehicle_type'=>'two_wheels','vehicle_ownership'=>'own',
            'vehicle_brand'=>'Brand','vehicle_model'=>'Model','plate_number'=>'PLATE-123',
            'nbi_clearance_file'=>$image(),'license_file'=>$image(),'selfie_file'=>$image(),'or_file'=>$image(),'cr_file'=>$image()];
    }

    private function invite(): array
    {
        $this->actingAs($this->admin)->post(route('logistics.riders.accounts.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(RiderActivationMail::class, 1);
        $mail = Mail::sent(RiderActivationMail::class)->first();
        $user = User::where('username','invited_rider')->firstOrFail();
        $this->app['auth']->guard()->logout();
        auth()->forgetGuards();
        return [$user, $mail->activationUrl, basename(parse_url($mail->activationUrl, PHP_URL_PATH))];
    }

    public function test_admin_adds_rider_without_password_and_email_activation_enables_login(): void
    {
        [$rider, $url, $token] = $this->invite();
        $this->assertSame('pending', $rider->status);
        foreach(['license_type','dl_codes','vehicle_dl_code','load_capacity_kg'] as $field)$this->assertArrayNotHasKey($field,$rider->getAttributes());
        Storage::disk('sensitive_documents')->assertExists($rider->nbi_clearance_file);
        Storage::disk('public')->assertMissing($rider->nbi_clearance_file);
        $this->assertArrayNotHasKey('nbi_clearance_file', $rider->toArray());
        $this->assertFalse(Hash::check('Rider@2026', $rider->password));
        $this->get($url)->assertOk()->assertSee('Set your rider password')->assertHeader('Referrer-Policy','no-referrer');
        $this->post(route('login.post'), ['email'=>$rider->email,'password'=>'Rider@2026'])->assertSessionHasErrors('email');
        $this->post(route('rider.activation.store',$token), ['password'=>'Rider@2026','password_confirmation'=>'Rider@2026'])
            ->assertRedirect(route('login'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $rider->refresh();
        $this->assertSame('approved',$rider->status);
        $this->assertTrue(Hash::check('Rider@2026',$rider->password));
        $this->assertNotNull($rider->email_verified_at);
        $this->post(route('login.post'),['email'=>$rider->email,'password'=>'Rider@2026'])->assertRedirect(route('rider.dashboard'));
        $this->assertDatabaseHas('hiring_application_history',['user_id'=>$rider->id,'status'=>'approved']);
        $this->get($url)->assertStatus(410);
        $this->postJson(route('rider.activation.store',$token),['password'=>'NewRider@2026','password_confirmation'=>'NewRider@2026'])->assertUnprocessable();
        $this->assertTrue(Hash::check('Rider@2026',$rider->fresh()->password));
    }

    public function test_rider_credentials_and_nbi_are_required_and_validated(): void
    {
        $this->actingAs($this->admin);
        foreach (['license_number', 'license_expiry', 'nbi_clearance_file'] as $field) {
            $data = $this->payload(); unset($data[$field]);
            $this->postJson(route('logistics.riders.accounts.store'), $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach ([['license_number'=>'invalid'], ['license_expiry'=>today()->toDateString()]] as $changes) {
            $this->postJson(route('logistics.riders.accounts.store'), array_replace($this->payload(), $changes))->assertUnprocessable();
        }
        $this->postJson(route('logistics.riders.accounts.store'), array_replace($this->payload(), ['nbi_clearance_file'=>UploadedFile::fake()->create('nbi.exe', 10)]))
            ->assertUnprocessable()->assertJsonValidationErrors('nbi_clearance_file');
        $this->postJson(route('logistics.riders.accounts.store'), array_replace($this->payload(), ['nbi_clearance_file'=>UploadedFile::fake()->create('nbi.pdf', 5121, 'application/pdf')]))
            ->assertUnprocessable()->assertJsonValidationErrors('nbi_clearance_file');
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('users', ['username'=>'invited_rider']);
    }

    public function test_missing_nbi_blocks_activation_until_replaced(): void
    {
        [$rider, $url, $token] = $this->invite();
        Storage::disk('sensitive_documents')->delete($rider->nbi_clearance_file);
        $this->postJson(route('rider.activation.store', $token), ['password'=>'Rider@2026','password_confirmation'=>'Rider@2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('nbi_clearance_file');
        $this->assertSame('pending', $rider->fresh()->status);
    }

    public function test_expiry_resend_and_replacement_are_enforced(): void
    {
        [$rider,$url,$token] = $this->invite();
        $this->travel(25)->hours();
        $this->get($url)->assertStatus(410);
        $this->postJson(route('rider.activation.store',$token),['password'=>'Rider@2026','password_confirmation'=>'Rider@2026'])->assertUnprocessable();
        $this->actingAs($this->admin)->post(route('logistics.riders.activation.resend',$rider))->assertRedirect();
        Mail::assertSent(RiderActivationMail::class,2);
        $replacement = Mail::sent(RiderActivationMail::class)->last()->activationUrl;
        $this->assertNotSame($url,$replacement);
        $this->get($url)->assertStatus(410);
        $this->get($replacement)->assertOk();
        $this->assertSame('pending',$rider->fresh()->status);
    }

    public function test_rejected_invited_rider_can_correct_using_email_link_before_setting_password(): void
    {
        [$rider,$url,$token]=$this->invite();
        DB::table('users')->where('id',$rider->id)->update(['status'=>'rejected','status_reason'=>'Replace your unclear license.','can_resubmit'=>true]);
        $this->get($url)->assertRedirect(route('application.correction.edit'));
        $this->get(route('application.correction.edit'))->assertOk()->assertSee('Replace your unclear license.');
        $data=$this->payload();
        foreach (['role','send_activation','username','email','logistics_hub_id'] as $field) unset($data[$field]);
        $data+=['province'=>'Laguna','municipality'=>'Santa Cruz'];
        $this->post(route('application.correction.update'),$data)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        Mail::assertSent(RiderActivationMail::class,2);
        $replacement=Mail::sent(RiderActivationMail::class)->last()->activationUrl;
        $this->assertNotSame($url,$replacement);
        $this->get($url)->assertStatus(410);
        $this->get($replacement)->assertOk()->assertSee('Set your rider password');
        $this->assertSame('pending',$rider->fresh()->status);
        $replacementToken=basename(parse_url($replacement,PHP_URL_PATH));
        $this->post(route('rider.activation.store',$replacementToken),['password'=>'Rider@2026','password_confirmation'=>'Rider@2026'])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertSame('approved',$rider->fresh()->status);
    }

    public function test_company_admin_can_resend_expired_link_for_a_rejected_invited_rider(): void
    {
        [$rider,$url,$token]=$this->invite();
        DB::table('users')->where('id',$rider->id)->update(['status'=>'rejected','status_reason'=>'Replace your unclear license.','can_resubmit'=>true]);
        $this->travel(25)->hours(); $this->get($url)->assertStatus(410);
        $this->actingAs($this->admin)->post(route('logistics.riders.activation.resend',$rider))->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(\App\Mail\ApplicationRejectedMail::class,1);
        $replacement=Mail::sent(\App\Mail\ApplicationRejectedMail::class)->last()->correctionUrl;
        $this->app['auth']->guard()->logout(); auth()->forgetGuards();
        $this->get($replacement)->assertRedirect(route('application.correction.edit'));
        $this->assertSame('rejected',$rider->fresh()->status);
    }

    public function test_final_rejection_revokes_invitation_and_correction_access(): void
    {
        [$rider,$url,$token]=$this->invite();
        $this->actingAs($this->admin)->patch(route('logistics.riders.reject',$rider->id),['reason_preset'=>'other','reason_details'=>'Applicant does not meet requirements.','rejection_decision'=>'final_rejection'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($rider->fresh()->can_resubmit);
        Mail::assertSent(\App\Mail\ApplicationRejectedMail::class,fn($mail)=>!$mail->canResubmit);
        $this->post(route('logistics.riders.activation.resend',$rider->id))->assertUnprocessable();
        $this->app['auth']->guard()->logout(); auth()->forgetGuards();
        $this->get($url)->assertStatus(410);
        $this->withSession(['application_correction'=>\App\Support\ApplicationCorrection::grant($rider->fresh())])->get(route('application.correction.edit'))->assertForbidden();
    }

    public function test_invalid_password_and_confirmation_cannot_activate(): void
    {
        [$rider,$url,$token] = $this->invite();
        foreach ([['weak','weak'],['Rider@2026','Different@2026']] as [$password,$confirmation]) {
            $this->postJson(route('rider.activation.store',$token),['password'=>$password,'password_confirmation'=>$confirmation])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->assertSame('pending',$rider->fresh()->status);
        $this->get($url)->assertOk();
    }

    public function test_other_companies_and_hub_staff_cannot_resend_or_create(): void
    {
        [$rider] = $this->invite();
        $other = User::create(['username'=>'other_admin','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Other','status'=>'approved']);
        $this->actingAs($other)->post(route('logistics.riders.activation.resend',$rider))->assertNotFound();
        $staff = User::create(['username'=>'staff','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'hub_staff','business_name'=>'Alpha','status'=>'approved','logistics_hub_id'=>$this->hub->id]);
        $this->actingAs($staff)->post(route('logistics.riders.activation.resend',$rider))->assertForbidden();
        $this->post(route('logistics.riders.accounts.store'),$this->payload())->assertForbidden();
    }

    public function test_names_accept_apostrophes_and_reject_periods(): void
    {
        $data = $this->payload(); $data['given_names']='John.';
        $this->actingAs($this->admin)->postJson(route('logistics.riders.accounts.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('given_names');
        Mail::assertNothingSent();
    }

    public function test_activation_cannot_bypass_hub_capacity(): void
    {
        [$rider,$url,$token] = $this->invite();
        $this->hub->rider_hire_limit = 1;
        $this->hub->save();
        $this->assertSame(1, $this->hub->fresh()->rider_hire_limit);
        User::create(['username'=>'existing_rider','account_type'=>'rider','is_admin'=>false,'is_logistics'=>false,'status'=>'approved','business_name'=>'Alpha','logistics_hub_id'=>$this->hub->id]);
        $this->postJson(route('rider.activation.store',$token),['password'=>'Rider@2026','password_confirmation'=>'Rider@2026'])->assertUnprocessable();
        $this->assertSame('pending',$rider->fresh()->status);
        $this->assertNull(DB::table('rider_account_activations')->where('user_id',$rider->id)->value('activated_at'));
        $this->get($url)->assertOk();
    }

    public function test_suspended_company_admin_cannot_activate_an_outstanding_invitation(): void
    {
        [$rider,$url,$token] = $this->invite();
        DB::table('users')->where('id',$this->admin->id)->update(['status'=>'suspended']);
        $this->postJson(route('rider.activation.store',$token),['password'=>'Rider@2026','password_confirmation'=>'Rider@2026'])->assertUnprocessable();
        $this->assertSame('pending',$rider->fresh()->status);
    }

    public function test_creation_form_uses_email_invitation_instead_of_admin_password_fields(): void
    {
        $this->actingAs($this->admin)->get(route('logistics.riders'))->assertOk()
            ->assertSee('name="send_activation" value="1"',false)
            ->assertSee('The rider will receive an email link')
            ->assertDontSee('name="password"',false);
    }

    public function test_invited_rider_cannot_be_manually_approved_before_setting_password(): void
    {
        [$rider] = $this->invite();
        $this->actingAs($this->admin)->patchJson(route('logistics.riders.approve',$rider))->assertUnprocessable();
        $this->assertSame('pending',$rider->fresh()->status);
    }
}
