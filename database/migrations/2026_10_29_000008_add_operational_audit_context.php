<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {if(!Schema::hasColumn('logistics_activity_logs','changes'))Schema::table('logistics_activity_logs',fn(Blueprint $t)=>$t->json('changes')->nullable());}
    public function down(): void {throw new RuntimeException('Operational audit history requires a forward migration.');}
};
