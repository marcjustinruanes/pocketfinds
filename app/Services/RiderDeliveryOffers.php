<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** A single expiring offer is issued per pickup or delivery job. */
class RiderDeliveryOffers
{
    public const LIMIT = 3;
    public const OFFER_SECONDS = 60;
    public const MAX_FAILURES = 3;

    public function expireOutstanding(): void
    {
        DeliveryAssignment::whereIn('leg', ['pickup', 'delivery'])->where('status', 'offered')->where('expires_at', '<=', now())
            ->pluck('shipment_id')->unique()->each(fn ($id) => $this->expire((string) $id));
    }

    public function offer(Shipment $shipment, string $leg = 'delivery', array $exclude = []): ?DeliveryAssignment
    {
        return DB::transaction(function () use ($shipment, $leg, $exclude) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($shipment, $leg);
            $failureColumn = $leg.'_offer_failures';
            if ((int) $shipment->{$failureColumn} >= self::MAX_FAILURES) return null;
            $current = DeliveryAssignment::where('shipment_id', $shipment->id)->where('leg', $leg)->lockForUpdate()->first();
            if ($current && $current->status === 'offered' && $current->expires_at?->isFuture()) return $current;
            $exclude = array_filter(array_merge($exclude, $current?->courier_id ? [(int) $current->courier_id] : []));
            $rider = $this->eligible($shipment, $leg, $exclude)->first();
            if (! $rider) return null;
            $assignment = DeliveryAssignment::updateOrCreate(['shipment_id' => $shipment->id, 'leg' => $leg], [
                'courier_id' => $rider->id, 'status' => 'offered', 'requested_at' => now(), 'accepted_at' => null,
                'expires_at' => now()->addSeconds(self::OFFER_SECONDS), 'declined_at' => null,
            ]);
            $shipment->update([$leg.'_offer_expires_at' => $assignment->expires_at]);
            return $assignment;
        }, 3);
    }

    public function expire(string $shipmentId): void
    {
        foreach (['pickup', 'delivery'] as $leg) {
            DB::transaction(function () use ($shipmentId, $leg) {
                $shipment = Shipment::whereKey($shipmentId)->lockForUpdate()->first();
                if (! $shipment || ! $this->open($shipment, $leg)) return;
                $assignment = DeliveryAssignment::where('shipment_id', $shipmentId)->where('leg', $leg)->lockForUpdate()->first();
                if (! $assignment || $assignment->status !== 'offered' || $assignment->expires_at?->isFuture()) return;
                $assignment->update(['status' => 'expired']);
                $shipment->increment($leg.'_offer_failures'); $shipment->refresh();
                if ((int) $shipment->{$leg.'_offer_failures'} < self::MAX_FAILURES) $this->offer($shipment, $leg, [(int) $assignment->courier_id]);
            }, 3);
        }
    }

    public function decline(User $rider, string $shipmentId, string $leg = 'delivery'): void
    {
        DB::transaction(function () use ($rider, $shipmentId, $leg) {
            $shipment = Shipment::whereKey($shipmentId)->lockForUpdate()->firstOrFail();
            $assignment = DeliveryAssignment::where('shipment_id', $shipmentId)->where('leg', $leg)->lockForUpdate()->firstOrFail();
            abort_unless($this->open($shipment, $leg) && $assignment->status === 'offered' && (int) $assignment->courier_id === (int) $rider->id, 422, 'This job offer is no longer available.');
            $assignment->update(['status' => 'declined', 'declined_at' => now()]);
            $shipment->increment($leg.'_offer_failures'); $shipment->refresh();
            if ((int) $shipment->{$leg.'_offer_failures'} < self::MAX_FAILURES) $this->offer($shipment, $leg, [$rider->id]);
        }, 3);
    }

    public function activeJobs(int $riderId): int
    {
        return Shipment::where(fn ($q) => $q->where('courier_id', $riderId)->whereIn('shipping_status', ['assigned_to_rider', 'out_for_delivery']))
            ->orWhere(fn ($q) => $q->where('pickup_rider_id', $riderId)->whereIn('shipping_status', ['ready_for_pickup', 'picked_up']))->count();
    }

    public function eligible(Shipment $shipment, string $leg, array $exclude = [])
    {
        $location = $leg === 'pickup' ? $shipment->origin_hub : $shipment->destination_hub;
        $active = Shipment::selectRaw('courier_id as rider_id, count(*) as active_jobs')->whereIn('shipping_status', ['assigned_to_rider', 'out_for_delivery'])->whereNotNull('courier_id')->groupBy('courier_id')
            ->unionAll(Shipment::selectRaw('pickup_rider_id as rider_id, count(*) as active_jobs')->whereIn('shipping_status', ['ready_for_pickup', 'picked_up'])->whereNotNull('pickup_rider_id')->groupBy('pickup_rider_id'));
        $loads = DB::query()->fromSub($active, 'job_rows')->selectRaw('rider_id, sum(active_jobs) as active_jobs')->groupBy('rider_id');
        return User::query()->leftJoinSub($loads, 'active', fn ($join) => $join->on('users.id', '=', 'active.rider_id'))
            ->where('users.account_type', 'rider')->where('users.status', 'approved')->where('users.is_available', true)->where('users.vehicle_type', 'two_wheels')
            ->where('users.business_name', $shipment->logistics_company)->whereNotIn('users.id', $exclude)
            ->whereRaw('COALESCE(active.active_jobs, 0) < ?', [self::LIMIT])
            ->orderByRaw('CASE WHEN users.municipality = ? THEN 0 ELSE 1 END', [$location])->orderByRaw('COALESCE(active.active_jobs, 0)')->orderBy('users.id')->select('users.*');
    }

    private function assertOpen(Shipment $shipment, string $leg): void { abort_unless($this->open($shipment, $leg), 422, 'This job is no longer available for an offer.'); }
    private function open(Shipment $shipment, string $leg): bool { return $leg === 'pickup' ? $shipment->shipping_status === 'ready_for_pickup' && $shipment->pickup_approved_at && ! $shipment->pickup_rider_id : $shipment->shipping_status === 'sorted' && ! $shipment->courier_id; }
}
