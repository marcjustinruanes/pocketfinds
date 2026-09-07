<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A buyer's saved delivery addresses — picked at checkout instead of always shipping to their profile address. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('contact_no')->nullable();
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('house_no')->nullable();
            $table->string('street')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_addresses');
    }
};
