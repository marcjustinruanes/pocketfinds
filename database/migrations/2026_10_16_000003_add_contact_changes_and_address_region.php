<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_verification_tokens', function (Blueprint $table) {
            $table->string('pending_value')->nullable();
        });
        Schema::table('buyer_addresses', function (Blueprint $table) {
            $table->string('region', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('account_verification_tokens', fn (Blueprint $table) => $table->dropColumn('pending_value'));
        Schema::table('buyer_addresses', fn (Blueprint $table) => $table->dropColumn('region'));
    }
};
