<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (array_keys(\App\Support\SellerDocuments::LABELS) as $field) {
            if (! Schema::hasColumn('users', $field)) {
                Schema::table('users', fn (Blueprint $table) => $table->string($field)->nullable());
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(array_keys(\App\Support\SellerDocuments::LABELS)));
    }
};
