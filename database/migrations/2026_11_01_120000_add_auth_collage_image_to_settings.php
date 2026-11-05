<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->binary('auth_collage_image_data')->nullable();
            $table->string('auth_collage_image_mime', 32)->nullable();
            $table->string('auth_collage_image_sha256', 64)->nullable();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE settings MODIFY auth_collage_image_data MEDIUMBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn([
            'auth_collage_image_data', 'auth_collage_image_mime', 'auth_collage_image_sha256',
        ]));
    }
};
