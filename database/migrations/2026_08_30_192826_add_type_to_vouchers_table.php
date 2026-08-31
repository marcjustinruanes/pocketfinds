<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('type')->default('amount')->after('code'); // 'amount' | 'free_shipping'
            $table->decimal('discount_amount', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->decimal('discount_amount', 10, 2)->nullable(false)->change();
        });
    }
};
