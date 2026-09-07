<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics staff no longer just type a free-text business name — they pick
 * which admin-approved logistics company they're joining, symbol/logo and
 * all, as the very first step of registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active'); // active|inactive — inactive companies stop appearing in the registration picker
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('logistics_company_id')->nullable()->after('business_name')
                ->constrained('logistics_companies')->nullOnDelete();
        });

        // There's already one out-of-band logistics account with no company on
        // record — give it a home so nothing is left dangling after this migration.
        $companyId = DB::table('logistics_companies')->insertGetId([
            'name'       => 'PocketFinds Logistics',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->where('is_logistics', true)->whereNull('logistics_company_id')
            ->update(['logistics_company_id' => $companyId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logistics_company_id');
        });
        Schema::dropIfExists('logistics_companies');
    }
};
