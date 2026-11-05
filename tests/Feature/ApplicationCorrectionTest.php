<?php

namespace Tests\Feature;

use App\Models\{LogisticsHub, User};
use App\Support\ApplicationCorrection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Mail, Storage};
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class ApplicationCorrectionTest extends TestCase
{
    use WorkflowSchema;
    private LogisticsHub $hub;
    private User $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp(); $this->createWorkflowSchema();
        foreach (['2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details'] as $migration) (require database_path('migrations/'.$migration.'.php'))->up();
        Storage::fake('public'); Storage::fake('sensitive_documents'); Mail::fake(); Http::preventStrayRequests();
        Http::fake([
            'https://psgc.gitlab.io/api/regions/'=>Http::response([['code'=>'040000000','name'=>'CALABARZON']]),
            'https://psgc.gitlab.io/api/provinces/'=>Http::response([['code'=>'043400000','name'=>'Laguna','regionCode'=>'040000000']]),
            'https://psgc.gitlab.io/api/provinces/043400000/cities-municipalities/'=>Http::response([['code'=>'043426000','name'=>'Santa Cruz']]),
            'https://psgc.gitlab.io/api/cities-municipalities/043426000/barangays/'=>Http::response([['code'=>'043426001','name'=>'Central']]),
        ]);
        DB::table('id_types')->insert(['id'=>1,'name'=>"Driver's License"]);
        DB::table('vehicle_types')->insert(['slug'=>'two_wheels','name'=>'Motorcycle']);
        $this->hub = LogisticsHub::create(['company_name'=>'Alpha','province'=>'Laguna','municipality'=>'Santa Cruz','is_operational'=>true,'is_hiring'=>true]);
        $this->companyAdmin = User::create(['username'=>'company_admin','email'=>'admin@gmail.com','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Alpha','status'=>'approved']);
    }

    private function applicant(string $role): User
    {
        foreach (['id_files/id.png','selfie_files/selfie.png','license_files/license.png','nbi_clearances/nbi.png','vehicle_docs/or.png','vehicle_docs/cr.png','business_permits/permit.pdf','business_certificates/sec.pdf','business_certificates/bir.pdf'] as $path) Storage::disk('sensitive_documents')->put($path,'private fixture');
        $data = ['username'=>'rejected_'.$role,'email'=>$role.'@gmail.com','given_names'=>'Alex','last_name'=>'Applicant','sex'=>'male','birthday'=>'1990-01-01','contact_no'=>'09171234567',
            'password'=>'ExistingPassword@123','status'=>'rejected','status_reason'=>'Please replace the unclear document.','region'=>'CALABARZON','province'=>'Laguna','municipality'=>'Santa Cruz','barangay'=>'Central','id_type_id'=>1,'id_file'=>'id_files/id.png','selfie_file'=>'selfie_files/selfie.png',
            'account_type'=>$role==='hub_staff'?'logistics':$role,'is_logistics'=>in_array($role,['logistics','hub_staff']),'logistics_role'=>$role==='hub_staff'?'hub_staff':($role==='logistics'?'admin':null)];
        if (in_array($role,['hub_staff','rider'])) $data += ['business_name'=>'Alpha','logistics_hub_id'=>$this->hub->id];
        if ($role==='logistics') $data += ['business_name'=>'Rejected Company','tin'=>'123456789000','sec_registration_no'=>'CS123456789','position'=>'Owner','postal_code'=>'4009','business_permit_file'=>'business_permits/permit.pdf','sec_certificate_file'=>'business_certificates/sec.pdf','bir_certificate_file'=>'business_certificates/bir.pdf'];
        if ($role==='rider') $data = array_replace($data,['id_file'=>'license_files/license.png','license_file'=>'license_files/license.png','license_number'=>'L01-23-456789','license_expiry'=>'2030-01-01','license_type'=>'professional','dl_codes'=>['A'],'vehicle_dl_code'=>'A','load_capacity_kg'=>20,'nbi_clearance_file'=>'nbi_clearances/nbi.png','vehicle_type'=>'two_wheels','vehicle_ownership'=>'own','vehicle_brand'=>'Brand','vehicle_model'=>'Model','plate_number'=>'TEST-1','or_file'=>'vehicle_docs/or.png','cr_file'=>'vehicle_docs/cr.png']);
        $user=User::create($data); $user->forceFill(['can_resubmit'=>true])->save(); return $user->fresh();
    }

    private function data(User $user): array
    {
        $data = $user->only(['given_names','middle_name','last_name','sex','contact_no','region','province','municipality','barangay','id_type_id']);
        $data['birthday']=$user->birthday->toDateString();
        if ($user->account_type==='rider') {
            unset($data['id_type_id']);
            $data += $user->only(['license_number','vehicle_type','vehicle_ownership','vehicle_brand','vehicle_model','plate_number']);
            $data['license_expiry']=$user->license_expiry->toDateString();
        }
        if ($user->logistics_role==='admin') $data += $user->only(['position','postal_code','tin','sec_registration_no']);
        return $data;
    }

    private function grant(User $user): array { return ['application_correction'=>ApplicationCorrection::grant($user)]; }

    public function test_rejected_login_opens_correction_for_each_new_role_and_keeps_dashboard_locked(): void
    {
        foreach (['buyer','rider','hub_staff','logistics'] as $role) {
            DB::beginTransaction(); $user=$this->applicant($role);
            $this->post(route('login.post'),['email'=>$user->email,'password'=>'WrongPassword@123'])->assertSessionHasErrors('email');
            $this->post(route('login.post'),['email'=>$user->email,'password'=>'ExistingPassword@123'])->assertRedirect(route('application.correction.edit'));
            $this->get(route('application.correction.edit'))->assertOk()->assertSee('Please replace the unclear document.')->assertSee('Resubmit application');
            $this->assertGuest(); $this->assertSame('rejected',$user->fresh()->status);
            $this->app['session']->flush(); DB::rollBack(); auth()->forgetGuards();
        }
    }

    public function test_each_role_resubmits_on_same_id_retaining_documents_and_rejection_history(): void
    {
        foreach (['buyer','rider','hub_staff','logistics'] as $role) {
            DB::beginTransaction(); $user=$this->applicant($role); $id=$user->id;
            $data=$this->data($user); $data['last_name']="O'Hara";
            $this->withSession($this->grant($user))->post(route('application.correction.update'),$data)->assertRedirect(route('login'))->assertSessionHasNoErrors()->assertSessionHas('success');
            $current=$user->fresh(); $this->assertSame($id,$current->id); $this->assertSame('pending',$current->status); $this->assertSame("O'Hara",$current->last_name);
            $this->assertFalse($current->can_resubmit);
            $this->assertSame($user->selfie_file,$current->selfie_file); $this->assertSame($user->password,$current->password); $this->assertFalse($current->is_admin);
            $this->assertDatabaseHas('hiring_application_history',['user_id'=>$id,'status'=>'rejected','reason'=>'Please replace the unclear document.']);
            $this->assertDatabaseHas('hiring_application_history',['user_id'=>$id,'status'=>'pending']);
            $this->get(route('application.correction.edit'))->assertForbidden();
            $this->app['session']->flush(); DB::rollBack(); auth()->forgetGuards();
        }
    }

    public function test_grants_are_required_expire_and_cannot_be_used_by_another_signed_in_account(): void
    {
        $user=$this->applicant('buyer');
        $this->get(route('application.correction.edit'))->assertForbidden();
        $this->post(route('application.correction.update'),$this->data($user))->assertForbidden();
        $expired=ApplicationCorrection::grant($user); $expired['expires_at']=now()->subMinute()->timestamp;
        $this->withSession(['application_correction'=>$expired])->get(route('application.correction.edit'))->assertForbidden();
        $this->actingAs($this->companyAdmin)->withSession($this->grant($user))->get(route('application.correction.edit'))->assertForbidden();
    }

    public function test_invalid_fields_and_privilege_changes_cannot_resubmit(): void
    {
        $user=$this->applicant('rider');
        foreach ([['is_admin'=>true],['account_type'=>'seller'],['status'=>'approved'],['license_number'=>'invalid'],['last_name'=>'Jr.']] as $changes) {
            $this->withSession($this->grant($user))->postJson(route('application.correction.update'),array_replace($this->data($user),$changes))->assertUnprocessable();
        }
        $this->assertSame('rejected',$user->fresh()->status);
        $this->assertSame('rider',$user->fresh()->account_type);
    }

    public function test_founder_requires_missing_certificates_and_stores_replacements_privately(): void
    {
        $user=$this->applicant('logistics');
        Storage::disk('sensitive_documents')->delete($user->sec_certificate_file);
        Storage::disk('sensitive_documents')->delete($user->bir_certificate_file);
        $this->withSession($this->grant($user))->postJson(route('application.correction.update'),$this->data($user))->assertUnprocessable()->assertJsonValidationErrors(['sec_certificate_file','bir_certificate_file']);
        $pdf=fn()=>UploadedFile::fake()->createWithContent('certificate.pdf',"%PDF-1.4\nCertificate");
        $this->withSession($this->grant($user))->post(route('application.correction.update'),$this->data($user)+['sec_certificate_file'=>$pdf(),'bir_certificate_file'=>$pdf()])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $current=$user->fresh(); Storage::disk('sensitive_documents')->assertExists($current->sec_certificate_file); Storage::disk('public')->assertMissing($current->sec_certificate_file);
        $this->assertSame($user->business_name,$current->business_name); $this->assertSame($user->tin,$current->tin);
    }

    public function test_closed_hub_and_invalid_address_block_personnel_correction(): void
    {
        $user=$this->applicant('rider'); $data=$this->data($user);
        $this->withSession($this->grant($user))->postJson(route('application.correction.update'),array_replace($data,['municipality'=>'Forged City']))->assertUnprocessable();
        $this->hub->is_hiring=false; $this->hub->rider_hiring_open=false; $this->hub->save();
        $this->withSession($this->grant($user))->postJson(route('application.correction.update'),$data)->assertUnprocessable()->assertJsonValidationErrors('logistics_hub_id');
        $this->assertSame('rejected',$user->fresh()->status);
    }

    public function test_logistics_registration_requires_both_certificates_and_retains_the_same_rejected_account(): void
    {
        $user=$this->applicant('logistics');
        $png=fn()=>UploadedFile::fake()->createWithContent('id.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        $pdf=fn()=>UploadedFile::fake()->createWithContent('certificate.pdf',"%PDF-1.4\nCertificate");
        $payload=fn()=>$this->data($user)+['account_type'=>'logistics','auth_method'=>'manual','no_middle_name'=>true,'email'=>$user->email,'username'=>$user->username,'business_name'=>$user->business_name,
            'password'=>'ExistingPassword@123','password_confirmation'=>'ExistingPassword@123','hub_areas'=>json_encode([['region'=>'CALABARZON','province'=>'Laguna','municipality'=>'Santa Cruz']]),
            'id_file'=>$png(),'selfie_file'=>$png(),'business_permit_file'=>$pdf(),'sec_certificate_file'=>$pdf(),'bir_certificate_file'=>$pdf()];
        foreach (['sec_certificate_file','bir_certificate_file'] as $field) {
            $data=$payload(); unset($data[$field]);
            $this->postJson('/register',$data)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $data=$payload(); $data['sec_certificate_file']=UploadedFile::fake()->create('certificate.exe',10);
        $this->postJson('/register',$data)->assertUnprocessable()->assertJsonValidationErrors('sec_certificate_file');
        $data=$payload(); $data['bir_certificate_file']=UploadedFile::fake()->create('certificate.pdf',5121,'application/pdf');
        $this->postJson('/register',$data)->assertUnprocessable()->assertJsonValidationErrors('bir_certificate_file');
        $this->withSession(['registration_verified_email'=>['email'=>$user->email,'expires_at'=>now()->addHour()->timestamp]])->postJson('/register',$payload())->assertOk()->assertJson(['success'=>true]);
        $current=$user->fresh(); $this->assertSame('pending',$current->status);
        Storage::disk('sensitive_documents')->assertExists($current->sec_certificate_file); Storage::disk('public')->assertMissing($current->sec_certificate_file);
        $this->assertSame(1,User::where('email',$user->email)->count());
    }

    public function test_public_reregistration_cannot_replace_another_rejected_applicants_password(): void
    {
        $user=$this->applicant('buyer');
        $this->postJson('/register',['account_type'=>'buyer','email'=>$user->email,'username'=>$user->username,'password'=>'AttackerPassword@123'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($user->password,$user->fresh()->password);
    }

    public function test_logistics_approval_requires_uploaded_certificates(): void
    {
        $user=$this->applicant('logistics'); $user->status='pending'; $user->save();
        Storage::disk('sensitive_documents')->delete($user->sec_certificate_file);
        $admin=User::create(['username'=>'platform','account_type'=>'admin','is_admin'=>true,'status'=>'approved']);
        $this->actingAs($admin)->patch(route('admin.registrations.approve',$user))->assertUnprocessable();
        $this->assertSame('pending',$user->fresh()->status);
        Storage::disk('sensitive_documents')->put($user->sec_certificate_file,'restored certificate');
        $this->patch(route('admin.registrations.approve',$user))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved',$user->fresh()->status);
    }
}
