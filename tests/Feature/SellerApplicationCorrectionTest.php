<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class SellerApplicationCorrectionTest extends TestCase
{
    use WorkflowSchema;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        foreach (['2026_10_16_000001_add_registration_business_details', '2026_10_16_000002_add_logistics_registration_details'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        (require database_path('migrations/2026_10_31_130000_add_seller_registration_documents.php'))->up();
        Storage::fake('public');
        Storage::fake('sensitive_documents');
        DB::table('id_types')->insert(['id' => 1, 'name' => 'Passport']);
        DB::table('categories')->insert(['id' => 1, 'name' => 'Home']);
        $this->seller = User::create($this->data() + [
            'email' => 'seller.fixture@gmail.com', 'username' => 'seller_fixture',
            'password' => 'FixturePassword123!', 'account_type' => 'seller',
            'status' => 'rejected', 'status_reason' => 'Business permit is blurred.',
            'id_file' => 'id_files/original.png', 'selfie_file' => 'selfie_files/original.png',
            'business_permit_file' => 'business_permits/original.pdf',
        ]);
        $this->seller->forceFill(['can_resubmit'=>true])->save();
        Storage::disk('sensitive_documents')->put($this->seller->id_file, 'original ID');
        Storage::disk('sensitive_documents')->put($this->seller->selfie_file, 'original selfie');
        Storage::disk('public')->put($this->seller->business_permit_file, 'original permit');
    }

    private function data(): array
    {
        return ['given_names' => 'Maria', 'last_name' => 'Santos', 'middle_name' => null, 'no_middle_name' => 1,
            'sex' => 'female', 'birthday' => '1998-05-14', 'business_name' => 'Fixture Shop',
            'seller_type' => 'individual', 'tin' => '123456789000', 'contact_no' => '09171234567',
            'region' => 'CALABARZON', 'province' => 'Laguna', 'municipality' => 'Santa Cruz',
            'barangay' => 'Central', 'house_no' => '12', 'street' => 'Main Street',
            'postal_code' => '4009', 'category_id' => 1, 'category_other' => null, 'id_type_id' => 1];
    }

    private function grant(): array
    {
        return ['seller_application_correction' => ['id' => $this->seller->id, 'expires_at' => now()->addMinutes(30)->timestamp]];
    }

    public function test_rejected_seller_signs_in_to_prefilled_correction_without_dashboard_access(): void
    {
        $this->post(route('login.post'), ['email' => $this->seller->email, 'password' => 'FixturePassword123!'])
            ->assertRedirect(route('seller.application.edit'));
        $this->assertGuest();
        $this->get(route('seller.application.edit'))->assertOk()
            ->assertSee('Business permit is blurred.')->assertSee('Document on file')
            ->assertSee('Fixture Shop')->assertSee('value="individual" selected', false);
        $this->get('/seller/dashboard')->assertRedirect();
    }

    public function test_wrong_password_and_missing_or_expired_grant_cannot_access_application(): void
    {
        $this->post(route('login.post'), ['email' => $this->seller->email, 'password' => 'Incorrect123!'])->assertSessionHasErrors('email');
        $this->get(route('seller.application.edit'))->assertForbidden();
        $this->post(route('seller.application.update'), $this->data())->assertForbidden();
        $this->withSession(['seller_application_correction' => ['id' => $this->seller->id, 'expires_at' => now()->subMinute()->timestamp]])
            ->get(route('seller.application.edit'))->assertForbidden();
    }

    public function test_owner_reuses_tin_replaces_one_document_and_returns_same_application_to_pending(): void
    {
        $id = $this->seller->id;
        $this->withSession($this->grant())->post(route('seller.application.update'), array_replace($this->data(), [
            'last_name' => "O’Hara", 'tin' => '123-456-789-000',
            'id_file' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nupdated ID"),
            'status' => 'approved', 'is_admin' => true,
        ]))->assertRedirect(route('login'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(1, User::count());
        $current = $this->seller->fresh();
        $this->assertSame($id, $current->id);
        $this->assertSame('pending', $current->status);
        $this->assertFalse($current->can_resubmit);
        $this->assertSame('123456789000', $current->tin);
        $this->assertSame('O’Hara', $current->last_name);
        $this->assertNull($current->status_reason);
        $this->assertFalse($current->is_admin);
        $this->assertSame($this->seller->selfie_file, $current->selfie_file);
        $this->assertNotSame($this->seller->id_file, $current->id_file);
        Storage::disk('sensitive_documents')->assertExists($current->id_file);
        $this->assertDatabaseHas('hiring_application_history', ['user_id' => $id, 'status' => 'rejected', 'reason' => 'Business permit is blurred.']);
        $this->assertDatabaseHas('hiring_application_history', ['user_id' => $id, 'status' => 'pending']);
        $this->get(route('seller.application.edit'))->assertForbidden();
    }

    public function test_tin_of_another_rejected_applicant_cannot_be_taken(): void
    {
        User::create(['email' => 'another@gmail.com', 'username' => 'other_seller', 'account_type' => 'seller', 'status' => 'rejected', 'tin' => '999888777000']);
        $this->withSession($this->grant())->postJson(route('seller.application.update'), array_replace($this->data(), ['tin' => '999-888-777-000']))
            ->assertUnprocessable()->assertJsonValidationErrors('tin');
        $this->assertSame('rejected', $this->seller->fresh()->status);
    }

    public function test_non_rejected_application_cannot_use_a_stale_correction_grant(): void
    {
        $this->seller->update(['status' => 'approved']);
        $this->withSession($this->grant())->post(route('seller.application.update'), $this->data())->assertNotFound();
    }

    public function test_public_reregistration_accepts_owners_tin_with_verified_password(): void
    {
        $data = $this->data();
        unset($data['category_id']);
        $data += ['account_type' => 'seller', 'auth_method' => 'manual', 'email' => $this->seller->email,
            'username' => $this->seller->username, 'password' => 'FixturePassword123!',
            'password_confirmation' => 'FixturePassword123!', 'category_ids' => [1]];
        // Missing uploads stop creation while exercising the actual TIN validation.
        $this->postJson('/register', $data)->assertUnprocessable()->assertJsonMissingValidationErrors('tin');
        $data['password'] = $data['password_confirmation'] = 'WrongPassword123!';
        $this->postJson('/register', $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame('rejected', $this->seller->fresh()->status);
    }

    public function test_public_reregistration_reuses_the_existing_account_and_tin(): void
    {
        $image = fn () => UploadedFile::fake()->createWithContent('id.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        $data = $this->data();
        unset($data['category_id']);
        $this->withSession(['registration_verified_email'=>['email'=>$this->seller->email,'expires_at'=>now()->addHour()->timestamp]])->postJson('/register', $data + [
            'account_type' => 'seller', 'auth_method' => 'manual', 'email' => $this->seller->email,
            'username' => $this->seller->username, 'password' => 'FixturePassword123!',
            'password_confirmation' => 'FixturePassword123!', 'category_ids' => [1],
            'id_file' => $image(), 'selfie_file' => $image(),
        ])->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, User::count());
        $this->assertSame('pending', $this->seller->fresh()->status);
        $this->assertSame('123456789000', $this->seller->fresh()->tin);
    }

    public function test_correction_requires_certificates_for_the_chosen_seller_type(): void
    {
        $this->withSession($this->grant())->postJson(route('seller.application.update'), array_replace($this->data(), ['seller_type' => 'sole_proprietorship']))
            ->assertUnprocessable()->assertJsonValidationErrors(['dti_certificate_file', 'bir_certificate_file']);
        $pdf = fn () => UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.4\ncertificate");
        $this->withSession($this->grant())->post(route('seller.application.update'), array_replace($this->data(), [
            'seller_type' => 'corporation', 'sec_certificate_file' => $pdf(), 'bir_certificate_file' => $pdf(),
        ]))->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $current = $this->seller->fresh();
        Storage::disk('sensitive_documents')->assertExists($current->sec_certificate_file);
        Storage::disk('sensitive_documents')->assertExists($current->bir_certificate_file);
        Storage::disk('public')->assertMissing($current->sec_certificate_file);
        $this->assertNull($current->dti_certificate_file);
    }

    public function test_registration_requires_the_correct_certificates_for_each_type(): void
    {
        $data = $this->data(); unset($data['category_id']);
        $data += ['account_type' => 'seller', 'auth_method' => 'manual', 'email' => $this->seller->email,
            'username' => $this->seller->username, 'password' => 'FixturePassword123!',
            'password_confirmation' => 'FixturePassword123!', 'category_ids' => [1]];
        foreach (['individual' => [], 'sole_proprietorship' => ['dti_certificate_file', 'bir_certificate_file'], 'corporation' => ['sec_certificate_file', 'bir_certificate_file']] as $type => $required) {
            $response = $this->postJson('/register', array_replace($data, ['seller_type' => $type]))->assertUnprocessable();
            if ($required) $response->assertJsonValidationErrors($required);
            $response->assertJsonMissingValidationErrors(array_values(array_diff(array_keys(\App\Support\SellerDocuments::LABELS), $required)));
            $response->assertJsonMissingValidationErrors('business_permit_file');
        }
    }

    public function test_registration_saves_sole_proprietor_certificates_privately(): void
    {
        Storage::fake('profile_images');
        $pdf = fn () => UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.4\ncertificate");
        $image = fn () => UploadedFile::fake()->createWithContent('id.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        $data = array_replace($this->data(), ['seller_type' => 'sole_proprietorship']); unset($data['category_id']);
        $this->withSession(['registration_verified_email'=>['email'=>$this->seller->email,'expires_at'=>now()->addHour()->timestamp]])->postJson('/register', $data + [
            'account_type' => 'seller', 'auth_method' => 'manual', 'email' => $this->seller->email,
            'username' => $this->seller->username, 'password' => 'FixturePassword123!',
            'password_confirmation' => 'FixturePassword123!', 'category_ids' => [1],
            'id_file' => $image(), 'selfie_file' => $image(), 'shop_logo'=>$image(), 'dti_certificate_file' => $pdf(), 'bir_certificate_file' => $pdf(),
        ])->assertOk()->assertJson(['success' => true]);
        $seller = $this->seller->fresh();
        $this->assertStringStartsWith('shop_logos/',$seller->profile_picture);
        Storage::disk('profile_images')->assertExists($seller->profile_picture);
        Storage::disk('sensitive_documents')->assertExists($seller->dti_certificate_file);
        Storage::disk('sensitive_documents')->assertExists($seller->bir_certificate_file);
        Storage::disk('public')->assertMissing($seller->dti_certificate_file);
        $this->assertNull($seller->sec_certificate_file);
        $this->assertArrayNotHasKey('dti_certificate_file', $seller->toArray());
    }

    public function test_admin_cannot_approve_a_corporation_without_its_certificates(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->seller->update(['status' => 'pending', 'seller_type' => 'corporation']);
        $admin = User::create(['username' => 'admin_fixture', 'email' => 'admin@gmail.com', 'account_type' => 'buyer', 'is_admin' => true, 'status' => 'approved']);
        $this->actingAs($admin)->patchJson(route('admin.registrations.approve', $this->seller))->assertUnprocessable();
        $this->assertSame('pending', $this->seller->fresh()->status);
        Storage::disk('sensitive_documents')->put('seller_documents/sec.pdf', '%PDF-1.4 certificate');
        Storage::disk('sensitive_documents')->put('seller_documents/bir.pdf', '%PDF-1.4 certificate');
        $this->seller->update(['sec_certificate_file' => 'seller_documents/sec.pdf', 'bir_certificate_file' => 'seller_documents/bir.pdf']);
        $this->patch(route('admin.registrations.approve', $this->seller))->assertRedirect();
        $this->assertSame('approved', $this->seller->fresh()->status);
    }

    public function test_approved_seller_certificate_updates_wait_for_admin_review(): void
    {
        $this->seller->update(['status' => 'approved', 'seller_type' => 'sole_proprietorship']);
        $pdf = fn () => UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.4\ncertificate");
        $this->actingAs($this->seller)->post(route('seller.account.documents'), [
            'dti_certificate_file' => $pdf(), 'bir_certificate_file' => $pdf(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $request = \App\Models\AccountUpdateRequest::where('user_id', $this->seller->id)->firstOrFail();
        $this->assertNull($this->seller->fresh()->dti_certificate_file);
        Storage::disk('sensitive_documents')->assertExists($request->requested_documents['dti_certificate_file']);
        $admin = User::create(['username' => 'admin_fixture', 'email' => 'admin@gmail.com', 'account_type' => 'buyer', 'is_admin' => true, 'status' => 'approved']);
        $this->actingAs($admin)->patch(route('admin.update-requests.approve', $request))->assertRedirect();
        $this->assertSame($request->requested_documents['dti_certificate_file'], $this->seller->fresh()->dti_certificate_file);
        Storage::disk('sensitive_documents')->assertExists($this->seller->fresh()->dti_certificate_file);
    }
}
