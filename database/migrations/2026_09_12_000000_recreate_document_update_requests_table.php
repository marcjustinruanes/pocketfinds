<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The original 2026_08_21_070951_create_document_update_requests_table
// migration is recorded as "Ran" in this database's migrations table,
// but the table itself is missing -- almost certainly dropped by a
// "cleanup dead tables" migration run from another branch against this
// same shared Supabase database. Laravel won't re-run an already-recorded
// migration, so this recreates just the missing table (idempotent guard
// in case it's actually present after all).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_update_requests')) {
            return;
        }

        Schema::create('document_update_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('id_type_id')->nullable();
            $table->string('id_file')->nullable();
            $table->string('business_permit_file')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_update_requests');
    }
};
