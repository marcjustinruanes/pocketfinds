import '../utils/formatters.dart';

String buyerStatusLabel(String status) => switch (status) {
  'pending_payment' => 'Pending Payment',
  'pending_payment_verification' => 'Awaiting Payment Verification',
  'payment_confirmed' => 'Payment Confirmed',
  'placed' => 'Order Placed',
  'confirmed' => 'To Process',
  'preparing' => 'Preparing Order',
  'ready_for_pickup' => 'Ready for Pickup',
  'picked_up' => 'Picked Up',
  'at_sorting_center' => 'At Sorting Center',
  'hub_transfer' => 'Transfer Between Hubs',
  'sorted' => 'Sorted for Delivery',
  'assigned_to_rider' => 'Assigned to Delivery Rider',
  _ => titleCase(status.replaceAll('_', ' ')),
};

/// One real row from `public.order_status_history` — a timestamped event
/// in an order's actual lifecycle (placed, picked up, out for delivery,
/// delivered, delivery failed, returned, completed), not a derived/
/// synthetic stage. See `OrderService.fetchOrderStatusHistory`.
class OrderStatusEvent {
  final String status;
  final String? notes;
  final DateTime createdAt;

  const OrderStatusEvent({
    required this.status,
    this.notes,
    required this.createdAt,
  });

  String get label => buyerStatusLabel(status);

  factory OrderStatusEvent.fromRow(Map<String, dynamic> row) {
    return OrderStatusEvent(
      status: row['status']?.toString() ?? '',
      notes: row['notes']?.toString(),
      createdAt:
          DateTime.tryParse(row['created_at']?.toString() ?? '') ??
          DateTime.now(),
    );
  }
}
