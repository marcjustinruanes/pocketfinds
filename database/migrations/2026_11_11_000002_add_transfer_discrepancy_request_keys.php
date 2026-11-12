<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hub_transfer_events', function (Blueprint $table) {
            $table->uuid('request_key')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->unique(['batch_id', 'request_key'], 'transfer_event_request_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hub_transfer_events', function (Blueprint $table) {
            $table->dropUnique('transfer_event_request_unique');
            $table->dropColumn(['request_key', 'request_hash']);
        });
    }
};
