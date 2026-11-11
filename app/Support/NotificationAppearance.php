<?php

namespace App\Support;

class NotificationAppearance
{
    public static function forType(?string $type): array
    {
        return match ($type) {
            'order_status', 'order_delivered', 'new_order', 'delivery_assigned', 'pickup_assigned', 'pickup_request', 'shipment_unassigned' => ['package', 'is-order'],
            'gcash_purchase', 'commission', 'commission_payment', 'commission_recovery', 'shipping_payment', 'cash_handover', 'refund' => ['wallet', 'is-order'],
            'delivery_otp' => ['shield', 'is-order'],
            'delivery_failed', 'doc_rejected', 'product_rejected', 'dispute' => ['x', 'is-danger'],
            'doc_approved', 'product_approved' => ['check', 'is-success'],
            'review_reply', 'message' => ['chat', 'is-message'],
            'registration', 'product_pending' => ['shield', 'is-announcement'],
            default => ['bell', 'is-announcement'],
        };
    }
}
