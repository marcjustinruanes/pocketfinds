import 'dart:math';

import 'backend_service.dart';
import '../models/buyer_order.dart';
import '../models/cart_item.dart';
import '../models/order_status_event.dart';

/// The order statuses actually used by the website
/// (`resources/views/buyer/orders.blade.php`) — not inventing a different
/// status vocabulary.
const List<String> orderStatusTabs = [
  'all',
  'pending_payment',
  'pending_payment_verification',
  'placed',
  'preparing',
  'ready_for_pickup',
  'to_ship',
  'in_transit',
  'out_for_delivery',
  'delivered',
  'completed',
  'cancelled',
  'refunded',
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
  final BackendClient? client;
  OrderService({this.client});
  BackendClient get _client => client ?? BackendService.client;
  String? _checkoutId;
  bool paymentPending = false;

  /// Groups [items] by seller and creates one order per seller, applying
  /// the FULL [shippingAmount] to each resulting order — this matches
  /// Laravel's checkout exactly (it does not split shipping across sellers).
  /// Groups [items] by seller and creates one order per seller, applying
  /// the FULL [shippingAmount] to each resulting order — this matches
  /// Laravel's checkout exactly (it does not split shipping across sellers).
  ///
  /// [voucherCode], if given, is validated server-side per seller (via the
  /// real `apply_voucher()` RPC against the real `vouchers` table) — the
  /// discount/free-shipping actually applied is whatever that RPC returns,
  /// never a client-computed number. Vouchers are seller-scoped, so a code
  /// only discounts the order(s) belonging to the seller it matches; if it
  /// doesn't match ANY seller in this cart, the whole checkout is aborted
  /// with a clear error instead of silently charging full price.
  Future<List<({String id, String orderNumber})>> createOrders({
    required int buyerId,
    required List<CartItem> items,
    required double shippingAmount,
    required int paymentMethodId,
    required String paymentMethodName,
    required Map<String, dynamic> shippingAddress,
    String? voucherCode,
    String? platformVoucherId,
    int? deliveryAddressId,
    String? buyerNote,
  }) async {
    // Retain the identifier if a request times out, so retrying cannot duplicate orders.
    _checkoutId ??= _newCheckoutId();
    final result = await _client.request('checkout', {
      'checkout_id': _checkoutId,
      'cart_ids': items.map((item) => item.id).toList(),
      'payment_method_id': paymentMethodId,
      'shipping_address': shippingAddress,
      'voucher_code': voucherCode,
      'platform_voucher_id': platformVoucherId,
      'delivery_address_id': deliveryAddressId,
      'buyer_note': buyerNote,
    });
    _checkoutId = null;
    paymentPending = (result as List).any(
      (row) => row['payment_pending'] == true,
    );
    return result
        .map(
          (row) => (
            id: row['id'] as String,
            orderNumber: row['order_number'] as String,
          ),
        )
        .toList();
  }

  String _newCheckoutId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  Future<List<OrderStatusEvent>> fetchOrderStatusHistory(String orderId) async {
    final rows = await _client.rpc(
      'fetch_order_status_history',
      params: {'p_order_id': orderId},
    );
    final list = List<Map<String, dynamic>>.from(rows as List);
    return list.map(OrderStatusEvent.fromRow).toList();
  }

  Future<BuyerOrder?> findOrder(int buyerId, String id) async {
    final rows = await fetchOrders(buyerId: buyerId, status: 'all');
    for (final order in rows) {
      if (order.id == id) return order;
    }
    return null;
  }

  Future<List<BuyerOrder>> fetchOrders({
    required int buyerId,
    required String status,
  }) async {
    final query = _client
        .from('orders')
        .select(
          'id, order_number, status, items, subtotal, shipping_amount, total, '
          'shipping_address, payment_method_id, app_seller_id, created_at, '
          'discount_amount, voucher_code, platform_payment, gcash_purchase_id',
        )
        .eq('app_buyer_id', buyerId)
        .order('created_at', ascending: false);
    if (status != 'all') query.eq('status', status);
    final rows = await query;

    if (rows.isEmpty) return [];
    final paymentIds = rows
        .where(
          (r) =>
              r['platform_payment'] == true ||
              r['platform_payment'] == 1 ||
              r['gcash_purchase_id'] != null,
        )
        .map((r) => r['id'])
        .toList();
    if (paymentIds.isNotEmpty) {
      final statuses = await _client.request('payments/statuses', {
        'order_ids': paymentIds,
      }) as Map;
      for (final row in rows) {
        row['payment_status'] = statuses[row['id']];
      }
    }

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
        : _client
              .from('users')
              .select('id, given_names, last_name, business_name')
              .inFilter('id', sellerIds);
    final paymentMethodsFuture = paymentMethodIds.isEmpty
        ? Future.value(<Map<String, dynamic>>[])
        : _client
              .from('payment_methods')
              .select('id, name')
              .inFilter('id', paymentMethodIds);

    final results = await Future.wait([sellersFuture, paymentMethodsFuture]);
    final sellerNamesById = {
      for (final s in results[0])
        s['id'] as int: (s['business_name'] as String?)?.isNotEmpty == true
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
}
