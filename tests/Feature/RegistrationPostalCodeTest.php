<?php

namespace Tests\Feature;

use App\Services\RegistrationPostalCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationPostalCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('registration_postal_api_v1');
        Http::preventStrayRequests();
    }

    private function api(): void
    {
        Http::fake([
            'psgc.cloud/api/cities'=>Http::response([['code'=>'0403428000','name'=>'City of Santa Rosa','zip_code'=>'4026']]),
            'psgc.cloud/api/municipalities'=>Http::response([
                ['code'=>'0403426000','name'=>'Santa Cruz ','zip_code'=>'4004'],
                ['code'=>'0102924000','name'=>'Santa Cruz','zip_code'=>'2732'],
                ['code'=>'1705111000','name'=>'Santa Cruz','zip_code'=>''],
            ]),
        ]);
    }

    public function test_lookup_uses_location_codes_not_ambiguous_names_and_caches_api_data(): void
    {
        $this->api();
        $this->getJson('/register/postal-code?municipality_code=043426000&municipality=Santa%20Cruz')->assertOk()->assertJson(['postal_code'=>'4004','status'=>'found']);
        $this->getJson('/register/postal-code?municipality_code=0102924000&municipality=Santa%20Cruz')->assertOk()->assertJson(['postal_code'=>'2732']);
        Http::assertSentCount(2);
    }

    public function test_mismatched_names_missing_codes_and_api_failure_never_invent_a_postal_code(): void
    {
        $this->api();
        $service=app(RegistrationPostalCode::class);
        $this->assertNull($service->lookup('043426000','Another Municipality'));
        $this->assertNull($service->lookup('1705111000','Santa Cruz'));
        $this->assertNull($service->lookup(null,'Santa Cruz'));
    }

    public function test_api_failure_shows_unavailable_without_manual_fallback(): void
    {
        Http::fake(['*'=>Http::response([],503)]);
        $this->getJson('/register/postal-code?municipality_code=043426000&municipality=Santa%20Cruz')->assertOk()->assertJson(['postal_code'=>null,'status'=>'unavailable']);
    }
}
