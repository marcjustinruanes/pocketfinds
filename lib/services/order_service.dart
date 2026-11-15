import 'dart:math';
import '../models/buyer_order.dart';
import '../models/cart_item.dart';
import 'supabase_service.dart';

/// The order statuses actually used by the website
/// (`resources/views/buyer/orders.blade.php`) — not inventing a different
/// status vocabulary.
const List<String> orderStatusTabs = [
  'to_ship',
  'in_transit',
  'out_for_delivery',
  'completed',
  'cancelled',
];

/// Creates and reads orders from the existing `orders` table, following
/// `BuyerController::checkout()`/`orders()` exactly: one order row per
/// seller in the cart, `items` stored as a JSON snapshot rather than a
/// separate order-items table (matching what the live Laravel code writes).
///
/// The live `orders` table carries two generations of columns
/// (`app_buyer_id`/`app_seller_id` that Laravel's code actually reads, PLUS
/// older NOT-NULL `buyer_id`/`seller_id`/`payment_method` columns with no
/// defaults) — both sets are populated here so the insert succeeds and stays
/// readable through Laravel's own queries.
class OrderService {
  /// Groups [items] by seller and creates one order per seller, applying
  /// the FULL [shippingAmount] to each resulting order — this matches
  /// Laravel's checkout exactly (it does not split shipping across sellers).
  Future<void> createOrders({
    required int buyerId,
    required List<CartItem> items,
    required double shippingAmount,
    required int paymentMethodId,
    required String paymentMethodName,
    required Map<String, dynamic> shippingAddress,
  }) async {
    final bySeller = <int, List<CartItem>>{};
    for (final item in items) {
      bySeller.putIfAbsent(item.product.sellerId, () => []).add(item);
    }

    for (final entry in bySeller.entries) {
      final sellerId = entry.key;
      final sellerItems = entry.value;
      final subtotal = sellerItems.fold<double>(
        0,
        (sum, item) => sum + item.lineTotal,
      );
      final total = subtotal + shippingAmount;

      final insertedOrder = await SupabaseService.client
          .from('orders')
          .insert({
        'order_number': _generateOrderNumber(),
        'app_buyer_id': buyerId,
        'app_seller_id': sellerId,
        'buyer_id': buyerId, // legacy NOT NULL column, no default
        'seller_id': sellerId, // legacy NOT NULL column, no default
        'status': 'to_ship',
        'order_status': 'pending', // legacy NOT NULL column, no default
        'items': sellerItems
            .map(
              (item) => {
                'product_id': item.productId,
                'name': item.product.name,
                'price': item.product.price,
                'qty': item.quantity,
                'color': item.color,
                'size': item.size,
              },
            )
            .toList(),
        'subtotal': subtotal,
        'shipping_amount': shippingAmount,
        'shipping_fee': shippingAmount, // legacy column mirror
        'discount_amount': 0,
        'total': total,
        'total_amount': total, // legacy column mirror
        'shipping_address': shippingAddress,
        'payment_method_id': paymentMethodId,
        'payment_method': paymentMethodName, // legacy NOT NULL column, no default
      }).select('id').single();

      // Also create a matching shipment, "available" for a rider to claim.
      // No Seller/Logistics screens exist in this app to move a shipment
      // through Laravel's normal pending -> for_verification -> verified ->
      // available staff pipeline, so checkout creates it directly as
      // available — otherwise Rider would have no real deliveries to see.
      await SupabaseService.client.from('shipments').insert({
        'order_id': insertedOrder['id'],
        'shipping_status': 'available',
      });
    }
  }

  Future<List<BuyerOrder>> fetchOrders({
    required int buyerId,
    required String status,
  }) async {
    final rows = await SupabaseService.client
        .from('orders')
        .select(
          'id, order_number, status, items, subtotal, shipping_amount, total, '
          'shipping_address, payment_method_id, app_seller_id, created_at',
        )
        .eq('app_buyer_id', buyerId)
        .eq('status', status)
        .order('created_at', ascending: false);

    if (rows.isEmpty) return [];

    final sellerIds = rows
        .map((r) => r['app_seller_id'] as int?)
        .whereType<int>()
        .toSet()
        .toList();
    final paymentMethodIds = rows
        .map((r) => r['payment_method_id'] as int?)
        .whereType<int>()
        .toSet()
        .toList();

    final sellersFuture = sellerIds.isEmpty
        ? Future.value(<Map<String, dynamic>>[])
        : SupabaseService.client
              .from('users')
              .select('id, given_names, last_name, business_name')
              .inFilter('id', sellerIds);
    final paymentMethodsFuture = paymentMethodIds.isEmpty
        ? Future.value(<Map<String, dynamic>>[])
        : SupabaseService.client
              .from('payment_methods')
              .select('id, name')
              .inFilter('id', paymentMethodIds);

    final results = await Future.wait([sellersFuture, paymentMethodsFuture]);
    final sellerNamesById = {
      for (final s in results[0])
        s['id'] as int:
            (s['business_name'] as String?)?.isNotEmpty == true
                ? s['business_name'] as String
                : '${s['given_names']} ${s['last_name']}'.trim(),
    };
    final paymentNamesById = {
      for (final p in results[1]) p['id'] as int: p['name'] as String,
    };

    return rows
        .map(
          (row) => BuyerOrder.fromRow(
            row,
            sellerName:
                sellerNamesById[row['app_seller_id']] ?? 'Seller not provided',
            paymentMethodName: paymentNamesById[row['payment_method_id']],
          ),
        )
        .toList();
  }

  String _generateOrderNumber() {
    final now = DateTime.now();
    final stamp =
        '${now.year}${_pad(now.month)}${_pad(now.day)}${_pad(now.hour)}${_pad(now.minute)}${_pad(now.second)}';
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    final random = Random();
    final suffix = List.generate(
      5,
      (_) => chars[random.nextInt(chars.length)],
    ).join();
    return 'PF-$stamp-$suffix';
  }

  String _pad(int value) => value.toString().padLeft(2, '0');
}
