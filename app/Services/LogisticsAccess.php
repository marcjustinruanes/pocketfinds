<?php

namespace App\Services;

use App\Models\CompanyVehicle;
use App\Models\LogisticsHub;
use App\Models\Shipment;
use App\Models\ShipmentHubLeg;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LogisticsAccess
{
    public function receivingStaff(User $user, ?string $municipality, ?string $province): User
    {
        $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->isHubStaff() && $actor->status === 'approved', 403, 'Only assigned Hub Staff may attest physical hub receipt.');
        $hub = LogisticsHub::whereKey($actor->logistics_hub_id)->lockForUpdate()->firstOrFail();
        abort_unless($hub->is_operational && $hub->company_name === $actor->business_name
            && $this->matches($hub, $municipality, $province), 403, 'Only the receiving hub staff may receive these parcels.');
        return $actor;
    }

    public function hub(User $user): LogisticsHub
    {
        $hub = $user->logisticsHub;
        abort_unless($hub && $hub->company_name === $user->business_name && $hub->is_operational, 403, 'No operational hub is assigned to this account.');

        return $hub;
    }

    public function pair(Builder $query, string $name, string $province, LogisticsHub $hub): void
    {
        $query->whereRaw('LOWER('.$name.') = ?', [mb_strtolower(trim($hub->municipality))])
            ->whereRaw('LOWER('.$province.') = ?', [mb_strtolower(trim($hub->province))]);
    }

    public function shipments(User $user): Builder
    {
        abort_unless(filled($user->business_name), 403, 'No logistics company is assigned.');
        $query = Shipment::where('logistics_company', $user->business_name);
        if ($user->isHubStaff()) {
            $hub = $this->hub($user);
            $query->where(function ($q) use ($hub) {
                $q->where(fn ($q) => $this->pair($q, 'origin_hub', 'origin_province', $hub))
                    ->orWhere(fn ($q) => $this->pair($q, 'destination_hub', 'destination_province', $hub))
                    ->orWhereHas('hubLegs', function ($q) use ($hub) {
                        $q->where(fn ($q) => $this->pair($q, 'from_hub', 'from_province', $hub))
                            ->orWhere(fn ($q) => $this->pair($q, 'to_hub', 'to_province', $hub));
                    });
            });
        }

        return $query;
    }

    public function legs(User $user): Builder
    {
        $query = ShipmentHubLeg::forCompany($user->business_name);
        if ($user->isHubStaff()) {
            $hub = $this->hub($user);
            $query->where(function ($q) use ($hub) {
                $q->where(fn ($q) => $this->pair($q, 'from_hub', 'from_province', $hub))
                    ->orWhere(fn ($q) => $this->pair($q, 'to_hub', 'to_province', $hub));
            });
        }

        return $query;
    }

    public function openParcelsForRider(User $rider, string $leg): Builder
    {
        $query = Shipment::where('logistics_company', $rider->business_name);
        $hub = $rider->logisticsHub;
        if ($rider->account_type !== 'rider' || $rider->status !== 'approved' || ! $hub || ! $hub->is_operational || $hub->company_name !== $rider->business_name
            || $rider->vehicle_type !== 'two_wheels' || ! in_array($rider->vehicle_ownership, ['own', 'company'], true)
            || ($rider->vehicle_ownership === 'company' && ! CompanyVehicle::typeAvailableAtHub($hub->id, 'two_wheels'))) {
            return $query->whereRaw('1 = 0');
        }
        $this->pair($query, $leg === 'pickup' ? 'origin_hub' : 'destination_hub', $leg === 'pickup' ? 'origin_province' : 'destination_province', $hub);

        return $leg === 'pickup' ? $query->where('shipping_status', 'ready_for_pickup')->whereNotNull('pickup_approved_at')->whereNull('pickup_rider_id')
            : $query->where('shipping_status', 'sorted')->whereNull('courier_id');
    }

    public function matches(LogisticsHub $hub, ?string $municipality, ?string $province): bool
    {
        return mb_strtolower(trim($hub->municipality)) === mb_strtolower(trim((string) $municipality))
            && mb_strtolower(trim($hub->province)) === mb_strtolower(trim((string) $province));
    }

    public function requireLocation(User $user, ?string $municipality, ?string $province): void
    {
        if ($user->isHubStaff()) {
            abort_unless($this->matches($this->hub($user), $municipality, $province), 403, 'This operation belongs to another hub.');
        }
    }
}
