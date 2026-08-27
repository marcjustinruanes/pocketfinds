/// One line item snapshotted inside an order's `items` jsonb column —
/// mirrors the shape Laravel's checkout stores (name/price/qty/color/size
/// captured at order time, not re-read from `products` later).
class OrderLineItem {
  final String? productId;
  final String name;
  final double price;
  final int qty;
  final String color;
  final String size;

  const OrderLineItem({
    this.productId,
    required this.name,
    required this.price,
    required this.qty,
    this.color = '',
    this.size = '',
  });

  String get variantLabel =>
      [color, size].where((v) => v.isNotEmpty).join(' · ');

  factory OrderLineItem.fromMap(Map<String, dynamic> map) {
    return OrderLineItem(
      productId: map['product_id']?.toString(),
      name: map['name']?.toString() ?? 'Product not provided',
      price: double.tryParse('${map['price']}') ?? 0,
      qty: (map['qty'] as num?)?.toInt() ?? 1,
      color: map['color']?.toString() ?? '',
      size: map['size']?.toString() ?? '',
    );
  }
}

/// A buyer's order — mirrors `resources/views/buyer/orders.blade.php` and
/// the `orders` table (one row per seller per checkout, exactly like
/// Laravel's `BuyerController::checkout()`).
class BuyerOrder {
  final String id;
  final String orderNumber;
  final String status; // to_ship, in_transit, out_for_delivery, completed, cancelled
  final List<OrderLineItem> items;
  final double subtotal;
  final double shippingAmount;
  final double total;
  final Map<String, dynamic> shippingAddress;
  final DateTime createdAt;

  final String sellerName;
  final String? paymentMethodName;

  const BuyerOrder({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.items,
    required this.subtotal,
    required this.shippingAmount,
    required this.total,
    required this.shippingAddress,
    required this.createdAt,
    required this.sellerName,
    this.paymentMethodName,
  });

  String get deliverTo => [
    shippingAddress['barangay'],
    shippingAddress['municipality'],
    shippingAddress['province'],
  ].where((v) => v != null && v.toString().isNotEmpty).join(', ');

  String get statusLabel => status.replaceAll('_', ' ');

  factory BuyerOrder.fromRow(
    Map<String, dynamic> row, {
    required String sellerName,
    String? paymentMethodName,
  }) {
    final rawItems = row['items'];
    final items = rawItems is List
        ? rawItems
              .whereType<Map>()
              .map((i) => OrderLineItem.fromMap(Map<String, dynamic>.from(i)))
              .toList()
        : <OrderLineItem>[];

    final rawAddress = row['shipping_address'];
    final address = rawAddress is Map
        ? Map<String, dynamic>.from(rawAddress)
        : <String, dynamic>{};

    return BuyerOrder(
      id: row['id'] as String,
      orderNumber: row['order_number']?.toString() ?? row['id'].toString(),
      status: row['status']?.toString() ?? 'to_ship',
      items: items,
      subtotal: double.tryParse('${row['subtotal']}') ?? 0,
      shippingAmount: double.tryParse('${row['shipping_amount']}') ?? 0,
      total: double.tryParse('${row['total']}') ?? 0,
      shippingAddress: address,
      createdAt: DateTime.tryParse(row['created_at']?.toString() ?? '') ??
          DateTime.now(),
      sellerName: sellerName,
      paymentMethodName: paymentMethodName,
    );
  }
}
