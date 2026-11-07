<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commission_enrollment_settings', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->boolean('enabled')->default(false);
            $t->timestamp('activation_at')->nullable();
            $t->unsignedInteger('revision')->default(1);
        });
        Schema::create('commission_enrollment_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('actor_id');
            $t->string('actor_name');
            $t->string('action', 20);
            $t->boolean('previous_enabled');
            $t->timestamp('previous_activation_at')->nullable();
            $t->timestamp('activation_at')->nullable();
            $t->unsignedInteger('revision')->unique();
            $t->text('reason');
            $t->timestamp('created_at');
            $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });
        // Deliberately does not import environment flags or enroll existing orders.
        DB::table('commission_enrollment_settings')->insert(['id'=>1,'enabled'=>false,'revision'=>1]);
    }

    public function down(): void
    {
        throw new RuntimeException('Commission activation history must be preserved. Use a forward migration.');
    }
};
