<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('gcash_purchases','review_flow')) Schema::table('gcash_purchases',fn(Blueprint $table)=>$table->string('review_flow',24)->default('seller'));
        foreach (['platform_gcash_name','platform_gcash_number'] as $column) {
            if (!Schema::hasColumn('settings',$column)) Schema::table('settings',fn(Blueprint $table)=>$table->string($column,150)->nullable());
        }
    }
    public function down(): void
    {
        // Preserve payment destinations and review ownership as historical financial records.
    }
};
