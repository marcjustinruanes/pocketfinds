<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DeliveryProofController extends Controller
{
    public function show(Shipment $shipment)
    {
        $actor = User::findOrFail(auth()->id());
        // Recheck current approval even when the session predates a suspension.
        if ($actor->is_logistics) {
            abort_unless($actor->status === 'approved' && $actor->account_type === 'logistics'
                && ! $actor->is_admin && ($actor->isLogisticsAdmin() || $actor->isHubStaff()), 404);
            if ($actor->isHubStaff()) {
                $hub = $actor->logisticsHub;
                abort_unless($hub && $hub->is_operational && $hub->company_name === $actor->business_name, 404);
                abort_unless(app(\App\Services\LogisticsAccess::class)->shipments($actor)->whereKey($shipment->id)->exists(), 404);
            }
        }
        $order = $shipment->order;
        $allowed = $order && ((int) $order->buyer_id === (int) $actor->id || (int) $order->seller_id === (int) $actor->id)
            || (int) $shipment->courier_id === (int) $actor->id
            || ($actor->is_logistics && filled($actor->business_name) && $actor->business_name === $shipment->logistics_company);
        abort_unless($allowed && $shipment->delivery_proof_file && Storage::disk('sensitive_documents')->exists($shipment->delivery_proof_file), 404);
        return Storage::disk('sensitive_documents')->response($shipment->delivery_proof_file, 'delivery-proof.'.pathinfo($shipment->delivery_proof_file, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
