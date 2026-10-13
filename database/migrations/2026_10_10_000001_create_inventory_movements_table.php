<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 64)->index();
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->integer('delta');
            $table->integer('requested_delta');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 120);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
