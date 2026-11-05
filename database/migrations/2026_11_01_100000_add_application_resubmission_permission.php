<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users','can_resubmit')) Schema::table('users',fn(Blueprint $table)=>$table->boolean('can_resubmit')->default(false));
    }

    public function down(): void
    {
        Schema::table('users',fn(Blueprint $table)=>$table->dropColumn('can_resubmit'));
    }
};
