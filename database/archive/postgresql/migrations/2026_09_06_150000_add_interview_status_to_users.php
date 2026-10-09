<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'interview' as a valid users.status value — used so far only by hub staff
 * applications (LogisticsController::inviteStaffInterview()/confirmStaffApproval()):
 * clicking "Approve" on a fresh application no longer grants login directly, it invites
 * the applicant to a face-to-face interview and holds them here (still blocked from
 * logging in, same as 'pending' — see AdminController::loginPost()) until the admin
 * comes back after that interview and gives the real, final approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_status_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status::text = ANY (ARRAY['pending','approved','rejected','suspended','interview']::text[]))");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET status = 'pending' WHERE status = 'interview'");
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_status_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status::text = ANY (ARRAY['pending','approved','rejected','suspended']::text[]))");
    }
};
