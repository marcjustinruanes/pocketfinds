<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Admin-authored, database-backed policy documents (starting with the registration
 * Terms & Conditions, previously a hardcoded 5-question quiz baked into every
 * registration Blade file). `policy_revisions` is an append-only log — every edit
 * inserts a snapshot of what the content WAS before the change, so nothing is ever
 * silently overwritten without a record of it (same pattern as order_status_history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // e.g. 'terms_and_conditions'
            $table->string('title');
            $table->longText('content');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('policy_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained()->cascadeOnDelete();
            $table->longText('content'); // the content as it was BEFORE this revision's change
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('policies')->insert([
            'type' => 'terms_and_conditions',
            'title' => 'Terms & Conditions',
            'content' => <<<'TEXT'
Welcome to PocketFinds. By creating an account, you agree to the following terms.

1. Account Use
Your PocketFinds account is for personal use only. You may not share, sell, or transfer your account to another person. You are responsible for all activity that occurs under your account.

2. Privacy & Data
We collect only the information needed to verify your identity and operate your account. Your data is never sold to third parties. You may request deletion of your account and data at any time.

3. Prohibited Content
You may not list counterfeit, illegal, or prohibited items on PocketFinds. Violations may result in immediate account suspension and may be reported to relevant authorities.

4. Reviews & Ratings
Reviews must be honest and based on real transactions. Fake reviews, review manipulation, or incentivized reviews are strictly prohibited and will result in removal of the review and possible account action.

5. Account Changes
PocketFinds reserves the right to update these terms at any time. Continued use of the platform after changes are posted means you accept the updated terms. You will be notified of major changes via email.
TEXT,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_revisions');
        Schema::dropIfExists('policies');
    }
};
