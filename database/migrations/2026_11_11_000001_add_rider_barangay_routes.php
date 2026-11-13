<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_barangay_routes', function (Blueprint $table) {
            $table->id();
            // Existing application user/hub identifiers are signed BIGINTs.
            $table->bigInteger('rider_id');
            $table->bigInteger('hub_id');
            $table->char('barangay_code', 9);
            $table->string('barangay_name');
            $table->string('barangay_key');
            $table->timestamps();
            $table->foreign('rider_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('hub_id')->references('id')->on('logistics_hubs')->cascadeOnDelete();
            $table->unique(['rider_id', 'hub_id', 'barangay_code'], 'rider_barangay_route_unique');
            $table->index(['rider_id', 'hub_id', 'barangay_key'], 'rider_barangay_route_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_barangay_routes');
    }
};
