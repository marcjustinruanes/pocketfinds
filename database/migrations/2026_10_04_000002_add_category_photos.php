<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE categories ADD image_data MEDIUMBLOB NULL');
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_mime', 32)->nullable();
            $table->string('image_sha256', 64)->nullable();
            $table->text('image_source')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn([
            'image_data', 'image_mime', 'image_sha256', 'image_source',
        ]));
    }
};
