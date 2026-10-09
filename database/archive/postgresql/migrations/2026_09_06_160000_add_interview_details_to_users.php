<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The date/time/location a hub staff applicant is invited to for their face-to-face
 * interview (LogisticsController::inviteStaffInterview()) — set once, when the admin
 * sends the invite, so both the invite email and the admin's own review modal can show
 * exactly what was scheduled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('interview_scheduled_at')->nullable()->after('logistics_hub_id');
            $table->string('interview_location')->nullable()->after('interview_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['interview_scheduled_at', 'interview_location']);
        });
    }
};
