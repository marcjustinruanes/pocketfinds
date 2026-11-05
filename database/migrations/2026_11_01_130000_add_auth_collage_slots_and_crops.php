<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach (['back', 'front'] as $slot) {
                $table->binary("auth_collage_{$slot}_image_data")->nullable();
                $table->string("auth_collage_{$slot}_image_mime", 32)->nullable();
                $table->string("auth_collage_{$slot}_image_sha256", 64)->nullable();
            }
            $table->json('auth_collage_crops')->nullable();
        });
        if (DB::getDriverName() === 'mysql') {
            foreach (['back', 'front'] as $slot) {
                DB::statement("ALTER TABLE settings MODIFY auth_collage_{$slot}_image_data MEDIUMBLOB NULL");
            }
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $columns = ['auth_collage_crops'];
            foreach (['back', 'front'] as $slot) {
                foreach (['data', 'mime', 'sha256'] as $suffix) $columns[] = "auth_collage_{$slot}_image_{$suffix}";
            }
            $table->dropColumn($columns);
        });
    }
};
