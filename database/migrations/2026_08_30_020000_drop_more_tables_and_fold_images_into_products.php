<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Fold product_images into products.images (jsonb array of paths) ──
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'images')) {
                $table->jsonb('images')->nullable()->after('image');
            }
        });
        DB::statement("
            UPDATE products SET images = sub.paths
            FROM (
                SELECT product_id, jsonb_agg(image_url ORDER BY sort_order) AS paths
                FROM product_images GROUP BY product_id
            ) sub
            WHERE sub.product_id = products.id
        ");
        Schema::dropIfExists('product_images');

        // ── Drop the rest of the requested tables. `migrations` is deliberately
        // excluded — it's Laravel's own record of what's been run, not app data;
        // dropping it would make the next `artisan migrate` try to recreate
        // every table from scratch.
        foreach ([
            'cache', 'cache_locks',
            'category_seller',
            'failed_jobs', 'job_batches', 'jobs',
            'password_reset_tokens',
            'policies',
            'sessions',
            'user_documents',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Not reversible — restore from the pre-migration backup in storage/app/backups/ if needed.');
    }
};
