<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/BuyerOrderSupport.php';

use App\Models\{Order, Shipment};
use Illuminate\Support\Facades\DB;

function assignmentStage(Order $order): array {
    foreach (BuyerOrderSupport::timeline($order) as $stage) {
        if ($stage['label'] === 'Rider Assigned') return $stage;
    }
    throw new RuntimeException('Assignment stage missing.');
}
function checkAssignment(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo 'PASS '.$message.PHP_EOL;
}

// Read-only regression against the shipment reported from the phone.
$shipment = Shipment::where('tracking_number', 'PF-SHIP-261115-PKIITD')->firstOrFail();
$order = Order::findOrFail($shipment->order_id);
$accepted = DB::table('delivery_assignments')->where('shipment_id', $shipment->id)
    ->where('leg', 'pickup')->whereNotNull('accepted_at')->orderBy('accepted_at')->value('accepted_at');
checkAssignment($accepted !== null, 'Reported shipment has confirmed pickup assignment evidence');
$shipment->assigned_at = null;
$order->setRelation('shipment', $shipment);
$stage = assignmentStage($order);
checkAssignment($stage['state'] === 'recorded' && $stage['recorded_at'] === $accepted,
    'Pickup acceptance checks Rider Assigned before any delivery rider assignment');

$empty = new Order;
$empty->id = -1;
$empty->setRelation('shipment', null);
checkAssignment(assignmentStage($empty)['state'] === 'upcoming', 'Unassigned order stays unchecked');

// An unsaved shipment has no accepted assignments; a rider ID alone is not evidence.
$offered = new Shipment;
$offered->id = -1;
$offered->pickup_rider_id = 1;
$empty->setRelation('shipment', $offered);
checkAssignment(assignmentStage($empty)['state'] === 'upcoming', 'Unaccepted rider offer stays unchecked');
