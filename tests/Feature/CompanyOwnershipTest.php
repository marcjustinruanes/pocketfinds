<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyOwnershipTest extends PersonnelOnboardingTest
{
    public function test_company_owner_guard_and_database_index_preserve_personnel(): void
    {
        (require database_path('migrations/2026_10_29_000006_enforce_logistics_company_owner.php'))->up();
        $this->create(); // Personnel share Alpha, not its unique founder key.
        try { User::create(['username'=>'duplicate','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>' alpha ','status'=>'pending']);$this->fail('Duplicate founder allowed.'); }
        catch(ValidationException $e){$this->assertArrayHasKey('business_name',$e->errors());}
        $rejected=User::create(['username'=>'rejected-founder','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Reserved Rejected','status'=>'rejected']);
        try{$rejected->update(['status'=>'approved','business_name'=>'Alpha']);$this->fail('Duplicate approval allowed.');}catch(ValidationException $e){$this->assertArrayHasKey('business_name',$e->errors());}
        $this->assertSame('rejected',$rejected->fresh()->status);
        try{User::create(['username'=>'steal-rejected','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'Reserved Rejected','status'=>'pending']);$this->fail('Rejected owner reservation lost.');}catch(ValidationException $e){$this->assertArrayHasKey('business_name',$e->errors());}
        $rejected=$rejected->fresh();$rejected->update(['status'=>'pending']);$this->assertSame('pending',$rejected->fresh()->status);
        try { DB::table('users')->insert(['username'=>'raw-duplicate','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>'alpha','status'=>'pending']);$this->fail('Database owner uniqueness missing.'); }
        catch(\Illuminate\Database\UniqueConstraintViolationException $e){$this->assertStringContainsString('company_owner_key',$e->getMessage());}
    }

    public function test_migration_refuses_ambiguous_legacy_owners_without_changing_them(): void
    {
        DB::table('users')->insert(['username'=>'legacy-duplicate','account_type'=>'logistics','is_logistics'=>true,'logistics_role'=>'admin','business_name'=>' alpha ','status'=>'pending']);
        $before=DB::table('users')->count();
        try{(require database_path('migrations/2026_10_29_000006_enforce_logistics_company_owner.php'))->up();$this->fail('Ambiguous owners accepted.');}
        catch(\RuntimeException $e){$this->assertStringContainsString('authorized review',$e->getMessage());}
        $this->assertSame($before,DB::table('users')->count());
    }

    public function test_rejected_founder_reapplies_on_same_id_and_preserves_hubs(): void
    {
        foreach(['2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details','2026_10_29_000006_enforce_logistics_company_owner'] as $m)(require database_path('migrations/'.$m.'.php'))->up();
        auth()->forgetGuards();$d=$this->data('hub_staff');unset($d['role'],$d['logistics_hub_id']);
        $d+=['account_type'=>'logistics','auth_method'=>'manual','business_name'=>'Reserved Company','province'=>'Laguna','municipality'=>'Santa Cruz','tin'=>'123456789012','sec_registration_no'=>'CS123456789','position'=>'Owner','postal_code'=>'4009','business_permit_file'=>$this->image(),'hub_areas'=>json_encode([['region'=>'CALABARZON','province'=>'Laguna','municipality'=>'Santa Cruz']])];
        $this->postJson('/register',$d)->assertOk()->assertJson(['success'=>true]);
        $founder=User::where('username','managed_application')->firstOrFail();$originalId=$founder->id;$hubId=DB::table('logistics_hubs')->where('company_name','Reserved Company')->value('id');
        $platform=User::create(['username'=>'platform-reviewer','account_type'=>'admin','is_admin'=>true,'status'=>'approved']);
        $this->actingAs($platform)->patch(route('admin.registrations.reject',$founder),['reason_preset'=>'other','reason_details'=>'Genuine application requires correction'])->assertRedirect();
        $this->assertSame('rejected',$founder->fresh()->status);$this->assertDatabaseHas('logistics_hubs',['id'=>$hubId]);
        auth()->forgetGuards();
        $this->getJson('/register/check-business-name?account_type=logistics&business_name=Reserved%20Company')->assertOk()->assertJson(['available'=>false,'reapplication_only'=>true]);
        $d['selfie_file']=$this->image();$d['id_file']=$this->image();$d['business_permit_file']=$this->image();
        $this->postJson('/register',$d)->assertOk()->assertJson(['success'=>true]);
        $this->assertSame($originalId,User::where('username','managed_application')->value('id'));$this->assertSame('pending',$founder->fresh()->status);
        $this->assertSame(1,DB::table('logistics_hubs')->where('company_name','Reserved Company')->count());$this->assertDatabaseHas('logistics_hubs',['id'=>$hubId]);
    }
}
