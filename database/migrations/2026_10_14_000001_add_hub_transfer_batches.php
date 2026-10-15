<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('shipment_hub_legs')->select('shipment_id', 'sequence')->groupBy('shipment_id', 'sequence')->havingRaw('COUNT(*) > 1')->first()) {
            throw new RuntimeException('Duplicate shipment route sequences must be reconciled before installing transfer constraints. No records have been removed.');
        }
        Schema::create('hub_transfer_batches', function (Blueprint $t) {
            $t->char('id', 36)->primary();
            $t->string('company_name', 150);
            foreach (['origin_hub_id', 'destination_hub_id'] as $column) {
                $t->bigInteger($column)->nullable();
                $t->foreign($column)->references('id')->on('logistics_hubs')->nullOnDelete();
            }
            foreach (['from_hub', 'from_province', 'to_hub', 'to_province'] as $column) {
                $t->string($column)->nullable();
            }
            $t->string('status', 32)->default('pending');
            foreach (['requested', 'approved', 'rejected', 'dispatched', 'received', 'cancelled'] as $action) {
                $t->bigInteger($action.'_by')->nullable();
                $t->timestamp($action.'_at')->nullable();
                $t->foreign($action.'_by')->references('id')->on('users')->nullOnDelete();
            }
            $t->bigInteger('rider_id')->nullable();
            $t->bigInteger('vehicle_id')->nullable();
            $t->foreign('rider_id')->references('id')->on('users')->nullOnDelete();
            $t->foreign('vehicle_id')->references('id')->on('company_vehicles')->nullOnDelete();
            $t->text('rejection_reason')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->boolean('legacy_import')->default(false);
            $t->timestamps();
            $t->index(['company_name', 'status']);
            $t->index(['origin_hub_id', 'status']);
            $t->index(['destination_hub_id', 'status']);
            $t->index(['rider_id', 'status']);
            $t->index(['vehicle_id', 'status']);
        });
        $needSequenceIndex = ! Schema::hasIndex('shipment_hub_legs', ['shipment_id', 'sequence'], 'unique');
        Schema::table('shipment_hub_legs', function (Blueprint $t) use ($needSequenceIndex) {
            if ($needSequenceIndex) {
                $t->unique(['shipment_id', 'sequence'], 'shipment_hub_legs_shipment_sequence_unique');
            }
            $t->char('transfer_batch_id', 36)->nullable()->index();
            $t->foreign('transfer_batch_id')->references('id')->on('hub_transfer_batches')->nullOnDelete();
        });
        Schema::create('hub_transfer_members', function (Blueprint $t) {
            $t->id();
            $t->char('batch_id', 36);
            $t->char('leg_id', 36)->nullable();
            $t->char('shipment_id', 36);
            $t->string('tracking_number')->nullable();
            $t->bigInteger('received_by')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->foreign('batch_id')->references('id')->on('hub_transfer_batches')->cascadeOnDelete();
            $t->foreign('leg_id')->references('id')->on('shipment_hub_legs')->nullOnDelete();
            $t->foreign('received_by')->references('id')->on('users')->nullOnDelete();
            $t->unique(['batch_id', 'shipment_id']);
            $t->unique(['batch_id', 'leg_id']);
        });
        Schema::create('hub_transfer_events', function (Blueprint $t) {
            $t->id();
            $t->char('batch_id', 36);
            $t->bigInteger('actor_id')->nullable();
            $t->char('shipment_id', 36)->nullable();
            $t->string('action', 32);
            $t->string('from_status', 32)->nullable();
            $t->string('to_status', 32);
            $t->text('reason')->nullable();
            $t->timestamp('created_at');
            $t->foreign('batch_id')->references('id')->on('hub_transfer_batches')->cascadeOnDelete();
            $t->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $t->index(['batch_id', 'id']);
        });

        // No old leg/status/timestamp is changed. There was no persistent batch identity;
        // import each existing requested/active/completed leg separately, without inventing history.
        DB::transaction(function () {
            DB::table('shipment_hub_legs')->orderBy('id')->chunk(200, function ($legs) {
                foreach ($legs as $leg) {
                    if (! $leg->requested_at && ! $leg->approved_at && ! in_array($leg->status, ['in_transit', 'completed'], true)) {
                        continue;
                    }
                    $s = DB::table('shipments')->where('id', $leg->shipment_id)->first();
                    if (! $s || ! filled($s->logistics_company)) {
                        continue;
                    }
                    $findHub = function ($town, $province) use ($s) {
                        $ids = DB::table('logistics_hubs')->where('company_name', $s->logistics_company)
                            ->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim((string) $town))])
                            ->whereRaw('LOWER(province) = ?', [mb_strtolower(trim((string) $province))])->pluck('id');

                        return $ids->count() === 1 ? $ids->first() : null;
                    };
                    $approver = $leg->approved_by ? DB::table('users')->where('id', $leg->approved_by)->first() : null;
                    $validApproval = $leg->approved_at && $approver && $approver->is_logistics && ! $approver->is_admin
                        && $approver->account_type === 'logistics' && in_array($approver->logistics_role, [null, 'admin'], true) && $approver->business_name === $s->logistics_company;
                    $status = match ($leg->status) {
                        'completed' => 'completed', 'in_transit' => 'in_transit', default => $validApproval ? 'approved' : 'pending'
                    };
                    $id = (string) Str::uuid();
                    DB::table('hub_transfer_batches')->insert([
                        'id' => $id, 'company_name' => $s->logistics_company,
                        'origin_hub_id' => $findHub($leg->from_hub, $leg->from_province), 'destination_hub_id' => $findHub($leg->to_hub, $leg->to_province),
                        'from_hub' => $leg->from_hub, 'from_province' => $leg->from_province, 'to_hub' => $leg->to_hub, 'to_province' => $leg->to_province,
                        'status' => $status, 'requested_by' => $leg->requested_by, 'requested_at' => $leg->requested_at,
                        'approved_by' => $leg->approved_by, 'approved_at' => $leg->approved_at,
                        'rider_id' => $leg->rider_id, 'dispatched_at' => $leg->started_at, 'received_at' => $leg->completed_at,
                        'legacy_import' => true, 'created_at' => $leg->created_at ?? now(), 'updated_at' => now(),
                    ]);
                    DB::table('hub_transfer_members')->insert(['batch_id' => $id, 'leg_id' => $leg->id, 'shipment_id' => $s->id, 'tracking_number' => $s->tracking_number, 'received_at' => $leg->completed_at]);
                    DB::table('shipment_hub_legs')->where('id', $leg->id)->update(['transfer_batch_id' => $id]);
                }
            });
        });
    }

    public function down(): void
    {
        // Preserve operational history; rollback requires an explicit backup/recovery plan.
        throw new RuntimeException('Hub transfer history is retained. Restore a verified backup rather than dropping operational records.');
    }
};
