<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_hubs', function (Blueprint $t) {
            foreach (['rider', 'staff'] as $role) {
                $t->boolean($role.'_hiring_open')->nullable();
                $t->unsignedInteger($role.'_hire_limit')->nullable();
                $t->dateTime($role.'_hiring_closes_at')->nullable();
            }
            $t->unsignedInteger('hiring_revision')->default(0);
        });
        DB::table('logistics_hubs')->update(['rider_hiring_open' => DB::raw('is_hiring'), 'staff_hiring_open' => DB::raw('is_hiring')]);
        Schema::table('logistics_activity_logs', fn (Blueprint $t) => $t->json('hiring_changes')->nullable());
        Schema::create('hiring_application_history', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('user_id');
            $t->uuid('attempt_id')->index();
            $t->string('company_name', 255)->index();
            $t->bigInteger('hub_id')->nullable();
            $t->string('role', 20);
            $t->string('status', 32);
            $t->text('reason')->nullable();
            $t->json('review_details')->nullable();
            $t->bigInteger('actor_id')->nullable();
            $t->dateTime('occurred_at');
            $t->dateTime('recorded_at');
            $t->boolean('legacy_snapshot')->default(false);
            $t->index(['user_id', 'id']);
            $t->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('hub_id')->references('id')->on('logistics_hubs')->nullOnDelete();
            $t->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('hiring_identity_locks', function (Blueprint $t) {
            $t->unsignedSmallInteger('id')->primary();
        });
        DB::table('hiring_identity_locks')->insert(array_map(fn ($id) => ['id' => $id], range(0, 255)));
        DB::table('users')->where(fn ($q) => $q->where('account_type', 'rider')->orWhere(fn ($q) => $q->where('account_type', 'logistics')->where('logistics_role', 'hub_staff')->where('is_logistics', true)))
            ->orderBy('id')->chunkById(200, function ($users) {
                foreach ($users as $u) {
                    DB::table('hiring_application_history')->insert(['user_id' => $u->id, 'attempt_id' => (string) Str::uuid(),
                        'company_name' => $u->business_name ?? '', 'hub_id' => $u->logistics_hub_id, 'role' => $u->account_type === 'rider' ? 'rider' : 'hub_staff',
                        'status' => $u->status ?? 'pending', 'reason' => $u->status_reason ?? null, 'occurred_at' => $u->updated_at ?? now(),
                        'review_details' => json_encode(['interview_scheduled_at' => $u->interview_scheduled_at ?? null, 'interview_location' => $u->interview_location ?? null]),
                        'recorded_at' => now(), 'legacy_snapshot' => true]);
                }
            });
    }

    public function down(): void
    {
        throw new RuntimeException('Hiring history is retained. Use a verified backup and an explicit recovery plan.');
    }
};
