<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reviews', fn (Blueprint $table) => $table->json('follow_ups')->nullable());
    }
    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn('follow_ups'));
    }
};
