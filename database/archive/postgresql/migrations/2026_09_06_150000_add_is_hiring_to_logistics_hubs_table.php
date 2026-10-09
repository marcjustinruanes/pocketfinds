<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_hubs', function (Blueprint $table) {
            $table->boolean('is_hiring')->default(true)->after('is_regional_hub');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_hubs', function (Blueprint $table) {
            $table->dropColumn('is_hiring');
        });
    }
};

