<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_hubs', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_no', 32)->nullable();
            $table->boolean('is_operational')->default(true);
        });
        Schema::table('shipment_hub_legs', function (Blueprint $table) {
            $table->string('from_province')->nullable();
            $table->string('to_province')->nullable();
            $table->index(['from_hub', 'from_province']);
            $table->index(['to_hub', 'to_province']);
        });
        // Old leg types describe which province each stop belongs to; no account or shipment is replaced.
        foreach (DB::table('shipment_hub_legs')->orderBy('id')->get() as $leg) {
            $shipment = DB::table('shipments')->where('id', $leg->shipment_id)->first();
            if (! $shipment) {
                continue;
            }
            DB::table('shipment_hub_legs')->where('id', $leg->id)->update([
                'from_province' => $leg->leg_type === 'delivery' ? $shipment->destination_province : $shipment->origin_province,
                'to_province' => $leg->leg_type === 'dispatch' ? $shipment->origin_province : $shipment->destination_province,
            ]);
        }
        Schema::create('logistics_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 150)->index();
            $table->bigInteger('actor_id')->nullable();
            $table->bigInteger('hub_id')->nullable();
            $table->string('action', 150);
            $table->string('record_id', 64)->nullable();
            $table->timestamp('created_at')->index();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('hub_id')->references('id')->on('logistics_hubs')->nullOnDelete();
        });
        Schema::create('logistics_issues', function (Blueprint $table) {
            $table->id();
            $table->char('shipment_id', 36);
            $table->string('company_name', 150)->index();
            $table->bigInteger('hub_id')->nullable();
            $table->bigInteger('reported_by')->nullable();
            $table->bigInteger('assigned_to')->nullable();
            $table->string('kind', 32);
            $table->text('description');
            $table->string('status', 32)->default('open');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->foreign('shipment_id')->references('id')->on('shipments')->cascadeOnDelete();
            foreach (['reported_by', 'assigned_to'] as $column) {
                $table->foreign($column)->references('id')->on('users')->nullOnDelete();
            }
            $table->foreign('hub_id')->references('id')->on('logistics_hubs')->nullOnDelete();
            $table->index(['company_name', 'status']);
        });
        Schema::create('logistics_issue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('logistics_issues')->cascadeOnDelete();
            $table->bigInteger('actor_id')->nullable();
            $table->string('status', 32);
            $table->bigInteger('assigned_to')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_issue_events');
        Schema::dropIfExists('logistics_issues');
        Schema::dropIfExists('logistics_activity_logs');
        Schema::table('shipment_hub_legs', function (Blueprint $table) {
            $table->dropIndex(['from_hub', 'from_province']);
            $table->dropIndex(['to_hub', 'to_province']);
            $table->dropColumn(['from_province', 'to_province']);
        });
        Schema::table('logistics_hubs', fn (Blueprint $table) => $table->dropColumn(['name', 'address', 'contact_no', 'is_operational']));
    }
};
