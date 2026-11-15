import '../models/shipment.dart';

/// What a task card/tag should show — purely a display classification now
/// (no longer a stored field; see [taskTypeForShipment]). Used by
/// [TaskTypeTag]/[TaskCard] in `lib/widgets/`.
enum TaskType { pickup, delivery, pickupAndDeliver }

/// Derives the redesign's task-type tag from a real [Shipment]'s status.
/// There's no stored task-type column — every real shipment always needs
/// both legs from the same rider (confirmed: pickup/delivery are never
/// split across different riders), so the tag is just which phase is
/// currently active:
/// - not yet claimed (`available`) → advertise the whole job
/// - claimed but not yet picked up → PICK UP FROM SELLER
/// - picked up or later → DELIVER TO CUSTOMER
TaskType taskTypeForShipment(Shipment shipment) {
  switch (shipment.shippingStatus) {
    case 'sorted':
      return TaskType.pickupAndDeliver;
    case 'assigned_to_rider':
      return TaskType.pickup;
    default: // picked_up, out_for_delivery, delivered, completed
      return TaskType.delivery;
  }
}
