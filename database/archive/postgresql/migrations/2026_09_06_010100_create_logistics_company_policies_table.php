<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each logistics company writes its own Terms & Conditions (separate from the
 * platform-wide, admin-authored ones in `policies`). A company's staff submit
 * an edit into pending_content; it only becomes the live, registrant-facing
 * `content` once an admin approves it. `history` is the same append-only
 * audit-trail shape used by Policy — one row per approved version, not one
 * row per edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_company_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_company_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->text('pending_content')->nullable();
            $table->foreignId('pending_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pending_submitted_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->json('history')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_company_policies');
    }
};
