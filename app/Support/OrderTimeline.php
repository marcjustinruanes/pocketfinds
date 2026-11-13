<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Carbon;

class OrderTimeline
{
    private const LABELS = [
        'placed' => 'Order placed',
        'payment_confirmed' => 'Payment confirmed',
        'confirmed' => 'Seller confirmed',
        'preparing' => 'Preparing',
        'ready_for_pickup' => 'Ready for pickup',
        'pickup_approved' => 'Pickup approved',
        'picked_up' => 'Picked up',
        'at_sorting_center' => 'At sorting center',
        'hub_transfer' => 'Hub transfer',
        'sorted' => 'Sorted',
        'assigned_to_rider' => 'Assigned to rider',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Order delivered',
        'completed' => 'Order completed',
        'delivery_failed' => 'Delivery failed',
        'returned' => 'Returned',
        'cancelled' => 'Cancelled',
    ];

    private const ICONS = [
        'placed' => 'bag', 'payment_confirmed' => 'clipboard-check', 'confirmed' => 'clipboard-check',
        'preparing' => 'package', 'ready_for_pickup' => 'package', 'pickup_approved' => 'clipboard-check',
        'picked_up' => 'map', 'at_sorting_center' => 'map', 'hub_transfer' => 'rider-delivery',
        'sorted' => 'package', 'assigned_to_rider' => 'rider-delivery', 'out_for_delivery' => 'rider-delivery',
        'delivered' => 'package-received', 'completed' => 'shield', 'delivery_failed' => 'flag',
        'returned' => 'package', 'cancelled' => 'ban',
    ];

    /** Only events backed by order history or a recorded payment/shipment timestamp are shown. */
    public static function forOrder(Order $order, iterable $history, mixed $paymentConfirmedAt = null): array
    {
        $events = collect($history)
            ->filter(fn ($event) => isset(self::LABELS[$event->status]));

        $shipment = $order->shipment;
        $recordTimestamp = function (string $status, mixed $timestamp) use (&$events): void {
            if (! $timestamp) return;
            $existingIndex = $events->search(fn ($event) => $event->status === $status);
            if ($existingIndex !== false) {
                if (! $events[$existingIndex]->created_at) $events[$existingIndex]->created_at = $timestamp;
                return;
            }
            $events->push((object) ['status' => $status, 'created_at' => $timestamp]);
        };

        $recordTimestamp('placed', $order->created_at);
        $recordTimestamp('payment_confirmed', $paymentConfirmedAt);
        $recordTimestamp('pickup_approved', $shipment?->pickup_approved_at);

        $shipmentTimes = [
            'picked_up' => $shipment?->picked_up_at,
            'at_sorting_center' => $shipment?->at_sorting_center_at,
            'sorted' => $shipment?->sorted_at,
            'assigned_to_rider' => $shipment?->assigned_at,
            'out_for_delivery' => $shipment?->out_for_delivery_at,
            'delivered' => $shipment?->delivered_at,
            'delivery_failed' => $shipment?->delivery_failed_at,
            'returned' => $shipment?->returned_at,
            'hub_transfer' => $shipment?->hub_transfer_started_at,
        ];

        foreach ($shipmentTimes as $status => $timestamp) {
            $recordTimestamp($status, $timestamp);
        }

        // The current order status is authoritative for older records that predate
        // complete status history. Add only that real current state, never future steps.
        if (isset(self::LABELS[$order->status]) && ! $events->contains(fn ($event) => $event->status === $order->status)) {
            $events->push((object) ['status' => $order->status, 'created_at' => null]);
        }

        $events = $events->values()->sort(function ($left, $right) use ($order) {
            $leftAt = $left->created_at ? Carbon::parse($left->created_at)->getTimestamp() : PHP_INT_MAX;
            $rightAt = $right->created_at ? Carbon::parse($right->created_at)->getTimestamp() : PHP_INT_MAX;
            if ($leftAt !== $rightAt) return $leftAt <=> $rightAt;
            if ($left->status === 'payment_confirmed' && $right->status === 'placed') return -1;
            if ($right->status === 'payment_confirmed' && $left->status === 'placed') return 1;
            return strcmp((string) $left->status, (string) $right->status);
        })->values();

        $latestCurrentIndex = $events->filter(fn ($event) => $event->status === $order->status)->keys()->last();
        $rows = $events->values()->map(function ($event, $index) use ($latestCurrentIndex) {
            $timestamp = $event->created_at ? Carbon::parse($event->created_at) : null;
            $state = $index === $latestCurrentIndex ? 'current' : 'completed';

            return [
                'status' => $event->status,
                'title' => self::LABELS[$event->status],
                'icon' => self::ICONS[$event->status] ?? 'package',
                'state' => $state,
                'label' => $state === 'current' ? 'Current' : 'Recorded',
                'timestamp' => $timestamp,
            ];
        })->all();

        return [
            'rows' => $rows,
            'stopped' => in_array($order->status, ['cancelled', 'returned', 'delivery_failed'], true),
            'explanation' => self::LABELS[$order->status] ?? 'Awaiting a recorded order update.',
        ];
    }
}
