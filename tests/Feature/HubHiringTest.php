<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\HubHiring;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class HubHiringTest extends TestCase
{
    use WorkflowSchema;

    private LogisticsHub $hub;

    private User $admin;

    private int $serial = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWorkflowSchema();
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('public');
        $this->hub = LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'is_operational' => true, 'is_hiring' => true]);
        $this->admin = $this->person('logistics_admin');
        DB::table('id_types')->insert(['id' => 1, 'name' => "Driver's License"]);
        DB::table('vehicle_types')->insert(['id' => 1, 'slug' => 'two_wheels', 'name' => 'Motorcycle']);
    }

    private function person(string $role, string $status = 'approved', ?LogisticsHub $hub = null): User
    {
        $hub ??= $this->hub;
        $n = ++$this->serial;

        return User::create(['username' => 'person_'.$n, 'email' => 'person_'.$n.'@gmail.com', 'given_names' => 'Fixture', 'last_name' => 'Person'.$n,
            'id_file' => 'fixture-id.png', 'selfie_file' => 'fixture-selfie.png', 'id_type_id' => 1, 'license_file' => 'fixture-license.png', 'license_number' => 'LICENSE-'.$n, 'license_expiry' => '2030-01-01', 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'vehicle_brand' => 'Test', 'vehicle_model' => 'Test', 'plate_number' => 'PLATE'.$n, 'or_file' => 'fixture-or.png', 'cr_file' => 'fixture-cr.png', 'birthday' => '1990-01-01', 'sex' => 'male', 'password' => 'FixturePassword123', 'account_type' => in_array($role, ['hub_staff', 'logistics_admin']) ? 'logistics' : $role,
            'logistics_role' => $role === 'hub_staff' ? 'hub_staff' : ($role === 'logistics_admin' ? 'admin' : null), 'is_admin' => $role === 'admin',
            'is_logistics' => in_array($role, ['hub_staff', 'logistics_admin']), 'business_name' => $hub->company_name, 'logistics_hub_id' => $hub->id, 'status' => $status]);
    }

    private function settings(string $role, array $values = [])
    {
        return $this->actingAs($this->admin)->patchJson('/logistics/hubs/'.$this->hub->id.'/toggle-hiring', array_merge([
            'role' => $role, 'is_hiring' => true, 'revision' => $this->hub->fresh()->hiring_revision, 'limit' => null, 'closes_at' => null,
        ], $values));
    }

    private function application(string $role): array
    {
        $image = fn () => UploadedFile::fake()->createWithContent('document.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1kAAAAASUVORK5CYII='));
        $data = ['account_type' => $role === 'rider' ? 'rider' : 'logistics', 'logistics_mode' => 'join', 'auth_method' => 'manual', 'username' => 'applicant_'.$role,
            'email' => $role.'@gmail.com', 'given_names' => 'New', 'last_name' => $role === 'rider' ? 'Rider' : 'Staff', 'birthday' => '2000-01-01', 'sex' => 'male',
            'password' => 'FixturePassword123', 'password_confirmation' => 'FixturePassword123', 'contact_no' => '09123456789', 'province' => 'Laguna',
            'municipality' => 'Santa Cruz', 'barangay' => 'Central', 'business_name' => 'Alpha', 'logistics_hub_id' => $this->hub->id, 'selfie_file' => $image()];

        return $role === 'rider' ? $data + ['vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'vehicle_brand' => 'Test', 'vehicle_model' => 'Test',
            'plate_number' => 'TEST001', 'license_number' => 'TEST-LICENSE', 'license_expiry' => '2030-01-01', 'license_file' => $image(), 'or_file' => $image(), 'cr_file' => $image()]
            : $data + ['id_type_id' => 1, 'id_file' => $image()];
    }

    public function test_staff_requires_interview_and_records_each_decision(): void
    {
        $this->postJson('/register', $this->application('hub_staff'))->assertOk();
        $staff = User::where('username', 'applicant_hub_staff')->firstOrFail();
        Mail::shouldReceive('raw')->twice()->withArgs(fn ($body, $callback) => DB::transactionLevel() === 0
            && in_array($staff->fresh()->status, ['interview', 'approved'], true)
            && DB::table('hiring_application_history')->where('user_id', $staff->id)->orderByDesc('id')->value('status') === $staff->fresh()->status);
        $this->actingAs($this->admin)->patchJson('/logistics/staff/'.$staff->id.'/confirm')->assertUnprocessable();
        $this->patchJson('/logistics/staff/'.$staff->id.'/activate')->assertUnprocessable();
        $this->patch('/logistics/staff/'.$staff->id.'/approve', ['interview_date' => now()->toDateString(), 'interview_time' => '12:00', 'interview_location' => 'Company hub'])->assertRedirect();
        $this->assertSame('interview', $staff->fresh()->status);
        $this->patch('/logistics/staff/'.$staff->id.'/confirm')->assertRedirect();
        $this->assertSame('approved', $staff->fresh()->status);
        $this->patchJson('/logistics/staff/'.$staff->id.'/confirm')->assertUnprocessable();
        $this->assertSame(['pending', 'interview', 'approved'], DB::table('hiring_application_history')->where('user_id', $staff->id)->orderBy('id')->pluck('status')->all());
    }

    public function test_platform_cannot_recruit_but_can_suspend_and_reactivate_personnel(): void
    {
        $platform = $this->person('admin');
        foreach (['rider', 'hub_staff'] as $role) {
            foreach (['pending', 'interview', 'rejected'] as $state) {
                $person = $this->person($role, $state);
                $this->actingAs($platform)->patchJson('/admin/registrations/'.$person->id.'/approve')->assertForbidden();
                $this->patchJson('/admin/users/'.$person->id.'/approve')->assertUnprocessable();
                $this->patchJson('/admin/users/'.$person->id.'/suspend', ['reason_preset' => 'Review'])->assertUnprocessable();
                $this->assertSame($state, $person->fresh()->status);
            }
            $person = $this->person($role);
            $this->patch('/admin/users/'.$person->id.'/suspend', ['reason_preset' => 'Review'])->assertRedirect();
            $this->assertSame('suspended', $person->fresh()->status);
            $this->patch('/admin/users/'.$person->id.'/approve')->assertRedirect();
            $this->assertSame('approved', $person->fresh()->status);
            $this->assertSame(['approved', 'suspended', 'approved'], DB::table('hiring_application_history')->where('user_id', $person->id)->orderBy('id')->pluck('status')->all());
        }
    }

    public function test_staff_early_rejection_requires_reason_and_preserves_reapplication(): void
    {
        $this->postJson('/register', $this->application('hub_staff'))->assertOk();
        $staff = User::where('username', 'applicant_hub_staff')->firstOrFail();
        $this->actingAs($this->admin)->patchJson('/logistics/staff/'.$staff->id.'/reject')->assertUnprocessable();
        $this->patch('/logistics/staff/'.$staff->id.'/reject', ['reason_preset' => 'Incomplete application'])->assertRedirect();
        $this->assertSame('rejected', $staff->fresh()->status);
        $this->patchJson('/logistics/staff/'.$staff->id.'/activate')->assertUnprocessable();
        auth()->logout();
        $this->postJson('/register', $this->application('hub_staff'))->assertOk();
        $this->assertSame('pending', $staff->fresh()->status);
        $this->assertDatabaseHas('hiring_application_history', ['user_id' => $staff->id, 'status' => 'rejected', 'reason' => 'Incomplete application']);
        $this->actingAs($this->admin)->patchJson('/logistics/staff/'.$staff->id.'/confirm')->assertUnprocessable();
    }

    public function test_generic_staff_creation_and_edits_cannot_establish_recruitment_states(): void
    {
        $data = ['role' => 'hub_staff', 'username' => 'managed_staff', 'email' => 'managed.staff@gmail.com', 'given_names' => 'Managed', 'last_name' => 'Staff',
            'sex' => 'female', 'birthday' => '2000-01-01', 'contact_no' => '09123456789', 'barangay' => 'Central', 'password' => 'FixturePassword123',
            'password_confirmation' => 'FixturePassword123', 'logistics_hub_id' => $this->hub->id];
        $this->actingAs($this->admin);
        foreach (['interview', 'approved', 'rejected', 'suspended'] as $status) {
            $this->postJson('/logistics/staff/accounts', $data + ['status' => $status])->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->post('/logistics/staff/accounts', $data)->assertRedirect();
        $staff = User::where('username', 'managed_staff')->firstOrFail();
        $this->assertSame('pending', $staff->status);
        foreach (['pending', 'interview', 'approved', 'rejected', 'suspended'] as $status) {
            $this->patchJson('/logistics/accounts/'.$staff->id, $data + ['status' => $status])->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->patch('/logistics/accounts/'.$staff->id, $data)->assertRedirect();
        $this->assertSame('pending', $staff->fresh()->status);
        $this->get('/logistics/accounts/'.$staff->id.'/edit')->assertOk()->assertDontSee('<select name="status"', false);
        $this->patchJson('/logistics/staff/'.$staff->id.'/confirm')->assertUnprocessable();
    }

    public function test_missing_approval_documents_and_stale_decisions_are_rejected(): void
    {
        $person = $this->person('rider', 'pending');
        $original = $person->fresh();
        $person->update(['license_file' => null]);
        $this->actingAs($this->admin);
        try {
            $original->update(['status' => 'approved']);
            $this->fail('Approval used stale document data.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('license_file', $e->errors());
        }
        $this->actingAs($this->admin)->patchJson('/logistics/riders/'.$person->id.'/approve')->assertUnprocessable()->assertJsonValidationErrors('license_file');
        $this->assertSame('pending', $person->fresh()->status);
        $person->update(['license_file' => 'license.png']);
        $stale = $person->fresh();
        $this->patch('/logistics/riders/'.$person->id.'/approve')->assertRedirect();
        try {
            $stale->update(['status' => 'rejected']);
            $this->fail('A stale decision was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame('approved', $person->fresh()->status);
        }
    }

    public function test_switches_are_independent_and_audited_without_changing_legacy_value(): void
    {
        $this->settings('rider', ['is_hiring' => false])->assertRedirect();
        $hub = $this->hub->fresh();
        $this->assertFalse($hub->rider_hiring_open);
        $this->assertTrue($hub->is_hiring);
        $this->assertTrue(app(HubHiring::class)->availability($hub, 'hub_staff')['eligible']);
        $changes = json_decode(DB::table('logistics_activity_logs')->value('hiring_changes'), true);
        $this->assertSame('rider', $changes['role']);
        $this->assertFalse($changes['after']['rider_hiring_open']);
        $this->postJson('/register', $this->application('rider'))->assertUnprocessable()->assertJsonValidationErrors('logistics_hub_id');
        $this->postJson('/register', $this->application('hub_staff'))->assertOk();
    }

    public function test_staff_switch_does_not_block_rider_or_change_other_settings(): void
    {
        $this->settings('hub_staff', ['is_hiring' => false, 'limit' => 0])->assertRedirect();
        $this->postJson('/register', $this->application('hub_staff'))->assertUnprocessable();
        $this->postJson('/register', $this->application('rider'))->assertOk();
        $this->assertNull($this->hub->fresh()->rider_hire_limit);
    }

    public function test_settings_validate_values_scope_roles_and_stale_versions(): void
    {
        foreach ([['is_hiring' => 'invalid'], ['limit' => -1], ['limit' => 'many'], ['limit' => 100001], ['closes_at' => 'not-a-date'], ['closes_at' => '2026-02-30T12:00'], ['role' => 'admin']] as $bad) {
            $this->settings('rider', $bad)->assertUnprocessable();
        }
        $this->settings('rider', ['revision' => 99])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $foreign = LogisticsHub::create(['company_name' => 'Beta', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        $this->actingAs($this->admin)->patchJson('/logistics/hubs/'.$foreign->id.'/toggle-hiring', ['role' => 'rider', 'is_hiring' => true, 'revision' => 0])->assertNotFound();
        foreach (['admin', 'buyer', 'seller', 'rider', 'hub_staff'] as $role) {
            $response = $this->actingAs($this->person($role))->patchJson('/logistics/hubs/'.$this->hub->id.'/toggle-hiring', ['role' => 'rider', 'is_hiring' => true, 'revision' => 0]);
            $this->assertContains($response->status(), [302, 403]);
        }
    }

    public function test_deadline_boundary_clear_and_closed_hub_are_server_rules(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 0));
        $this->settings('rider', ['closes_at' => '2026-10-14T12:01'])->assertRedirect();
        $this->assertSame('Scheduled to close', app(HubHiring::class)->availability($this->hub->fresh(), 'rider')['status']);
        $this->travel(1)->minutes();
        $this->postJson('/register', $this->application('rider'))->assertUnprocessable();
        $this->assertTrue(app(HubHiring::class)->availability($this->hub->fresh(), 'hub_staff')['eligible']);
        $this->settings('rider')->assertRedirect();
        $this->assertNull($this->hub->fresh()->rider_hiring_closes_at);
        $this->hub->update(['is_operational' => false]);
        foreach (['rider', 'hub_staff'] as $role) {
            $this->postJson('/register', $this->application($role))->assertUnprocessable();
        }
    }

    public function test_only_approved_assigned_role_consumes_slots_and_zero_blocks_intake(): void
    {
        foreach (['pending', 'interview', 'rejected', 'suspended'] as $status) {
            $this->person('rider', $status);
        }
        $this->person('hub_staff');
        $other = LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        $this->person('rider', 'approved', $other);
        $this->assertSame(0, app(HubHiring::class)->availability($this->hub, 'rider')['count']);
        $this->settings('rider', ['limit' => 0])->assertRedirect();
        $this->postJson('/register', $this->application('rider'))->assertUnprocessable();
        $this->settings('rider')->assertRedirect();
        $this->postJson('/register', $this->application('rider'))->assertOk();
    }

    public function test_full_limits_protect_company_and_platform_approval_and_reactivation(): void
    {
        foreach (['rider', 'hub_staff'] as $role) {
            $approved = $this->person($role);
            $applicationStatus = $role === 'hub_staff' ? 'interview' : 'pending';
            $pending = $this->person($role, $applicationStatus);
            if ($role === 'hub_staff') {
                $pending->update(['interview_scheduled_at' => now(), 'interview_location' => 'Company hub']);
            }
            $suspended = $this->person($role, 'suspended');
            $this->settings($role, ['limit' => 1])->assertRedirect();
            $path = $role === 'rider' ? 'riders/'.$pending->id.'/approve' : 'staff/'.$pending->id.'/confirm';
            $this->actingAs($this->admin)->patchJson('/logistics/'.$path)->assertUnprocessable();
            $path = $role === 'rider' ? 'riders' : 'staff';
            $this->patchJson('/logistics/'.$path.'/'.$suspended->id.'/activate')->assertUnprocessable();
            $this->actingAs($this->person('admin'))->patchJson('/admin/registrations/'.$pending->id.'/approve')->assertForbidden();
            $this->patchJson('/admin/users/'.$suspended->id.'/approve')->assertUnprocessable();
            $this->assertSame($applicationStatus, $pending->fresh()->status);
            $this->assertSame('suspended', $suspended->fresh()->status);
            $this->settings($role, ['limit' => 0])->assertRedirect();
            $this->assertSame('approved', $approved->fresh()->status);
            $this->assertTrue(app(HubHiring::class)->availability($this->hub->fresh(), $role)['over_capacity']);
        }
    }

    public function test_pending_reviews_can_approve_after_intake_closes_when_capacity_remains(): void
    {
        $rider = $this->person('rider', 'pending');
        $this->settings('rider', ['is_hiring' => false, 'limit' => 1, 'closes_at' => '2000-01-01T00:00'])->assertRedirect();
        $this->actingAs($this->admin)->patch('/logistics/riders/'.$rider->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $rider->fresh()->status);
    }

    public function test_model_boundary_protects_direct_approved_creation_and_hub_moves(): void
    {
        $rider = $this->person('rider');
        $other = LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        $other->rider_hire_limit = 0;
        $other->save();
        try {
            $rider->update(['logistics_hub_id' => $other->id]);
            $this->fail('Full hub movement accepted.');
        } catch (ValidationException $e) {
            $this->assertSame($this->hub->id, $rider->fresh()->logistics_hub_id);
        }
        $this->settings('rider', ['limit' => 1])->assertRedirect();
        try {
            $this->person('rider');
            $this->fail('Over-capacity creation accepted.');
        } catch (ValidationException $e) {
            $this->assertSame(1, app(HubHiring::class)->personnel($this->hub, 'rider')->count());
        }
    }

    public function test_reapplication_preserves_account_id_rejection_and_attempt_history(): void
    {
        $this->postJson('/register', $this->application('rider'))->assertOk();
        $rider = User::where('username', 'applicant_rider')->firstOrFail();
        $rider->update(['status' => 'rejected', 'status_reason' => 'Original rejection']);
        $oldEvent = DB::table('hiring_application_history')->where('user_id', $rider->id)->orderByDesc('id')->first();
        $this->postJson('/register', $this->application('rider'))->assertOk();
        $this->assertSame($rider->id, User::where('username', 'applicant_rider')->firstOrFail()->id);
        $this->assertDatabaseHas('hiring_application_history', ['id' => $oldEvent->id, 'status' => 'rejected', 'reason' => 'Original rejection']);
        $latest = DB::table('hiring_application_history')->where('user_id', $rider->id)->orderByDesc('id')->first();
        $this->assertNotSame($oldEvent->attempt_id, $latest->attempt_id);
        $this->postJson('/register', $this->application('rider'))->assertUnprocessable();
        $this->actingAs($this->admin)->get('/logistics/accounts/'.$rider->id.'/edit')->assertOk()->assertSee('Original rejection');
        $foreign = $this->person('logistics_admin', 'approved', LogisticsHub::create(['company_name' => 'Beta', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]));
        $this->actingAs($foreign)->get('/logistics/accounts/'.$rider->id.'/edit')->assertNotFound();
    }

    public function test_history_failure_rolls_back_account_and_uploaded_files(): void
    {
        DB::unprepared("CREATE TRIGGER reject_history BEFORE INSERT ON hiring_application_history BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->postJson('/register', $this->application('rider'))->assertStatus(500);
        $this->assertDatabaseMissing('users', ['username' => 'applicant_rider']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_management_creation_status_changes_and_moves_enforce_capacity(): void
    {
        $data = ['role' => 'rider', 'username' => 'managed_rider', 'email' => 'managed@gmail.com', 'given_names' => 'Managed', 'last_name' => 'Rider',
            'sex' => 'male', 'birthday' => '2000-01-01', 'contact_no' => '09123456789', 'barangay' => 'Central', 'password' => 'FixturePassword123',
            'password_confirmation' => 'FixturePassword123', 'status' => 'approved', 'logistics_hub_id' => $this->hub->id, 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own'];
        $this->settings('rider', ['limit' => 0])->assertRedirect();
        $this->postJson('/logistics/riders/accounts', $data)->assertUnprocessable();
        $data['status'] = 'pending';
        $this->settings('rider', ['limit' => 1])->assertRedirect();
        $this->post('/logistics/riders/accounts', $data)->assertRedirect();
        $user = User::where('username', 'managed_rider')->firstOrFail();
        $data['status'] = 'approved';
        $this->patchJson('/logistics/accounts/'.$user->id, $data)->assertUnprocessable();
        $editing = $data;
        unset($editing['status']);
        $editing['given_names'] = 'Updated';
        $editing['password'] = $editing['password_confirmation'] = 'ReplacementPassword123';
        $events = DB::table('hiring_application_history')->where('user_id', $user->id)->count();
        $this->patch('/logistics/accounts/'.$user->id, $editing)->assertRedirect();
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertTrue(Hash::check('ReplacementPassword123', $user->fresh()->password));
        $this->assertSame($events, DB::table('hiring_application_history')->where('user_id', $user->id)->count());
        $this->settings('rider', ['limit' => 1])->assertRedirect();
        $this->patchJson('/logistics/accounts/'.$user->id, $data)->assertUnprocessable();
        $user->update(['id_file' => 'id.png', 'id_type_id' => 1, 'selfie_file' => 'selfie.png', 'license_file' => 'license.png', 'license_number' => 'managed-license', 'license_expiry' => '2030-01-01', 'vehicle_brand' => 'Test', 'vehicle_model' => 'Test', 'plate_number' => 'MANAGED', 'or_file' => 'or.png', 'cr_file' => 'cr.png']);
        $this->patch('/logistics/riders/'.$user->id.'/approve')->assertRedirect();
        unset($data['status']);
        $other = LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        $other->rider_hire_limit = 0;
        $other->save();
        $data['logistics_hub_id'] = $other->id;
        $this->patchJson('/logistics/accounts/'.$user->id, $data)->assertUnprocessable();
        $this->assertSame($this->hub->id, $user->fresh()->logistics_hub_id);
    }

    public function test_staff_fallback_ownership_and_vehicle_rules_remain_enforced(): void
    {
        $data = $this->application('hub_staff');
        unset($data['logistics_hub_id']);
        $this->postJson('/register', $data)->assertOk();
        $this->assertDatabaseHas('users', ['username' => 'applicant_hub_staff', 'logistics_hub_id' => $this->hub->id]);
        $this->hub->update(['is_operational' => false]);
        $this->postJson('/register', $data)->assertUnprocessable();
        $this->hub->update(['is_operational' => true]);
        $foreign = LogisticsHub::create(['company_name' => 'Beta', 'province' => 'Laguna', 'municipality' => 'Pila', 'is_operational' => true]);
        $rider = $this->application('rider');
        $rider['logistics_hub_id'] = $foreign->id;
        $this->postJson('/register', $rider)->assertUnprocessable()->assertJsonValidationErrors('logistics_hub_id');
        $rider['logistics_hub_id'] = $this->hub->id;
        $rider['vehicle_ownership'] = 'company';
        $this->postJson('/register', $rider)->assertUnprocessable()->assertJsonValidationErrors('vehicle_type');
    }

    public function test_final_intake_check_rejects_a_hub_closed_after_preliminary_validation(): void
    {
        $flow = app(HubHiring::class);
        $flow->assertIntake($this->hub, 'rider');
        $this->hub->rider_hiring_open = false;
        $this->hub->save();
        $data = $this->application('rider');
        unset($data['selfie_file'], $data['license_file'], $data['or_file'], $data['cr_file']);
        try {
            $flow->register($data, 'rider');
            $this->fail('Stale intake validation was accepted.');
        } catch (ValidationException $e) {
            $this->assertDatabaseMissing('users', ['username' => 'applicant_rider']);
        }
    }

    public function test_shared_registration_preserves_rejected_buyer_records_without_hiring_restrictions(): void
    {
        $data = $this->application('hub_staff');
        $data['account_type'] = 'buyer';
        $this->settings('hub_staff', ['is_hiring' => false, 'limit' => 0])->assertRedirect();
        $this->postJson('/register', $data)->assertOk();
        $buyer = User::where('username', $data['username'])->firstOrFail();
        $buyer->update(['status' => 'rejected', 'status_reason' => 'Original buyer rejection']);
        $this->postJson('/register', $data)->assertOk();
        $this->assertSame($buyer->id, User::where('username', $data['username'])->value('id'));
        $this->assertDatabaseHas('hiring_application_history', ['user_id' => $buyer->id, 'status' => 'rejected', 'reason' => 'Original buyer rejection']);
    }

    public function test_hub_pagination_retains_search_and_company_isolation(): void
    {
        for ($i = 0; $i < 22; $i++) {
            LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => 'Town'.sprintf('%02d', $i), 'is_hiring' => true, 'is_operational' => true]);
        }
        LogisticsHub::create(['company_name' => 'Beta', 'province' => 'Laguna', 'municipality' => 'Town99', 'is_hiring' => true, 'is_operational' => true]);
        $this->actingAs($this->admin)->get('/logistics/hubs?search=Town')->assertOk()->assertSee('Town00')->assertDontSee('Town20')->assertDontSee('Town99');
        $this->get('/logistics/hubs?search=Town&page=2')->assertOk()->assertSee('Town20')->assertDontSee('Town00')->assertDontSee('Town99');
    }
}
