<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        DB::table('orders')
            ->where('status', 'to_ship')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('notifications')
                    ->whereColumn('notifications.reference_id', 'orders.id')
                    ->where('notifications.notification_type', 'new_order');
            })
            ->orderBy('created_at')
            ->get()
            ->each(function ($order) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $order->seller_id,
                    'title' => 'New Order',
                    'message' => 'Order ' . $order->order_number . ' is waiting for your review. Total: ₱' . number_format((float) $order->total, 2) . '.',
                    'notification_type' => 'new_order',
                    'reference_id' => $order->id,
                    'is_read' => false,
                    'created_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Historical notifications are intentionally retained on rollback.
    }
};
