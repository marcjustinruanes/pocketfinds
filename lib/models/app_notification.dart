/// A real notification row from `public.notifications` (28 live rows in
/// this project already — product/order lifecycle events), read via
/// `fetch_my_notifications()`.
class AppNotification {
  final String id;
  final String title;
  final String message;
  final String type; // product_approved, product_pending, new_order, product_rejected, order_status
  final String? referenceId;
  final bool isRead;
  final DateTime createdAt;

  const AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.isRead,
    required this.createdAt,
    this.referenceId,
  });

  /// Categories use persisted notification types, never message-text guesses.
  String get category {
    final value = type.toLowerCase();
    if (value == 'gcash_purchase' ||
        value.startsWith('payment') ||
        value.startsWith('refund') ||
        value.startsWith('rider_payout') ||
        value.startsWith('commission') ||
        value == 'cash_handover') {
      return 'Payments';
    }
    if (value == 'flash_sale' ||
        value == 'announcement' ||
        value.startsWith('promotion') ||
        value.startsWith('voucher') ||
        value.startsWith('discount')) {
      return 'Promotions';
    }
    if (value.startsWith('delivery') ||
        value.startsWith('shipment') ||
        value.startsWith('pickup') ||
        value.startsWith('hub_transfer') ||
        value == 'in_transit' ||
        value == 'out_for_delivery') {
      return 'Delivery';
    }
    if (value == 'new_order' || value.startsWith('order')) return 'Orders';
    return 'Account';
  }

  AppNotification withRead(bool value) => AppNotification(
    id: id,
    title: title,
    message: message,
    type: type,
    referenceId: referenceId,
    isRead: value,
    createdAt: createdAt,
  );

  factory AppNotification.fromRow(Map<String, dynamic> row) {
    return AppNotification(
      id: row['id'].toString(),
      title: row['title']?.toString() ?? 'Notification',
      message: row['message']?.toString() ?? '',
      type: row['notification_type']?.toString() ?? '',
      referenceId: row['reference_id']?.toString(),
      isRead:
          row['is_read'] == true ||
          row['is_read'] == 1 ||
          row['is_read'] == '1',
      createdAt:
          DateTime.tryParse(row['created_at']?.toString() ?? '') ??
          DateTime.fromMillisecondsSinceEpoch(0),
    );
  }
}
