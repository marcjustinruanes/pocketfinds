<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class HubHiringMigrationTest extends TestCase
{
    use WorkflowSchema;

    public function test_backfill_preserves_shared_hiring_and_original_account_values(): void
    {
        $this->createWorkflowSchema(false, false);
        foreach ([true, false] as $open) {
            $hub = LogisticsHub::create(['company_name' => 'Alpha', 'province' => 'Laguna', 'municipality' => $open ? 'Santa Cruz' : 'Pila', 'is_hiring' => $open, 'is_operational' => true]);
            User::create(['account_type' => 'rider', 'username' => $open ? 'open_rider' : 'closed_rider', 'email' => $open ? 'open@gmail.com' : 'closed@gmail.com',
                'status' => 'rejected', 'status_reason' => 'Original reason', 'business_name' => 'Alpha', 'logistics_hub_id' => $hub->id, 'given_names' => 'Original', 'last_name' => 'Applicant', 'password' => 'FixturePassword123']);
        }
        $columns = Schema::getColumnListing('users');
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        (require database_path('migrations/2026_10_14_000002_add_role_hiring_management.php'))->up();
        $this->assertSame($before, DB::table('users')->select($columns)->orderBy('id')->get()->toJson());
        foreach (LogisticsHub::all() as $hub) {
            $this->assertSame($hub->is_hiring, $hub->rider_hiring_open);
            $this->assertSame($hub->is_hiring, $hub->staff_hiring_open);
            $this->assertNull($hub->rider_hire_limit);
            $this->assertNull($hub->staff_hiring_closes_at);
        }
        $this->assertDatabaseCount('hiring_application_history', 2);
        $this->assertDatabaseHas('hiring_application_history', ['status' => 'rejected', 'reason' => 'Original reason', 'legacy_snapshot' => true]);
        $this->assertDatabaseCount('hiring_identity_locks', 256);
    }
}
