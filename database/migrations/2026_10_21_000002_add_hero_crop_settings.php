<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach (['hero_crop_', 'hero_2_crop_'] as $prefix) {
                $table->unsignedTinyInteger($prefix.'x')->default(50);
                $table->unsignedTinyInteger($prefix.'y')->default(50);
                $table->unsignedTinyInteger($prefix.'zoom')->default(100);
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['hero_crop_x', 'hero_crop_y', 'hero_crop_zoom', 'hero_2_crop_x', 'hero_2_crop_y', 'hero_2_crop_zoom']);
        });
    }
};
