<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('public_image_assets')) return;
        Schema::create('public_image_assets', function (Blueprint $table) {
            $table->string('path', 255)->primary();
            $table->string('mime', 32);
            $table->string('sha256', 64);
            $table->longText('data_base64');
        });
    }
    public function down(): void { Schema::dropIfExists('public_image_assets'); }
};
