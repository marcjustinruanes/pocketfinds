import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/models/app_notification.dart';
import 'package:pocketfinds/utils/notification_presentation.dart';

void main() {
  test('assignment notification has no old deadline and opens assignment action', () {
    final notification = AppNotification.fromRow({
      'id': 'offer-notification',
      'notification_type': 'delivery_assigned',
      'message': 'Hub staff offered you a delivery job. Accept within 60 seconds to confirm.',
      'reference_id': 'order-id',
      'created_at': '2026-11-16T00:25:00+08:00',
    });
    expect(notification.message, isNot(contains('60 seconds')));
    expect(notificationActionLabel(notification), 'View Assignment');
  });
}
