<?php

namespace App\Services;

use App\Models\{HubTransferBatch, User};
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class HubTransferNotifications
{
    public const TYPES = ['hub_transfer_requested', 'hub_transfer_approved', 'hub_transfer_rejected',
        'hub_transfer_cancelled', 'hub_transfer_pickup_confirmed', 'hub_transfer_parcel_received', 'hub_transfer_completed'];

    public function receipt(HubTransferBatch $batch, ?string $shipmentId = null): void
    {
        $tracking = $shipmentId ? $batch->members()->where('shipment_id', $shipmentId)->value('tracking_number') : null;
        $type = $shipmentId ? 'hub_transfer_parcel_received' : 'hub_transfer_completed';
        $users = User::where('business_name', $batch->company_name)->where('account_type', 'logistics')
            ->where('is_logistics', true)->where('is_admin', false)->where('status', 'approved')
            ->where(function ($query) use ($batch) {
                $query->whereNull('logistics_role')->orWhere('logistics_role', 'admin')
                    ->orWhere(fn ($q) => $q->where('logistics_role', 'hub_staff')
                        ->whereIn('logistics_hub_id', [$batch->origin_hub_id, $batch->destination_hub_id])
                        ->whereHas('logisticsHub', fn ($h) => $h->where('company_name', $batch->company_name)->where('is_operational', true)));
            })->pluck('id');
        foreach ($users as $userId) {
            DB::table('notifications')->insertOrIgnore([
                'id' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', ['hub-transfer', $batch->id, $type, $shipmentId ?? 'batch', $userId])),
                'user_id' => $userId, 'title' => $shipmentId ? 'Transfer Parcel Received' : 'Hub Transfer Completed',
                'message' => $shipmentId ? 'Destination Hub Staff confirmed physical receipt of parcel '.($tracking ?: $shipmentId).'.' : 'Destination Hub Staff confirmed receipt of all parcels in this batch.',
                'notification_type' => $type, 'reference_id' => $batch->id, 'is_read' => false, 'created_at' => now(),
            ]);
        }
    }

    public function target(User $user, object $notification): HubTransferBatch
    {
        $scope = app(HubTransferWorkflow::class)->scope($user);
        $batch = (clone $scope)->find($notification->reference_id);
        // Old pickup alerts referenced a shipment. Resolve its batch as of the alert time.
        if (! $batch && $notification->notification_type === 'hub_transfer_pickup_confirmed') {
            $pickup = fn ($q) => $q->where('action', 'pickup_confirmed')->where('shipment_id', $notification->reference_id)
                ->where('created_at', '<=', $notification->created_at);
            $candidates = $scope->whereHas('members', fn ($q) => $q->where('shipment_id', $notification->reference_id))
                ->whereHas('events', $pickup)->withMax(['events as alert_pickup_at' => $pickup], 'created_at')
                ->whereNotNull('dispatched_at')->where('dispatched_at', '<=', $notification->created_at)
                ->orderByDesc('alert_pickup_at')->limit(2)->get();
            // Second-resolution legacy timestamps can be ambiguous; do not guess a batch.
            if ($candidates->count() === 1 || ($candidates->count() === 2 && $candidates[0]->alert_pickup_at !== $candidates[1]->alert_pickup_at)) {
                $batch = $candidates->first();
            }
        }
        abort_unless($batch, 404);

        return $batch;
    }
}
