import '../models/app_notification.dart';
import 'backend_service.dart';

/// Reads the signed-in user's real notifications (`public.notifications`,
/// RPC-only — table itself has zero direct grants). Shared by Buyer and
/// Rider (both resolve to the caller's own `public.users.id` server-side).
class NotificationService {
  final BackendClient client;
  NotificationService({BackendClient? client})
    : client = client ?? BackendService.client;
  Future<List<AppNotification>> fetchAll() async {
    final rows = await client.rpc('fetch_my_notifications');
    return List<Map<String, dynamic>>.from(rows as List)
        .map(AppNotification.fromRow)
        .toList();
  }

  Future<int> fetchUnreadCount() async {
    final count = await client.rpc('unread_notification_count');
    if (count is num) return count.toInt();
    return int.tryParse('$count') ??
        (throw const FormatException('Invalid unread count.'));
  }

  Future<void> markRead(String id) {
    return client.rpc('mark_notification_read', params: {'p_id': id});
  }

  Future<void> markAllRead() {
    return client.rpc('mark_all_notifications_read');
  }
}
