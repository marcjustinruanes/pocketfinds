import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/notification_service.dart';

void main() {
  test('notification read actions accept null API responses', () async {
    final service = NotificationService(
      client: BackendClient(
        transport: MockClient((_) async => http.Response('null', 200)),
      ),
    );
    await service.markRead('notification');
    await service.markAllRead();
  });
}
