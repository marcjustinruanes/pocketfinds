<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_shipping_obligations', function (Blueprint $table) {
            $table->uuid('rate_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_shipping_obligations', function (Blueprint $table) {
            $table->uuid('rate_id')->nullable(false)->change();
        });
    }
};
