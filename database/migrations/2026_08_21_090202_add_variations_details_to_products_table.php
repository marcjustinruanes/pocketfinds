<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('variations')->nullable()->after('image'); // [{name, options:[{value,stock}]}]
            $table->jsonb('details')->nullable()->after('variations'); // [{label, value}]
            $table->unsignedInteger('stock')->default(0)->after('details'); // used when no variations
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['variations', 'details', 'stock']);
        });
    }
};
