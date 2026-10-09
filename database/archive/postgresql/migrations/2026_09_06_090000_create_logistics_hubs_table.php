<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The provinces/municipalities a logistics company covers, registered during
 * sign-up (via the PSGC API, same as every other address field in the app —
 * never a hardcoded list). Each row IS a hub: a city the company can move a
 * parcel into or out of. Two hubs belonging to the same company are always
 * assumed connected — that's the whole point of one company's hub network —
 * so "can company X service a seller-in-A / buyer-in-B order" is just "does X
 * have a hub row in both A and B," no separate route table needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_hubs', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('province');
            $table->string('municipality');
            $table->timestamps();
            $table->unique(['company_name', 'province', 'municipality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_hubs');
    }
};
