<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single-row table backing the Admin Settings page. Replaces the platform
 * name / support email / commission rate / feature toggles that used to be
 * hardcoded in PHP and Blade with real, admin-editable values. Read through
 * App\Models\Setting::current(), which creates this default row on first use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('platform_name')->default('PocketFinds');
            $table->string('support_email')->default('anchetanicole1020@gmail.com');
            $table->decimal('commission_rate', 5, 2)->default(10.00); // percent, e.g. 10.00 = 10%
            $table->boolean('google_signin_enabled')->default(true);
            $table->boolean('new_registrations_enabled')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
