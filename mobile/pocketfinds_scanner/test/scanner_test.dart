import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:pocketfinds_scanner/api.dart';
import 'package:pocketfinds_scanner/scan_queue.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  sqfliteFfiInit();
  final user = {'id': '1', 'company': 'Company', 'hub_id': '5'};
  const server = 'https://example.test/api/v1/mobile/';
  late Directory dir;
  late ScanQueue queue;
  setUp(() async {
    dir = await Directory.systemTemp.createTemp('pf-scanner-');
    queue = await ScanQueue.open(
      factory: databaseFactoryFfi,
      file: '${dir.path}/scans.db',
    );
  });
  tearDown(() async {
    await queue.db.close();
    await dir.delete(recursive: true);
  });
  ScannerApi api(MockClient client) =>
      ScannerApi(server, client: client)..token = 'test-token';
  test(
    'pending scans survive restart and interrupted synchronization recovers',
    () async {
      final id = await queue.add(server, user, 'TRACK');
      await queue.state(id, 'Syncing');
      await queue.db.close();
      queue = await ScanQueue.open(
        factory: databaseFactoryFfi,
        file: '${dir.path}/scans.db',
      );
      final rows = await queue.records(server, '1');
      expect(rows.single['id'], id);
      expect(rows.single['state'], 'Pending Sync');
      expect(await queue.pendingCount(server, '1'), 1);
    },
  );
  test(
    'server and account switching never syncs another owner queue',
    () async {
      await queue.add(server, user, 'ONE');
      await queue.add(server, {...user, 'id': '2'}, 'TWO');
      await queue.add('https://other.test/api/v1/mobile/', user, 'OTHER');
      final sent = <String>[];
      final client = MockClient((request) async {
        if (request.url.path.endsWith('/me')) {
          return http.Response(
            jsonEncode({
              'user': {'id': '2'},
            }),
            200,
          );
        }
        sent.add(jsonDecode(request.body)['code']);
        return http.Response('{"accepted":true}', 200);
      });
      await ScanSync(queue, api(client)).run('2');
      expect(sent, ['TWO']);
      expect(await queue.pendingCount(server, '1'), 1);
      expect(
        await queue.pendingCount('https://other.test/api/v1/mobile/', '1'),
        1,
      );
    },
  );
  test(
    'network loss retains retry ID, bounded retries, and manual reconnection',
    () async {
      final id = await queue.add(server, user, 'TRACK');
      var failing = true;
      final ids = <String>[];
      final client = MockClient((request) async {
        if (request.url.path.endsWith('/me')) {
          return http.Response('{"user":{"id":"1"}}', 200);
        }
        ids.add(jsonDecode(request.body)['operation_id']);
        if (failing) return http.Response('{"message":"Unavailable"}', 503);
        return http.Response('{"accepted":true}', 200);
      });
      final sync = ScanSync(queue, api(client));
      await expectLater(sync.run('1'), throwsA(isA<ApiError>()));
      expect(
        (await queue.records(server, '1')).single['state'],
        'Retry Required',
      );
      await sync.run('1');
      expect(ids.length, 1); // Backoff honored.
      failing = false;
      await sync.run('1', manual: true);
      expect(ids, [id, id]);
      expect((await queue.records(server, '1')).single['state'], 'Accepted');
    },
  );
  test(
    'rejected parcels are visible and do not block unrelated scans',
    () async {
      await queue.add(server, user, 'UNKNOWN');
      await queue.add(server, user, 'VALID');
      final client = MockClient((request) async {
        if (request.url.path.endsWith('/me')) {
          return http.Response('{"user":{"id":"1"}}', 200);
        }
        return jsonDecode(request.body)['code'] == 'UNKNOWN'
            ? http.Response('{"message":"Not permitted"}', 404)
            : http.Response('{"accepted":true}', 200);
      });
      await ScanSync(queue, api(client)).run('1');
      final rows = await queue.records(server, '1');
      expect(
        rows.map((r) => r['state']),
        containsAll(['Rejected', 'Accepted']),
      );
      expect(
        rows.firstWhere((r) => r['state'] == 'Rejected')['error'],
        'Not permitted',
      );
    },
  );
  test('expired credentials preserve all pending scans', () async {
    await queue.add(server, user, 'TRACK');
    final client = MockClient(
      (_) async => http.Response('{"message":"Unauthenticated"}', 401),
    );
    await expectLater(
      ScanSync(queue, api(client)).run('1'),
      throwsA(isA<ApiError>()),
    );
    expect(await queue.pendingCount(server, '1'), 1);
    expect((await queue.records(server, '1')).single['state'], 'Pending Sync');
  });
  test('legitimate repeated scans have different durable IDs', () async {
    expect(
      await queue.add(server, user, 'TRACK'),
      isNot(await queue.add(server, user, 'TRACK')),
    );
    await expectLater(queue.add(server, user, ''), throwsArgumentError);
  });
  test(
    'pairing accepts only configured server and never accepts a parcel QR',
    () {
      final token = List.filled(64, 'a').join();
      final base = Uri.parse(server);
      expect(
        pairingToken('https://example.test/logistics/scan/phone/$token', base),
        token,
      );
      expect(
        () => pairingToken(
          'https://foreign.test/logistics/scan/phone/$token',
          base,
        ),
        throwsArgumentError,
      );
      expect(() => pairingToken('TRACKING-NUMBER', base), throwsArgumentError);
      expect(() => ScannerApi('file:///api/v1/mobile/'), throwsArgumentError);
    },
  );
}
