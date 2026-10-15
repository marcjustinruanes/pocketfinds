<?php

namespace App\Services;

use App\Models\CompanyVehicle;
use App\Models\GcashPurchase;
use App\Models\LogisticsHub;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GcashLocks
{
    // Unified order: products, operational hubs, users, purchases, orders,
    // vehicles, batches, members, shipments, legs, then mutation-specific rows. Call inside a transaction.
    public static function resources(array $items, array $users = []): mixed
    {
        $ids = array_unique(array_column($items, 'product_id'));
        $products = $ids ? Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();
        if ($users) {
            User::whereIn('id', array_unique($users))->orderBy('id')->lockForUpdate()->get();
        }

        return $products;
    }

    public static function purchase(string $id): array
    {
        $original = GcashPurchase::findOrFail($id);
        $products = self::resources($original->snapshot['items'], [$original->buyer_id, $original->seller_id]);

        return [GcashPurchase::whereKey($id)->lockForUpdate()->firstOrFail(), $products];
    }

    public static function order(string $id): Order
    {
        $original = Order::findOrFail($id);
        if ($original->gcash_purchase_id) {
            self::purchase($original->gcash_purchase_id);
        } else {
            self::resources($original->items ?? []);
        }

        return Order::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public static function shipment(string $id): void
    {
        self::operation([$id], [], auth()->id() ? [auth()->id()] : []);
    }

    /** Full operational footprint. Must run before actor/batch/shipment locks. */
    public static function operation(array $shipmentIds = [], array $batchIds = [], array $users = [], array $hubs = [], array $vehicles = []): array
    {
        $batchTable = Schema::hasTable('hub_transfer_batches');
        $legTable = Schema::hasTable('shipment_hub_legs');
        $seedUsers = $users;
        $seedVehicles = $vehicles;
        if ($batchTable) {
            $conflicts = DB::table('hub_transfer_batches')->where('status', 'in_transit')->where(function ($q) use ($seedUsers, $seedVehicles) {
                $q->whereIn('rider_id', $seedUsers)->orWhereIn('vehicle_id', $seedVehicles);
            })->pluck('id')->all();
            $batchIds = array_merge($batchIds, $conflicts);
        }
        do {
            $before = [count(array_unique($shipmentIds)), count(array_unique($batchIds))];
            if ($legTable && $batchTable && $shipmentIds) {
                $batchIds = array_merge($batchIds, DB::table('shipment_hub_legs')->whereIn('shipment_id', $shipmentIds)->where('status', '!=', 'completed')->whereNotNull('transfer_batch_id')->pluck('transfer_batch_id')->all());
            }
            $batchIds = array_values(array_unique($batchIds));
            if ($batchTable && $batchIds) {
                $shipmentIds = array_merge($shipmentIds, DB::table('hub_transfer_members')->whereIn('batch_id', $batchIds)->pluck('shipment_id')->all());
            }
            $shipmentIds = array_values(array_unique($shipmentIds));
        } while ($before !== [count($shipmentIds), count($batchIds)]);

        $peekShipments = Shipment::whereIn('id', $shipmentIds)->get()->keyBy('id');
        foreach ($peekShipments as $shipment) {
            $users = array_merge($users, array_filter([$shipment->courier_id, $shipment->pickup_rider_id, $shipment->hub_transfer_rider_id]));
        }
        $orderIds = $peekShipments->pluck('order_id')->filter()->unique()->all();
        $peekOrders = Order::whereIn('id', $orderIds)->get()->keyBy('id');
        $purchaseIds = $peekOrders->pluck('gcash_purchase_id')->filter()->unique()->all();
        $purchases = GcashPurchase::query();
        // Legacy deployments/fixtures need no GCash query when no purchase is linked.
        $peekPurchases = $purchaseIds ? $purchases->whereIn('id', $purchaseIds)->get() : collect();
        $items = $peekOrders->flatMap(fn ($order) => $order->items ?? [])->all();
        foreach ($peekPurchases as $purchase) {
            $items = array_merge($items, $purchase->snapshot['items']);
            $users = array_merge($users, [$purchase->buyer_id, $purchase->seller_id]);
        }
        $peekBatches = $batchTable ? DB::table('hub_transfer_batches')->whereIn('id', $batchIds)->get()->keyBy('id') : collect();
        foreach ($peekBatches as $batch) {
            $users = array_merge($users, array_filter([$batch->rider_id]));
            $hubs = array_merge($hubs, [$batch->origin_hub_id, $batch->destination_hub_id]);
            $vehicles = array_merge($vehicles, array_filter([$batch->vehicle_id]));
        }
        if ($legTable && $shipmentIds) {
            $peekLegs = DB::table('shipment_hub_legs')->whereIn('shipment_id', $shipmentIds)->get();
            foreach ($peekLegs as $leg) {
                $users = array_merge($users, array_filter([$leg->rider_id ?? null, $leg->requested_by ?? null, $leg->approved_by ?? null]));
                $company = $peekShipments->get($leg->shipment_id)?->logistics_company;
                foreach ([[$leg->from_hub, $leg->from_province ?? null], [$leg->to_hub, $leg->to_province ?? null]] as [$town, $province]) {
                    if ($company && $town && $province) {
                        $hubs = array_merge($hubs, LogisticsHub::where('company_name', $company)->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($town))])->whereRaw('LOWER(province) = ?', [mb_strtolower(trim($province))])->pluck('id')->all());
                    }
                }
            }
        }
        $peekUsers = User::whereIn('id', array_unique($users))->get()->keyBy('id');
        $hubs = array_merge($hubs, $peekUsers->pluck('logistics_hub_id')->filter()->all());
        $peekHubs = $hubs ? LogisticsHub::whereIn('id', array_unique($hubs))->get()->keyBy('id') : collect();
        self::resources($items);
        // HubHiring already uses hub-before-personnel locking; keep that shared order.
        $lockedHubs = $hubs ? LogisticsHub::whereIn('id', array_unique($hubs))->orderBy('id')->lockForUpdate()->get() : collect();
        abort_unless($lockedHubs->count() === $peekHubs->count(), 409, 'Hub footprint changed. Retry the whole operation.');
        foreach ($lockedHubs as $hub) {
            $old = $peekHubs[$hub->id];
            abort_unless([$hub->company_name, $hub->municipality, $hub->province, $hub->is_operational] === [$old->company_name, $old->municipality, $old->province, $old->is_operational], 409, 'Hub eligibility changed. Retry the whole operation.');
        }
        $lockedUsers = User::whereIn('id', array_unique($users))->orderBy('id')->lockForUpdate()->get();
        foreach ($lockedUsers as $user) {
            abort_unless($user->logistics_hub_id === $peekUsers[$user->id]->logistics_hub_id, 409, 'Personnel hub changed. Retry the whole operation.');
        }
        if ($purchaseIds) {
            GcashPurchase::whereIn('id', $purchaseIds)->orderBy('id')->lockForUpdate()->get();
        }
        $orders = Order::whereIn('id', $orderIds)->orderBy('id')->lockForUpdate()->get();
        foreach ($orders as $order) {
            abort_unless($order->gcash_purchase_id === $peekOrders[$order->id]->gcash_purchase_id && $order->items === $peekOrders[$order->id]->items, 409, 'Commerce footprint changed. Retry the whole operation.');
        }
        if ($vehicles) {
            CompanyVehicle::whereIn('id', array_unique($vehicles))->orderBy('id')->lockForUpdate()->get();
        }
        if ($batchTable) {
            // This current read catches newly committed conflict batches after the user lock wait.
            $batches = DB::table('hub_transfer_batches')->where(function ($q) use ($batchIds, $seedUsers, $seedVehicles) {
                $q->whereIn('id', $batchIds)->orWhere(fn ($r) => $r->where('status', 'in_transit')->where(fn ($x) => $x->whereIn('rider_id', $seedUsers)->orWhereIn('vehicle_id', $seedVehicles)));
            })->orderBy('id')->lockForUpdate()->get();
            foreach ($batches as $batch) {
                $old = $peekBatches->get($batch->id);
                abort_unless($old && [$batch->origin_hub_id, $batch->destination_hub_id, $batch->rider_id, $batch->vehicle_id] === [$old->origin_hub_id, $old->destination_hub_id, $old->rider_id, $old->vehicle_id], 409, 'Batch footprint changed. Retry the whole operation.');
            }
            $members = DB::table('hub_transfer_members')->whereIn('batch_id', $batchIds)->orderBy('id')->lockForUpdate()->get();
            abort_unless(array_diff($members->pluck('shipment_id')->all(), $shipmentIds) === [], 409, 'Batch membership changed. Retry the whole operation.');
        }
        $shipments = Shipment::whereIn('id', $shipmentIds)->orderBy('id')->lockForUpdate()->get();
        foreach ($shipments as $shipment) {
            abort_unless($shipment->order_id === $peekShipments[$shipment->id]->order_id, 409, 'Shipment ownership changed. Retry the whole operation.');
        }
        if ($legTable) {
            $legs = DB::table('shipment_hub_legs')->whereIn('shipment_id', $shipmentIds)->orderBy('id')->lockForUpdate()->get();
            if ($batchTable) {
                $active = $legs->filter(fn ($leg) => $leg->status !== 'completed' && $leg->transfer_batch_id)->pluck('transfer_batch_id')->all();
                abort_unless(array_diff($active, $batchIds) === [], 409, 'Transfer membership changed. Retry the whole operation.');
            }
        }

        return ['shipments' => $shipmentIds, 'batches' => $batchIds, 'vehicles' => array_values(array_unique($vehicles))];
    }

    public static function rejection(string $product): void
    {
        $ids = DB::table('gcash_stock_allocations')->where('product_id', $product)->whereIn('state', ['held', 'paid', 'transferred'])->pluck('purchase_id')->unique()->sort()->values()->all();
        $purchases = GcashPurchase::whereIn('id', $ids)->get();
        $items = [['product_id' => $product]];
        $users = [];
        foreach ($purchases as $p) {
            $items = array_merge($items, $p->snapshot['items']);
            $users = array_merge($users, [$p->buyer_id, $p->seller_id]);
        }
        self::resources($items, $users);
        GcashPurchase::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        // A reservation committed while the footprint was read: retry from a fresh footprint.
        $current = DB::table('gcash_stock_allocations')->where('product_id', $product)->whereIn('state', ['held', 'paid', 'transferred'])->lockForUpdate()->get()->pluck('purchase_id')->unique()->sort()->values()->all();
        abort_unless(array_diff($current, $ids) === [], 409, 'Allocations changed while reviewing this listing. Refresh and retry.');
    }
}
