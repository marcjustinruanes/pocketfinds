<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private bool $createdTable = false;

    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'order_number')) $table->string('order_number')->nullable();
                if (!Schema::hasColumn('orders', 'buyer_id')) $table->foreignId('buyer_id')->nullable();
                if (!Schema::hasColumn('orders', 'seller_id')) $table->foreignId('seller_id')->nullable();
                if (!Schema::hasColumn('orders', 'status')) $table->string('status')->default('to_ship')->index();
                if (!Schema::hasColumn('orders', 'items')) $table->json('items')->nullable();
                if (!Schema::hasColumn('orders', 'subtotal')) $table->decimal('subtotal', 12, 2)->default(0);
                if (!Schema::hasColumn('orders', 'shipping_amount')) $table->decimal('shipping_amount', 12, 2)->default(0);
                if (!Schema::hasColumn('orders', 'discount_amount')) $table->decimal('discount_amount', 12, 2)->default(0);
                if (!Schema::hasColumn('orders', 'total')) $table->decimal('total', 12, 2)->default(0);
                if (!Schema::hasColumn('orders', 'shipping_address')) $table->json('shipping_address')->nullable();
                if (!Schema::hasColumn('orders', 'payment_method_id')) $table->foreignId('payment_method_id')->nullable();
            });
            return;
        }

        $this->createdTable = true;
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained('users');
            $table->string('status')->default('to_ship')->index();
            $table->json('items');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->json('shipping_address');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if ($this->createdTable) Schema::dropIfExists('orders');
    }
};