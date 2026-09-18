<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** length_cm/width_cm/height_cm were unsignedInteger, but the add-product form
 *  (step="0.1") and its validation (numeric) both allow decimals — any seller
 *  entering a non-whole dimension 500'd on insert. Widen to match what the
 *  form already promises. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('length_cm', 6, 1)->unsigned()->nullable()->change();
            $table->decimal('width_cm', 6, 1)->unsigned()->nullable()->change();
            $table->decimal('height_cm', 6, 1)->unsigned()->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('length_cm')->nullable()->change();
            $table->unsignedInteger('width_cm')->nullable()->change();
            $table->unsignedInteger('height_cm')->nullable()->change();
        });
    }
};
