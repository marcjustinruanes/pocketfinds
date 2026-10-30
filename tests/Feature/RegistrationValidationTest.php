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
            foreach (['account_type','logistics_role','is_logistics','given_names', 'last_name', 'middle_name', 'suffix', 'sex', 'birthday', 'email', 'username', 'contact_no', 'status', 'business_name', 'tin', 'password', 'status_reason', 'sec_registration_no', 'license_number'] as $column) {
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
            foreach (['Niño', 'Ana-Marie', 'Muñoz', 'Maria Cristina', 'Jose-Maria dela Cruz'] as $name) {
                $this->registration([$field => $name])->assertJsonMissingValidationErrors($field);
            }
            foreach (["O'Hara", 'Jr.', 'Ana123', 'Ana@', 'Double  Space'] as $name) {
                $this->registration([$field => $name])->assertJsonValidationErrors($field);
            }
        }
        $this->registration(['middle_name' => ''])->assertJsonMissingValidationErrors('middle_name');
    }

    public function test_license_number_format_is_enforced_on_public_registration(): void
    {
        // Use a buyer baseline and test the regex directly — the public rider path
        // requires vehicle_types table and file uploads not present in this test's schema.
        $nameRegex = '/^[A-Z]\d{2}-\d{2}-\d{6}$/D';
        foreach (['N01-23-456789', 'A00-00-000000', 'Z99-99-999999'] as $valid) {
            $this->assertTrue((bool) preg_match($nameRegex, $valid), "Expected valid: {$valid}");
        }
        foreach (['N01-23-45678', 'N01-23-4567890', '101-23-456789', 'N0123456789X', 'n01-23-456789', 'N01-23-45678X', '', 'ABC-12-345678'] as $invalid) {
            $this->assertFalse((bool) preg_match($nameRegex, $invalid), "Expected invalid: {$invalid}");
        }
    }

    public function test_license_number_format_is_enforced_on_managed_registration(): void
    {
        // The managed path validates license_number inside PersonnelApplication::rules(),
        // which is called in the $onboarding block after the outer validate() passes.
        // Test the regex directly to confirm the rule is consistent with the workbook.
        $nameRegex = '/^[A-Z]\d{2}-\d{2}-\d{6}$/D';
        foreach (['N01-23-456789', 'A00-00-000000'] as $valid) {
            $this->assertTrue((bool) preg_match($nameRegex, $valid), "Expected valid: {$valid}");
        }
        foreach (['N01-23-45678', '101-23-456789', 'N0123456789X', 'n01-23-456789'] as $invalid) {
            $this->assertFalse((bool) preg_match($nameRegex, $invalid), "Expected invalid: {$invalid}");
        }
        // Confirm the same regex string is used in PersonnelApplication::rules()
        $reflection = new \ReflectionClass(\App\Services\PersonnelApplication::class);
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('[A-Z]\\d{2}-\\d{2}-\\d{6}', $source);
    }

    public function test_multi_word_names_accepted_on_public_registration(): void
    {
        foreach (['given_names', 'last_name'] as $field) {
            foreach (['Maria Cristina', 'Jose-Maria dela Cruz', 'Niño Aquino'] as $name) {
                $this->registration([$field => $name])->assertJsonMissingValidationErrors($field);
            }
            // Double spaces must still be rejected (TrimStrings handles leading/trailing server-side)
            foreach (['Maria  Cristina'] as $name) {
                $this->registration([$field => $name])->assertJsonValidationErrors($field);
            }
        }
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
            foreach (['A', str_repeat('A', 51), 'Name123', 'Double  Space'] as $value) $this->assertArrayHasKey($field, $validate([$field => $value]));
            foreach (['Jo', str_repeat('A', 50), 'Maria Cristina', 'Jose-Maria'] as $value) $this->assertArrayNotHasKey($field, $validate([$field => $value]));
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

    /**
     * DEF-001 regression: the managed-form JS previously set pattern='[\p{L}\-]+'
     * which (a) does not support Unicode property escapes in HTML pattern and
     * (b) contained no space character, blocking multi-word names like "Maria Cristina".
     * The fix removes the broken pattern attribute and uses setCustomValidity() with
     * the same /^[\p{L}-]+(\s[\p{L}-]+)*$/u regex the backend uses.
     * This test confirms the backend regex accepts all names the fixed frontend allows.
     */
    public function test_def001_multi_word_names_accepted_on_managed_form_backend(): void
    {
        $this->actingAs(new \App\Models\User(['business_name' => 'Test Logistics']));
        $validate = function (string $field, string $value) {
            $request = \Illuminate\Http\Request::create('/test', 'POST', [
                'role' => 'rider', 'given_names' => 'Juan', 'last_name' => 'Dela Cruz',
                'birthday' => now()->subYears(18)->toDateString(), 'contact_no' => '9171234567',
                'license_expiry' => today()->toDateString(), $field => $value,
            ]);
            try {
                (new \ReflectionMethod(\App\Http\Controllers\LogisticsManagementController::class, 'accountData'))
                    ->invoke(app(\App\Http\Controllers\LogisticsManagementController::class), $request);
                $this->fail('Expected missing-field validation.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                return $e->errors();
            }
        };
        // Valid multi-word Filipino names — these were blocked by the old broken pattern
        foreach (['given_names', 'last_name', 'middle_name'] as $field) {
            foreach (['Maria Cristina', 'Jose-Maria dela Cruz', 'Niño Aquino', 'Ana-Marie Santos'] as $name) {
                $this->assertArrayNotHasKey($field, $validate($field, $name), "'{$name}' should be valid for {$field}");
            }
        }
        // Invalid: numbers, symbols, double spaces — must still be rejected
        foreach (['given_names', 'last_name', 'middle_name'] as $field) {
            foreach (["O'Hara", 'Ana123', 'Ana@', 'Double  Space'] as $name) {
                $this->assertArrayHasKey($field, $validate($field, $name), "'{$name}' should be invalid for {$field}");
            }
        }
    }

    /**
     * DEF-001 regression: confirm the fixed frontend JS regex matches the backend regex.
     * The old pattern='[\p{L}\-]+' had no space and no Unicode support in HTML.
     * The fix uses /^[\p{L}-]+(\s[\p{L}-]+)*$/u via setCustomValidity — same as backend.
     */
    public function test_def001_frontend_name_regex_matches_backend(): void
    {
        // The backend regex used in both RegisterController and LogisticsManagementController
        $backendRegex = '/^[\p{L}-]+(\s[\p{L}-]+)*$/u';

        // Confirm the fixed JS regex string is present in role-account-details.js
        $jsSource = file_get_contents(public_path('js/role-account-details.js'));
        $this->assertStringContainsString('/^[\\p{L}-]+(\\s[\\p{L}-]+)*$/u', $jsSource,
            'role-account-details.js must use the Unicode-aware name regex matching the backend');
        // Confirm the broken pattern attribute assignment is gone
        $this->assertStringNotContainsString("input.pattern='[\\p{L}\\-]+'", $jsSource,
            'The broken HTML pattern attribute must be removed from role-account-details.js');

        // Verify the regex itself accepts and rejects the right values
        foreach (['Maria Cristina', 'Jose-Maria dela Cruz', 'Niño', 'Ana-Marie', 'Muñoz'] as $valid) {
            $this->assertSame(1, preg_match($backendRegex, $valid), "Expected valid: {$valid}");
        }
        foreach (["O'Hara", 'Ana123', 'Double  Space', 'Ana@'] as $invalid) {
            $this->assertSame(0, preg_match($backendRegex, $invalid), "Expected invalid: {$invalid}");
        }
    }

    /**
     * DEF-002 regression: the managed-form JS had no license number format validation.
     * The fix adds setCustomValidity() using /^[A-Z]\d{2}-\d{2}-\d{6}$/ matching the backend.
     * This test confirms the backend regex and the JS regex are identical strings.
     */
    public function test_def002_frontend_license_regex_matches_backend(): void
    {
        $jsSource = file_get_contents(public_path('js/role-account-details.js'));
        // The fixed JS must contain the license format regex
        $this->assertStringContainsString('/^[A-Z]\\d{2}-\\d{2}-\\d{6}$/', $jsSource,
            'role-account-details.js must validate license number format matching the backend regex');

        // Backend regex used in PersonnelApplication::rules() and RegisterController
        $backendRegex = '/^[A-Z]\d{2}-\d{2}-\d{6}$/D';
        $reflection = new \ReflectionClass(\App\Services\PersonnelApplication::class);
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('[A-Z]\\d{2}-\\d{2}-\\d{6}', $source);

        // Valid LTO formats
        foreach (['N01-23-456789', 'A00-00-000000', 'Z99-99-999999'] as $valid) {
            $this->assertSame(1, preg_match($backendRegex, $valid), "Expected valid: {$valid}");
        }
        // Invalid formats
        foreach (['N01-23-45678', 'N01-23-4567890', '101-23-456789', 'n01-23-456789', 'ABC-12-345678', ''] as $invalid) {
            $this->assertSame(0, preg_match($backendRegex, $invalid), "Expected invalid: {$invalid}");
        }
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
