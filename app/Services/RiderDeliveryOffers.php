<?php

namespace App\Services;

use App\Models\CompanyVehicle;
use App\Models\DeliveryAssignment;
use App\Models\Order;
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
            $shipment = $this->lock($shipment->id);
            if (! $this->open($shipment, $leg)) {
                return null;
            }
            app(AssignmentReassignment::class)->assertNoPending($shipment->id);
            $failureColumn = $leg.'_offer_failures';
            $current = DeliveryAssignment::where('shipment_id', $shipment->id)->where('leg', $leg)->lockForUpdate()->first();
            if ($current && $current->status === 'offered' && $current->expires_at?->isFuture()
                && $this->canAssign($shipment, $leg, (int) $current->courier_id)) {
                return $current;
            }
            if ($current && $current->status === 'offered') {
                $current->update(['status' => 'expired', 'expires_at' => null]);
                $shipment->update([$leg.'_offer_expires_at' => null]);
            }
            if ((int) $shipment->{$failureColumn} >= self::MAX_FAILURES) {
                return null;
            }
            $exclude = array_filter(array_merge($exclude, $current?->courier_id ? [(int) $current->courier_id] : []));
            $rider = $this->eligible($shipment, $leg, $exclude)->get()->first(fn ($candidate) => $this->canAssign($shipment, $leg, $candidate->id));
            if (! $rider) {
                return null;
            }

            return $this->send($shipment, $rider->id, $leg);
        }, 3);
    }

    public function expire(string $shipmentId): void
    {
        foreach (['pickup', 'delivery'] as $leg) {
            DB::transaction(function () use ($shipmentId, $leg) {
                $shipment = $this->lock($shipmentId);
                if (! $shipment || ! $this->open($shipment, $leg)) {
                    return;
                }
                $assignment = DeliveryAssignment::where('shipment_id', $shipmentId)->where('leg', $leg)->lockForUpdate()->first();
                if (! $assignment || $assignment->status !== 'offered' || ! $assignment->expires_at || $assignment->expires_at->isFuture()) {
                    return;
                }
                $assignment->update(['status' => 'expired', 'expires_at' => null]);
                $shipment->update([$leg.'_offer_expires_at' => null]);
                $shipment->increment($leg.'_offer_failures');
                $shipment->refresh();
                if ((int) $shipment->{$leg.'_offer_failures'} < self::MAX_FAILURES) {
                    $this->offer($shipment, $leg, [(int) $assignment->courier_id]);
                }
            }, 3);
        }
    }

    public function decline(User $rider, string $shipmentId, string $leg = 'delivery'): void
    {
        DB::transaction(function () use ($rider, $shipmentId, $leg) {
            $shipment = $this->lock($shipmentId, [$rider->id]);
            $assignment = DeliveryAssignment::where('shipment_id', $shipmentId)->where('leg', $leg)->lockForUpdate()->firstOrFail();
            abort_unless($this->open($shipment, $leg) && $assignment->status === 'offered' && $assignment->expires_at?->isFuture() && (int) $assignment->courier_id === (int) $rider->id, 422, 'This job offer is no longer available.');
            $assignment->update(['status' => 'declined', 'declined_at' => now(), 'expires_at' => null]);
            $shipment->update([$leg.'_offer_expires_at' => null]);
            $shipment->increment($leg.'_offer_failures');
            $shipment->refresh();
            if ((int) $shipment->{$leg.'_offer_failures'} < self::MAX_FAILURES) {
                $this->offer($shipment, $leg, [$rider->id]);
            }
        }, 3);
    }

    public function activeJobs(int $riderId): int
    {
        return Shipment::where(fn ($q) => $q->where('courier_id', $riderId)->whereIn('shipping_status', ['assigned_to_rider', 'out_for_delivery']))
            ->orWhere(fn ($q) => $q->where('pickup_rider_id', $riderId)->whereIn('shipping_status', ['ready_for_pickup', 'picked_up']))->count();
    }

    public function eligible(Shipment $shipment, string $leg, array $exclude = [])
    {
        $this->assertLeg($leg);
        $town = $leg === 'pickup' ? $shipment->origin_hub : $shipment->destination_hub;
        $province = $leg === 'pickup' ? $shipment->origin_province : $shipment->destination_province;

        $query = $this->candidates((string) $shipment->logistics_company)
            ->whereRaw('LOWER(eligible_hub.municipality) = ?', [mb_strtolower(trim((string) $town))])->whereRaw('LOWER(eligible_hub.province) = ?', [mb_strtolower(trim((string) $province))])
            ->whereNotIn('users.id', $exclude);
        $coverage = app(RiderBarangayCoverage::class);
        if ($coverage->installed()) {
            $barangay = $coverage->barangay($shipment, $leg);
            $query->join('rider_barangay_routes as coverage', function ($join) use ($coverage, $barangay) {
                $join->on('coverage.rider_id', '=', 'users.id')->on('coverage.hub_id', '=', 'users.logistics_hub_id')
                    ->where('coverage.barangay_key', '=', $coverage->key($barangay));
            });
            if ($barangay === '') {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    /** One eligibility query for staff choices, automatic offers and acceptance. */
    public function candidates(string $company)
    {
        $active = Shipment::selectRaw('courier_id as rider_id, count(*) as active_jobs')->whereIn('shipping_status', ['assigned_to_rider', 'out_for_delivery'])->whereNotNull('courier_id')->groupBy('courier_id')
            ->unionAll(Shipment::selectRaw('pickup_rider_id as rider_id, count(*) as active_jobs')->whereIn('shipping_status', ['ready_for_pickup', 'picked_up'])->whereNotNull('pickup_rider_id')->groupBy('pickup_rider_id'));
        $loads = DB::query()->fromSub($active, 'job_rows')->selectRaw('rider_id, sum(active_jobs) as active_jobs')->groupBy('rider_id');

        return User::query()->join('logistics_hubs as eligible_hub', 'eligible_hub.id', '=', 'users.logistics_hub_id')
            ->leftJoinSub($loads, 'active', fn ($join) => $join->on('users.id', '=', 'active.rider_id'))
            ->where('users.account_type', 'rider')->where('users.status', 'approved')->where('users.is_available', true)->where('users.vehicle_type', 'two_wheels')
            ->where('users.is_admin', false)->where('users.is_logistics', false)
            ->where('users.business_name', $company)
            ->where('eligible_hub.company_name', $company)->where('eligible_hub.is_operational', true)
            ->where(function ($q) use ($company) {
                $q->where('users.vehicle_ownership', 'own')->orWhere(fn ($q) => $q->where('users.vehicle_ownership', 'company')
                    ->whereIn('users.logistics_hub_id', CompanyVehicle::where('company_name', $company)->where('vehicle_type', 'two_wheels')->riderVisible()->select('logistics_hub_id')));
            })
            ->whereRaw('COALESCE(active.active_jobs, 0) < ?', [self::LIMIT])
            ->orderByRaw('COALESCE(active.active_jobs, 0)')->orderBy('users.id')->select('users.*');
    }

    /** Staff assignment is an offer; only the rider can accept it. */
    public function propose(User $staff, string $shipmentId, int $riderId, string $leg): DeliveryAssignment
    {
        return DB::transaction(function () use ($staff, $shipmentId, $riderId, $leg) {
            $shipment = $this->lock($shipmentId, [$staff->id, $riderId]);
            $actor = User::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->account_type === 'logistics' && ! $actor->is_admin && ($actor->isHubStaff() || $actor->isLogisticsAdmin()) && $actor->status === 'approved', 403);
            abort_unless($actor->business_name === $shipment->logistics_company, 404);
            app(LogisticsAccess::class)->requireLocation($actor, $leg === 'pickup' ? $shipment->origin_hub : $shipment->destination_hub, $leg === 'pickup' ? $shipment->origin_province : $shipment->destination_province);
            $this->assertOpen($shipment, $leg);
            app(AssignmentReassignment::class)->assertNoPending($shipment->id);
            abort_unless($this->canAssign($shipment, $leg, $riderId), 422, 'Choose an approved online rider covering the parcel barangay at its hub, with an available two-wheel vehicle and fewer than three active pickup/delivery jobs.');
            if ($shipment->order) {
                app(GcashWorkflow::class)->assertOrderOperation($shipment->order, $leg === 'pickup');
            }
            $current = DeliveryAssignment::where('shipment_id', $shipment->id)->where('leg', $leg)->lockForUpdate()->first();
            if ($current && $current->status === 'offered' && (int) $current->courier_id === $riderId && $current->expires_at?->isFuture()) {
                return $current;
            }
            $assignment = $this->send($shipment, $riderId, $leg);
            Order::notifyUser($riderId, 'New '.ucfirst($leg).' Offer', 'Hub staff offered you a '.$leg.' job. Accept within 60 seconds to confirm.', 'delivery_assigned', $shipment->order_id);

            return $assignment;
        }, 3);
    }

    public function accept(User $rider, string $shipmentId, string $leg): void
    {
        DB::transaction(function () use ($rider, $shipmentId, $leg) {
            $shipment = $this->lock($shipmentId, [$rider->id]);
            $this->assertOpen($shipment, $leg);
            app(AssignmentReassignment::class)->assertNoPending($shipment->id);
            $assignment = DeliveryAssignment::where('shipment_id', $shipmentId)->where('leg', $leg)->lockForUpdate()->first();
            abort_unless($assignment && $assignment->status === 'offered' && $assignment->expires_at?->isFuture() && (int) $assignment->courier_id === (int) $rider->id, 422, 'This job offer is no longer available.');
            abort_unless($this->canAssign($shipment, $leg, $rider->id), 422, 'This job requires an approved online rider at the parcel hub, an available two-wheel vehicle and fewer than three active pickup/delivery jobs.');
            if ($shipment->order) {
                app(GcashWorkflow::class)->assertOrderOperation($shipment->order, $leg === 'pickup');
            }
            $updates = [$leg === 'pickup' ? 'pickup_rider_id' : 'courier_id' => $rider->id, $leg.'_offer_expires_at' => null];
            if ($leg === 'delivery') {
                $updates += ['shipping_status' => 'assigned_to_rider', 'assigned_at' => now()];
            }
            $shipment->update($updates);
            $assignment->update(['status' => $leg === 'pickup' ? 'accepted' : 'assigned_to_rider', 'accepted_at' => now(), 'expires_at' => null]);
            if ($leg === 'delivery' && $shipment->order) {
                $shipment->order->update(['status' => 'assigned_to_rider']);
                $shipment->order->logStatus('assigned_to_rider', $rider->id);
                Order::notifyUser($shipment->order->buyer_id, 'Rider Assigned', 'A courier has accepted delivery of order #'.$shipment->order->order_number.'.', 'order_status', $shipment->order_id);
            }
            if ($leg === 'pickup') {
                Order::notifyUser($shipment->order?->seller_id, 'Pickup Rider Assigned', 'A rider accepted the pickup for order #'.$shipment->order?->order_number.'.', 'order_status', $shipment->order_id);
            }
        }, 3);
    }

    /** Use the existing operational lock order, serializing company rider capacity checks.
     * Lock all company riders before parcels so retries/offer rotation keep that order too.
     */
    public function lockForRetry(string $shipmentId, int $actorId): Shipment
    {
        return $this->lock($shipmentId, [$actorId]);
    }

    /** Reassignment competes for the same ordinary pickup/delivery capacity. */
    public function lockForReassignment(string $shipmentId, array $actors): Shipment
    {
        return $this->lock($shipmentId, $actors);
    }

    public function assertRetryEligible(Shipment $shipment): void
    {
        abort_unless($shipment->shipping_status === 'delivery_failed', 422, 'Only failed deliveries can be retried.');
        abort_if($this->retryExhausted($shipment), 422, 'Return custody review is required for refused or exhausted deliveries.');
        app(AssignmentReassignment::class)->assertNoPending($shipment->id);
        abort_unless($shipment->courier_id && $this->canAssign($shipment, 'delivery', (int) $shipment->courier_id), 422,
            'The assigned rider must be online, approved, cover the destination barangay and have hub, vehicle and capacity eligibility.');
    }

    public function retryExhausted(Shipment $shipment): bool
    {
        return $shipment->delivery_failed_reason === 'refused' || $shipment->delivery_attempts >= 3;
    }

    /** Record one actual failure under the caller's existing operational transaction/locks.
     * Keep rider custody and compensation unchanged until the existing receipt workflows.
     */
    public function recordFailure(Shipment $shipment, User $actor, string $reason, string $attemptToken): bool
    {
        $workflow = app(AssignmentReassignment::class);
        if ($actor->account_type === 'rider') {
            $actor = $workflow->approvedRider($actor, $shipment);
            abort_unless((int) $shipment->courier_id === (int) $actor->id, 403);
            $states = ['out_for_delivery'];
        } else {
            $actor = $workflow->actor($actor);
            abort_unless($actor->business_name === $shipment->logistics_company, 403);
            app(LogisticsAccess::class)->requireLocation($actor, $shipment->destination_hub, $shipment->destination_province);
            $states = ['assigned_to_rider', 'out_for_delivery'];
        }
        abort_unless(in_array($shipment->shipping_status, $states, true)
            && hash_equals($shipment->failedDeliveryToken(), $attemptToken), 409, 'This delivery attempt changed. Refresh before reporting a failure.');
        abort_unless($shipment->order_id && $shipment->courier_id, 409, 'Current order and rider evidence is required. Review this parcel before recording failure.');
        $order = Order::lockFresh($shipment->order_id);
        abort_unless($order->status === $shipment->shipping_status && ! $shipment->delivered_at, 409, 'This delivery attempt is no longer open.');
        $workflow->assertNoPending($shipment->id, null, true);
        $assignment = DeliveryAssignment::where('shipment_id', $shipment->id)->where('leg', 'delivery')
            ->where('courier_id', $shipment->courier_id)->lockForUpdate()->first();
        abort_unless($shipment->courier_id && $assignment && $assignment->status === $shipment->shipping_status
            && ! $assignment->delivered_at, 409, 'A current delivery assignment is required.');
        $shipment->update(['shipping_status' => 'delivery_failed', 'delivery_failed_at' => now(),
            'delivery_failed_reason' => $reason, 'delivery_attempts' => (int) $shipment->delivery_attempts + 1]);
        $order->update(['status' => 'delivery_failed']);
        $shipment->setRelation('order', $order);
        $event = (string) \Illuminate\Support\Str::uuid();
        DB::table('order_status_history')->insert(['id' => $event, 'order_id' => $order->id,
            'status' => 'delivery_failed', 'changed_by' => $actor->id, 'notes' => $reason, 'created_at' => now()]);
        app(LogisticsParcelNotifications::class)->failed($shipment, $event);

        return $this->retryExhausted($shipment);
    }

    private function lock(string $shipmentId, array $actors = []): Shipment
    {
        $peek = Shipment::findOrFail($shipmentId);
        $riders = User::where('business_name', $peek->logistics_company)->where('account_type', 'rider')->pluck('id')->all();
        $vehicles = CompanyVehicle::where('company_name', $peek->logistics_company)->where('vehicle_type', 'two_wheels')->pluck('id')->all();
        GcashLocks::operation([$shipmentId], [], array_unique(array_merge($riders, $actors)), [], $vehicles);

        return Shipment::whereKey($shipmentId)->lockForUpdate()->firstOrFail();
    }

    /** A locking read sees committed capacity after waiting for the rider lock, including
     * when MySQL's transaction snapshot was established before another acceptance.
     */
    private function currentCapacity(int $riderId): int
    {
        return Shipment::where(fn ($q) => $q->where('courier_id', $riderId)->whereIn('shipping_status', ['assigned_to_rider', 'out_for_delivery']))
            ->orWhere(fn ($q) => $q->where('pickup_rider_id', $riderId)->whereIn('shipping_status', ['ready_for_pickup', 'picked_up']))
            ->orderBy('id')->lockForUpdate()->get(['id'])->count();
    }

    /** Call after the shared operational lock, inside the assignment transaction. */
    public function canAssign(Shipment $shipment, string $leg, int $riderId): bool
    {
        $rider = $this->eligible($shipment, $leg)->where('users.id', $riderId)->lockForUpdate()->first();
        if (! $rider || $this->currentCapacity($riderId) >= self::LIMIT) {
            return false;
        }

        // Re-read vehicle availability under a lock rather than a previous MySQL snapshot.
        return $rider->vehicle_ownership === 'own' || CompanyVehicle::where('company_name', $shipment->logistics_company)
            ->where('logistics_hub_id', $rider->logistics_hub_id)->where('vehicle_type', 'two_wheels')->riderVisible()->lockForUpdate()->first(['id']) !== null;
    }

    private function send(Shipment $shipment, int $riderId, string $leg): DeliveryAssignment
    {
        $assignment = DeliveryAssignment::updateOrCreate(['shipment_id' => $shipment->id, 'leg' => $leg], [
            'courier_id' => $riderId, 'status' => 'offered', 'requested_at' => now(), 'accepted_at' => null,
            'expires_at' => now()->addSeconds(self::OFFER_SECONDS), 'declined_at' => null,
        ]);
        $shipment->update([$leg.'_offer_expires_at' => $assignment->expires_at]);

        return $assignment;
    }

    private function assertLeg(string $leg): void
    {
        abort_unless(in_array($leg, ['pickup', 'delivery'], true), 422, 'Invalid assignment leg.');
    }

    private function assertOpen(Shipment $shipment, string $leg): void
    {
        abort_unless($this->open($shipment, $leg), 422, 'This job is no longer available for an offer.');
    }

    private function open(Shipment $shipment, string $leg): bool
    {
        $this->assertLeg($leg);

        return $leg === 'pickup' ? $shipment->shipping_status === 'ready_for_pickup' && $shipment->pickup_approved_at && ! $shipment->pickup_rider_id : $shipment->shipping_status === 'sorted' && ! $shipment->courier_id;
    }
}
