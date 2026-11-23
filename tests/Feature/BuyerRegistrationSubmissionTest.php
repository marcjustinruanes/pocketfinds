<?php
namespace Tests\Feature;

use App\Models\User;

class BuyerRegistrationSubmissionTest extends PersonnelOnboardingTest
{
    public function test_complete_buyer_multipart_form_registers_and_keeps_the_entered_postal_code(): void
    {
        foreach (['2026_10_16_000001_add_registration_business_details','2026_10_16_000002_add_logistics_registration_details'] as $migration) (require database_path('migrations/'.$migration.'.php'))->up();
        auth()->forgetGuards();
        $data=[
            'account_type'=>'buyer','auth_method'=>'manual','given_names'=>'Maria','last_name'=>'Registration',
            'no_middle_name'=>'1','sex'=>'female','birthday'=>'1995-01-02',
            'email'=>'buyer.registration@gmail.com','contact_no'=>'09171234567',
            'region'=>'CALABARZON','province'=>'Laguna','municipality'=>'Santa Cruz','barangay'=>'Central',
            'municipality_psgc_code'=>'043426000','postal_code'=>'4010','username'=>'buyer_registration',
            'password'=>'Buyer@2026','password_confirmation'=>'Buyer@2026',
            'id_type_id'=>'1','id_file'=>$this->image(),'selfie_file'=>$this->image(),
        ];
        $this->withSession(['registration_verified_email'=>['email'=>$data['email'],'expires_at'=>now()->addHour()->timestamp]])
            ->post('/register',$data,['Accept'=>'application/json'])->assertOk()->assertJson(['success'=>true]);
        $this->assertSame('4010',User::where('username',$data['username'])->firstOrFail()->postal_code);
    }
}
