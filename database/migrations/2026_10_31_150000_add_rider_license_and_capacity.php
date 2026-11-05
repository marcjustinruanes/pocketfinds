<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['license_type', 'vehicle_dl_code', 'nbi_clearance_file'] as $field) {
            if (!Schema::hasColumn('users', $field)) Schema::table('users', fn (Blueprint $table) => $table->string($field)->nullable());
        }
        if (!Schema::hasColumn('users', 'dl_codes')) Schema::table('users', fn (Blueprint $table) => $table->json('dl_codes')->nullable());
        if (!Schema::hasColumn('users', 'load_capacity_kg')) Schema::table('users', fn (Blueprint $table) => $table->unsignedInteger('load_capacity_kg')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['license_type', 'vehicle_dl_code', 'nbi_clearance_file', 'dl_codes', 'load_capacity_kg']));
    }
};
