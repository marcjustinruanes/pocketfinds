<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherLocationTest extends TestCase
{
    public function test_buy_addr_002_local_geographic_lists_scope_cities_and_barangays_to_their_parent(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/locations/regions')->assertOk()->assertJsonFragment(['name' => 'NCR', 'code' => '130000000']);
        $this->getJson('/locations/regions/130000000/provinces')->assertOk()->assertExactJson([]);
        $cities = $this->getJson('/locations/regions/130000000/cities-municipalities')->assertOk();
        $cities->assertJsonFragment(['name' => 'City of Makati', 'code' => '137602000'])->assertJsonMissing(['name' => 'City of Cebu']);
        $barangays = $this->getJson('/locations/cities-municipalities/137602000/barangays')->assertOk();
        $barangays->assertJsonFragment(['name' => 'Bel-Air'])->assertJsonMissing(['name' => 'Lahug']);
        $this->getJson('/locations/regions/070000000/provinces')->assertOk()->assertJsonFragment(['name' => 'Cebu']);
        $this->getJson('/locations/regions/999999999/cities-municipalities')->assertOk()->assertExactJson([]);
        $this->getJson('/locations/../../.env')->assertNotFound();
        Http::assertNothingSent();
    }
}
