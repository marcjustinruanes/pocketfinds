<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class BusinessNumberAvailabilityTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        \Illuminate\Support\Facades\Schema::table('users',function(\Illuminate\Database\Schema\Blueprint $table){$table->string('tin')->nullable();$table->string('sec_registration_no')->nullable();});
    }

    public function test_used_numbers_are_normalized_and_checked_across_account_types(): void
    {
        User::create(['username'=>'existing_company','email'=>'company@gmail.com','password'=>'Password123!',
            'account_type'=>'logistics','status'=>'pending','tin'=>'123-456-789-000','sec_registration_no'=>'CS202012345']);
        $this->getJson('/register/check-business-number?field=tin&value=123456789000')->assertOk()->assertJson(['valid'=>true,'available'=>false]);
        $this->getJson('/register/check-business-number?field=sec_registration_no&value=cs202012345')->assertOk()->assertJson(['valid'=>true,'available'=>false]);
        $this->getJson('/register/check-business-number?field=tin&value=987654321000')->assertOk()->assertJson(['valid'=>true,'available'=>true]);
    }

    public function test_rejected_numbers_remain_reserved_for_the_original_applicant(): void
    {
        $user=User::create(['username'=>'rejected_company','email'=>'rejected@gmail.com','password'=>'Password123!',
            'account_type'=>'logistics','status'=>'rejected','can_resubmit'=>true,'tin'=>'123456789000']);
        $user->forceFill(['can_resubmit'=>true])->save();
        $this->getJson('/register/check-business-number?field=tin&value=123456789000')->assertJson(['available'=>false,'reapplication_only'=>true]);
        $user->forceFill(['can_resubmit'=>false])->save();
        $this->getJson('/register/check-business-number?field=tin&value=123456789000')->assertJson(['available'=>false,'reapplication_only'=>false]);
    }

    public function test_incomplete_numbers_and_unrecognized_fields_are_rejected(): void
    {
        $this->getJson('/register/check-business-number?field=tin&value=123')->assertOk()->assertJson(['valid'=>false]);
        $this->getJson('/register/check-business-number?field=email&value=company@gmail.com')->assertUnprocessable()->assertJsonValidationErrors('field');
    }
}
