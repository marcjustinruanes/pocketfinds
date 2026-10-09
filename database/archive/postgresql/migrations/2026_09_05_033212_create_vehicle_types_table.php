<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors id_types — a real lookup table instead of hardcoding the vehicle type list (and
 * which ones require plate/OR/CR/license) in PHP validation rules, JS, and Blade radio
 * buttons. `users.vehicle_type` keeps storing the slug string unchanged (no migration of
 * existing data/columns needed) — this table only backs the registration form's options.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('requires_documents')->default(true);
            $table->timestamps();
        });

        DB::table('vehicle_types')->insert([
            ['slug' => 'motorcycle', 'name' => 'Motorcycle', 'requires_documents' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'bicycle', 'name' => 'Bicycle', 'requires_documents' => false, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'car_van', 'name' => 'Car / Van', 'requires_documents' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
