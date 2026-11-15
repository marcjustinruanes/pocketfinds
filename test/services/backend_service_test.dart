import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:pocketfinds/services/backend_service.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  for (final remember in [false, true]) {
    test('Remember me $remember controls session persistence', () async {
      final client = BackendClient(
        transport: MockClient(
          (_) async => http.Response(
            jsonEncode({
              'access_token': 'test-token',
              'user': {'id': 42, 'email': 'buyer@example.invalid'},
            }),
            200,
          ),
        ),
      );
      await client.auth.initialize();
      await client.auth.signInWithPassword(
        email: 'buyer@example.invalid',
        password: 'test-password',
        remember: remember,
      );
      expect(client.auth.currentUser?.id, '42');
      final saved = (await SharedPreferences.getInstance()).getString(
        'mysql_session',
      );
      expect(saved != null, remember);
    });
  }

  test(
    'registration challenge never creates or persists a shopping session',
    () async {
      final client = BackendClient(
        transport: MockClient(
          (_) async => http.Response(
            jsonEncode({
              'verification_required': true,
              'registration_token': 'verification-only-token',
              'email': 'buyer@example.invalid',
            }),
            200,
          ),
        ),
      );
      await client.auth.initialize();
      final response = await client.auth.registerBuyer({
        'email': 'buyer@example.invalid',
      });
      expect(response.registrationToken, 'verification-only-token');
      expect(response.session, isNull);
      expect(client.auth.currentUser, isNull);
      expect(
        (await SharedPreferences.getInstance()).getString('mysql_session'),
        isNull,
      );
    },
  );

  test('restores the local account and sends its bearer to the API', () async {
    SharedPreferences.setMockInitialValues({
      'mysql_session': jsonEncode({
        'access_token': 'local-test-token',
        'user': {'id': 42, 'email': 'test@example.invalid'},
      }),
    });
    final client = BackendClient(
      transport: MockClient((request) async {
        expect(request.headers['authorization'], 'Bearer local-test-token');
        expect(request.url.path, '/query');
        final body = jsonDecode(request.body) as Map;
        expect(body['filters'], [
          {'column': 'buyer_id', 'op': '=', 'value': 42},
        ]);
        return http.Response('[{"id":"cart-test","qty":2}]', 200);
      }),
    );
    await client.auth.initialize();
    final rows = await client
        .from('cart_items')
        .select('id,qty')
        .eq('buyer_id', 42);
    expect(rows.single['qty'], 2);
  });

  test(
    'server rejection clears an expired session and local persistence',
    () async {
      SharedPreferences.setMockInitialValues({
        'mysql_session': jsonEncode({
          'access_token': 'expired',
          'user': {'id': 42},
        }),
      });
      final client = BackendClient(
        transport: MockClient(
          (_) async => http.Response('{"message":"Session expired"}', 401),
        ),
      );
      await client.auth.initialize();
      await expectLater(
        client.rpc('get_my_profile'),
        throwsA(isA<AuthException>()),
      );
      expect(client.auth.currentSession, isNull);
      expect(
        (await SharedPreferences.getInstance()).getString('mysql_session'),
        isNull,
      );
    },
  );

  test('invalid responses show a controlled API error', () async {
    final client = BackendClient(
      transport: MockClient(
        (_) async => http.Response('<html>Error</html>', 500),
      ),
    );
    await expectLater(
      client.rpc('get_my_profile'),
      throwsA(isA<DatabaseException>()),
    );
  });

  test('duplicate rows cannot silently become one cart entry', () async {
    final client = BackendClient(
      transport: MockClient(
        (_) async => http.Response('[{"id":"a"},{"id":"b"}]', 200),
      ),
    );
    await expectLater(
      client.from('cart_items').maybeSingle(),
      throwsA(isA<DatabaseException>()),
    );
  });

  test('corrupt saved session falls back to guest', () async {
    SharedPreferences.setMockInitialValues({'mysql_session': 'broken JSON'});
    final client = BackendClient();
    await client.auth.initialize();
    expect(client.auth.currentUser, isNull);
  });
}
