<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A brand-new logistics company's policy row has no live `content` yet — only
 * a `pending_content` awaiting admin approval — so `content` can no longer be
 * NOT NULL now that this table also holds company-authored rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE policies ALTER COLUMN content DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('policies')->whereNull('content')->update(['content' => '']);
        DB::statement('ALTER TABLE policies ALTER COLUMN content SET NOT NULL');
    }
};
