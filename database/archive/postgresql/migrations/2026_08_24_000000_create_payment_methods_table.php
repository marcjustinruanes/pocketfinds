<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('payment_methods')->insert([
            ['name' => 'Cash on Delivery', 'description' => 'Pay when your order arrives.', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'GCash', 'description' => 'Pay securely using GCash.', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bank Transfer', 'description' => 'Transfer payment to the provided bank account.', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};