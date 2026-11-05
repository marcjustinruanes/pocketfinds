<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns=array_values(array_filter(['license_type','dl_codes','vehicle_dl_code','load_capacity_kg'],fn($field)=>Schema::hasColumn('users',$field)));
        if($columns)Schema::table('users',fn(Blueprint $table)=>$table->dropColumn($columns));
    }

    public function down(): void
    {
        Schema::table('users',function(Blueprint $table){
            $table->enum('license_type',\App\Enums\LicenseType::values())->nullable();
            $table->json('dl_codes')->nullable();
            $table->enum('vehicle_dl_code',array_column(\App\Enums\DriverLicenseCode::vehicleCategories(),'value'))->nullable();
            $table->unsignedInteger('load_capacity_kg')->nullable();
        });
    }
};
