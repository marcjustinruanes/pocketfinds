<?php
namespace Tests\Feature;
use App\Services\RegistrationPostalCode;
use Tests\TestCase;
class RegistrationPostalCodeTest extends TestCase
{
    public function test_official_postal_codes_use_both_municipality_and_province(): void
    {
        $this->getJson('/register/postal-code?municipality_code=043417000&municipality=Magdalena&province=Laguna')->assertOk()->assertJson(['postal_code'=>'4007','status'=>'found']);
        $service=app(RegistrationPostalCode::class);
        $this->assertSame('4009',$service->lookup('043426000','Santa Cruz','Laguna'));
        $this->assertSame('4902',$service->lookup('174005000','Santa Cruz','Marinduque'));
        $this->assertNull($service->lookup('043426000','Santa Cruz','Unknown Province'));
    }
    public function test_unknown_districts_and_missing_province_never_get_a_guessed_code(): void
    {
        $service=app(RegistrationPostalCode::class);
        $this->assertNull($service->lookup('043426000','Another Municipality','Laguna'));
        $this->assertNull($service->lookup('043426000','Santa Cruz'));
        $this->assertNull($service->lookup(null,'Santa Cruz','Laguna'));
        $this->getJson('/register/postal-code?municipality_code=043426000&municipality=Santa%20Cruz&province=Unknown')->assertOk()->assertJson(['postal_code'=>null,'status'=>'unavailable']);
    }
}
