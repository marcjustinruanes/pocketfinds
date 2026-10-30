<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

class RemainingRegistrationAuditTest extends PersonnelOnboardingTest
{
    public function test_audit_current_managed_unicode_name_password_and_licence_rules(): void
    {
        foreach (['José María','李 明','Anne-Marie'] as $name) {
            DB::beginTransaction();
            $d=$this->data();$d['given_names']=$name;
            $this->post('/logistics/riders/accounts',$d)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('users',['given_names'=>$name]);DB::rollBack();
        }
        foreach (['Jane123','Jane<script>','Jane  Mary'] as $name) {
            $d=$this->data();$d['given_names']=$name;
            $this->postJson('/logistics/riders/accounts',$d)->assertUnprocessable()->assertJsonValidationErrors('given_names');
        }
        foreach (['NoSymbol12345','onlylowercase!1','ABCDEF!12345','PasswordOnly!','Abc!1234'] as $password) {
            $d=$this->data();$d['password']=$d['password_confirmation']=$password;
            $this->postJson('/logistics/riders/accounts',$d)->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        foreach (['garbage','N1-23-456789','N01-23456789','n01-23-456789'] as $licence) {
            $d=$this->data();$d['license_number']=$licence;
            $this->postJson('/logistics/riders/accounts',$d)->assertUnprocessable()->assertJsonValidationErrors('license_number');
        }
    }

    public function test_audit_current_public_registration_unicode_and_negative_validation(): void
    {
        foreach(['2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details'] as $migration) (require database_path('migrations/'.$migration.'.php'))->up();
        auth()->forgetGuards();
        $data=function(){ $d=$this->data();unset($d['role']);return $d+['account_type'=>'rider','auth_method'=>'manual','business_name'=>'Alpha','province'=>'Laguna','municipality'=>'Santa Cruz']; };
        foreach(['José María','李 明'] as $name) {
            DB::beginTransaction();$d=$data();$d['given_names']=$name;
            $r=$this->postJson('/register',$d);$r->assertOk()->assertJson(['success'=>true]);DB::rollBack();
        }
        foreach([['given_names','Jane123'],['given_names','Jane<script>'],['password','NoSymbol12345'],['license_number','garbage']] as [$field,$value]) {
            $d=$data();$d[$field]=$value;if($field==='password')$d['password_confirmation']=$value;
            $this->postJson('/register',$d)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
}
