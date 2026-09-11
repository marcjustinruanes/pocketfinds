<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('messages', 'reactions')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->json('reactions')->nullable()->after('reply_to_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'reactions')) $table->dropColumn('reactions');
        });
    }
};
