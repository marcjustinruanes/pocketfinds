<?php

namespace Tests\Feature;

use App\Support\PhilippineMobile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            foreach (['given_names', 'last_name', 'middle_name', 'suffix', 'sex', 'birthday', 'email', 'username', 'contact_no', 'status', 'business_name', 'tin', 'password', 'status_reason', 'sec_registration_no', 'license_number'] as $column) {
                $t->string($column)->nullable();
            }
        });
    }

    private function registration(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        // Omit uploads so these checks never create accounts or store files.
        return $this->postJson('/register', array_replace([
            'account_type' => 'buyer', 'auth_method' => 'manual',
            'given_names' => 'Niño', 'last_name' => 'Peñaflor-Reyes',
            'sex' => 'male', 'birthday' => now()->subYears(18)->toDateString(),
            'email' => 'registration.test@gmail.com', 'username' => 'buyer_test',
            'contact_no' => '+639171234567', 'region' => 'Central Visayas', 'province' => 'Cebu',
            'municipality' => 'Cebu City', 'barangay' => 'Lahug',
            'password' => 'Mamimili@2026', 'password_confirmation' => 'Mamimili@2026',
        ], $overrides))->assertStatus(422);
    }

    public function test_names_and_buyer_age_boundaries(): void
    {
        foreach ([1 => false, 2 => true, 50 => true, 51 => false] as $length => $valid) {
            $response = $this->registration(['given_names' => str_repeat('ñ', $length)]);
            $valid ? $response->assertJsonMissingValidationErrors('given_names') : $response->assertJsonValidationErrors('given_names');
        }
        $this->registration(['birthday' => now()->subYears(18)->addDay()->toDateString()])->assertJsonValidationErrors('birthday');
        $this->registration()->assertJsonMissingValidationErrors(['birthday', 'password', 'contact_no']);
        $this->registration(['birthday' => now()->subYears(18)->subDay()->toDateString()])->assertJsonMissingValidationErrors('birthday');
    }

    public function test_names_accept_only_letters_and_hyphens(): void
    {
        foreach (['given_names', 'middle_name', 'last_name'] as $field) {
            foreach (['Niño', 'Ana-Marie', 'Muñoz'] as $name) {
                $this->registration([$field => $name])->assertJsonMissingValidationErrors($field);
            }
            foreach (['Juan Miguel', "O'Hara", 'Jr.', 'Ana123', 'Ana@'] as $name) {
                $this->registration([$field => $name])->assertJsonValidationErrors($field);
            }
        }
        $this->registration(['middle_name' => ''])->assertJsonMissingValidationErrors('middle_name');
    }

    public function test_public_rider_age_and_license_expiry_boundaries(): void
    {
        $rider = ['account_type' => 'rider', 'business_name' => '', 'license_number' => 'N01-23-456789'];
        $this->registration(array_replace($rider, ['birthday' => now()->subYears(18)->addDay()->toDateString()]))->assertJsonValidationErrors('birthday');
        foreach ([today(), today()->addDay()] as $expiry) {
            $this->registration(array_replace($rider, ['license_expiry' => $expiry->toDateString()]))->assertJsonMissingValidationErrors(['birthday', 'license_expiry']);
        }
        $this->registration(array_replace($rider, ['license_expiry' => today()->subDay()->toDateString()]))->assertJsonValidationErrors('license_expiry');
    }

    public function test_managed_rider_validation_matches_registration_rules(): void
    {
        $this->actingAs(new \App\Models\User(['business_name' => 'Test Logistics']));
        $validate = function (array $overrides = []) {
            $request = \Illuminate\Http\Request::create('/test', 'POST', array_replace([
                'role' => 'rider', 'given_names' => 'Niño', 'last_name' => 'Reyes',
                'birthday' => now()->subYears(18)->toDateString(), 'contact_no' => '9171234567',
                'license_expiry' => today()->toDateString(),
            ], $overrides));
            try {
                // Other required fields are deliberately absent, preventing writes.
                (new \ReflectionMethod(\App\Http\Controllers\LogisticsManagementController::class, 'accountData'))
                    ->invoke(app(\App\Http\Controllers\LogisticsManagementController::class), $request);
                $this->fail('Expected missing-field validation.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                return $e->errors();
            }
        };
        $errors = $validate();
        foreach (['given_names', 'last_name', 'birthday', 'contact_no', 'license_expiry'] as $field) $this->assertArrayNotHasKey($field, $errors);
        foreach (['given_names', 'last_name', 'middle_name'] as $field) {
            foreach (['A', str_repeat('A', 51), 'Name123'] as $value) $this->assertArrayHasKey($field, $validate([$field => $value]));
            foreach (['Jo', str_repeat('A', 50)] as $value) $this->assertArrayNotHasKey($field, $validate([$field => $value]));
        }
        $this->assertArrayHasKey('birthday', $validate(['birthday' => now()->subYears(18)->addDay()->toDateString()]));
        $this->assertArrayHasKey('license_expiry', $validate(['license_expiry' => today()->subDay()->toDateString()]));
        $this->assertArrayNotHasKey('license_expiry', $validate(['license_expiry' => today()->addDay()->toDateString()]));
        DB::table('users')->insert(['contact_no' => '09171234567']);
        $this->assertArrayHasKey('contact_no', $validate());
    }

    public function test_mobile_formats_and_duplicates_across_roles(): void
    {
        foreach (['9171234567', '09171234567', '639171234567', '+639171234567'] as $number) {
            $this->assertSame('09171234567', PhilippineMobile::normalize($number));
            $this->registration(['contact_no' => $number])->assertJsonMissingValidationErrors('contact_no');
        }
        foreach (['0917123456', '091712345678', '0917ABC4567', '12345678901'] as $number) {
            $this->registration(['contact_no' => $number])->assertJsonValidationErrors('contact_no');
        }
        DB::table('users')->insert(['contact_no' => '09171234567', 'status' => 'approved']);
        foreach (['9171234567', '09171234567', '639171234567', '+639171234567'] as $number) {
            $this->registration(['contact_no' => $number])->assertJsonValidationErrors('contact_no');
        }
        DB::table('users')->delete();
        DB::table('users')->insert(['contact_no' => '+639171234567', 'status' => 'pending']);
        $this->registration(['contact_no' => '09171234567'])->assertJsonValidationErrors('contact_no');
    }

    public function test_password_complexity_and_confirmation(): void
    {
        foreach (['Bili@26', 'mamimili@2026', 'MAMIMILI@2026', 'Mamimili@abcd', 'Mamimili2026'] as $password) {
            $this->registration(['password' => $password, 'password_confirmation' => $password])->assertJsonValidationErrors('password');
        }
        $this->registration(['password' => 'Bili@202', 'password_confirmation' => 'Bili@202'])->assertJsonMissingValidationErrors('password');
        $this->registration(['password_confirmation' => 'Different@2026'])->assertJsonValidationErrors('password');
    }

    public function test_registration_rechecks_mobile_inside_the_save_transaction(): void
    {
        Schema::create('hiring_identity_locks', fn (Blueprint $t) => $t->unsignedInteger('id')->primary());
        DB::table('hiring_identity_locks')->insert(array_map(fn ($id) => ['id' => $id], range(0, 255)));
        DB::table('users')->insert(['contact_no' => '09171234567', 'status' => 'approved']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('This mobile number is already registered.');
        app(\App\Services\HubHiring::class)->register([
            'email' => 'another@gmail.com', 'username' => 'another_buyer',
            'contact_no' => '639171234567',
        ], null);
    }

    public function test_seller_shop_type_tin_and_region_validation(): void
    {
        $seller = ['account_type' => 'seller', 'business_name' => 'Test Shop', 'seller_type' => 'individual', 'tin' => '123456789000', 'postal_code' => '1603'];
        foreach ([2 => false, 3 => true, 30 => true, 31 => false] as $length => $valid) {
            $response = $this->registration(array_replace($seller, ['business_name' => str_repeat('A', $length)]));
            $valid ? $response->assertJsonMissingValidationErrors('business_name') : $response->assertJsonValidationErrors('business_name');
        }
        foreach (\App\Enums\SellerType::cases() as $type) {
            $this->registration(array_replace($seller, ['seller_type' => $type->value]))->assertJsonMissingValidationErrors(['seller_type', 'tin', 'region']);
        }
        $this->registration(array_replace($seller, ['seller_type' => 'unknown']))->assertJsonValidationErrors('seller_type');
        $this->registration(array_replace($seller, ['seller_type' => '']))->assertJsonValidationErrors('seller_type');
        foreach (['12345678900', '1234567890001', '123-456-78A-000', '123456-789000'] as $tin) {
            $this->registration(array_replace($seller, ['tin' => $tin]))->assertJsonValidationErrors('tin');
        }
        $this->registration(array_replace($seller, ['region' => '']))->assertJsonValidationErrors('region');
        DB::table('users')->insert(['tin' => '123456789000', 'status' => 'approved']);
        $this->registration($seller)->assertJsonValidationErrors('tin');
    }

    public function test_logistics_company_fields_are_required_and_validated(): void
    {
        $logistics = ['account_type' => 'logistics', 'business_name' => 'Test Logistics',
            'tin' => '234567890000', 'sec_registration_no' => 'CS202012345',
            'position' => 'Operations Manager', 'postal_code' => '0123',
            'hub_areas' => json_encode([['region' => 'Central Visayas', 'province' => 'Cebu', 'municipality' => 'Cebu City']])];
        $this->registration($logistics)->assertJsonMissingValidationErrors(['tin', 'sec_registration_no', 'position', 'postal_code', 'hub_areas']);
        foreach (['tin', 'sec_registration_no', 'position', 'postal_code'] as $field) {
            $this->registration(array_replace($logistics, [$field => '']))->assertJsonValidationErrors($field);
        }
        foreach (['CS20201234', '202012345CS', 'CS2020-12345', '12345678901'] as $sec) {
            $this->registration(array_replace($logistics, ['sec_registration_no' => $sec]))->assertJsonValidationErrors('sec_registration_no');
        }
        foreach (['160', '16030', '16A3'] as $postal) {
            $this->registration(array_replace($logistics, ['postal_code' => $postal]))->assertJsonValidationErrors('postal_code');
        }
        $this->registration(array_replace($logistics, ['tin' => '234-567-890-000']))->assertJsonValidationErrors('tin');
        $this->registration(array_replace($logistics, ['hub_areas' => json_encode([['province' => 'Cebu', 'municipality' => 'Cebu City']])]))->assertJsonValidationErrors('hub_areas');
        DB::table('users')->insert(['sec_registration_no' => 'CS202012345', 'tin' => '234567890000', 'status' => 'approved']);
        $this->registration($logistics)->assertJsonValidationErrors(['sec_registration_no', 'tin']);
    }

    public function test_rejection_reason_is_shown_only_after_password_verification(): void
    {
        DB::table('users')->insert(['email' => 'rejected@gmail.com', 'status' => 'rejected',
            'password' => \Illuminate\Support\Facades\Hash::make('Test@2026'), 'status_reason' => 'Business permit is unreadable.']);
        $this->post('/login', ['email' => 'rejected@gmail.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $this->post('/login', ['email' => 'rejected@gmail.com', 'password' => 'Test@2026'])
            ->assertSessionHasErrors(['email' => 'Your account application was rejected. Reason: Business permit is unreadable.']);
        $this->assertGuest();
    }

    public function test_otp_expires_after_five_minutes_and_resend_replaces_it(): void
    {
        Mail::shouldReceive('raw')->twice()->withArgs(fn ($body, $callback) => str_contains($body, 'expires in 5 minutes'));
        $email = 'registration.test@gmail.com';
        $key = 'otp_'.md5($email);
        $this->postJson('/register/send-otp', ['email' => $email])->assertJson(['success' => true]);
        $first = Cache::get($key);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $first);
        $this->travel(299)->seconds();
        $this->assertSame($first, Cache::get($key));
        $this->travel(2)->seconds();
        $this->postJson('/register/verify-otp', ['email' => $email, 'otp' => $first])->assertJson(['success' => false]);
        Cache::put($key, 'old-code', now()->addMinutes(5));
        $this->postJson('/register/send-otp', ['email' => $email])->assertJson(['success' => true]);
        $this->assertNotSame('old-code', Cache::get($key));
        $this->postJson('/register/verify-otp', ['email' => $email, 'otp' => Cache::get($key)])->assertJson(['success' => true]);
    }
}
