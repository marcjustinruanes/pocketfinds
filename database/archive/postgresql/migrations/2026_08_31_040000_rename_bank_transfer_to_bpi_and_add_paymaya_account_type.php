<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The single generic "Bank Transfer" payment method is really a
        // specific bank the shop accepts — name it after that bank.
        DB::table('payment_methods')
            ->where('type', 'bank')
            ->where('name', 'Bank Transfer')
            ->update(['name' => 'BPI']);
    }

    public function down(): void
    {
        DB::table('payment_methods')
            ->where('type', 'bank')
            ->where('name', 'BPI')
            ->update(['name' => 'Bank Transfer']);
    }
};
