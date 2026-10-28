<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['position' => 100, 'sec_registration_no' => 11, 'postal_code' => 4] as $column => $length) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->string($column, $length)->nullable());
            }
        }
        if (! Schema::hasIndex('users', 'users_sec_registration_no_unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->unique('sec_registration_no'));
        }
        if (! Schema::hasColumn('users', 'service_regions')) {
            Schema::table('users', fn (Blueprint $table) => $table->json('service_regions')->nullable());
        }
        if (! Schema::hasColumn('logistics_hubs', 'region')) {
            Schema::table('logistics_hubs', fn (Blueprint $table) => $table->string('region')->nullable());
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restore a backup to undo these registration fields without losing application data.');
    }
};
