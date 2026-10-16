<?php

namespace Tests\Feature;

use App\Mail\RegistrationCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherRegistrationTest extends TestCase
{
    use WorkflowSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_16_000001_add_account_verification.php'))->up();
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2026-10-16 12:00:00'));
        DB::table('categories')->insert(['id' => 1, 'name' => 'Home & Living']);
    }

    public function test_public_registration_requires_a_complete_email_domain_for_all_roles(): void
    {
        foreach (['buyer', 'seller', 'logistics'] as $role) {
            $this->postJson('/register', array_replace($this->data($role), ['email' => 'buyer@example']))->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingSent();
    }

    private function data(string $role = 'buyer'): array
    {
        $data = ['account_type' => $role, 'given_names' => 'Juan Miguel', 'last_name' => 'Dela Cruz',
            'email' => 'juanmiguel.delacruz@example.com', 'contact_no' => '09171234567', 'birthday' => '1998-05-14',
            'password' => 'Mamimili@2026', 'password_confirmation' => 'Mamimili@2026', 'terms' => 1];
        if ($role !== 'buyer') {
            $data += ['business_name' => 'Maria Clara Handicrafts', 'tin' => '123-456-789-000',
                'region' => 'National Capital Region (NCR)', 'province' => 'Metro Manila', 'municipality' => 'Quezon City',
                'barangay' => 'Commonwealth', 'street' => 'Blk 12 Lot 8, Sampaguita St.', 'postal_code' => '1121',
                'id_file' => UploadedFile::fake()->image('govt_id_front.jpg')];
        }
        if ($role === 'seller') {
            $data += ['category_id' => 1, 'seller_type' => 'sole_proprietorship', 'dti_file' => UploadedFile::fake()->create('dti_certificate.pdf', 850, 'application/pdf'),
                'bir_file' => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf')];
        }
        if ($role === 'logistics') {
            $data += ['sec_registration_no' => 'CS202012345', 'position' => 'Operations Manager', 'service_areas' => ['NCR', 'Region IV-A'],
                'sec_file' => UploadedFile::fake()->create('sec_certificate.pdf', 1000, 'application/pdf'),
                'bir_file' => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf'),
                'business_permit_file' => UploadedFile::fake()->create('mayors_permit.pdf', 1300, 'application/pdf')];
        }

        return $data;
    }

    private function code(): string
    {
        return Mail::sent(RegistrationCode::class)->last()->code;
    }

    private function uniqueData(string $role): array
    {
        $serial = User::count() + 1;

        return array_replace($this->data($role), ['email' => $role.$serial.'@example.com',
            'contact_no' => '0917'.str_pad((string) $serial, 7, '0', STR_PAD_LEFT),
            'business_name' => 'Teacher Store '.$serial, 'tin' => str_pad((string) $serial, 3, '0', STR_PAD_LEFT).'-456-789-000',
            'sec_registration_no' => 'CS'.str_pad((string) $serial, 9, '0', STR_PAD_LEFT)]);
    }

    public function test_seller_and_logistics_each_required_field_blocks_registration_without_sending_email(): void
    {
        foreach (['seller', 'logistics'] as $role) {
            $data = $this->data($role);
            unset($data['birthday']); // Neither business role requires a birth date in the workbook.
            $this->postJson('/register', ['account_type' => $role])->assertUnprocessable();
            foreach (array_keys($data) as $field) {
                if ($field === 'account_type') {
                    continue;
                }
                $input = $this->data($role);
                unset($input[$field]);
                $this->postJson('/register', $input)->assertUnprocessable()->assertJsonValidationErrors($field === 'password_confirmation' ? 'password' : $field);
            }
        }
        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_seller_and_logistics_names_enforce_format_and_two_to_fifty_characters(): void
    {
        foreach (['seller', 'logistics'] as $role) {
            foreach (['J', str_repeat('J', 51), 'Juan7', 'Juan@#'] as $name) {
                $this->postJson('/register', array_replace($this->uniqueData($role), ['given_names' => $name]))->assertUnprocessable()->assertJsonValidationErrors('given_names');
            }
            foreach (['Jo', str_repeat('J', 50), 'Niño Angelo'] as $name) {
                $data = array_replace($this->uniqueData($role), ['given_names' => $name, 'last_name' => "Peñaflor-O'Hara"]);
                $this->postJson('/register', $data)->assertOk();
                $user = User::where('email', $data['email'])->firstOrFail();
                $this->assertSame($name, $user->given_names);
                $this->assertSame("Peñaflor-O'Hara", $user->last_name);
            }
        }
    }

    public function test_business_role_email_phone_password_and_terms_rules(): void
    {
        foreach (['seller', 'logistics'] as $role) {
            foreach (['invalid', 'juan@', 'juan@@example.com'] as $email) {
                $this->postJson('/register', array_replace($this->uniqueData($role), ['email' => $email]))->assertUnprocessable()->assertJsonValidationErrors('email');
            }
            foreach (['0917ABC4567', '0917-123-456', '12345678901', '0917123456', '091712345678'] as $phone) {
                $this->postJson('/register', array_replace($this->uniqueData($role), ['contact_no' => $phone]))->assertUnprocessable()->assertJsonValidationErrors('contact_no');
            }
            foreach (['Bili@26', 'mamimili@2026', 'MAMIMILI@2026', 'Mamimili@abcd', 'Mamimili2026'] as $password) {
                $this->postJson('/register', array_replace($this->uniqueData($role), ['password' => $password, 'password_confirmation' => $password]))
                    ->assertUnprocessable()->assertJsonValidationErrors('password');
            }
            $this->postJson('/register', array_replace($this->uniqueData($role), ['password_confirmation' => 'Mismatch@2026']))->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->postJson('/register', array_replace($this->uniqueData($role), ['terms' => false]))->assertUnprocessable()->assertJsonValidationErrors('terms');
            $data = $this->uniqueData($role);
            $data['email'] = '  '.strtoupper($data['email']).'  ';
            $data['contact_no'] = '+63'.substr($data['contact_no'], 1);
            $data['password'] = $data['password_confirmation'] = 'Bili@202';
            $this->postJson('/register', $data)->assertOk();
            $this->assertDatabaseHas('users', ['email' => mb_strtolower(trim($data['email'])), 'contact_no' => '0'.substr($data['contact_no'], 3)]);
            $data['business_name'] = 'Another Store';
            $this->postJson('/register', $data)->assertUnprocessable()->assertJsonValidationErrors(['email', 'contact_no']);
        }
        $this->assertSame(2, User::count());
    }

    public function test_shop_name_length_and_case_insensitive_unique_business_identifiers(): void
    {
        foreach (['AB', str_repeat('A', 31)] as $name) {
            $this->postJson('/register', array_replace($this->data('seller'), ['business_name' => $name]))->assertUnprocessable()->assertJsonValidationErrors('business_name');
        }
        foreach (['ABC', str_repeat('A', 30)] as $name) {
            $data = array_replace($this->uniqueData('seller'), ['business_name' => $name]);
            $this->postJson('/register', $data)->assertOk();
            $this->postJson('/register', array_replace($this->uniqueData('seller'), ['business_name' => strtolower($name)]))
                ->assertUnprocessable()->assertJsonValidationErrors('business_name');
        }
        $data = $this->uniqueData('logistics');
        $this->postJson('/register', $data)->assertOk();
        $this->postJson('/register', array_replace($this->uniqueData('logistics'), ['business_name' => strtolower($data['business_name'])]))
            ->assertUnprocessable()->assertJsonValidationErrors('business_name');
        $this->postJson('/register', array_replace($this->uniqueData('seller'), ['tin' => $data['tin']]))->assertUnprocessable()->assertJsonValidationErrors('tin');
        $this->postJson('/register', array_replace($this->uniqueData('logistics'), ['sec_registration_no' => $data['sec_registration_no']]))
            ->assertUnprocessable()->assertJsonValidationErrors('sec_registration_no');
    }

    public function test_tin_postal_and_sec_formats_and_multiple_service_areas(): void
    {
        foreach (['seller', 'logistics'] as $role) {
            foreach (['123456789', '123-456-789', 'ABC-456-789-000', '123-456-789-0000'] as $tin) {
                $this->postJson('/register', array_replace($this->data($role), ['tin' => $tin]))->assertUnprocessable()->assertJsonValidationErrors('tin');
            }
            foreach (['123', '12345', 'ABCD'] as $postal) {
                $this->postJson('/register', array_replace($this->data($role), ['postal_code' => $postal]))->assertUnprocessable()->assertJsonValidationErrors('postal_code');
            }
        }
        foreach (['C202012345', 'CS20201234', 'CS2020123456', '12ABCDEFGHI'] as $sec) {
            $this->postJson('/register', array_replace($this->data('logistics'), ['sec_registration_no' => $sec]))->assertUnprocessable()->assertJsonValidationErrors('sec_registration_no');
        }
        $data = $this->data('logistics');
        $this->postJson('/register', $data)->assertOk();
        $this->assertSame(['NCR', 'Region IV-A'], User::firstOrFail()->service_areas);
    }

    public function test_seller_document_requirements_change_with_business_type(): void
    {
        foreach (['individual' => [], 'sole_proprietorship' => ['dti_file', 'bir_file'], 'corporation' => ['sec_file', 'bir_file']] as $type => $required) {
            foreach ($required as $missing) {
                $data = array_replace($this->uniqueData('seller'), ['seller_type' => $type]);
                $data['sec_file'] = UploadedFile::fake()->create('sec.pdf', 10, 'application/pdf');
                unset($data[$missing]);
                $this->postJson('/register', $data)->assertUnprocessable()->assertJsonValidationErrors($missing);
            }
            $data = array_replace($this->uniqueData('seller'), ['seller_type' => $type]);
            unset($data['dti_file'], $data['bir_file']);
            foreach ($required as $field) {
                $data[$field] = UploadedFile::fake()->create($field.'.pdf', 10, 'application/pdf');
            }
            $this->postJson('/register', $data)->assertOk();
        }
        $this->assertSame(3, User::count());
    }

    public function test_business_documents_accept_supported_types_and_enforce_five_mb_limit(): void
    {
        foreach (['seller', 'logistics'] as $role) {
            $fields = $role === 'seller' ? ['id_file', 'dti_file', 'bir_file'] : ['id_file', 'sec_file', 'bir_file', 'business_permit_file'];
            foreach ($fields as $field) {
                foreach ([UploadedFile::fake()->create('bad.exe', 1, 'application/octet-stream'), UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')] as $file) {
                    $this->postJson('/register', array_replace($this->uniqueData($role), [$field => $file]))->assertUnprocessable()->assertJsonValidationErrors($field);
                }
            }
            foreach (['jpg', 'png', 'pdf'] as $extension) {
                $data = $this->uniqueData($role);
                foreach ($fields as $field) {
                    $data[$field] = $extension === 'pdf'
                        ? UploadedFile::fake()->create('limit.pdf', 5120, 'application/pdf') : UploadedFile::fake()->image('document.'.$extension);
                }
                $this->postJson('/register', $data)->assertOk();
                $user = User::where('email', $data['email'])->firstOrFail();
                foreach ($fields as $field) {
                    Storage::disk('public')->assertExists($user->{$field});
                }
            }
        }
    }

    public function test_business_pending_approval_rejection_reason_and_resubmission(): void
    {
        $admin = User::create(['account_type' => 'admin', 'is_admin' => true, 'status' => 'approved', 'email' => 'admin@example.com']);
        foreach (['seller', 'logistics'] as $role) {
            $data = $this->uniqueData($role);
            $this->postJson('/register', $data)->assertOk();
            $user = User::where('email', $data['email'])->firstOrFail();
            $this->actingAs($admin)->patchJson('/admin/registrations/'.$user->id.'/approve')->assertUnprocessable();
            $this->postJson('/register/verify-otp', ['otp' => $this->code()])->assertOk();
            $this->assertSame('pending', $user->fresh()->status);
            $this->actingAs($user)->get('/'.$role.'/dashboard')->assertForbidden();
            $this->actingAs($admin)->get('/admin/registrations')->assertOk()->assertSee($user->email);
            $this->patch('/admin/registrations/'.$user->id.'/reject', ['reason_preset' => 'Documents incomplete'])->assertRedirect();
            $this->assertSame('rejected', $user->fresh()->status);
            $this->assertStringContainsString('Documents incomplete', $user->fresh()->status_reason);
            $this->postJson('/register', $data)->assertOk();
            $this->assertSame($user->id, User::where('email', $data['email'])->firstOrFail()->id);
            $this->assertSame('unverified', $user->fresh()->status);
            $this->assertDatabaseHas('hiring_application_history', ['user_id' => $user->id, 'status' => 'rejected', 'reason' => 'Documents incomplete']);
            $this->postJson('/register/verify-otp', ['otp' => $this->code()])->assertOk();
            $this->patch('/admin/registrations/'.$user->id.'/approve')->assertRedirect();
            $this->assertSame('approved', $user->fresh()->status);
        }
    }

    public function test_all_roles_save_unverified_and_only_transition_after_correct_email_code(): void
    {
        foreach (['buyer', 'seller', 'logistics'] as $i => $role) {
            $data = $this->data($role);
            $data['email'] = $role.'@example.com';
            $data['contact_no'] = '0917000000'.($i + 1);
            $data['tin'] = $i.'23-456-789-000';
            $this->postJson('/register', $data)->assertOk()->assertJsonPath('success', true);
            $user = User::where('email', $data['email'])->firstOrFail();
            $this->assertSame('unverified', $user->status);
            $this->assertNull($user->email_verified_at);
            $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $this->code());
            $this->get('/register/verify')->assertOk()->assertSee($data['email']);
            $this->postJson('/register/verify-otp', ['otp' => $this->code()])->assertOk();
            $this->assertSame($role === 'buyer' ? 'approved' : 'pending', $user->fresh()->status);
            $this->assertNotNull($user->fresh()->email_verified_at);
        }
        Mail::assertSent(RegistrationCode::class, 3);
    }

    public function test_tc_buy_002_empty_form_creates_no_user_and_sends_no_email(): void
    {
        $this->postJson('/register', ['account_type' => 'buyer'])->assertUnprocessable()
            ->assertJsonValidationErrors(['given_names', 'last_name', 'email', 'contact_no', 'birthday', 'password', 'terms']);
        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_tc_buy_003_each_required_field_is_rejected_without_losing_other_data(): void
    {
        foreach (['given_names', 'last_name', 'email', 'contact_no', 'birthday', 'password', 'password_confirmation'] as $field) {
            $data = $this->data();
            $data[$field] = '';
            $this->postJson('/register', $data)->assertUnprocessable()->assertJsonValidationErrors($field === 'password_confirmation' ? 'password' : $field);
        }
        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_tc_buy_004_name_numbers_and_symbols_are_rejected(): void
    {
        $this->postJson('/register', array_replace($this->data(), ['given_names' => 'Juan Miguel123', 'last_name' => 'Dela Cruz@#']))
            ->assertUnprocessable()->assertJsonValidationErrors(['given_names', 'last_name']);
        $this->assertSame(0, User::count());
    }

    public function test_tc_buy_005_names_preserve_enye_and_allowed_punctuation(): void
    {
        $this->postJson('/register', array_replace($this->data(), ['given_names' => 'Niño Angelo', 'last_name' => "Peñaflor-O'Hara"]))->assertOk();
        $this->assertSame('Niño Angelo', User::firstOrFail()->given_names);
        $this->assertSame("Peñaflor-O'Hara", User::firstOrFail()->last_name);
    }

    public function test_tc_buy_006_actual_name_lengths_one_two_fifty_and_fifty_one(): void
    {
        foreach ([1, 51] as $length) {
            $this->postJson('/register', array_replace($this->data(), ['given_names' => str_repeat('J', $length)]))->assertUnprocessable()->assertJsonValidationErrors('given_names');
        }
        foreach ([2, 50] as $i => $length) {
            $data = array_replace($this->data(), ['given_names' => str_repeat('J', $length), 'email' => 'length'.$i.'@example.com', 'contact_no' => '0917000000'.($i + 1)]);
            $this->postJson('/register', $data)->assertOk();
            $this->assertSame($length, mb_strlen(User::where('email', $data['email'])->firstOrFail()->given_names));
        }
    }

    public function test_tc_buy_007_eighteenth_birthday_boundary_and_008_future_date(): void
    {
        foreach (['2008-10-17', '2026-10-17'] as $birthday) {
            $this->postJson('/register', array_replace($this->data(), ['birthday' => $birthday]))->assertUnprocessable()->assertJsonValidationErrors('birthday');
        }
        foreach (['2008-10-16', '2008-10-15'] as $i => $birthday) {
            $this->postJson('/register', array_replace($this->data(), ['birthday' => $birthday,
                'email' => 'age'.$i.'@example.com', 'contact_no' => '0917000000'.($i + 1)]))->assertOk();
        }
        $this->assertSame(2, User::count());
    }

    public function test_tc_buy_009_invalid_email_format(): void
    {
        foreach (['juan', 'juan@', 'juan.example.com', 'juan@@example.com'] as $email) {
            $this->postJson('/register', array_replace($this->data(), ['email' => $email]))->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertSame(0, User::count());
    }

    public function test_tc_buy_010_011_012_email_uniqueness_case_and_whitespace(): void
    {
        $this->postJson('/register', array_replace($this->data(), ['email' => '  JuanMiguel.DelaCruz@EXAMPLE.COM  ']))->assertOk();
        $this->assertSame('juanmiguel.delacruz@example.com', User::firstOrFail()->email);
        foreach (['juanmiguel.delacruz@example.com', 'JUANMIGUEL.DELACRUZ@EXAMPLE.COM'] as $email) {
            $this->postJson('/register', array_replace($this->data(), ['email' => $email]))->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertSame(1, User::count());
    }

    public function test_tc_buy_013_014_phone_format_and_length(): void
    {
        foreach (['0917ABC4567', '0917-123-456', '12345678901', '02-8123-4567', '0917123456', '091712345678'] as $phone) {
            $this->postJson('/register', array_replace($this->data(), ['contact_no' => $phone]))->assertUnprocessable()->assertJsonValidationErrors('contact_no');
        }
        $this->assertSame(0, User::count());
        $this->postJson('/register', array_replace($this->data(), ['contact_no' => '09170000001']))->assertOk();
    }

    public function test_tc_buy_015_016_phone_uniqueness_and_plus63_normalization(): void
    {
        $this->postJson('/register', array_replace($this->data(), ['contact_no' => '+639171234567']))->assertOk();
        $this->assertSame('09171234567', User::firstOrFail()->contact_no);
        foreach (['09171234567', '+639171234567'] as $phone) {
            $this->postJson('/register', array_replace($this->data(), ['email' => 'spare@example.com', 'contact_no' => $phone]))
                ->assertUnprocessable()->assertJsonValidationErrors('contact_no');
        }
        $this->assertSame(1, User::count());
    }

    public function test_tc_buy_017_018_019_password_boundaries_complexity_and_confirmation(): void
    {
        foreach (['Bili@26', 'mamimili@2026', 'MAMIMILI@2026', 'Mamimili@abcd', 'Mamimili2026'] as $password) {
            $this->postJson('/register', array_replace($this->data(), ['password' => $password, 'password_confirmation' => $password]))
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson('/register', array_replace($this->data(), ['password_confirmation' => 'Mismatch@2026']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame(0, User::count());
        $this->postJson('/register', array_replace($this->data(), ['password' => 'Bili@202', 'password_confirmation' => 'Bili@202']))->assertOk();
    }

    public function test_tc_buy_021_terms_and_022_duplicate_submission(): void
    {
        $this->postJson('/register', array_replace($this->data(), ['terms' => false]))->assertUnprocessable()->assertJsonValidationErrors('terms');
        $this->assertSame(0, User::count());
        $this->postJson('/register', $this->data())->assertOk();
        $this->postJson('/register', $this->data())->assertUnprocessable();
        $this->assertSame(1, User::count());
        Mail::assertSent(RegistrationCode::class, 1);
    }

    public function test_tc_buy_023_024_025_026_027_codes_format_expiry_and_resend(): void
    {
        $this->postJson('/register', $this->data())->assertOk();
        $first = $this->code();
        $wrong = $first === '000000' ? '111111' : '000000';
        $this->postJson('/register/verify-otp', ['otp' => $wrong])->assertUnprocessable();
        foreach (['48291', '4829137', '48A91B', ''] as $code) {
            $this->postJson('/register/verify-otp', ['otp' => $code])->assertUnprocessable()->assertJsonValidationErrors('otp');
        }
        $this->assertSame('unverified', User::firstOrFail()->status);
        $this->travel(5)->minutes();
        $this->postJson('/register/verify-otp', ['otp' => $first])->assertUnprocessable();
        $this->postJson('/register/send-otp')->assertOk();
        $second = $this->code();
        $this->assertNotSame($first, $second);
        $this->postJson('/register/verify-otp', ['otp' => $first])->assertUnprocessable();
        $this->postJson('/register/verify-otp', ['otp' => $second])->assertOk();
        $this->assertSame('approved', User::firstOrFail()->status);
        $this->postJson('/register/verify-otp', ['otp' => $second])->assertForbidden();
    }

    public function test_tc_buy_028_029_030_unverified_login_verified_login_and_role_isolation(): void
    {
        $this->postJson('/register', $this->data())->assertOk();
        $this->post('/login', ['email' => $this->data()['email'], 'password' => 'Mamimili@2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->postJson('/register/verify-otp', ['otp' => $this->code()])->assertOk();
        $this->post('/login', ['email' => $this->data()['email'], 'password' => 'Mamimili@2026'])->assertRedirect('/buyer/dashboard');
        $this->assertAuthenticated();
        foreach (['seller', 'logistics', 'admin'] as $role) {
            $response = $this->get('/'.$role.'/dashboard');
            $this->assertContains($response->status(), [302, 403]);
        }
    }

    public function test_tc_rid_029_public_rider_registration_is_not_available(): void
    {
        $this->get('/register/rider')->assertForbidden();
        $this->postJson('/register', $this->data('rider'))->assertForbidden();
        $this->assertSame(0, User::count());
    }
}
