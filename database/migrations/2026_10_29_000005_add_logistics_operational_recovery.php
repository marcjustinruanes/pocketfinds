<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Per-column guards allow resuming MySQL's non-transactional DDL safely.
        if (!Schema::hasColumn('delivery_assignments','revision')) Schema::table('delivery_assignments',fn(Blueprint $t)=>$t->unsignedInteger('revision')->default(0));
        if (!Schema::hasColumn('delivery_assignments','reassignment')) Schema::table('delivery_assignments',fn(Blueprint $t)=>$t->json('reassignment')->nullable());
        $foreigns=array_column(Schema::getForeignKeys('logistics_cash_handovers'),'name');
        if (!Schema::hasColumn('logistics_cash_handovers','submitted_by')) Schema::table('logistics_cash_handovers',fn(Blueprint $t)=>$t->bigInteger('submitted_by')->nullable());
        elseif (!in_array('logistics_cash_handovers_submitted_by_foreign',$foreigns,true)) Schema::table('logistics_cash_handovers',fn(Blueprint $t)=>$t->bigInteger('submitted_by')->nullable()->change());
        if (!in_array('logistics_cash_handovers_submitted_by_foreign',$foreigns,true)) Schema::table('logistics_cash_handovers',fn(Blueprint $t)=>$t->foreign('submitted_by')->references('id')->on('users')->restrictOnDelete());
        if (!Schema::hasColumn('logistics_cash_handovers','recovery_reason')) Schema::table('logistics_cash_handovers',fn(Blueprint $t)=>$t->text('recovery_reason')->nullable());
        Schema::table('logistics_issues', function (Blueprint $t) {
            if (!Schema::hasColumn('logistics_issues','recovery_key')) $t->uuid('recovery_key')->nullable()->unique();
            if (!Schema::hasColumn('logistics_issues','recovery')) $t->json('recovery')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Operational recovery and attribution history must not be destructively rolled back.');
    }
};
