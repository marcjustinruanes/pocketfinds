<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\LogisticsIssue;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use App\Services\HubTransferWorkflow;
use App\Services\LogisticsAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LogisticsAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            foreach (['username', 'email', 'given_names', 'last_name', 'sex', 'birthday', 'contact_no', 'barangay', 'province', 'municipality', 'password', 'business_name', 'account_type', 'auth_method', 'logistics_role', 'vehicle_type', 'vehicle_ownership', 'status', 'id_file', 'id_type_id', 'selfie_file', 'license_file', 'license_number', 'license_expiry', 'vehicle_brand', 'vehicle_model', 'plate_number', 'or_file', 'cr_file'] as $column) {
                $t->string($column)->nullable();
            }
            $t->integer('age')->nullable();
            $t->bigInteger('logistics_hub_id')->nullable();
            $t->boolean('is_admin')->default(false);
            $t->boolean('is_logistics')->default(false);
            $t->timestamps();
        });
        Schema::create('id_types', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        DB::table('id_types')->insert(['id' => 1, 'name' => 'License']);
        Schema::create('vehicle_types', fn (Blueprint $t) => [$t->id(), $t->string('slug')]);
        DB::table('vehicle_types')->insert(['id' => 1, 'slug' => 'two_wheels']);
        Schema::create('logistics_hubs', function (Blueprint $t) {
            $t->id();
            $t->string('company_name');
            $t->string('municipality');
            $t->string('province');
            $t->boolean('is_regional_hub')->default(false);
            $t->boolean('is_hiring')->default(false);
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('order_id')->nullable();
            foreach (['logistics_company', 'origin_hub', 'origin_province', 'destination_hub', 'destination_province', 'tracking_number', 'shipping_status', 'delivery_failed_reason'] as $column) {
                $t->string($column)->nullable();
            }
            foreach (['courier_id', 'pickup_rider_id', 'hub_transfer_rider_id'] as $column) {
                $t->bigInteger($column)->nullable();
            }
            foreach (['pickup_approved_at', 'assigned_at', 'picked_up_at', 'out_for_delivery_at', 'delivered_at', 'delivery_failed_at', 'returned_at'] as $column) {
                $t->timestamp($column)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('shipment_hub_legs', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('shipment_id');
            $t->integer('sequence');
            $t->string('leg_type');
            $t->string('from_hub');
            $t->string('to_hub');
            foreach (['requested_at', 'approved_at', 'started_at', 'completed_at', 'rider_confirmed_pickup_at'] as $column) {
                $t->timestamp($column)->nullable();
            }
            foreach (['requested_by', 'approved_by', 'rider_id'] as $column) {
                $t->integer($column)->nullable();
            }
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->integer('buyer_id')->nullable();
            $t->integer('seller_id')->nullable();
            $t->string('order_number')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });
        Schema::create('delivery_assignments', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('shipment_id');
            $t->integer('courier_id');
            $t->string('status');
            $t->string('leg');
            foreach (['requested_at', 'accepted_at', 'picked_up_at', 'delivered_at'] as $column) {
                $t->timestamp($column)->nullable();
            }
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->integer('user_id');
            $t->string('title');
            $t->text('message');
            $t->string('notification_type');
            $t->string('reference_id')->nullable();
            $t->boolean('is_read');
            $t->timestamp('created_at');
        });
        Schema::create('company_vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('company_name');
            $t->integer('logistics_hub_id');
            $t->string('vehicle_type');
            $t->string('platform_status');
            $t->boolean('is_available');
            $t->text('status_reason')->nullable();
            $t->timestamps();
        });
        $migration = require database_path('migrations/2026_10_13_000001_add_logistics_operations.php');
        $migration->up();
        (require database_path('migrations/2026_10_14_000002_add_role_hiring_management.php'))->up();
        (require database_path('migrations/2026_10_14_000001_add_hub_transfer_batches.php'))->up();
    }

    private function hub(string $company = 'Alpha', string $town = 'Santa Cruz', string $province = 'Laguna'): LogisticsHub
    {
        return LogisticsHub::create(['company_name' => $company, 'municipality' => $town, 'province' => $province, 'is_operational' => true, 'is_hiring' => true]);
    }

    private function user(LogisticsHub $hub, string $role = 'hub_staff', string $status = 'approved'): User
    {
        $name = 'user_'.bin2hex(random_bytes(4));

        return User::create(['username' => $name, 'email' => $name.'@example.test', 'given_names' => 'Test', 'last_name' => 'Account', 'status' => $status,
            'business_name' => $hub->company_name, 'logistics_hub_id' => $hub->id, 'is_admin' => $role === 'admin', 'is_logistics' => in_array($role, ['hub_staff', 'logistics_admin']),
            'account_type' => in_array($role, ['hub_staff', 'logistics_admin']) ? 'logistics' : $role, 'logistics_role' => $role === 'hub_staff' ? 'hub_staff' : ($role === 'logistics_admin' ? 'admin' : null),
            'id_file' => 'id.png', 'id_type_id' => 1, 'selfie_file' => 'selfie.png', 'license_file' => 'license.png', 'license_number' => $name, 'license_expiry' => '2030-01-01', 'vehicle_brand' => 'Test', 'vehicle_model' => 'Test', 'plate_number' => $name, 'or_file' => 'or.png', 'cr_file' => 'cr.png', 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'password' => 'TestPassword!123']);
    }

    private function parcel(LogisticsHub $from, LogisticsHub $to, string $id = 'parcel', string $status = 'sorted'): Shipment
    {
        return Shipment::create(['id' => $id, 'logistics_company' => $from->company_name, 'origin_hub' => $from->municipality, 'origin_province' => $from->province,
            'destination_hub' => $to->municipality, 'destination_province' => $to->province, 'tracking_number' => 'TRACK-'.$id, 'shipping_status' => $status]);
    }

    public function test_hub_staff_visibility_distinguishes_same_named_municipalities_and_companies(): void
    {
        $a = $this->hub();
        $sameTown = $this->hub('Alpha', 'Santa Cruz', 'Marinduque');
        $other = $this->hub('Beta');
        $this->parcel($a, $a, 'own');
        $this->parcel($sameTown, $sameTown, 'other-province');
        $this->parcel($other, $other, 'other-company');
        $access = app(LogisticsAccess::class);
        $this->assertSame(['own'], $access->shipments($this->user($a))->pluck('id')->all());
        $this->assertEqualsCanonicalizing(['own', 'other-province'], $access->shipments($this->user($a, 'logistics_admin'))->pluck('id')->all());
    }

    public function test_suspended_staff_and_non_logistics_roles_cannot_use_logistics_routes(): void
    {
        $hub = $this->hub();
        $this->actingAs($this->user($hub, 'hub_staff', 'suspended'))->get('/logistics/scan')->assertForbidden();
        foreach (['buyer', 'seller', 'rider', 'admin'] as $role) {
            $this->actingAs($this->user($hub, $role))->get('/logistics/accounts')->assertRedirect('/login');
        }
    }

    public function test_hub_staff_cannot_open_company_management_or_scan_an_unrelated_parcel(): void
    {
        $a = $this->hub();
        $b = $this->hub('Alpha', 'Pila');
        $this->parcel($b, $b);
        $this->actingAs($this->user($a))->get('/logistics/accounts')->assertForbidden();
        $this->get('/logistics/activity')->assertForbidden();
        $this->postJson('/logistics/scan/lookup', ['code' => 'TRACK-parcel'])->assertNotFound();
        $this->patchJson('/logistics/scan/parcel/receive')->assertNotFound();
    }

    public function test_staff_cannot_receive_a_parcel_at_the_destination_before_origin_handoff(): void
    {
        $a = $this->hub();
        $b = $this->hub('Alpha', 'Pila');
        $this->parcel($a, $b, 'parcel', 'picked_up');
        $this->actingAs($this->user($b))->patchJson('/logistics/scan/parcel/receive')->assertForbidden();
        $this->assertDatabaseHas('shipments', ['id' => 'parcel', 'shipping_status' => 'picked_up']);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
    }

    public function test_delivery_assignment_rejects_other_hubs_and_inactive_riders_then_audits_valid_assignment(): void
    {
        $a = $this->hub();
        $b = $this->hub('Alpha', 'Pila');
        $admin = $this->user($a, 'logistics_admin');
        $this->parcel($a, $a);
        $wrongHub = $this->user($b, 'rider');
        $inactive = $this->user($a, 'rider', 'suspended');
        $rider = $this->user($a, 'rider');
        $this->actingAs($admin)->patchJson('/logistics/assignments/parcel/assign', ['courier_id' => $wrongHub->id])->assertUnprocessable();
        $this->patchJson('/logistics/assignments/parcel/assign', ['courier_id' => $inactive->id])->assertNotFound();
        $this->patch('/logistics/assignments/parcel/assign', ['courier_id' => $rider->id])->assertRedirect();
        $this->assertDatabaseHas('shipments', ['id' => 'parcel', 'courier_id' => $rider->id, 'shipping_status' => 'assigned_to_rider']);
        $this->assertDatabaseHas('logistics_activity_logs', ['actor_id' => $admin->id, 'action' => 'logistics.assignments.assign', 'record_id' => 'parcel']);
        $this->patchJson('/logistics/assignments/parcel/assign', ['courier_id' => $rider->id])->assertUnprocessable();
        $this->assertDatabaseCount('delivery_assignments', 1);
    }

    public function test_rider_self_claim_cannot_take_another_company_or_hubs_parcel(): void
    {
        $a = $this->hub();
        $other = $this->hub('Beta');
        $rider = $this->user($a, 'rider');
        $this->parcel($other, $other, 'foreign');
        $this->actingAs($rider)->patch('/rider/requests/foreign/accept')->assertRedirect();
        $this->assertDatabaseHas('shipments', ['id' => 'foreign', 'courier_id' => null]);
        $this->assertDatabaseCount('delivery_assignments', 0);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
    }

    public function test_company_admin_can_create_staff_without_accepting_injected_platform_privileges(): void
    {
        $hub = $this->hub();
        $admin = $this->user($hub, 'logistics_admin');
        $data = ['role' => 'hub_staff', 'username' => 'new_staff', 'email' => 'new_staff@example.test', 'given_names' => 'New', 'last_name' => 'Staff',
            'sex' => 'female', 'birthday' => '2000-01-01', 'contact_no' => '09123456789', 'barangay' => 'Test', 'password' => 'NewStaffPassword!123',
            'password_confirmation' => 'NewStaffPassword!123', 'status' => 'pending', 'logistics_hub_id' => $hub->id, 'is_admin' => true, 'business_name' => 'Beta'];
        $this->actingAs($admin)->post('/logistics/accounts', $data)->assertRedirect();
        $staff = User::where('username', 'new_staff')->firstOrFail();
        $this->assertFalse($staff->is_admin);
        $this->assertTrue($staff->isHubStaff());
        $this->assertSame('Alpha', $staff->business_name);
        $this->assertTrue(Hash::check('NewStaffPassword!123', $staff->password));
        $this->assertStringNotContainsString('NewStaffPassword', json_encode(DB::table('logistics_activity_logs')->get()));
        $this->assertDatabaseHas('logistics_activity_logs', ['action' => 'logistics.accounts.store', 'record_id' => (string) $staff->id]);
        $outsider = $this->user($this->hub('Beta'));
        $this->patchJson('/logistics/accounts/'.$outsider->id, $data)->assertNotFound();
    }

    public function test_role_specific_account_creation_forms_and_endpoints_keep_riders_and_staff_separate(): void
    {
        (require database_path('migrations/2026_10_16_000001_add_account_verification.php'))->up();
        Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('public');
        $hub = $this->hub();
        $admin = $this->user($hub, 'logistics_admin');
        $this->actingAs($admin)->get('/logistics/accounts?role=rider')->assertOk()
            ->assertSee('/logistics/riders/create', false)->assertDontSee('name="password"', false);
        $this->get('/logistics/riders/create')->assertOk()->assertSee('name="license_file"', false)->assertSee('name="vehicle_type"', false);
        $this->get('/logistics/accounts?role=hub_staff')->assertOk()
            ->assertSee('name="role" value="hub_staff"', false)->assertSee('/logistics/staff/accounts', false)
            ->assertDontSee('name="vehicle_type"', false);
        $data = ['role' => 'hub_staff', 'username' => 'specific_staff', 'email' => 'specific_staff@example.test',
            'given_names' => 'Specific', 'last_name' => 'Staff', 'sex' => 'female', 'birthday' => '2000-01-01',
            'contact_no' => '09123456789', 'barangay' => 'Test', 'password' => 'SpecificPassword!123',
            'password_confirmation' => 'SpecificPassword!123', 'status' => 'pending', 'logistics_hub_id' => $hub->id];
        $this->postJson('/logistics/riders/accounts', $data)->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'specific_staff']);
        $this->post('/logistics/staff/accounts', $data)->assertRedirect();
        $this->assertTrue(User::where('username', 'specific_staff')->firstOrFail()->isHubStaff());
        $data = array_merge($data, ['role' => 'rider', 'username' => 'specific_rider', 'email' => 'specific_rider@example.test',
            'vehicle_type' => 'four_wheels', 'vehicle_ownership' => 'own']);
        $data += ['license_number' => 'N01-23-456789', 'license_expiry' => '2030-01-01', 'plate_number' => 'SPECIFIC',
            'license_file' => \Illuminate\Http\UploadedFile::fake()->image('license.jpg'), 'or_cr_file' => \Illuminate\Http\UploadedFile::fake()->image('or-cr.jpg')];
        unset($data['password'], $data['password_confirmation'], $data['status']);
        $data['contact_no'] = '09123456788';
        $this->postJson('/logistics/staff/accounts', $data)->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/logistics/riders/accounts', array_diff_key($data, ['vehicle_type' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('vehicle_type');
        $this->post('/logistics/riders/accounts', $data)->assertRedirect();
        $rider = User::where('username', 'specific_rider')->firstOrFail();
        $this->assertSame('rider', $rider->account_type);
        $this->assertFalse($rider->is_logistics);
        $this->assertSame('four_wheels', $rider->vehicle_type);
        $this->get('/logistics/riders?status=pending_activation')->assertSee('specific_rider@example.test')->assertDontSee('specific_staff@example.test');
        $this->get('/logistics/staff?status=pending')->assertSee('specific_staff@example.test')->assertDontSee('specific_rider@example.test');
        $this->actingAs($this->user($hub))->postJson('/logistics/riders/accounts', $data)->assertForbidden();
        $this->postJson('/logistics/staff/accounts', $data)->assertForbidden();
    }

    public function test_issue_records_and_ownership_are_scoped_and_keep_resolution_history(): void
    {
        $a = $this->hub();
        $b = $this->hub('Alpha', 'Pila');
        $this->parcel($a, $a);
        $staff = $this->user($a);
        $other = $this->user($b);
        $this->actingAs($staff)->post('/logistics/issues', ['tracking_number' => 'TRACK-parcel', 'kind' => 'damaged', 'description' => 'Box torn'])->assertRedirect();
        $id = DB::table('logistics_issues')->value('id');
        $this->actingAs($other)->patchJson('/logistics/issues/'.$id, ['status' => 'resolved', 'resolution' => 'Checked'])->assertNotFound();
        $this->actingAs($staff)->patchJson('/logistics/issues/'.$id, ['status' => 'resolved'])->assertUnprocessable();
        $this->patch('/logistics/issues/'.$id, ['status' => 'resolved', 'resolution' => 'Repacked', 'assigned_to' => $staff->id])->assertRedirect();
        $this->assertDatabaseHas('logistics_issues', ['id' => $id, 'status' => 'resolved', 'resolution' => 'Repacked']);
        $this->assertDatabaseCount('logistics_issue_events', 2);
    }

    public function test_invalid_redelivery_transition_does_not_write_an_assignment(): void
    {
        $hub = $this->hub();
        $admin = $this->user($hub, 'logistics_admin');
        $rider = $this->user($hub, 'rider');
        $parcel = $this->parcel($hub, $hub);
        $parcel->update(['courier_id' => $rider->id]);
        $this->actingAs($admin)->patch('/logistics/status/parcel', ['status' => 'assigned_to_rider'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('delivery_assignments', 0);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
        $this->assertDatabaseHas('shipments', ['id' => 'parcel', 'shipping_status' => 'sorted']);
    }

    public function test_rendered_error_responses_roll_back_partial_changes(): void
    {
        $hub = $this->hub();
        $admin = $this->user($hub, 'logistics_admin');
        Route::middleware(['web', 'logistics', 'logistics.activity'])->patch('/test-logistics-error', function () use ($admin) {
            User::where('id', $admin->id)->update(['given_names' => 'Must roll back']);

            return response('Operation failed', 422);
        })->name('logistics.test-error');
        $this->actingAs($admin)->patch('/test-logistics-error')->assertUnprocessable();
        $this->assertSame('Test', $admin->fresh()->given_names);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
    }

    public function test_unavailable_company_vehicle_prevents_new_assignments(): void
    {
        $hub = $this->hub();
        $admin = $this->user($hub, 'logistics_admin');
        $rider = $this->user($hub, 'rider');
        $rider->update(['vehicle_ownership' => 'company']);
        $this->parcel($hub, $hub);
        $this->actingAs($admin)->patchJson('/logistics/assignments/parcel/assign', ['courier_id' => $rider->id])->assertUnprocessable();
        $this->assertDatabaseCount('delivery_assignments', 0);
        $this->assertCount(0, app(LogisticsAccess::class)->openParcelsForRider($rider, 'delivery')->get());
    }

    public function test_report_export_filters_company_and_escapes_spreadsheet_formulas(): void
    {
        $hub = $this->hub();
        $other = $this->hub('Beta');
        $admin = $this->user($hub, 'logistics_admin');
        $parcel = $this->parcel($hub, $hub, 'own', 'completed');
        $parcel->update(['tracking_number' => '=TEST()']);
        $this->parcel($other, $other, 'foreign', 'completed');
        $response = $this->actingAs($admin)->get('/logistics/reports/export?status=completed');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=TEST()", $csv);
        $this->assertStringNotContainsString('TRACK-foreign', $csv);
        $this->get('/logistics/reports/export?hub_id='.$other->id)->assertSessionHasErrors('hub_id');
    }

    public function test_transfer_requests_distinguish_provinces_and_log_each_affected_leg(): void
    {
        $a = $this->hub();
        $b = $this->hub('Alpha', 'Pila');
        $other = $this->hub('Alpha', 'Santa Cruz', 'Marinduque');
        $otherTo = $this->hub('Alpha', 'Pila', 'Marinduque');
        $own = $this->parcel($a, $b, 'own', 'at_sorting_center');
        $foreign = $this->parcel($other, $otherTo, 'foreign', 'at_sorting_center');
        foreach (['leg-own' => $own, 'leg-foreign' => $foreign] as $id => $shipment) {
            ShipmentHubLeg::create(['id' => $id, 'shipment_id' => $shipment->id, 'sequence' => 1, 'leg_type' => 'direct', 'from_hub' => $shipment->origin_hub, 'to_hub' => $shipment->destination_hub, 'status' => 'pending']);
        }
        $staff = $this->user($a);
        $this->user($a, 'logistics_admin');
        $this->actingAs($staff)->post('/logistics/hub/request-transfer', ['to_hub' => 'Pila', 'to_province' => 'Laguna'])->assertRedirect();
        $this->assertNotNull(DB::table('shipment_hub_legs')->where('id', 'leg-own')->value('requested_at'));
        $this->assertNull(DB::table('shipment_hub_legs')->where('id', 'leg-foreign')->value('requested_at'));
        $this->assertDatabaseHas('logistics_activity_logs', ['actor_id' => $staff->id, 'record_id' => 'leg-own', 'action' => 'logistics.hub.request-transfer']);
        $this->assertDatabaseCount('logistics_activity_logs', 1);
    }

    public function test_admin_issue_assignment_remains_visible_to_the_responsible_hub_staff(): void
    {
        $hub = $this->hub();
        $otherHub = $this->hub('Alpha', 'Pila');
        $staff = $this->user($hub);
        $other = $this->user($otherHub);
        $admin = $this->user($hub, 'logistics_admin');
        $this->parcel($hub, $hub);
        $this->actingAs($admin)->post('/logistics/issues', ['tracking_number' => 'TRACK-parcel', 'hub_id' => $hub->id, 'kind' => 'damaged', 'description' => 'Recorded torn parcel'])->assertRedirect();
        $id = DB::table('logistics_issues')->value('id');
        $this->patch('/logistics/issues/'.$id, ['status' => 'in_progress', 'assigned_to' => $staff->id])->assertRedirect();
        $this->actingAs($staff)->get('/logistics/issues')->assertOk()->assertSee('Recorded torn parcel');
        $this->actingAs($other)->get('/logistics/issues')->assertOk()->assertDontSee('Recorded torn parcel');
    }

    public function test_every_logistics_route_rejects_platform_admin_buyer_seller_and_rider(): void
    {
        $hub = $this->hub();
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'logistics.'));
        $this->assertGreaterThan(50, $routes->count());
        foreach (['admin', 'buyer', 'seller', 'rider'] as $role) {
            $this->actingAs($this->user($hub, $role));
            foreach ($routes as $route) {
                $uri = '/'.preg_replace('/\{[^}]+\}/', '999999', str_replace('{token}', str_repeat('a', 64), $route->uri()));
                $this->call($route->methods()[0], $uri)->assertRedirect('/login');
            }
        }
    }

    public function test_every_company_admin_route_rejects_hub_staff(): void
    {
        $this->actingAs($this->user($this->hub()));
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->getName() ?? '', 'logistics.') || ! in_array('logistics.admin', $route->gatherMiddleware(), true)) {
                continue;
            }
            $this->call($route->methods()[0], '/'.preg_replace('/\{[^}]+\}/', '999999', str_replace('{token}', str_repeat('a', 64), $route->uri())))->assertForbidden();
        }
    }

    public function test_unknown_logistics_subrole_cannot_become_company_admin(): void
    {
        $user = $this->user($this->hub(), 'logistics_admin');
        $user->update(['logistics_role' => 'unexpected']);
        $this->assertFalse($user->isLogisticsAdmin());
        $this->actingAs($user)->get('/logistics/accounts')->assertForbidden();
    }

    public function test_invalid_closed_and_cross_company_hub_assignments_are_denied(): void
    {
        $hub = $this->hub();
        foreach ([null, 999999, $this->hub('Beta')->id] as $id) {
            $staff = $this->user($hub);
            DB::table('users')->where('id', $staff->id)->update(['logistics_hub_id' => $id]);
            $staff->refresh();
            $this->actingAs($staff)->get('/logistics/scan')->assertForbidden();
        }
        $closedStaff = $this->user($hub);
        $hub->update(['is_operational' => false]);
        $this->actingAs($closedStaff)->get('/logistics/scan')->assertForbidden();
    }

    public function test_message_reply_cannot_reference_another_conversation(): void
    {
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->integer('sender_id');
            $t->integer('receiver_id');
            $t->text('body')->nullable();
            $t->boolean('read')->default(false);
            $t->integer('reply_to_id')->nullable();
            $t->string('product_id')->nullable();
            $t->timestamps();
        });
        $hub = $this->hub();
        $staff = $this->user($hub);
        $rider = $this->user($hub, 'rider');
        $other = $this->user($this->hub('Beta'), 'rider');
        $id = DB::table('messages')->insertGetId(['sender_id' => $other->id, 'receiver_id' => $rider->id, 'body' => 'Private unrelated message', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($staff)->postJson('/logistics/messages/send', ['receiver_id' => $rider->id, 'body' => 'Reply', 'reply_to_id' => $id])->assertUnprocessable();
        $this->assertDatabaseCount('messages', 1);
        DB::table('messages')->insert(['sender_id' => $staff->id, 'receiver_id' => $rider->id, 'body' => 'Old malformed reply', 'reply_to_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/logistics/messages/poll?receiver_id='.$rider->id)->assertOk()->assertJsonPath('messages.0.reply_to', null)->assertDontSee('Private unrelated message');
    }

    public function test_public_rider_registration_rejects_a_closed_but_hiring_hub(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('company_logo')->nullable();
        });
        $hub = $this->hub();
        $hub->update(['is_hiring' => true, 'is_operational' => false]);
        $this->user($hub, 'logistics_admin');
        $response = $this->postJson('/register', ['account_type' => 'rider', 'auth_method' => 'manual', 'business_name' => 'Alpha', 'logistics_hub_id' => $hub->id,
            'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own', 'license_number' => 'AUDIT-LICENSE']);
        $response->assertForbidden();
    }

    public function test_rider_invitation_activation_suspension_and_hub_move(): void
    {
        (require database_path('migrations/2026_10_16_000001_add_account_verification.php'))->up();
        \Illuminate\Support\Facades\Storage::fake('public');
        Mail::fake();
        $hub = $this->hub();
        $next = $this->hub('Alpha', 'Pila');
        $admin = $this->user($hub, 'logistics_admin');
        $data = ['role' => 'rider', 'username' => 'audit_rider', 'email' => 'audit_rider@example.test', 'given_names' => 'Audit', 'last_name' => 'Rider', 'sex' => 'female',
            'birthday' => '2000-01-01', 'contact_no' => '09123456789', 'barangay' => 'Test', 'password' => 'AuditPassword!123', 'password_confirmation' => 'AuditPassword!123',
            'status' => 'pending', 'logistics_hub_id' => $hub->id, 'vehicle_type' => 'two_wheels', 'vehicle_ownership' => 'own'];
        $data += ['license_number' => 'N01-23-456789', 'license_expiry' => '2030-01-01', 'plate_number' => 'AUDIT',
            'license_file' => \Illuminate\Http\UploadedFile::fake()->image('license.jpg'), 'or_cr_file' => \Illuminate\Http\UploadedFile::fake()->image('or-cr.jpg')];
        unset($data['password'], $data['password_confirmation'], $data['status']);
        $this->actingAs($admin)->post('/logistics/accounts', $data)->assertRedirect();
        $mail = Mail::sent(\App\Mail\RiderActivation::class)->last();
        $this->postJson(route('rider.activation.submit', ['user' => $mail->rider->id, 'token' => $mail->token]), ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->assertOk();
        $rider = User::where('username', 'audit_rider')->firstOrFail();
        $rider->update(['id_file' => 'id.png', 'id_type_id' => 1, 'selfie_file' => 'selfie.png', 'license_file' => 'license.png', 'license_number' => 'audit-license', 'license_expiry' => '2030-01-01', 'vehicle_brand' => 'Test', 'vehicle_model' => 'Test', 'plate_number' => 'AUDIT', 'or_file' => 'or.png', 'cr_file' => 'cr.png']);
        foreach (['suspend' => 'suspended', 'activate' => 'approved'] as $action => $status) {
            $this->patch('/logistics/riders/'.$rider->id.'/'.$action)->assertRedirect();
            $this->assertSame($status, $rider->fresh()->status);
        }
        $data['logistics_hub_id'] = $next->id;
        unset($data['status'], $data['password'],$data['password_confirmation']);
        $this->patch('/logistics/accounts/'.$rider->id, $data)->assertRedirect();
        $this->assertSame($next->id, $rider->fresh()->logistics_hub_id);
        $parcel = $this->parcel($next, $next);
        $parcel->update(['courier_id' => $rider->id, 'shipping_status' => 'assigned_to_rider']);
        $data['logistics_hub_id'] = $hub->id;
        $this->patchJson('/logistics/accounts/'.$rider->id, $data)->assertUnprocessable();
        $this->assertSame($next->id, $rider->fresh()->logistics_hub_id);
    }

    public function test_audit_insert_failure_rolls_back_the_operational_change(): void
    {
        $admin = $this->user($this->hub(), 'logistics_admin');
        Route::middleware(['web', 'logistics', 'logistics.activity'])->patch('/audit-insert-failure', function () use ($admin) {
            User::where('id', $admin->id)->update(['given_names' => 'Must roll back']);

            return response('OK');
        })->name('logistics.audit-failure');
        DB::statement("CREATE TRIGGER audit_failure BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'Simulated audit failure'); END");
        $this->actingAs($admin)->patch('/audit-insert-failure')->assertStatus(500);
        $this->assertSame('Test', $admin->fresh()->given_names);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
    }

    public function test_operational_lists_paginate_with_company_and_status_isolation(): void
    {
        $hub = $this->hub();
        $other = $this->hub('Beta');
        $this->actingAs($this->user($hub, 'logistics_admin'));
        for ($i = 0; $i < 25; $i++) {
            $this->parcel($hub, $hub, 'list-'.$i, 'ready_for_pickup');
            $this->user($hub, 'rider', 'pending');
            $this->user($hub, 'hub_staff', 'pending');
        }
        $this->parcel($other, $other, 'foreign-list', 'ready_for_pickup');
        $this->user($other, 'rider', 'pending');
        $this->user($other, 'hub_staff', 'pending');
        foreach (['requests' => 'shipments', 'riders' => 'riders', 'staff?status=pending' => 'staff'] as $path => $key) {
            $url = '/logistics/'.$path.(str_contains($path, '?') ? '&' : '?').'page=2';
            $rows = $this->get($url)->assertOk()->viewData($key);
            $this->assertSame(25, $rows->total());
            $this->assertCount(5, $rows->items());
            $this->assertStringContainsString('page=1', $rows->previousPageUrl());
            if ($key === 'staff') {
                $this->assertStringContainsString('status=pending', $rows->previousPageUrl());
            }
        }
        DB::table('shipments')->where('logistics_company', 'Alpha')->update(['shipping_status' => 'sorted']);
        foreach (['assignments', 'monitor'] as $path) {
            $rows = $this->get('/logistics/'.$path.'?page=2')->assertOk()->viewData('shipments');
            $this->assertSame(25, $rows->total());
            $this->assertCount(5, $rows->items());
        }
    }

    public function test_transfer_pagination_keeps_complete_batches_and_company_isolation(): void
    {
        $hub = $this->hub();
        for ($i = 0; $i < 21; $i++) {
            $to = $this->hub('Alpha', sprintf('Town %02d', $i));
            for ($j = 0; $j < 2; $j++) {
                $parcel = $this->parcel($hub, $to, 'batch-'.$i.'-'.$j, 'at_sorting_center');
                ShipmentHubLeg::create(['shipment_id' => $parcel->id, 'sequence' => 1, 'leg_type' => 'direct', 'from_hub' => $hub->municipality, 'from_province' => $hub->province, 'to_hub' => $to->municipality, 'to_province' => $to->province, 'status' => 'pending']);
            }
        }
        $foreign = $this->hub('Beta');
        $parcel = $this->parcel($foreign, $foreign, 'foreign-batch', 'at_sorting_center');
        ShipmentHubLeg::create(['shipment_id' => $parcel->id, 'sequence' => 1, 'leg_type' => 'direct', 'from_hub' => $foreign->municipality, 'from_province' => $foreign->province, 'to_hub' => $foreign->municipality, 'to_province' => $foreign->province, 'status' => 'pending']);
        $admin = $this->user($hub, 'logistics_admin');
        $response = $this->actingAs($admin)->get('/logistics/hub-requests?unrequested_batch_page=2')->assertOk();
        $this->assertSame(21, $response->viewData('notYetRequestedPages')->total());
        $this->assertCount(10, $response->viewData('notYetRequested'));
        foreach ($response->viewData('notYetRequested') as $legs) {
            $this->assertCount(2, $legs);
        }
        $this->actingAs($this->user($hub));
        $response = $this->get('/logistics/dashboard?request_batch_page=3')->assertOk();
        $this->assertSame(21, $response->viewData('requestablePages')->total());
        $this->assertSame(42, $response->viewData('requestableCount'));
        $this->assertCount(1, $response->viewData('requestable'));
        $batch = $response->viewData('requestable')->first();
        $this->assertCount(2, $batch);
        $this->post('/logistics/hub/request-transfer', ['to_hub' => $batch->first()->to_hub, 'to_province' => $batch->first()->to_province])->assertRedirect();
        foreach ($batch as $leg) {
            $this->assertNotNull($leg->fresh()->requested_at);
        }
        $this->actingAs($admin)->get('/logistics/hub-requests')->assertOk()->assertViewHas('pendingTransferRequests', fn ($rows) => $rows->count() === 1 && $rows->first()->count() === 2);
    }

    public function test_report_query_count_does_not_grow_per_rider_or_hub_and_route_touches_are_distinct(): void
    {
        $hub = $this->hub();
        $this->actingAs($this->user($hub, 'logistics_admin'));
        $rider = $this->user($hub, 'rider');
        $parcel = $this->parcel($hub, $hub, 'report-base', 'completed');
        $parcel->update(['courier_id' => $rider->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $initial = $this->get('/logistics/reports')->assertOk();
        $queries = count(DB::getQueryLog());
        for ($i = 0; $i < 25; $i++) {
            $extra = $this->hub('Alpha', 'Town '.$i, 'Province '.$i);
            $this->user($extra, 'rider');
        }
        $sameName = $this->hub('Alpha', 'Santa Cruz', 'Marinduque');
        ShipmentHubLeg::create(['shipment_id' => $parcel->id, 'sequence' => 1, 'leg_type' => 'regional_to_destination', 'from_hub' => $hub->municipality, 'from_province' => $hub->province, 'to_hub' => $sameName->municipality, 'to_province' => $sameName->province, 'status' => 'completed']);
        $this->parcel($this->hub('Beta'), $hub, 'foreign-report', 'completed');
        DB::flushQueryLog();
        $result = $this->get('/logistics/reports')->assertOk();
        $this->assertLessThanOrEqual($queries, count(DB::getQueryLog()));
        $this->assertCount(10, $result->viewData('courierStats'));
        $this->assertEquals(1, $result->viewData('courierStats')->first()->delivered_count);
        $counts = $result->viewData('hubSummary')->keyBy('id');
        $this->assertEquals(1, $counts[$hub->id]->parcel_count);
        $this->assertEquals(1, $counts[$sameName->id]->parcel_count);
        $this->assertEquals(2, $counts->sum('parcel_count'));
        DB::disableQueryLog();
    }

    public function test_history_filters_pagination_and_report_counts_match_fixture_data(): void
    {
        $hub = $this->hub();
        $other = $this->hub('Beta');
        $admin = $this->user($hub, 'logistics_admin');
        for ($i = 0; $i < 25; $i++) {
            $this->parcel($hub, $hub, 'history-'.$i, 'completed');
        }
        $old = $this->parcel($hub, $hub, 'old', 'completed');
        DB::table('shipments')->where('id', $old->id)->update(['created_at' => now()->subDays(40)]);
        $this->parcel($hub, $hub, 'failed', 'delivery_failed');
        $this->parcel($other, $other, 'foreign', 'completed');
        $response = $this->actingAs($admin)->get('/logistics/history?status=completed&from='.now()->subDays(2)->toDateString().'&page=2&hub_id='.$hub->id);
        $response->assertOk();
        $rows = $response->viewData('shipments');
        $this->assertSame(25, $rows->total());
        $this->assertCount(5, $rows->items());
        $this->get('/logistics/history?search=TRACK-old')->assertOk()->assertViewHas('shipments', fn ($rows) => $rows->total() === 1);
        $report = $this->get('/logistics/reports?from='.now()->subDays(2)->toDateString());
        $report->assertOk()->assertViewHas('total', 26)->assertViewHas('completed', 25)->assertViewHas('failed', 1);
        $this->get('/logistics/dashboard')->assertOk()->assertViewHas('total', 27)->assertViewHas('completed', 26)->assertViewHas('failed', 1);
    }

    public function test_company_admin_cannot_use_foreign_resource_ids_on_logistics_endpoints(): void
    {
        $hub = $this->hub();
        $other = $this->hub('Beta');
        $admin = $this->user($hub, 'logistics_admin');
        $rider = $this->user($other, 'rider');
        $staff = $this->user($other);
        $parcel = $this->parcel($other, $other, 'foreign', 'ready_for_pickup');
        $parcel->update(['pickup_approved_at' => now()]);
        $vehicle = DB::table('company_vehicles')->insertGetId(['company_name' => 'Beta', 'logistics_hub_id' => $other->id, 'vehicle_type' => 'two_wheels', 'platform_status' => 'approved', 'is_available' => true]);
        $issue = LogisticsIssue::create(['company_name' => 'Beta', 'hub_id' => $other->id, 'shipment_id' => $parcel->id, 'kind' => 'damaged', 'description' => 'Private Beta issue']);
        $this->actingAs($admin);
        foreach (['approve', 'reject', 'activate', 'suspend'] as $action) {
            $this->patchJson('/logistics/riders/'.$rider->id.'/'.$action)->assertNotFound();
        }
        foreach (['approve', 'confirm', 'reject', 'activate', 'suspend'] as $action) {
            $this->patchJson('/logistics/staff/'.$staff->id.'/'.$action)->assertNotFound();
        }
        $this->get('/logistics/accounts/'.$staff->id.'/edit')->assertNotFound();
        $this->patchJson('/logistics/accounts/'.$staff->id)->assertNotFound();
        $this->patchJson('/logistics/hubs/'.$other->id, ['is_operational' => false])->assertNotFound();
        $this->patchJson('/logistics/hubs/'.$other->id.'/toggle-hiring')->assertNotFound();
        $this->patchJson('/logistics/vehicles/'.$vehicle, ['is_available' => false])->assertNotFound();
        $this->deleteJson('/logistics/vehicles/'.$vehicle)->assertNotFound();
        $this->patchJson('/logistics/issues/'.$issue->id, ['status' => 'resolved', 'resolution' => 'Forged'])->assertNotFound();
        foreach (['assign', 'assign-hub-transfer'] as $action) {
            $this->patchJson('/logistics/assignments/foreign/'.$action, ['courier_id' => $rider->id])->assertNotFound();
        }
        foreach (['receive', 'complete-hub-transfer'] as $action) {
            $this->patchJson('/logistics/scan/foreign/'.$action)->assertNotFound();
        }
        foreach (['approve', 'reject', 'assign-pickup'] as $action) {
            $this->patchJson('/logistics/requests/foreign/'.$action, ['courier_id' => $rider->id])->assertNotFound();
        }
        $this->patchJson('/logistics/status/foreign', ['status' => 'delivered'])->assertNotFound();
        $this->getJson('/logistics/messages/poll?receiver_id='.$rider->id)->assertForbidden();
        $this->postJson('/logistics/messages/send', ['receiver_id' => $rider->id, 'body' => 'Forged'])->assertForbidden();
        $this->assertDatabaseCount('logistics_activity_logs', 0);
        $this->assertSame('approved', $rider->fresh()->status);
    }

    public function test_staff_from_a_different_hub_cannot_receive_an_active_transfer(): void
    {
        $from = $this->hub();
        $to = $this->hub('Alpha', 'Pila');
        $wrong = $this->hub('Alpha', 'Pila', 'Marinduque');
        $parcel = $this->parcel($from, $to, 'transfer', 'hub_transfer');
        $leg = ShipmentHubLeg::create(['shipment_id' => $parcel->id, 'sequence' => 1, 'leg_type' => 'direct', 'from_hub' => $from->municipality, 'to_hub' => $to->municipality, 'status' => 'in_transit', 'rider_confirmed_pickup_at' => now()]);
        $this->actingAs($this->user($wrong))->patchJson('/logistics/scan/transfer/complete-hub-transfer')->assertNotFound();
        $this->actingAs($this->user($from))->patchJson('/logistics/scan/transfer/complete-hub-transfer')->assertForbidden();
        $this->assertSame('in_transit', $leg->fresh()->status);
    }

    public function test_suspended_staff_can_still_log_out(): void
    {
        $this->actingAs($this->user($this->hub(), 'hub_staff', 'suspended'))->post('/logistics/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_management_preserves_the_existing_registration_age_rule(): void
    {
        $hub = $this->hub();
        $admin = $this->user($hub, 'logistics_admin');
        $this->actingAs($admin)->post('/logistics/accounts', [
            'role' => 'hub_staff', 'username' => 'young_staff', 'email' => 'young_staff@example.test', 'given_names' => 'Young', 'last_name' => 'Staff', 'sex' => 'female',
            'birthday' => now()->subYears(17)->toDateString(), 'contact_no' => '09123456789', 'barangay' => 'Test', 'password' => 'YoungPassword!123', 'password_confirmation' => 'YoungPassword!123',
            'status' => 'pending', 'logistics_hub_id' => $hub->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['username' => 'young_staff', 'age' => 17]);
    }

    public function test_rider_approval_does_not_send_email_before_the_audit_commits(): void
    {
        Mail::shouldReceive('raw')->never();
        $hub = $this->hub();
        $admin = $this->user($hub, 'logistics_admin');
        $rider = $this->user($hub, 'rider', 'pending');
        DB::statement("CREATE TRIGGER approval_audit_failure BEFORE INSERT ON logistics_activity_logs BEGIN SELECT RAISE(ABORT, 'Simulated audit failure'); END");
        $this->actingAs($admin)->patch('/logistics/riders/'.$rider->id.'/approve')->assertStatus(500);
        $this->assertSame('pending', $rider->fresh()->status);
        $this->assertDatabaseCount('logistics_activity_logs', 0);
    }

    public function test_rider_approval_email_runs_only_after_successful_outer_commit(): void
    {
        $hub = $this->hub();
        $rider = $this->user($hub, 'rider', 'pending');
        $this->actingAs($this->user($hub, 'logistics_admin'));
        Mail::shouldReceive('raw')->once()->withArgs(fn ($body, $callback) => str_contains($body, 'approved') && DB::transactionLevel() === 0 && $rider->fresh()->status === 'approved' && DB::table('logistics_activity_logs')->count() === 1);
        DB::beginTransaction();
        $this->patch('/logistics/riders/'.$rider->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $rider->fresh()->status);
        DB::commit();
    }

    public function test_transfer_assignability_does_not_skip_a_prior_leg(): void
    {
        $from = $this->hub();
        $to = $this->hub('Alpha', 'Pila');
        $parcel = $this->parcel($from, $to, 'route', 'at_sorting_center');
        $first = ShipmentHubLeg::create(['shipment_id' => $parcel->id, 'sequence' => 1, 'leg_type' => 'direct', 'from_hub' => $from->municipality, 'to_hub' => $to->municipality, 'status' => 'pending']);
        ShipmentHubLeg::create(['shipment_id' => $parcel->id, 'sequence' => 2, 'leg_type' => 'direct', 'from_hub' => $to->municipality, 'to_hub' => $from->municipality, 'status' => 'pending', 'requested_at' => now(), 'approved_at' => now()]);
        $this->assertNull($parcel->nextAssignableHubLeg());
        $first->update(['status' => 'in_transit']);
        $this->assertNull($parcel->nextAssignableHubLeg());
    }

    public function test_transfer_assignment_and_arrival_update_the_assignment_progress(): void
    {
        Schema::create('order_status_history', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('order_id');
            $t->string('status');
            $t->integer('changed_by');
            $t->text('notes')->nullable();
            $t->timestamp('created_at');
        });
        Schema::table('shipments', function (Blueprint $t) {
            foreach (['hub_transfer_started_at', 'hub_transfer_completed_at', 'sorted_at'] as $column) {
                $t->timestamp($column)->nullable();
            } $t->string('sorted_area')->nullable();
        });
        $from = $this->hub();
        $to = $this->hub('Alpha', 'Pila');
        $admin = $this->user($from, 'logistics_admin');
        $rider = $this->user($from, 'rider');
        $rider->update(['vehicle_type' => 'four_wheels']);
        $wrong = $this->user($to, 'rider');
        $wrong->update(['vehicle_type' => 'four_wheels']);
        DB::table('orders')->insert(['id' => 'transfer-order', 'status' => 'at_sorting_center', 'created_at' => now(), 'updated_at' => now()]);
        $parcel = $this->parcel($from, $to, 'transfer', 'at_sorting_center');
        $parcel->update(['order_id' => 'transfer-order']);
        $leg = ShipmentHubLeg::create(['shipment_id' => $parcel->id, 'sequence' => 1, 'leg_type' => 'direct', 'from_hub' => $from->municipality, 'to_hub' => $to->municipality, 'status' => 'pending', 'requested_at' => now(), 'approved_at' => now()]);
        $batch = app(HubTransferWorkflow::class)->legacyBatch($admin, $leg->id);
        app(HubTransferWorkflow::class)->decide($admin, $batch->id, 'approved');
        $this->actingAs($admin)->patchJson('/logistics/assignments/transfer/assign-hub-transfer', ['courier_id' => $wrong->id])->assertUnprocessable();
        $this->patch('/logistics/assignments/transfer/assign-hub-transfer', ['courier_id' => $rider->id])->assertRedirect();
        $this->assertDatabaseHas('delivery_assignments', ['shipment_id' => 'transfer', 'leg' => 'hub_transfer', 'courier_id' => $rider->id, 'status' => 'hub_transfer']);
        $this->assertSame('in_transit', $leg->fresh()->status);
        $leg->update(['rider_confirmed_pickup_at' => now()]);
        $this->actingAs($this->user($to))->patchJson('/logistics/scan/transfer/complete-hub-transfer')->assertOk();
        $this->assertSame('sorted', $parcel->fresh()->shipping_status);
        $this->assertSame('completed', $leg->fresh()->status);
        $this->assertDatabaseHas('delivery_assignments', ['shipment_id' => 'transfer', 'leg' => 'hub_transfer', 'status' => 'completed']);
    }
}
