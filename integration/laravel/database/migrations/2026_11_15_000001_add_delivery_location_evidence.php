<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('delivery_verifications','delivery_latitude')) Schema::table('delivery_verifications', function (Blueprint $t) {
            $t->decimal('delivery_latitude',10,7)->nullable();
            $t->decimal('delivery_longitude',11,7)->nullable();
            $t->timestamp('location_recorded_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('delivery_verifications',fn(Blueprint $t)=>$t->dropColumn(['delivery_latitude','delivery_longitude','location_recorded_at']));
    }
};
