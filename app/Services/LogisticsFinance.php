<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\HubTransferBatch;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Financial foundation. Does not infer cash receipt or payment from parcel status. */
class LogisticsFinance
{
    public function installed(): bool
    {
        return Schema::hasTable('logistics_finance_settings') && Schema::hasTable('logistics_compensations') && Schema::hasTable('logistics_financial_events');
    }

    public function admin(User $actor): User
    {
        $actor = $actor->fresh();
        abort_unless($actor && $actor->status === 'approved' && $actor->isLogisticsAdmin()
            && $actor->account_type === 'logistics' && $actor->is_logistics && ! $actor->is_admin && filled($actor->business_name), 403);

        return $actor;
    }

    public function cents(string $amount): int
    {
        if (! preg_match('/^(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?$/D', $amount, $parts)) {
            throw ValidationException::withMessages(['amount' => 'Enter a non-negative PHP amount with no more than two decimal places.']);
        }

        return ((int) $parts[1]) * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public function settings(string $company): object
    {
        abort_unless(filled($company), 422, 'A logistics company is required.');
        $created = DB::table('logistics_finance_settings')->insertOrIgnore([
            'company_name' => $company, 'pickup_cents' => 2500, 'transfer_cents' => 15000,
            'delivery_cents' => 4000, 'confirmation_hours' => 168, 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($created) {
            $this->event($company, 'rates_initialized', 'settings', $company,
                ['pickup_cents' => 2500, 'transfer_cents' => 15000, 'delivery_cents' => 4000, 'confirmation_hours' => 168], auth()->id());
        }

        return DB::table('logistics_finance_settings')->where('company_name', $company)->first();
    }

    public function event(string $company, string $event, string $type, string $id, array $details, ?int $actor = null): void
    {
        DB::table('logistics_financial_events')->insert([
            'company_name' => $company, 'actor_id' => $actor ?? auth()->id(), 'event' => $event,
            'subject_type' => $type, 'subject_id' => $id, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    public function configure(User $actor, array $data): void
    {
        $actor = $this->admin($actor);
        $values = [];
        foreach (['pickup', 'transfer', 'delivery'] as $leg) {
            $values[$leg.'_cents'] = $this->cents((string) $data[$leg]);
        }
        // The initial approved timeout is fixed at seven days; do not add a new policy range here.
        DB::transaction(function () use ($actor, $data, $values) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->settings($actor->business_name);
            $before = DB::table('logistics_finance_settings')->where('company_name', $actor->business_name)->lockForUpdate()->first();
            abort_unless((int) $data['revision'] === (int) $before->revision, 409, 'Settings changed. Refresh before saving.');
            $values += ['revision' => $before->revision + 1, 'updated_by' => $actor->id, 'updated_at' => now()];
            DB::table('logistics_finance_settings')->where('company_name', $actor->business_name)->update($values);
            $this->event($actor->business_name, 'rates_updated', 'settings', $actor->business_name, [
                'before' => ['pickup_cents' => $before->pickup_cents, 'transfer_cents' => $before->transfer_cents, 'delivery_cents' => $before->delivery_cents, 'revision' => $before->revision],
                'after' => $values,
            ], $actor->id);
        });
    }

    public function snapshotAssignment(DeliveryAssignment $assignment): void
    {
        if (! $this->installed() || ! in_array($assignment->leg, ['pickup', 'delivery'], true)
            || ! in_array($assignment->status, ['accepted', 'assigned_to_rider'], true)) {
            return;
        }
        $shipment = Shipment::find($assignment->shipment_id);
        if (! $shipment || ! $shipment->logistics_company) {
            return;
        }
        $this->snapshot('assignment', $assignment->id, $shipment->logistics_company, (int) $assignment->courier_id, $assignment->leg);
    }

    public function snapshotTransfer(HubTransferBatch $batch): void
    {
        if ($this->installed() && $batch->rider_id && ! $batch->legacy_import) {
            $this->snapshot('transfer_batch', $batch->id, $batch->company_name, (int) $batch->rider_id, 'transfer');
        }
    }

    private function snapshot(string $type, string $source, string $company, int $riderId, string $leg): void
    {
        DB::transaction(function () use ($type, $source, $company, $riderId, $leg) {
            // Serialize on the authoritative source, not a caller-supplied company or rate.
            $table = $type === 'transfer_batch' ? 'hub_transfer_batches' : 'delivery_assignments';
            $current = DB::table($table)->where('id', $source)->lockForUpdate()->firstOrFail();
            if ($type === 'transfer_batch') {
                abort_unless((int) $current->rider_id === $riderId && $current->company_name === $company
                    && $current->status === 'in_transit' && ! $current->legacy_import, 409, 'Transfer assignment changed. Refresh before snapshotting.');
            } else {
                $shipment = Shipment::findOrFail($current->shipment_id);
                abort_unless((int) $current->courier_id === $riderId && $current->leg === $leg && $shipment->logistics_company === $company
                    && in_array($current->status, ['accepted', 'assigned_to_rider'], true), 409, 'Delivery assignment changed. Refresh before snapshotting.');
            }
            $rider = User::whereKey($riderId)->lockForUpdate()->firstOrFail();
            abort_unless($rider->account_type === 'rider' && $rider->business_name === $company && $rider->status === 'approved', 422, 'Compensation requires an approved rider in the assigned company.');
            $settings = $this->settings($company);
            $settings = DB::table('logistics_finance_settings')->where('company_name', $company)->lockForUpdate()->first();
            $rows = DB::table('logistics_compensations')->where('source_type', $type)->where('source_id', $source)->lockForUpdate()->get();
            // An assignment already earned must never become a second entitlement on reassignment.
            abort_if($rows->contains(fn ($row) => $row->rider_id != $riderId && $row->status !== 'pending' && $row->status !== 'cancelled'), 422, 'An earned assignment cannot be reassigned financially.');
            foreach ($rows->where('status', 'pending')->where('rider_id', '!=', $riderId) as $old) {
                DB::table('logistics_compensations')->where('id', $old->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                $this->event($company, 'assignment_reassigned', 'compensation', $old->id, ['status' => 'cancelled']);
            }
            $existing = $rows->firstWhere('rider_id', $riderId);
            if ($existing) {
                if ($existing->status === 'cancelled') {
                    DB::table('logistics_compensations')->where('id', $existing->id)->update(['status' => 'pending', 'updated_at' => now()]);
                    $this->event($company, 'assignment_restored', 'compensation', $existing->id, ['amount_cents' => $existing->amount_cents]);
                }
                return;
            }
            $id = (string) Str::uuid();
            DB::table('logistics_compensations')->insert([
                'id' => $id, 'company_name' => $company, 'source_type' => $type, 'source_id' => $source,
                'rider_id' => $riderId, 'hub_id' => $rider->logistics_hub_id, 'leg' => $leg,
                'amount_cents' => $settings->{$leg.'_cents'}, 'settings_revision' => $settings->revision,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($company, 'compensation_snapshotted', 'compensation', $id, [
                'source_type' => $type, 'source_id' => $source, 'rider_id' => $riderId,
                'amount_cents' => $settings->{$leg.'_cents'}, 'settings_revision' => $settings->revision,
            ]);
        });
    }
}
