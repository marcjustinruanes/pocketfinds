<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Carbon;

class OrderTimeline
{
    public static function forOrder(Order $order, iterable $history): array
    {
        $groups = [
            ['Order placed', ['placed']],
            ['Seller confirmed', ['confirmed']],
            ['Preparation & pickup', ['preparing', 'ready_for_pickup']],
            ['In transit', Order::BUYER_IN_TRANSIT_STATUSES],
            ['Out for delivery', ['out_for_delivery']],
            ['Delivered', ['delivered', 'completed']],
        ];
        $explanations = [
            'placed' => 'Awaiting seller confirmation. Your order has not shipped yet.',
            'confirmed' => 'The seller confirmed your order. Preparation is next.',
            'preparing' => 'The seller is preparing your items for shipment.',
            'ready_for_pickup' => 'Ready for courier pickup. Pickup has not been confirmed yet.',
            'picked_up' => 'The courier recorded pickup from the seller.',
            'at_sorting_center' => 'Your parcel is at a sorting center.',
            'hub_transfer' => 'Your parcel is being transferred between hubs.',
            'sorted' => 'Sorting is recorded. Awaiting the next delivery update.',
            'assigned_to_rider' => 'A rider is assigned. Awaiting an out-for-delivery update.',
            'out_for_delivery' => 'The courier marked your parcel out for delivery.',
            'delivered' => 'Delivery is recorded. Confirm receipt only after receiving your items.',
            'completed' => 'This order is completed.',
            'delivery_failed' => 'Delivery was unsuccessful. Contact the seller for the next step.',
            'returned' => 'This order was returned. Contact the seller about payment reconciliation if needed.',
            'cancelled' => 'This order was cancelled. No further delivery is scheduled for this order.',
        ];
        $events = collect($history);
        $stopped = in_array($order->status, ['cancelled', 'returned', 'delivery_failed'], true);
        $current = null;
        foreach ($groups as $index => [, $statuses]) {
            if (in_array($order->status, $statuses, true)) {
                $current = $index;
            }
        }
        $rows = [];
        foreach ($groups as $index => [$title, $statuses]) {
            $record = $events->filter(fn ($event) => in_array($event->status, $statuses, true))->last();
            if ($index === 5 && $record?->status === 'completed') {
                $title = 'Order completed';
            }
            $timestamp = $record?->created_at;
            if ($index === 0 && ! $timestamp) {
                $timestamp = $order->created_at;
            }
            if ($index === 5 && ! $timestamp) {
                $timestamp = $order->shipment?->delivered_at;
            }
            $observed = (bool) $timestamp || (bool) $record;
            $state = match (true) {
                $index === $current && $order->status !== 'completed' => 'current',
                $observed || ($index === $current && $order->status === 'completed') => 'completed',
                $stopped => 'unavailable',
                $current !== null && $index < $current => 'unrecorded',
                $current !== null && $index === $current + 1 => 'pending',
                default => 'future',
            };
            $rows[] = [
                'title' => $title, 'state' => $state,
                'label' => match ($state) {
                    'current' => 'Current', 'completed' => 'Recorded', 'pending' => 'Next',
                    'unrecorded' => 'No separate update', 'unavailable' => 'Not reached', default => 'Upcoming',
                },
                'timestamp' => $timestamp ? Carbon::parse($timestamp) : null,
            ];
        }

        return ['rows' => $rows, 'stopped' => $stopped, 'explanation' => $explanations[$order->status] ?? 'Awaiting a recorded order update.'];
    }
}
