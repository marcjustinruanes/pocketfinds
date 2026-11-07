<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'pickup_offer_failures')) $table->unsignedTinyInteger('pickup_offer_failures')->default(0)->after('delivery_offer_failures');
            if (! Schema::hasColumn('shipments', 'pickup_offer_expires_at')) $table->timestamp('pickup_offer_expires_at')->nullable()->after('delivery_offer_expires_at');
            if (! Schema::hasColumn('shipments', 'delivery_otp_ciphertext')) $table->text('delivery_otp_ciphertext')->nullable()->after('delivery_otp_hash');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn(['pickup_offer_failures', 'pickup_offer_expires_at', 'delivery_otp_ciphertext']));
    }
};
