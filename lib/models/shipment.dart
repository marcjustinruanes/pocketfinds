import '../utils/formatters.dart';
import 'buyer_order.dart';

/// The rider-facing subset of the existing order/delivery workflow —
/// combines a `shipments` row with its `orders` row (items/total/shipping
/// address), the same two tables Laravel's `LogisticsController` already
/// uses. Buyer contact info is fetched separately (only once assigned) via
/// `get_delivery_contact_info()`.
class Shipment {
  final String id;
  final int attemptCount;
  final String? trackingNumber;
  final String orderId;
  final String shippingStatus; // available, accepted, picked_up, out_for_delivery, delivered, completed, ...
  final int? courierId; // legacy public.users.id — NOT auth.uid()
  final DateTime? pickedUpAt;
  final DateTime? outForDeliveryAt;
  final DateTime? deliveredAt;
  final DateTime createdAt;

  final String orderNumber;
  final List<OrderLineItem> items;
  final double total;
  final Map<String, dynamic> shippingAddress;

  /// The real delivery charge on the order. Rider earnings and payout amounts
  /// come separately from the backend job ledger, not from this charge.
  final double deliveryFee;

  /// The buyer's `public.users.id` for this shipment's order — lets
  /// Delivery Detail open a real conversation with them without a second
  /// round trip, since `orders` is already joined in here.
  final int? buyerId;

  /// "{seller's municipality}, {province}" — resolved separately (see
  /// `ShipmentService._joinOrders`) since `products`/`users` aren't
  /// reachable from a plain `shipments`/`orders` join. Null until resolved
  /// or if the seller has no location on file.
  final String? pickupLocation;
  final String? sellerName;

  const Shipment({
    this.attemptCount = 0,
    this.trackingNumber,
    required this.id,
    required this.orderId,
    required this.shippingStatus,
    required this.orderNumber,
    required this.items,
    required this.total,
    required this.shippingAddress,
    required this.createdAt,
    this.deliveryFee = 0,
    this.buyerId,
    this.courierId,
    this.pickedUpAt,
    this.outForDeliveryAt,
    this.deliveredAt,
    this.pickupLocation,
    this.sellerName,
  });

  String get deliverTo => [
    shippingAddress['barangay'],
    shippingAddress['municipality'],
    shippingAddress['province'],
  ].where((v) => v != null && v.toString().isNotEmpty).join(', ');

  String get statusLabel => titleCase(shippingStatus.replaceAll('_', ' '));

  /// The rider's primary forward action from here, or null if this
  /// shipment is at a terminal state for the rider (delivered/completed/
  /// cancelled/returned). From `delivery_failed` this is "retry" (going
  /// back to `out_for_delivery`) — the spec's "Reschedule Delivery" branch;
  /// see [canReturnToSeller] for the other branch ("Return Parcel").
  String? get nextStatus => switch (shippingStatus) {
    'assigned_to_rider' => 'out_for_delivery',
    'picked_up' => 'out_for_delivery',
    'rescheduled' => 'out_for_delivery',
    'out_for_delivery' => 'delivered',
    _ => null,
  };

  /// Only true while actually out for delivery — the rider can report a
  /// failed attempt (with a required reason) instead of marking delivered.
  bool get canReportFailure => shippingStatus == 'out_for_delivery';

  /// Only true once a delivery attempt has already failed — the other
  /// branch besides retrying: give up and send the parcel back.
  bool get canReturnToSeller => shippingStatus == 'delivery_failed';

  Shipment copyWith({
    String? pickupLocation,
    String? sellerName,
    String? shippingStatus,
  }) {
    return Shipment(
      attemptCount: attemptCount,
      trackingNumber: trackingNumber,
      id: id,
      orderId: orderId,
      shippingStatus: shippingStatus ?? this.shippingStatus,
      orderNumber: orderNumber,
      items: items,
      total: total,
      shippingAddress: shippingAddress,
      createdAt: createdAt,
      deliveryFee: deliveryFee,
      buyerId: buyerId,
      courierId: courierId,
      pickedUpAt: pickedUpAt,
      outForDeliveryAt: outForDeliveryAt,
      deliveredAt: deliveredAt,
      pickupLocation: pickupLocation ?? this.pickupLocation,
      sellerName: sellerName ?? this.sellerName,
    );
  }

  factory Shipment.fromRow(
    Map<String, dynamic> shipmentRow,
    Map<String, dynamic> orderRow,
  ) {
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
      trackingNumber: shipmentRow['tracking_number']?.toString(),
      attemptCount: int.tryParse('${shipmentRow['attempt_count']}') ?? 0,
      id: shipmentRow['id'] as String,
      orderId: shipmentRow['order_id'] as String,
      shippingStatus: shipmentRow['shipping_status']?.toString() ?? 'sorted',
      courierId: shipmentRow['courier_id'] as int?,
      deliveryFee: double.tryParse('${shipmentRow['delivery_fee']}') ?? 0,
      pickedUpAt: DateTime.tryParse(
        shipmentRow['picked_up_at']?.toString() ?? '',
      ),
      outForDeliveryAt: DateTime.tryParse(
        shipmentRow['out_for_delivery_at']?.toString() ?? '',
      ),
      deliveredAt: DateTime.tryParse(
        shipmentRow['delivered_at']?.toString() ?? '',
      ),
      createdAt:
          DateTime.tryParse(shipmentRow['created_at']?.toString() ?? '') ??
          DateTime.now(),
      orderNumber: orderRow['order_number']?.toString() ?? '',
      items: items,
      total: double.tryParse('${orderRow['total']}') ?? 0,
      shippingAddress: address,
      buyerId: orderRow['app_buyer_id'] as int?,
      pickupLocation: [
        shipmentRow['destination_hub'],
        shipmentRow['destination_province'],
      ].whereType<String>().where((v) => v.isNotEmpty).join(', '),
    );
  }
}
