<?php

namespace App\Services;

use App\Models\GcashPurchase;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CancellationRefund
{
    /** Called inside the cancellation transaction, after locking the order. */
    public function initiate(Order $order): void
    {
        if (!$order->gcash_purchase_id || !Schema::hasTable('cancellation_refunds')) return;
        $payment = GcashPurchase::whereKey($order->gcash_purchase_id)->lockForUpdate()->first();
        if (!$payment?->verified_at || (float)$payment->verified_amount <= 0 || (float)$order->total <= 0) return;
        $created = DB::table('cancellation_refunds')->insertOrIgnore([
            'order_id'=>$order->id, 'buyer_id'=>$order->buyer_id, 'seller_id'=>$order->seller_id,
            'amount'=>$order->total, 'method'=>'gcash', 'status'=>'pending', 'payment_reference'=>$payment->payment_reference,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        if (!$created) return;
        foreach ([$order->buyer_id, $order->seller_id] as $userId) {
            Order::notifyUser($userId, 'Cancellation refund pending', 'Order '.$order->order_number.' was cancelled. A refund of ₱'.number_format($order->total,2).' is pending repayment to the original GCash payment account.', 'order_status', $order->id);
        }
        foreach (User::where('is_admin', true)->where('status','approved')->pluck('id') as $adminId) {
            Order::notifyUser($adminId, 'Cancellation refund pending', 'A prepaid order was cancelled. '.($payment->usesAdminReview()?'The platform admin':'The seller').' must repay ₱'.number_format($order->total,2).' for '.$order->order_number.'. Reason: '.$order->cancellation_reason.'.'.($order->cancellation_note ? ' Seller note: '.$order->cancellation_note : ''), $payment->usesAdminReview()?'gcash_purchase':'order_status', $payment->usesAdminReview()?$payment->id:$order->id);
        }
    }
}
