<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Support\Facades\Storage;

class DeliveryProofController extends Controller
{
    public function show(Shipment $shipment)
    {
        $actor = auth()->user();
        $order = $shipment->order;
        $allowed = $order && ((int) $order->buyer_id === (int) $actor->id || (int) $order->seller_id === (int) $actor->id)
            || (int) $shipment->courier_id === (int) $actor->id
            || ($actor->is_logistics && $actor->business_name === $shipment->logistics_company);
        abort_unless($allowed && $shipment->delivery_proof_file && Storage::disk('sensitive_documents')->exists($shipment->delivery_proof_file), 404);
        return Storage::disk('sensitive_documents')->response($shipment->delivery_proof_file, 'delivery-proof.'.pathinfo($shipment->delivery_proof_file, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
