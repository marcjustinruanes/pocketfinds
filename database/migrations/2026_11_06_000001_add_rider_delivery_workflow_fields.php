<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_available')) {
                $table->boolean('is_available')->default(false)->after('status')->index();
                $table->timestamp('availability_updated_at')->nullable()->after('is_available');
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'delivery_attempts')) {
                $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('delivery_failed_reason');
                $table->unsignedTinyInteger('delivery_offer_failures')->default(0)->after('delivery_attempts');
                $table->timestamp('delivery_offer_expires_at')->nullable()->after('assigned_at');
                $table->unsignedTinyInteger('pickup_offer_failures')->default(0)->after('delivery_offer_failures');
                $table->timestamp('pickup_offer_expires_at')->nullable()->after('delivery_offer_expires_at');
                $table->string('delivery_proof_file')->nullable()->after('delivery_failed_at');
                $table->string('delivery_otp_hash')->nullable()->after('delivery_proof_file');
                $table->text('delivery_otp_ciphertext')->nullable()->after('delivery_otp_hash');
                $table->unsignedTinyInteger('delivery_otp_attempts')->default(0)->after('delivery_otp_hash');
                $table->timestamp('delivery_otp_locked_until')->nullable()->after('delivery_otp_attempts');
            }
        });

        Schema::table('delivery_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_assignments', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('requested_at');
                $table->timestamp('declined_at')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'declined_at']);
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['delivery_attempts', 'delivery_offer_failures', 'delivery_offer_expires_at', 'pickup_offer_failures', 'pickup_offer_expires_at', 'delivery_proof_file', 'delivery_otp_hash', 'delivery_otp_ciphertext', 'delivery_otp_attempts', 'delivery_otp_locked_until']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_available', 'availability_updated_at']);
        });
    }
};
