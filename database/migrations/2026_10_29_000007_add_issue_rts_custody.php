<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if(!Schema::hasColumn('logistics_issues','rts_shipment_id'))Schema::table('logistics_issues',function(Blueprint $t){$t->uuid('rts_shipment_id')->nullable()->unique();$t->foreign('rts_shipment_id')->references('id')->on('shipments')->restrictOnDelete();});
        if(!Schema::hasColumn('logistics_issues','rts'))Schema::table('logistics_issues',fn(Blueprint $t)=>$t->json('rts')->nullable());
    }
    public function down(): void { throw new RuntimeException('RTS custody history requires a forward migration.'); }
};
