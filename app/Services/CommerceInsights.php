<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommerceInsights
{
    public function snapshot(int $days = 30): array
    {
        $days = in_array($days, [1, 7, 30, 90], true) ? $days : 30;
        $start = now()->subDays($days - 1)->startOfDay();
        $orders = Order::whereBetween('created_at', [$start, now()]);
        $completed = (clone $orders)->whereIn('status', ['delivered', 'completed']);
        $daily = (clone $completed)->selectRaw('DATE(created_at) AS day, SUM(total) AS value, COUNT(*) AS orders')->groupBy('day')->get()->keyBy('day');
        $series = collect(range(0, $days - 1))->map(function ($offset) use ($start, $daily) {
            $day = $start->copy()->addDays($offset)->toDateString();
            return ['date' => $day, 'order_value' => round((float) ($daily[$day]->value ?? 0), 2), 'orders' => (int) ($daily[$day]->orders ?? 0)];
        })->all();
        $previous = Order::whereIn('status', ['delivered', 'completed'])->whereBetween('created_at', [$start->copy()->subDays($days), $start->copy()->subMicrosecond()])->sum('total');
        $current = (float) (clone $completed)->sum('total');

        // Historical item prices, not today's catalog prices, determine product sales.
        $sales = []; $historyStart = now()->subDays(90)->startOfDay();
        $hasItems = Schema::hasColumn('orders', 'items');
        if ($hasItems) {
            Order::whereIn('status', ['delivered', 'completed'])->whereBetween('created_at', [$historyStart, now()])
                ->select(['id', 'items', 'created_at'])->chunkById(200, function ($batch) use (&$sales, $start) {
                    foreach ($batch as $order) foreach ($order->items ?? [] as $item) {
                        if (!is_array($item) || empty($item['product_id'])) continue;
                        $id = (string) $item['product_id']; $qty = max(0, (int) ($item['qty'] ?? 0));
                        $sales[$id] ??= ['units_90d' => 0, 'orders_90d' => [], 'units_period' => 0, 'item_value_period' => 0];
                        $sales[$id]['units_90d'] += $qty;
                        $sales[$id]['orders_90d'][$order->id] = true;
                        if ($order->created_at >= $start) {
                            $sales[$id]['units_period'] += $qty;
                            $sales[$id]['item_value_period'] += $qty * max(0, (float) ($item['price'] ?? 0));
                        }
                    }
                });
        }
        $firstSale = Order::whereIn('status', ['delivered', 'completed'])->whereBetween('created_at', [$historyStart, now()])->min('created_at');
        $observed = $firstSale ? min(90, max(1, (int) \Carbon\Carbon::parse($firstSale)->startOfDay()->diffInDays(now()->startOfDay()) + 1)) : 0;
        $historyCount = Order::whereIn('status', ['delivered', 'completed'])->whereBetween('created_at', [$historyStart, now()])->count();
        $historyValue = (float) Order::whereIn('status', ['delivered', 'completed'])->whereBetween('created_at', [$historyStart, now()])->sum('total');
        $forecast = ['label' => 'Estimate, not a guarantee', 'method' => 'Historical daily average over up to 90 days; no seasonality adjustment.', 'observed_days' => $observed,
            'next_7_days_order_value' => $observed >= 28 && $historyCount >= 20 ? round($historyValue / $observed * 7, 2) : null,
            'minimum_evidence' => '28 observed days and 20 delivered/completed orders. Missing estimates mean insufficient evidence.'];
        $ranking = []; $inventory = []; $quality = []; $alerts = [];
        Product::where('status', 'active')->with('category:id,name')->chunkById(200, function ($batch) use (&$ranking, &$inventory, &$quality, &$alerts, $sales, $observed, $hasItems) {
            foreach ($batch as $product) {
                $sold = $sales[$product->id] ?? ['units_90d' => 0, 'orders_90d' => [], 'units_period' => 0, 'item_value_period' => 0];
                $stock = $product->total_stock;
                $base = ['id' => $product->id, 'name' => Str::limit(strip_tags($product->name), 100)];
                $ranking[] = $base + ['units' => $sold['units_period'], 'item_value_before_order_discounts' => round($sold['item_value_period'], 2)];
                $productDays = $product->created_at ? min($observed, max(1, (int) $product->created_at->startOfDay()->diffInDays(now()->startOfDay()) + 1)) : 0;
                $enough = $productDays >= 28 && count($sold['orders_90d']) >= 5 && $sold['units_90d'] > 0;
                $velocity = $enough ? $sold['units_90d'] / $productDays : null;
                $cover = $velocity ? round(max(0, $stock) / $velocity, 1) : null;
                $flag = $stock < 0 ? 'stock_discrepancy' : ($stock <= 5 ? 'low_stock' : ($cover !== null && $cover > 90 ? 'potential_overstock' : ($cover !== null && $cover <= 14 ? 'restock_soon' : null)));
                if ($flag) {
                    $row = $base + ['flag' => $flag, 'stock' => $stock, 'estimated_days_remaining' => $cover,
                        'estimated_reorder_units_for_30_days' => $velocity ? max(0, (int) ceil($velocity * 30 - $stock)) : null];
                    $inventory[] = $row;
                    $alerts[] = ['type' => $flag, 'title' => $base['name'], 'evidence' => 'Recorded stock: '.$stock.($cover !== null ? '; estimated cover: '.$cover.' days.' : '; insufficient sales history for restock estimates.'),
                        'action' => $flag === 'stock_discrepancy' ? 'Reconcile physical stock and records.' : 'Review stock and supplier lead times before reordering.', 'mode' => 'inventory'];
                }
                $missing = [];
                if (!filled($product->description)) $missing[] = 'description';
                if (!$product->category_id) $missing[] = 'category';
                if ((float) $product->price <= 0) $missing[] = 'positive price';
                if ($product->discount_price !== null && ((float) $product->discount_price <= 0 || (float) $product->discount_price >= (float) $product->price)) $missing[] = 'valid discount price';
                if ($missing) $quality[] = $base + ['checks' => $missing];
                if ($hasItems && $observed >= 28 && $product->created_at && $product->created_at <= now()->subDays(28) && $sold['units_90d'] === 0) {
                    $quality[] = $base + ['checks' => ['No delivered/completed units in observed history. Check visibility, demand and description; not proof of poor conversion.']];
                }
            }
        });
        usort($ranking, fn ($a, $b) => $b['units'] <=> $a['units']);
        $patterns = [];
        $hasBuyers = Schema::hasColumn('orders', 'buyer_id');
        if ($hasBuyers) {
            $baskets = (clone $completed)->selectRaw('buyer_id, COUNT(*) AS purchases')->groupBy('buyer_id')->get();
            $patterns = ['purchasing_customers' => $baskets->count(), 'repeat_purchasing_customers' => $baskets->where('purchases', '>', 1)->count(),
                'average_completed_order_value' => $baskets->sum('purchases') ? round($current / $baskets->sum('purchases'), 2) : null];
            // Explainable heuristics; IDs are only opaque review references, not identities sent to AI.
            $bursts = (clone $orders)->selectRaw('buyer_id, DATE(created_at) AS day, COUNT(*) AS attempts')->groupBy('buyer_id', 'day')->havingRaw('COUNT(*) >= 5')->limit(20)->get();
            foreach ($bursts as $row) $alerts[] = ['type' => 'order_burst', 'title' => 'Account '.substr(hash_hmac('sha256', (string) $row->buyer_id, (string) config('app.key')), 0, 10),
                'evidence' => $row->attempts.' orders placed on '.$row->day.'. Could be legitimate shopping.', 'action' => 'Review order timing and fulfillment; do not block automatically.', 'mode' => 'anomalies',
                'order_ids' => (clone $orders)->where('buyer_id', $row->buyer_id)->whereDate('created_at', $row->day)->orderBy('created_at')->limit(3)->pluck('id')->all()];
            $cancellations = (clone $orders)->where('status', 'cancelled')->selectRaw('buyer_id, COUNT(*) AS cancellations')->groupBy('buyer_id')->havingRaw('COUNT(*) >= 3')->limit(10)->get();
            foreach ($cancellations as $row) $alerts[] = ['type' => 'repeated_cancellations', 'title' => 'Account '.substr(hash_hmac('sha256', (string) $row->buyer_id, (string) config('app.key')), 0, 10),
                'evidence' => $row->cancellations.' cancelled orders in the selected period. Cancellation may be legitimate.', 'action' => 'Review cancellation reasons and offer support; not proof of fraud.', 'mode' => 'anomalies',
                'order_ids' => (clone $orders)->where('buyer_id', $row->buyer_id)->where('status', 'cancelled')->orderBy('created_at')->limit(3)->pluck('id')->all()];
        }
        $medianValues = (clone $completed)->orderBy('total')->pluck('total')->map(fn ($value) => (float) $value);
        $median = $medianValues->median();
        if ($medianValues->count() >= 20 && $median > 0) {
            foreach ((clone $orders)->where('total', '>', $median * 5)->limit(10)->get(['id', 'total']) as $order) $alerts[] = ['type' => 'unusual_order_value',
                'title' => 'Order '.$order->id, 'evidence' => 'Value PHP '.number_format((float) $order->total, 2).' exceeds five times the completed-order median.',
                'action' => 'Verify order details manually. High value is not proof of fraud.', 'mode' => 'anomalies', 'order_ids' => [$order->id]];
        }
        $movements = []; $hasMovements = Schema::hasTable('inventory_movements');
        if ($hasMovements) {
            $recent = DB::table('inventory_movements')->whereBetween('created_at', [$start, now()])->orderByDesc('id')->limit(50)->get();
            $names = Product::whereIn('id', $recent->pluck('product_id'))->pluck('name', 'id');
            $currentStock = Product::whereIn('id', $recent->pluck('product_id'))->get()->keyBy('id');
            $checked = [];
            foreach ($recent as $change) {
                $name = Str::limit(strip_tags($names[$change->product_id] ?? 'Removed product'), 100);
                $movements[] = ['product' => $name, 'before' => $change->stock_before, 'after' => $change->stock_after, 'delta' => $change->delta,
                    'reason' => $change->reason, 'recorded_at' => $change->created_at];
                $mismatch = $change->delta != $change->requested_delta;
                $large = abs($change->delta) >= max(20, abs($change->stock_before) * .5);
                $discrepancy = !isset($checked[$change->product_id]) && isset($currentStock[$change->product_id]) && $currentStock[$change->product_id]->total_stock !== (int) $change->stock_after;
                $checked[$change->product_id] = true;
                if ($mismatch || $large || $discrepancy) $alerts[] = ['type' => 'inventory_change', 'title' => $name,
                    'evidence' => 'Recorded change '.$change->stock_before.' to '.$change->stock_after.' units on '.$change->created_at.'.'.($mismatch ? ' Applied quantity differed from requested quantity.' : '').($discrepancy ? ' Current stock differs from the latest logged balance.' : ''),
                    'action' => 'Reconcile with restocks, cancellations and manual edits. Large changes can be legitimate.', 'mode' => 'inventory'];
            }
        }
        return ['daily_sales' => $series, 'previous_period_order_value' => (float) $previous,
            'order_value_change_percent' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null,
            'product_sales' => array_slice(array_filter($ranking, fn ($row) => $row['units'] > 0), 0, 10),
            'customer_behavior' => $patterns, 'forecast' => $forecast, 'inventory_recommendations' => array_slice($inventory, 0, 20),
            'listing_quality_checks' => array_slice($quality, 0, 12), 'alerts' => array_slice($alerts, 0, 30),
            'alert_count' => count($alerts), 'data_limits' => ['Order value is not profit; refunds are not deducted. Item values exclude shipping and order-level discounts.',
                'Stock cover/reorder estimates require 28 observed days and 5 completed orders for that product. Supplier lead times are unknown.',
                'No payment-attempt, login-activity or product-impression logs exist. Failed-payment detection, abnormal-login detection and conversion rates are unavailable.',
                $hasMovements ? 'Stock history covers future application stock operations only, not older imports or all manual listing edits. Latest 50 movements are sampled; discrepancies need verification.' : 'Stock-movement tracking is unavailable until the inventory_movements migration is applied.',
                'Flags are deterministic review warnings, not evidence of fraud. No accounts, products or orders are automatically changed.'],
            'item_history_available' => $hasItems, 'inventory_movement_tracking' => $hasMovements, 'recent_inventory_movements' => $movements,
            'timezone' => config('app.timezone')];
    }
}
