<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Folds the separate policy_revisions table into a single `history` JSON column on
 * `policies` itself — one table instead of two, still keeping every past version
 * (content + who changed it + when) rather than silently overwriting on each edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->json('history')->nullable()->after('content');
        });

        // Carry forward whatever revisions already exist before dropping the table.
        $policies = DB::table('policies')->get(['id']);
        foreach ($policies as $policy) {
            $revisions = DB::table('policy_revisions')
                ->where('policy_id', $policy->id)
                ->orderBy('created_at')
                ->get(['content', 'changed_by', 'created_at'])
                ->map(fn ($r) => ['content' => $r->content, 'changed_by' => $r->changed_by, 'created_at' => $r->created_at])
                ->values()->all();

            if ($revisions) {
                DB::table('policies')->where('id', $policy->id)->update(['history' => json_encode($revisions)]);
            }
        }

        Schema::dropIfExists('policy_revisions');
    }

    public function down(): void
    {
        Schema::create('policy_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::table('policies', function (Blueprint $table) {
            $table->dropColumn('history');
        });
    }
};
