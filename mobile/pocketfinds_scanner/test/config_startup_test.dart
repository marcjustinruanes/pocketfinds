import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds_scanner/api.dart';
import 'package:pocketfinds_scanner/scanner_config.dart';
import 'package:pocketfinds_scanner/session_startup.dart';

void main() {
  final now = DateTime.utc(2026, 10, 14);
  final configuration = ScannerConfiguration.resolve(
    address: 'https://verified.example/api/v1/mobile',
    debug: false,
  );
  Map<String, dynamic> saved({String? server, String? expiry}) => {
    'url': server ?? configuration.namespace,
    'token': 'test-token',
    'user': {'id': '1'},
    'expires_at': expiry ?? now.add(const Duration(hours: 1)).toIso8601String(),
  };
  test(
    'production default never uses a local IP or an unverified endpoint',
    () {
      expect(
        verifiedProductionApiBaseUrl,
        'https://pocketfindsph.shop/api/v1/mobile',
      );
      expect(
        ScannerConfiguration.resolve(debug: false).baseUrl,
        verifiedProductionApiBaseUrl,
      );
      expect(
        () => ScannerConfiguration.resolve(address: '', debug: false),
        throwsA(isA<ConfigurationError>()),
      );
    },
  );
  test('explicit debug override permits local HTTP but release rejects it', () {
    expect(
      ScannerConfiguration.resolve(
        address: 'http://192.168.1.47:8001/api/v1/mobile',
        debug: true,
      ).namespace,
      'http://192.168.1.47:8001/api/v1/mobile/',
    );
    expect(
      () => ScannerConfiguration.resolve(
        address: 'http://192.168.1.47:8001/api/v1/mobile',
        debug: false,
      ),
      throwsA(isA<ConfigurationError>()),
    );
    expect(
      () => ScannerConfiguration.resolve(
        address: 'https://host/api/v1/mobile?token=bad',
      ),
      throwsA(isA<ConfigurationError>()),
    );
  });
  test(
    'startup without a session goes directly to login without validation',
    () async {
      var validations = 0;
      final result = await SessionStartup.resolve(
        configuration: configuration,
        saved: null,
        now: now,
        validate: (_) async {
          validations++;
          return {};
        },
      );
      expect(result.user, isNull);
      expect(result.token, isNull);
      expect(validations, 0);
    },
  );
  test(
    'expired or differently scoped saved sessions never send their token',
    () async {
      var validations = 0;
      for (final value in [
        saved(
          expiry: now.subtract(const Duration(seconds: 1)).toIso8601String(),
        ),
        saved(server: 'https://other.example/api/v1/mobile/'),
      ]) {
        final result = await SessionStartup.resolve(
          configuration: configuration,
          saved: value,
          now: now,
          validate: (_) async {
            validations++;
            return {};
          },
        );
        expect(result.user, isNull);
      }
      expect(validations, 0);
    },
  );
  test('valid session restores and revoked session returns to login', () async {
    final valid = await SessionStartup.resolve(
      configuration: configuration,
      saved: saved(),
      now: now,
      validate: (token) async {
        expect(token, 'test-token');
        return {
          'user': {'id': '1'},
        };
      },
    );
    expect(valid.user?['id'], '1');
    final revoked = await SessionStartup.resolve(
      configuration: configuration,
      saved: saved(),
      now: now,
      validate: (_) async => throw ApiError(401, 'Unauthenticated'),
    );
    expect(revoked.user, isNull);
    expect(revoked.removeSaved, true);
  });
  test(
    'connection failure retains known valid offline session but not unknown legacy expiry',
    () async {
      final known = await SessionStartup.resolve(
        configuration: configuration,
        saved: saved(),
        now: now,
        validate: (_) async => throw ApiError(503, 'Unavailable'),
      );
      expect(known.offline, true);
      expect(known.user?['id'], '1');
      expect(known.removeSaved, false);
      final legacy = saved()..remove('expires_at');
      final unknown = await SessionStartup.resolve(
        configuration: configuration,
        saved: legacy,
        now: now,
        validate: (_) async => throw ApiError(503, 'Unavailable'),
      );
      expect(unknown.user, isNull);
      expect(unknown.removeSaved, false);
    },
  );
  test(
    'HTTP failure stays on the configured server without fallback or logging tokens',
    () async {
      final requests = <Uri>[];
      final api = ScannerApi(
        configuration.baseUrl,
        client: MockClient((request) async {
          requests.add(request.url);
          return http.Response('error code: 1033', 530);
        }),
      );
      await expectLater(api.call('me'), throwsA(isA<ApiError>()));
      await expectLater(api.call('me'), throwsA(isA<ApiError>()));
      expect(requests.map((uri) => uri.host).toSet(), {'verified.example'});
      expect(api.token, isNull);
    },
  );
}
