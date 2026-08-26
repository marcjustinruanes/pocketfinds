<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'app_buyer_id')) $table->unsignedBigInteger('app_buyer_id')->nullable()->index();
            if (!Schema::hasColumn('orders', 'app_seller_id')) $table->unsignedBigInteger('app_seller_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = collect(['app_buyer_id', 'app_seller_id'])->filter(fn ($column) => Schema::hasColumn('orders', $column))->all();
            if ($columns) $table->dropColumn($columns);
        });
    }
};