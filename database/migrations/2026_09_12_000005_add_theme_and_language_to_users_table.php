<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'theme')) {
                $table->string('theme', 10)->default('system')->after('status');
            }
            if (!Schema::hasColumn('users', 'preferred_language')) {
                $table->string('preferred_language', 10)->default('en')->after('theme');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'theme')) $table->dropColumn('theme');
            if (Schema::hasColumn('users', 'preferred_language')) $table->dropColumn('preferred_language');
        });
    }
};
