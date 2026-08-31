<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Logistics notification preferences — read by the topbar alert dropdown.
            $table->boolean('notify_new_requests')->default(true);
            $table->boolean('notify_unassigned_shipments')->default(true);
            // Which scanning method the Scan Parcel page should show for this user.
            $table->string('preferred_scanner')->default('both'); // 'both' | 'webcam' | 'usb'
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_new_requests', 'notify_unassigned_shipments', 'preferred_scanner']);
        });
    }
};
