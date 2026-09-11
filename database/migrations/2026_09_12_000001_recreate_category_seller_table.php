<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same situation as 2026_09_12_000000_recreate_document_update_requests_table:
// 2026_08_27_000001_create_category_seller_table is recorded as "Ran" in this
// database's migrations table, but the table itself is missing -- dropped by
// a migration run from another branch against this same shared Supabase
// database. Recreates just the missing table; the users.category_other
// column already exists so that part is skipped via the same guard the
// original migration used.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('category_seller')) {
            Schema::create('category_seller', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'category_id']);
            });
        }

        if (!Schema::hasColumn('users', 'category_other')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('category_other')->nullable()->after('category_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_seller');
    }
};
