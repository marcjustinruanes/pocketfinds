<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('first_name', 'given_names');
            $table->renameColumn('middle_initial', 'middle_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('given_names', 'first_name');
            $table->renameColumn('middle_name', 'middle_initial');
        });
    }
};
