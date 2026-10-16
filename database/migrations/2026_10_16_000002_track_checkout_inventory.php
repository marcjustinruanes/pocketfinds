<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Existing placed orders have not reserved stock; their confirmation
            // continues to use the legacy deduction path.
            $table->boolean('inventory_reserved')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('inventory_reserved'));
    }
};
