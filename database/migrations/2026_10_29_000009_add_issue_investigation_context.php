<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {if(!Schema::hasColumn('logistics_issues','investigation'))Schema::table('logistics_issues',fn(Blueprint $t)=>$t->json('investigation')->nullable());}
    public function down(): void {throw new RuntimeException('Issue investigation history requires a forward migration.');}
};
