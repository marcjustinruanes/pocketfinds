import 'buyer_order.dart';

/// The rider-facing subset of the existing order/delivery workflow —
/// combines a `shipments` row with its `orders` row (items/total/shipping
/// address), the same two tables Laravel's `LogisticsController` already
/// uses. Buyer contact info is fetched separately (only once assigned) via
/// `get_delivery_contact_info()`.
class Shipment {
  final String id;
  final String orderId;
  final String shippingStatus; // available, accepted, picked_up, out_for_delivery, delivered, completed, ...
  final String? courierId;
  final DateTime? pickedUpAt;
  final DateTime? outForDeliveryAt;
  final DateTime? deliveredAt;
  final DateTime createdAt;

  final String orderNumber;
  final List<OrderLineItem> items;
  final double total;
  final Map<String, dynamic> shippingAddress;

  const Shipment({
    required this.id,
    required this.orderId,
    required this.shippingStatus,
    required this.orderNumber,
    required this.items,
    required this.total,
    required this.shippingAddress,
    required this.createdAt,
    this.courierId,
    this.pickedUpAt,
    this.outForDeliveryAt,
    this.deliveredAt,
  });

  String get deliverTo => [
    shippingAddress['barangay'],
    shippingAddress['municipality'],
    shippingAddress['province'],
  ].where((v) => v != null && v.toString().isNotEmpty).join(', ');

  String get statusLabel => shippingStatus.replaceAll('_', ' ');

  /// The rider-permitted next status, or null if this shipment is at a
  /// terminal state for the rider (delivered/completed/cancelled/failed).
  String? get nextStatus => switch (shippingStatus) {
    'accepted' => 'picked_up',
    'picked_up' => 'out_for_delivery',
    'out_for_delivery' => 'delivered',
    _ => null,
  };

  factory Shipment.fromRow(Map<String, dynamic> shipmentRow, Map<String, dynamic> orderRow) {
    final rawItems = orderRow['items'];
    final items = rawItems is List
        ? rawItems
              .whereType<Map>()
              .map((i) => OrderLineItem.fromMap(Map<String, dynamic>.from(i)))
              .toList()
        : <OrderLineItem>[];

    final rawAddress = orderRow['shipping_address'];
    final address = rawAddress is Map
        ? Map<String, dynamic>.from(rawAddress)
        : <String, dynamic>{};

    return Shipment(
      id: shipmentRow['id'] as String,
      orderId: shipmentRow['order_id'] as String,
      shippingStatus: shipmentRow['shipping_status']?.toString() ?? 'available',
      courierId: shipmentRow['courier_id'] as String?,
      pickedUpAt: DateTime.tryParse(shipmentRow['picked_up_at']?.toString() ?? ''),
      outForDeliveryAt: DateTime.tryParse(shipmentRow['out_for_delivery_at']?.toString() ?? ''),
      deliveredAt: DateTime.tryParse(shipmentRow['delivered_at']?.toString() ?? ''),
      createdAt: DateTime.tryParse(shipmentRow['created_at']?.toString() ?? '') ?? DateTime.now(),
      orderNumber: orderRow['order_number']?.toString() ?? '',
      items: items,
      total: double.tryParse('${orderRow['total']}') ?? 0,
      shippingAddress: address,
    );
  }
}
