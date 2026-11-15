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
        if(!$origin->is_operational) return 'The origin hub is not operational.';
        if($rider->business_name!==$origin->company_name || $rider->account_type!=='rider' || $rider->is_admin || $rider->is_logistics) return 'The rider must belong to this logistics company.';
        if($rider->status!=='approved') return 'The rider account is not approved.';
        if((int)$rider->logistics_hub_id!==(int)$origin->id) return 'The rider is assigned to a different hub.';
        if($rider->vehicle_type!=='four_wheels') return 'The rider must use a four-wheel vehicle.';
        if(!in_array($rider->vehicle_ownership,['own','company'],true)) return 'Vehicle ownership is not configured.';
        if(!$rider->is_available) return 'The rider is offline. Go online in the rider app before assignment.';
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
