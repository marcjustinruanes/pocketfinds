<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'category_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'category_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }
    }
};
