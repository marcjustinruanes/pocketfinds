<?php

namespace Tests\Feature;

use App\Mail\RiderActivation;
use App\Models\LogisticsHub;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class TeacherRiderInvitationTest extends TestCase
{
    use WorkflowSchema;

    private User $company;

    private LogisticsHub $hub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        (require database_path('migrations/2026_10_16_000001_add_account_verification.php'))->up();
        $this->travelTo(Carbon::parse('2026-10-16 12:00:00'));
        Mail::fake();
        Storage::fake('public');
        $this->company = User::create(['account_type' => 'logistics', 'is_logistics' => true, 'logistics_role' => 'admin',
            'business_name' => 'Bilis Hatid Express Inc.', 'email' => 'operations@bilishatid.example.com', 'status' => 'approved']);
        $this->hub = LogisticsHub::create(['company_name' => $this->company->business_name, 'province' => 'Metro Manila',
            'municipality' => 'Pasig City', 'is_operational' => true, 'is_hiring' => true]);
    }

    private function data(): array
    {
        return ['given_names' => 'Pedro Jose', 'last_name' => 'Garcia', 'birthday' => '1995-03-22',
            'email' => 'pedrojose.garcia@example.com', 'contact_no' => '09451239876', 'license_number' => 'N01-23-456789',
            'license_expiry' => '2030-03-22', 'vehicle_type' => 'two_wheels', 'plate_number' => '123ABC',
            'logistics_hub_id' => $this->hub->id, 'license_file' => UploadedFile::fake()->image('drivers_license.jpg'),
            'or_cr_file' => UploadedFile::fake()->create('or_cr.pdf', 900, 'application/pdf')];
    }

    private function invite(): RiderActivation
    {
        $this->actingAs($this->company)->postJson('/logistics/riders', $this->data())->assertOk()->assertJsonPath('status', 'pending_activation');

        return Mail::sent(RiderActivation::class)->last();
    }

    private function link(RiderActivation $mail): string
    {
        return route('rider.activation', ['user' => $mail->rider->id, 'token' => $mail->token]);
    }

    public function test_tc_rid_001_valid_rider_is_created_once_with_documents_and_email_invitation(): void
    {
        $mail = $this->invite();
        $rider = $mail->rider->fresh();
        $this->assertSame('pending_activation', $rider->status);
        $this->assertNull($rider->password);
        $this->assertSame($this->company->business_name, $rider->business_name);
        Storage::disk('public')->assertExists([$rider->license_file, $rider->or_cr_file]);
        $token = DB::table('account_verification_tokens')->where('user_id', $rider->id)->first();
        $this->assertSame(now()->addHours(24)->toDateTimeString(), $token->expires_at);
        $this->get($this->link($mail))->assertOk();
        Mail::assertSent(RiderActivation::class, 1);
    }

    public function test_tc_rid_002_003_all_required_fields_must_be_present(): void
    {
        $this->actingAs($this->company)->postJson('/logistics/riders', [])->assertUnprocessable();
        foreach (array_keys($this->data()) as $field) {
            $data = $this->data();
            unset($data[$field]);
            $this->postJson('/logistics/riders', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(1, User::count());
        Storage::disk('public')->assertDirectoryEmpty('/');
        Mail::assertNothingSent();
    }

    public function test_tc_rid_004_005_name_format_and_length(): void
    {
        $this->actingAs($this->company);
        foreach (['Pedro7', 'Pedro@#', 'P', str_repeat('P', 51)] as $name) {
            $this->postJson('/logistics/riders', array_replace($this->data(), ['given_names' => $name]))->assertUnprocessable()->assertJsonValidationErrors('given_names');
        }
        $this->postJson('/logistics/riders', array_replace($this->data(), ['given_names' => 'Jo', 'last_name' => str_repeat('G', 50)]))->assertOk();
    }

    public function test_tc_rid_006_age_boundary(): void
    {
        $this->actingAs($this->company);
        $this->postJson('/logistics/riders', array_replace($this->data(), ['birthday' => '2008-10-17']))->assertUnprocessable()->assertJsonValidationErrors('birthday');
        $this->postJson('/logistics/riders', array_replace($this->data(), ['birthday' => '2008-10-16']))->assertOk();
    }

    public function test_tc_rid_007_008_email_format_and_cross_role_uniqueness(): void
    {
        $this->actingAs($this->company);
        foreach (['invalid', 'juan@@example.com', strtoupper($this->company->email)] as $email) {
            $this->postJson('/logistics/riders', array_replace($this->data(), ['email' => $email]))->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertSame(1, User::count());
    }

    public function test_tc_rid_009_010_011_mobile_formats_and_duplicate_normalization(): void
    {
        $this->actingAs($this->company);
        foreach (['0945ABC9876', '0945123987', '094512398765', '12345678901'] as $phone) {
            $this->postJson('/logistics/riders', array_replace($this->data(), ['contact_no' => $phone]))->assertUnprocessable()->assertJsonValidationErrors('contact_no');
        }
        $this->postJson('/logistics/riders', array_replace($this->data(), ['contact_no' => '+639451239876']))->assertOk();
        $this->postJson('/logistics/riders', $this->data())->assertUnprocessable()->assertJsonValidationErrors('contact_no');
        $this->assertSame('09451239876', User::where('account_type', 'rider')->firstOrFail()->contact_no);
    }

    public function test_tc_rid_012_013_014_015_license_format_expiry_and_unique_identifiers(): void
    {
        $this->actingAs($this->company);
        foreach (['12345678901', 'N0-23-456789', 'N01-23456789'] as $license) {
            $this->postJson('/logistics/riders', array_replace($this->data(), ['license_number' => $license]))->assertUnprocessable()->assertJsonValidationErrors('license_number');
        }
        $this->postJson('/logistics/riders', array_replace($this->data(), ['license_expiry' => '2026-10-15']))->assertUnprocessable()->assertJsonValidationErrors('license_expiry');
        $this->postJson('/logistics/riders', array_replace($this->data(), ['license_expiry' => '2026-10-16']))->assertOk();
        $this->postJson('/logistics/riders', $this->data())->assertUnprocessable()->assertJsonValidationErrors(['license_number', 'plate_number']);
    }

    public function test_tc_rid_017_018_upload_content_and_size_boundaries(): void
    {
        $this->actingAs($this->company);
        $text = UploadedFile::fake()->createWithContent('renamed.jpg', 'This is not an image');
        // Use the real UploadedFile MIME detector: File::fake reports MIME from its name.
        $renamed = new UploadedFile($text->getPathname(), 'renamed.jpg', 'image/jpeg', null, true);
        foreach (['license_file', 'or_cr_file'] as $field) {
            foreach ([UploadedFile::fake()->create('bad.exe', 1, 'application/octet-stream'), UploadedFile::fake()->create('too-large.pdf', 5121, 'application/pdf'),
                $renamed] as $file) {
                $response = $this->postJson('/logistics/riders', array_replace($this->data(), [$field => $file]));
                $this->assertSame(422, $response->status(), $field.' '.$file->getClientOriginalName());
                $response->assertJsonValidationErrors($field);
            }
        }
        $this->assertSame(1, User::count());
        $this->postJson('/logistics/riders', array_replace($this->data(), ['or_cr_file' => UploadedFile::fake()->create('limit.pdf', 5120, 'application/pdf')]))->assertOk();
    }

    public function test_tc_rid_019_multiple_save_requests_create_only_one_rider_and_invitation(): void
    {
        $this->invite();
        $this->postJson('/logistics/riders', $this->data())->assertUnprocessable();
        $this->assertSame(1, User::where('account_type', 'rider')->count());
        Mail::assertSent(RiderActivation::class, 1);
    }

    public function test_tc_rid_020_025_026_027_activation_login_and_single_use(): void
    {
        $mail = $this->invite();
        auth()->logout();
        $this->post('/login', ['email' => $mail->rider->email, 'password' => 'Rider@2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->postJson($this->link($mail), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertOk();
        $this->assertSame('approved', $mail->rider->fresh()->status);
        $this->assertTrue(Hash::check('Rider@2026', $mail->rider->fresh()->password));
        $this->get($this->link($mail))->assertStatus(410);
        $this->postJson($this->link($mail), ['password' => 'Rider@2027', 'password_confirmation' => 'Rider@2027'])->assertStatus(410);
        $this->post('/login', ['email' => $mail->rider->email, 'password' => 'Rider@2026'])->assertRedirect('/rider/dashboard');
    }

    public function test_tc_rid_021_022_password_rules_and_confirmation(): void
    {
        $mail = $this->invite();
        foreach (['Ridr@26', 'rider@2026', 'RIDER@2026', 'Rider@abcd', 'Rider2026'] as $password) {
            $this->postJson($this->link($mail), ['password' => $password, 'password_confirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson($this->link($mail), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2027'])->assertUnprocessable();
        $this->assertSame('pending_activation', $mail->rider->fresh()->status);
        $this->assertNull($mail->rider->fresh()->password);
    }

    public function test_tc_rid_023_024_expiry_and_resend_invalidates_old_invitation(): void
    {
        $mail = $this->invite();
        $this->travel(24)->hours();
        $this->get($this->link($mail))->assertStatus(410);
        $this->postJson($this->link($mail), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertStatus(410);
        $this->postJson('/logistics/riders/'.$mail->rider->id.'/resend-invitation')->assertOk();
        $replacement = Mail::sent(RiderActivation::class)->last();
        $this->assertNotSame($mail->token, $replacement->token);
        $this->get($this->link($mail))->assertForbidden();
        $this->postJson($this->link($replacement), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertOk();
    }

    public function test_tc_rid_028_other_company_cannot_resend_or_assign_its_hub(): void
    {
        $mail = $this->invite();
        $other = User::create(['account_type' => 'logistics', 'is_logistics' => true, 'logistics_role' => 'admin', 'business_name' => 'Agila Cargo Movers Corp.', 'status' => 'approved']);
        $this->actingAs($other)->postJson('/logistics/riders/'.$mail->rider->id.'/resend-invitation')->assertNotFound();
        $this->postJson('/logistics/riders', array_replace($this->data(), ['email' => 'other@example.com']))->assertUnprocessable()->assertJsonValidationErrors('logistics_hub_id');
    }

    public function test_tc_rid_030_031_creation_is_restricted_to_approved_logistics_admins(): void
    {
        foreach (['buyer', 'seller'] as $role) {
            $person = User::create(['account_type' => $role, 'status' => 'approved']);
            $this->actingAs($person)->get('/logistics/riders/create')->assertRedirect('/login');
            $this->postJson('/logistics/riders', $this->data())->assertRedirect('/login');
        }
        $this->company->update(['status' => 'pending']);
        $this->actingAs($this->company)->get('/logistics/riders/create')->assertForbidden();
        $this->postJson('/logistics/riders', $this->data())->assertForbidden();
        $this->assertSame(0, User::where('account_type', 'rider')->count());
    }
}
