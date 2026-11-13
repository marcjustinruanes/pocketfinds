<?php

namespace App\Services;

use App\Models\{LogisticsIssue, Shipment, User};
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/** Notifications for existing failed-delivery and return-custody records. */
class LogisticsParcelNotifications
{
    public function failed(Shipment $shipment, string $event): void
    {
        $order = $shipment->order;
        if (! $order) return;
        $return = $shipment->delivery_failed_reason === 'refused' || $shipment->delivery_attempts >= 3;
        $this->send($shipment, $this->operators($shipment)->merge([$order->buyer_id, $order->seller_id]),
            'delivery_failed', $order->id, $event, $return ? 'Return Custody Review Required' : 'Delivery Attempt Failed',
            'Order #'.$order->order_number.' could not be delivered: '.str_replace('_', ' ', $shipment->delivery_failed_reason).'.'.($return ? ' Physical Seller receipt has not been confirmed.' : ' Logistics review is required before another attempt.'));
    }

    public function rts(Shipment $shipment, LogisticsIssue $issue): void
    {
        $state = $issue->rts['state'];
        $users = $this->operators($shipment)->merge([$shipment->order?->seller_id]);
        $rider = User::whereKey($shipment->courier_id)->where('account_type', 'rider')->where('is_admin', false)
            ->where('is_logistics', false)->where('status', 'approved')->where('business_name', $shipment->logistics_company)->value('id');
        $users->push($rider);
        if ($state === 'seller_received') $users->push($shipment->order?->buyer_id);
        $this->send($shipment, $users, 'rts_custody', $shipment->id, $issue->id.':'.$issue->rts['revision'],
            'Return Custody Update', 'Parcel '.$shipment->tracking_number.': '.str_replace('_', ' ', $state).'.');
    }

    private function operators(Shipment $shipment)
    {
        return User::where('business_name', $shipment->logistics_company)->where('account_type', 'logistics')
            ->where('is_logistics', true)->where('is_admin', false)->where('status', 'approved')
            ->where(function ($q) use ($shipment) {
                $q->whereNull('logistics_role')->orWhere('logistics_role', 'admin')
                    ->orWhere(fn ($q) => $q->where('logistics_role', 'hub_staff')->whereHas('logisticsHub', function ($h) use ($shipment) {
                        $h->where('company_name', $shipment->logistics_company)->where('is_operational', true);
                        app(LogisticsAccess::class)->pair($h, 'municipality', 'province', new \App\Models\LogisticsHub([
                            'municipality' => $shipment->destination_hub, 'province' => $shipment->destination_province,
                        ]));
                    }));
            })->pluck('id');
    }

    private function send(Shipment $shipment, $users, string $type, string $reference, string $event, string $title, string $message): void
    {
        foreach (User::whereIn('id', $users->filter()->unique())->where('status', 'approved')->pluck('id') as $id) {
            DB::table('notifications')->insertOrIgnore([
                'id' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', ['parcel', $shipment->id, $type, $event, $id])),
                'user_id' => $id, 'title' => $title, 'message' => $message, 'notification_type' => $type,
                'reference_id' => $reference, 'is_read' => false, 'created_at' => now(),
            ]);
        }
    }

    public function target(User $user, object $notification): string
    {
        abort_unless($user->status === 'approved' && ! $user->is_admin, 403);
        $query = Shipment::query();
        $shipment = ($notification->notification_type === 'delivery_failed'
            ? $query->where('order_id', $notification->reference_id) : $query->whereKey($notification->reference_id))->firstOrFail();
        if ($user->account_type === 'logistics') {
            $actor = app(AssignmentReassignment::class)->actor($user);
            app(LogisticsAccess::class)->requireLocation($actor, $shipment->destination_hub, $shipment->destination_province);
            abort_unless($actor->business_name === $shipment->logistics_company, 404);
            return route('logistics.issues', ['shipment' => $shipment->id]).'#parcel-'.$shipment->id;
        }
        if ($user->account_type === 'seller' && ! $user->is_logistics && (int) $shipment->order?->seller_id === (int) $user->id) {
            return route('seller.orders', ['q' => $shipment->order->order_number]);
        }
        if ($user->account_type === 'buyer' && ! $user->is_logistics && (int) $shipment->order?->buyer_id === (int) $user->id) {
            return route('buyer.orders');
        }
        abort_unless($user->account_type === 'rider' && ! $user->is_logistics && $user->business_name === $shipment->logistics_company
            && (int) $shipment->courier_id === (int) $user->id, 403);
        return route('rider.deliveries.show', $shipment->id);
    }
}
