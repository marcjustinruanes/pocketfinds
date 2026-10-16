<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->change();
            $table->bigInteger('category_id')->nullable()->change();
            $table->uuid('creation_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['creation_key']);
            $table->dropColumn('creation_key');
        });
        // Keep draft-capable status/category columns so existing drafts are retained.
    }
};
