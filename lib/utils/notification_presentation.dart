import '../models/app_notification.dart';

const notificationCategories = [
  'All Notifications',
  'Orders',
  'Delivery',
  'Payments',
  'Promotions',
  'Account',
];
const notificationDateGroups = [
  'Today',
  'Yesterday',
  'Earlier This Week',
  'Previous Notifications',
];

String notificationDateGroup(DateTime timestamp, DateTime now) {
  final local = timestamp.toLocal();
  now = now.toLocal();
  final today = DateTime(now.year, now.month, now.day);
  final date = DateTime(local.year, local.month, local.day);
  if (date == today) return 'Today';
  if (date == DateTime(today.year, today.month, today.day - 1)) {
    return 'Yesterday';
  }
  final weekStart = DateTime(
    today.year,
    today.month,
    today.day - today.weekday + DateTime.monday,
  );
  if (!date.isBefore(weekStart) && date.isBefore(today)) {
    return 'Earlier This Week';
  }
  return 'Previous Notifications';
}

Map<String, List<AppNotification>> groupNotifications(
  Iterable<AppNotification> items,
  String category,
  DateTime now,
) {
  final unique = {for (final item in items) item.id: item};
  final sorted =
      unique.values
          .where(
            (n) => category == 'All Notifications' || n.category == category,
          )
          .toList()
        ..sort((a, b) => b.createdAt.compareTo(a.createdAt));
  return {
    for (final group in notificationDateGroups)
      if (sorted.any((n) => notificationDateGroup(n.createdAt, now) == group))
        group: sorted
            .where((n) => notificationDateGroup(n.createdAt, now) == group)
            .toList(),
  };
}

String notificationActionLabel(AppNotification n) {
  if (n.referenceId == null || n.referenceId!.isEmpty) return 'View Details';
  return switch (n.category) {
    'Orders' => 'View Order',
    'Delivery' => 'Track Order',
    'Payments' => 'View Payment',
    'Promotions' => 'View Promotion',
    _ => 'View Details',
  };
}
