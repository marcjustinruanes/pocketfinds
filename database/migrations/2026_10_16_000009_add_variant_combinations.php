<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [];
        foreach (DB::table('products')->get(['id', 'sku', 'variations']) as $product) {
            if ($product->sku !== null && trim($product->sku) !== '') $rows[] = ['sku' => mb_strtolower(trim($product->sku)), 'product_id' => $product->id, 'option_reference' => null];
            foreach (json_decode($product->variations ?? '[]', true) ?: [] as $group) foreach ($group['options'] ?? [] as $option) {
                if (!empty($option['sku'])) $rows[] = ['sku' => mb_strtolower(trim($option['sku'])), 'product_id' => $product->id, 'option_reference' => 'legacy:'.hash('sha256', ($group['name'] ?? '').':'.($option['value'] ?? ''))];
            }
        }
        if (count(array_unique(array_column($rows, 'sku'))) !== count($rows)) throw new RuntimeException('Existing SKU collisions must be resolved before installing the variant SKU registry; no schema changes were applied.');
        Schema::table('products', function (Blueprint $table) {
            $table->json('variant_axes')->nullable();
            $table->json('variant_rows')->nullable();
        });
        Schema::create('product_skus', function (Blueprint $table) {
            $table->string('sku', 100)->primary();
            $table->uuid('product_id')->index();
            $table->string('option_reference', 150)->nullable();
        });
        foreach (array_chunk($rows, 500) as $chunk) DB::table('product_skus')->insert($chunk);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_skus');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['variant_axes', 'variant_rows']));
    }
};
