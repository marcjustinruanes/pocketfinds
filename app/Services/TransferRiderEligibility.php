<?php

namespace App\Services;

use App\Models\{CompanyVehicle, HubTransferBatch, LogisticsHub, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Transfer riders retain exclusive-work rules, independent of local delivery capacity. */
class TransferRiderEligibility
{
    public function problem(User $rider, LogisticsHub $origin): ?string
    {
        if (! $origin->is_operational || $rider->business_name !== $origin->company_name
            || $rider->account_type !== 'rider' || $rider->is_admin || $rider->is_logistics
            || $rider->status !== 'approved' || ! $rider->is_available
            || (int) $rider->logistics_hub_id !== (int) $origin->id
            || $rider->vehicle_type !== 'four_wheels' || ! in_array($rider->vehicle_ownership, ['own', 'company'], true)) {
            return 'Choose an online, approved four-wheel transfer rider assigned to the origin hub.';
        }
        if (HubTransferBatch::where('rider_id', $rider->id)->where('status', 'in_transit')->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->exists()
            || app(AssignmentWork::class)->blocksRider($rider->id)) {
            return 'The rider has active or ambiguous work requiring review.';
        }
        if ($rider->vehicle_ownership === 'company' && ! CompanyVehicle::where('company_name', $origin->company_name)
            ->where('logistics_hub_id', $origin->id)->where('vehicle_type', 'four_wheels')->riderVisible()->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->exists()) {
            return 'No approved available four-wheel company vehicle is available at the origin hub.';
        }

        return null;
    }

    public function available(LogisticsHub $origin): Collection
    {
        return User::where('business_name', $origin->company_name)->where('logistics_hub_id', $origin->id)
            ->where('account_type', 'rider')->where('status', 'approved')->where('is_available', true)
            ->where('vehicle_type', 'four_wheels')->orderBy('username')->get()
            ->filter(fn ($rider) => $this->problem($rider, $origin) === null)->values();
    }
}
