<?php

namespace Tests\Feature;

use App\Models\{LogisticsHub,User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Http,Mail,Storage};
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class PersonnelOnboardingTest extends TestCase
{
    use WorkflowSchema;
    protected User $admin; protected LogisticsHub $hub;
    protected function setUp(): void
    {
        parent::setUp();$this->createWorkflowSchema();Storage::fake('public');Storage::fake('sensitive_documents');Mail::fake();Http::preventStrayRequests();
        Http::fake(['https://psgc.gitlab.io/api/regions/'=>Http::response([['code'=>'040000000','name'=>'CALABARZON']]),'https://psgc.gitlab.io/api/provinces/'=>Http::response([['code'=>'043400000','name'=>'Laguna','regionCode'=>'040000000']]),'https://psgc.gitlab.io/api/provinces/043400000/cities-municipalities/'=>Http::response([['code'=>'043426000','name'=>'Santa Cruz']]),'https://psgc.gitlab.io/api/cities-municipalities/043426000/barangays/'=>Http::response([['code'=>'043426001','name'=>'Central']])]);
        $this->hub=LogisticsHub::create(['company_name'=>'Alpha','province'=>'Laguna','municipality'=>'Santa Cruz','is_operational'=>true,'is_hiring'=>true]);
        DB::table('id_types')->insert(['id'=>1,'name'=>"Driver's License"]);DB::table('vehicle_types')->insert(['slug'=>'two_wheels','name'=>'Motorcycle']);
        $this->admin=User::create(['username'=>'admin_fixture','account_type'=>'logistics','logistics_role'=>'admin','is_logistics'=>true,'is_admin'=>false,'business_name'=>'Alpha','status'=>'approved','logistics_hub_id'=>$this->hub->id]);
        $this->actingAs($this->admin);
    }
    protected function image(): UploadedFile { return UploadedFile::fake()->createWithContent('document.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII=')); }
    protected function data(string $role='rider'): array
    {
        $d=['role'=>$role,'username'=>'managed_application','email'=>'managed@gmail.com','given_names'=>'Managed','last_name'=>'Applicant','sex'=>'male','birthday'=>'2000-01-01','contact_no'=>'09123456789','barangay'=>'Central','region'=>'CALABARZON','password'=>'FixturePassword123!','password_confirmation'=>'FixturePassword123!','logistics_hub_id'=>$this->hub->id,'selfie_file'=>$this->image()];
        return $d+($role==='rider'?['license_number'=>'L01-00-000001','license_expiry'=>'2030-01-01','license_file'=>$this->image(),'vehicle_type'=>'two_wheels','vehicle_ownership'=>'own','vehicle_brand'=>'Brand','vehicle_model'=>'Model','plate_number'=>'PLATE-1','or_file'=>$this->image(),'cr_file'=>$this->image()]:['id_type_id'=>1,'id_file'=>$this->image()]);
    }
    protected function create(string $role='rider'): User { $this->post('/logistics/'.($role==='rider'?'riders':'staff').'/accounts',$this->data($role))->assertRedirect()->assertSessionHasNoErrors();return User::where('username','managed_application')->firstOrFail(); }
    public function test_complete_managed_rider_stays_pending_and_uses_private_licence_identity(): void
    {
        $r=$this->create();$this->assertSame('pending',$r->status);$this->assertSame($r->license_file,$r->id_file);
        Storage::disk('sensitive_documents')->assertExists($r->license_file);$this->assertSame([],Storage::disk('public')->allFiles());
        $this->get(route('logistics.accounts.edit',$r->id))->assertOk()->assertSee('Document on file');
        $this->patch('/logistics/riders/'.$r->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('approved',$r->fresh()->status);
        $this->assertSame(['pending','approved'],DB::table('hiring_application_history')->where('user_id',$r->id)->pluck('status')->all());
    }
    public function test_missing_files_forged_paths_and_unavailable_company_vehicle_are_rejected(): void
    {
        $d=$this->data();unset($d['license_file']);$this->postJson('/logistics/riders/accounts',$d)->assertUnprocessable()->assertJsonValidationErrors('license_file');
        $d=$this->data();$d['license_file']='license_files/forged.png';$this->postJson('/logistics/riders/accounts',$d)->assertUnprocessable();
        $d=$this->data();$d['vehicle_ownership']='company';unset($d['or_file'],$d['cr_file'],$d['plate_number'],$d['vehicle_brand'],$d['vehicle_model']);
        $this->postJson('/logistics/riders/accounts',$d)->assertUnprocessable()->assertJsonValidationErrors('vehicle_type');$this->assertDatabaseMissing('users',['username'=>'managed_application']);
        \App\Models\CompanyVehicle::create(['company_name'=>'Alpha','logistics_hub_id'=>$this->hub->id,'vehicle_type'=>'two_wheels','status'=>'approved','platform_status'=>'approved','is_available'=>true,'plate_number'=>'COMPANY-1']);
        $this->post('/logistics/riders/accounts',$d)->assertRedirect()->assertSessionHasNoErrors();$this->assertNull(User::where('username','managed_application')->firstOrFail()->or_file);
    }
    public function test_existing_pending_application_completion_preserves_identity_status_and_documents(): void
    {
        $r=$this->create();$original=$r->license_file;$d=$this->data();unset($d['role'],$d['password'],$d['password_confirmation'],$d['license_file'],$d['selfie_file'],$d['or_file'],$d['cr_file']);
        $this->patch('/logistics/accounts/'.$r->id,$d)->assertRedirect()->assertSessionHasNoErrors();$this->assertSame($original,$r->fresh()->license_file);$this->assertSame('pending',$r->fresh()->status);
        $this->assertSame('application_completed',json_decode(DB::table('hiring_application_history')->where('user_id',$r->id)->latest('id')->value('review_details'),true)['action']);
        Storage::disk('sensitive_documents')->delete($r->selfie_file);
        $this->patchJson('/logistics/riders/'.$r->id.'/approve')->assertUnprocessable();$this->assertSame('pending',$r->fresh()->status);
        $d['selfie_file']=$this->image();$this->patch('/logistics/accounts/'.$r->id,$d)->assertRedirect()->assertSessionHasNoErrors();$this->assertNotSame($r->selfie_file,$r->fresh()->selfie_file);
    }
    public function test_staff_requires_explicit_completed_interview_attestation(): void
    {
        $s=$this->create('hub_staff');$this->patchJson('/logistics/staff/'.$s->id.'/confirm',['interview_completed'=>'1'])->assertUnprocessable();
        $this->patch('/logistics/staff/'.$s->id.'/approve',['interview_date'=>now()->toDateString(),'interview_time'=>'10:00','interview_location'=>'Actual hub'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson('/logistics/staff/'.$s->id.'/confirm')->assertUnprocessable()->assertJsonValidationErrors('interview_completed');
        $this->patch('/logistics/staff/'.$s->id.'/confirm',['interview_completed'=>'1'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('approved',$s->fresh()->status);
        $event=DB::table('hiring_application_history')->where('user_id',$s->id)->latest('id')->first();$details=json_decode($event->review_details,true);$this->assertTrue($details['interview_completed']);$this->assertSame($this->admin->id,$details['interview_completed_attested_by']);
    }
    public function test_decisions_require_valid_reasons_and_reactivation_preserves_history(): void
    {
        $r=$this->create();$this->patchJson('/logistics/riders/'.$r->id.'/reject')->assertUnprocessable();
        $this->patchJson('/logistics/riders/'.$r->id.'/reject',['reason_preset'=>'FORGED'])->assertUnprocessable();
        $this->patchJson('/logistics/riders/'.$r->id.'/reject',['reason_preset'=>'other','reason_details'=>'  '])->assertUnprocessable();
        $this->patch('/logistics/riders/'.$r->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/logistics/riders/'.$r->id.'/suspend',['reason_preset'=>'other','reason_details'=>'Supervised personnel review'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson('/logistics/riders/'.$r->id.'/activate')->assertUnprocessable();
        $this->patch('/logistics/riders/'.$r->id.'/activate',['reason_preset'=>'other','reason_details'=>'Review completed and access restored'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($r->fresh()->status_reason);$events=DB::table('hiring_application_history')->where('user_id',$r->id)->orderBy('id')->get();$this->assertSame(['pending','approved','suspended','approved'],$events->pluck('status')->all());$last=$events->last();$this->assertSame($this->admin->id,(int)$last->actor_id);$this->assertSame('suspended',json_decode($last->review_details,true)['from_status']);$this->assertSame('Review completed and access restored',$last->reason);$this->assertSame($events[0]->attempt_id,$last->attempt_id);
    }
    public function test_company_staff_foreign_company_and_revoked_admin_cannot_complete_applications(): void
    {
        $r=$this->create();$d=$this->data();unset($d['role']);
        $staff=User::create(['account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'hub_staff','business_name'=>'Alpha','logistics_hub_id'=>$this->hub->id,'status'=>'approved','username'=>'staff_fixture']);
        $this->actingAs($staff)->patchJson('/logistics/accounts/'.$r->id,$d)->assertForbidden();
        $other=User::create(['account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Other','status'=>'approved','username'=>'other_admin']);
        $this->actingAs($other)->patchJson('/logistics/accounts/'.$r->id,$d)->assertNotFound();
        DB::table('users')->where('id',$this->admin->id)->update(['status'=>'suspended']);
        $this->actingAs($this->admin)->patchJson('/logistics/accounts/'.$r->id,$d)->assertForbidden();
        $this->assertSame('pending',$r->fresh()->status);
    }
    public function test_interview_completion_edit_retains_status_attempt_and_original_documents(): void
    {
        $s=$this->create('hub_staff');$this->patch('/logistics/staff/'.$s->id.'/approve',['interview_date'=>now()->toDateString(),'interview_time'=>'10:00','interview_location'=>'Company hub'])->assertRedirect()->assertSessionHasNoErrors();
        $old=$s->fresh();$d=$this->data('hub_staff');unset($d['role'],$d['password'],$d['password_confirmation'],$d['id_file'],$d['selfie_file']);
        $before=DB::table('hiring_application_history')->where('user_id',$s->id)->get()->toJson();
        $this->patch('/logistics/accounts/'.$s->id,$d)->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('interview',$s->fresh()->status);$this->assertSame($old->id_file,$s->fresh()->id_file);$this->assertSame($old->interview_scheduled_at->toDateTimeString(),$s->fresh()->interview_scheduled_at->toDateTimeString());
        $this->assertSame($before,DB::table('hiring_application_history')->where('user_id',$s->id)->limit(2)->get()->toJson());
    }
    public function test_history_failure_rolls_back_new_application_and_private_uploads(): void
    {
        DB::statement("CREATE TRIGGER fail_application BEFORE INSERT ON hiring_application_history BEGIN SELECT RAISE(ABORT,'application audit failed'); END");
        $this->post('/logistics/riders/accounts',$this->data())->assertServerError();$this->assertDatabaseMissing('users',['username'=>'managed_application']);$this->assertSame([],Storage::disk('sensitive_documents')->allFiles());
    }
    public function test_replacement_audit_failure_preserves_original_private_documents(): void
    {
        $r=$this->create();$paths=Storage::disk('sensitive_documents')->allFiles();sort($paths);$old=$r->license_file;
        DB::statement("CREATE TRIGGER fail_completion BEFORE INSERT ON hiring_application_history BEGIN SELECT RAISE(ABORT,'completion audit failed'); END");
        $d=$this->data();unset($d['role']);$this->patch('/logistics/accounts/'.$r->id,$d)->assertServerError();$this->assertSame($old,$r->fresh()->license_file);$after=Storage::disk('sensitive_documents')->allFiles();sort($after);$this->assertSame($paths,$after);
    }
    public function test_decision_audit_failure_rolls_back_status_reason_and_notification(): void
    {
        $r=$this->create();$this->patch('/logistics/riders/'.$r->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();Mail::fake();
        DB::statement("CREATE TRIGGER fail_decision BEFORE INSERT ON hiring_application_history WHEN NEW.status='suspended' BEGIN SELECT RAISE(ABORT,'decision audit failed'); END");
        $this->patch('/logistics/riders/'.$r->id.'/suspend',['reason_preset'=>'other','reason_details'=>'Confidential investigation reference'])->assertServerError();$this->assertSame('approved',$r->fresh()->status);$this->assertNull($r->fresh()->status_reason);Mail::assertNothingSent();
    }
    public function test_reactivation_does_not_require_missing_legacy_documents_or_valid_current_licence(): void
    {
        $r=$this->create();$this->patch('/logistics/riders/'.$r->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();$this->patch('/logistics/riders/'.$r->id.'/suspend',['reason_preset'=>'other','reason_details'=>'Temporary administrative review'])->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('sensitive_documents')->delete($r->license_file);DB::table('users')->where('id',$r->id)->update(['license_expiry'=>'2020-01-01']);
        $this->patch('/logistics/riders/'.$r->id.'/activate',['reason_preset'=>'Review completed; operational access restored'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('approved',$r->fresh()->status);
    }
    public function test_rejection_notification_runs_after_commit_without_internal_reason(): void
    {
        $r=$this->create();
        Mail::shouldReceive('raw')->once()->withArgs(fn($body,$callback)=>DB::transactionLevel()===0 && $r->fresh()->status==='rejected' && !str_contains($body,'CONFIDENTIAL') && DB::table('hiring_application_history')->where('user_id',$r->id)->where('status','rejected')->exists());
        $this->patch('/logistics/riders/'.$r->id.'/reject',['reason_preset'=>'other','reason_details'=>'CONFIDENTIAL investigation reference'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('CONFIDENTIAL investigation reference',$r->fresh()->status_reason);
    }
    public function test_duplicate_normalized_identifiers_and_nonmeaningful_reasons_are_rejected(): void
    {
        $r=$this->create();$d=$this->data();$d['username']='different_applicant';$d['email']='different@gmail.com';$d['given_names']='Different';$d['contact_no']='09123456780';$d['license_number']='L01-00-000001';
        $this->postJson('/logistics/riders/accounts',$d)->assertUnprocessable()->assertJsonValidationErrors('license_number');
        $this->patchJson('/logistics/riders/'.$r->id.'/reject',['reason_preset'=>'other','reason_details'=>'---'])->assertUnprocessable();
        $this->patchJson('/logistics/riders/'.$r->id.'/reject',['reason_preset'=>'other','reason_details'=>['forged']])->assertUnprocessable();
        $this->patchJson('/logistics/riders/'.$r->id.'/reject',['reason_preset'=>['other'],'reason_details'=>'Actual explanation'])->assertUnprocessable();
        $this->assertSame('pending',$r->fresh()->status);
    }
}
