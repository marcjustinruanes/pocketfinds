import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/delivery_service.dart';

void main() {
  test('a lost response keeps the same idempotency key for a retry', () async {
    final requests = <Map<String, dynamic>>[];
    final client = BackendClient(
      transport: MockClient((request) async {
        requests.add(jsonDecode(request.body) as Map<String, dynamic>);
        if (requests.length == 1) throw http.ClientException('Offline');
        return http.Response('{"ok":true,"status":"picked_up"}', 200);
      }),
    );
    final service = DeliveryService(client: client);
    await expectLater(
      service.act('parcel', 'pickup', {'parcel_code': 'tracking'}),
      throwsA(isA<http.ClientException>()),
    );
    final result = await service.act('parcel', 'pickup', {
      'parcel_code': 'tracking',
    });
    expect(result['status'], 'picked_up');
    expect(requests[0]['request_id'], requests[1]['request_id']);
    expect(
      requests[0]['request_id'],
      matches(
        RegExp(
          r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
        ),
      ),
    );
  });

  test('a rejected delivery never emits a success refresh event', () async {
    var updates = 0;
    final subscription = DeliveryService.updates.stream.listen(
      (_) => updates++,
    );
    addTearDown(subscription.cancel);
    final service = DeliveryService(
      client: BackendClient(
        transport: MockClient(
          (_) async => http.Response('{"message":"Incorrect code"}', 422),
        ),
      ),
    );
    await expectLater(
      service.act('parcel', 'verify-otp', {'code': '123456'}),
      throwsA(isA<DatabaseException>()),
    );
    await Future<void>.delayed(Duration.zero);
    expect(updates, 0);
  });

  test('the next independent action receives a fresh request ID', () async {
    final ids = <String>[];
    final service = DeliveryService(
      client: BackendClient(
        transport: MockClient((request) async {
          ids.add((jsonDecode(request.body) as Map)['request_id'] as String);
          return http.Response('{"ok":true,"status":"out_for_delivery"}', 200);
        }),
      ),
    );
    await service.act('parcel', 'request-otp');
    await service.act('parcel', 'request-otp');
    expect(ids[0], isNot(ids[1]));
  });
}
