<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Group payment methods into E-Wallet / Bank Accounts / Cash on Delivery.
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('type')->default('other')->after('name');
        });
        DB::table('payment_methods')->where('name', 'Cash on Delivery')->update(['type' => 'cod']);
        DB::table('payment_methods')->where('name', 'GCash')->update(['type' => 'ewallet']);
        DB::table('payment_methods')->where('name', 'Bank Transfer')->update(['type' => 'bank']);
        if (!DB::table('payment_methods')->where('name', 'PayMaya')->exists()) {
            DB::table('payment_methods')->insert([
                'name' => 'PayMaya', 'type' => 'ewallet', 'description' => null,
                'is_active' => true, 'sort_order' => 4,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Sellers set their own shop-wide shipping fee.
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('shipping_fee', 8, 2)->nullable()->after('business_name');
        });

        // Sellers create vouchers for their own shop.
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('code');
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('minimum_spend', 10, 2)->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['seller_id', 'code']);
        });

        // Buyers verify a GCash / bank account once, then reuse it at checkout.
        Schema::create('buyer_payment_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // 'gcash' | 'bank'
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_name')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // Order-level extras: buyer note and which voucher (if any) was applied.
        Schema::table('orders', function (Blueprint $table) {
            $table->text('buyer_note')->nullable()->after('shipping_address');
            $table->string('voucher_code')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['buyer_note', 'voucher_code']);
        });
        Schema::dropIfExists('buyer_payment_accounts');
        Schema::dropIfExists('vouchers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
