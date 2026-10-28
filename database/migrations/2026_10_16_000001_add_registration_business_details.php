<?php

use App\Enums\SellerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'seller_type')) {
            Schema::table('users', fn (Blueprint $table) => $table->enum('seller_type', SellerType::values())->nullable());
        }
        if (! Schema::hasColumn('users', 'tin')) {
            Schema::table('users', fn (Blueprint $table) => $table->char('tin', 12)->nullable());
        }
        if (! Schema::hasIndex('users', 'users_tin_unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->unique('tin'));
        }
        if (! Schema::hasColumn('users', 'region')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('region')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tin']);
            // Region may predate this migration; retain existing address data.
            $table->dropColumn(['seller_type', 'tin']);
        });
    }
};
