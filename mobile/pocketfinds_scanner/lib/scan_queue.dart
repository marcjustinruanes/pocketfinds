import 'dart:convert';
import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart' as p;
import 'package:uuid/uuid.dart';
import 'api.dart';

class ScanQueue {
  final Database db;
  ScanQueue(this.db);
  static Future<ScanQueue> open({
    DatabaseFactory? factory,
    String? file,
  }) async {
    final f = factory ?? databaseFactory;
    final db = await f.openDatabase(
      file ?? p.join(await f.getDatabasesPath(), 'pocketfinds_scans.db'),
      options: OpenDatabaseOptions(
        version: 1,
        onCreate: (db, _) async {
          await db.execute(
            'CREATE TABLE scans (id TEXT PRIMARY KEY, server TEXT NOT NULL, owner TEXT NOT NULL, code TEXT NOT NULL, company TEXT NOT NULL, hub_id TEXT, scanned_at TEXT NOT NULL, state TEXT NOT NULL, retries INTEGER NOT NULL DEFAULT 0, next_retry TEXT, error TEXT, response TEXT)',
          );
          await db.execute(
            'CREATE INDEX scans_owner ON scans(server, owner, scanned_at)',
          );
        },
      ),
    );
    await db.update(
      'scans',
      {'state': 'Pending Sync'},
      where: 'state = ?',
      whereArgs: ['Syncing'],
    );
    return ScanQueue(db);
  }

  Future<String> add(
    String server,
    Map<String, dynamic> user,
    String code,
  ) async {
    code = code.trim();
    if (code.isEmpty || code.length > 100) {
      throw ArgumentError('Enter a code of at most 100 characters.');
    }
    final id = const Uuid().v4();
    await db.insert('scans', {
      'id': id,
      'server': server,
      'owner': user['id'].toString(),
      'code': code,
      'company': user['company'],
      'hub_id': user['hub_id'],
      'scanned_at': DateTime.now().toUtc().toIso8601String(),
      'state': 'Pending Sync',
    });
    return id;
  }

  Future<List<Map<String, Object?>>> records(String server, String owner) =>
      db.query(
        'scans',
        where: 'server = ? AND owner = ?',
        whereArgs: [server, owner],
        orderBy: 'scanned_at DESC, id DESC',
        limit: 100,
      );
  Future<int> pendingCount(String server, String owner) async =>
      Sqflite.firstIntValue(
        await db.rawQuery(
          'SELECT COUNT(*) FROM scans WHERE server = ? AND owner = ? AND state NOT IN (?, ?)',
          [server, owner, 'Accepted', 'Rejected'],
        ),
      ) ??
      0;
  Future<void> state(
    String id,
    String state, {
    String? error,
    Map<String, dynamic>? response,
  }) async {
    await db.update(
      'scans',
      {
        'state': state,
        'error': error,
        if (response != null) 'response': jsonEncode(response),
      },
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  Future<void> retry(String id, int attempts, String error) async {
    await db.update(
      'scans',
      {
        'state': 'Retry Required',
        'retries': attempts,
        'error': error,
        'next_retry': DateTime.now()
            .toUtc()
            .add(Duration(seconds: 5 * (1 << (attempts > 6 ? 6 : attempts))))
            .toIso8601String(),
      },
      where: 'id = ?',
      whereArgs: [id],
    );
  }
}

class ScanSync {
  final ScanQueue queue;
  final ScannerApi api;
  bool busy = false;
  ScanSync(this.queue, this.api);
  Future<void> run(String owner, {bool manual = false}) async {
    if (busy) return;
    busy = true;
    try {
      final current = await api.call('me');
      if (current['user']['id'].toString() != owner) {
        throw ApiError(401, 'Sign into the owner of these scans.');
      }
      final rows = await queue.db.query(
        'scans',
        where: 'server = ? AND owner = ? AND state IN (?, ?)',
        whereArgs: [api.namespace, owner, 'Pending Sync', 'Retry Required'],
        orderBy: 'scanned_at, id',
        limit: 20,
      );
      for (final row in rows) {
        final attempts = row['retries'] as int;
        if (!manual &&
            (attempts >= 5 ||
                (row['next_retry'] != null &&
                    DateTime.parse(
                      row['next_retry'] as String,
                    ).isAfter(DateTime.now().toUtc())))) {
          continue;
        }
        final id = row['id'] as String;
        await queue.state(id, 'Syncing');
        try {
          final data = await api.call(
            'scans',
            body: {
              'operation_id': id,
              'code': row['code'],
              'scanned_at': row['scanned_at'],
              'company': row['company'],
              'hub_id': row['hub_id'],
            },
          );
          await queue.state(id, 'Accepted', response: data);
        } on ApiError catch (error) {
          if ([401, 403].contains(error.status)) {
            await queue.state(id, 'Pending Sync', error: error.message);
            rethrow;
          }
          if ([400, 404, 409, 422].contains(error.status)) {
            await queue.state(id, 'Rejected', error: error.message);
          } else {
            await queue.retry(id, attempts + 1, error.message);
            rethrow;
          }
        } catch (_) {
          await queue.retry(id, attempts + 1, 'Network unavailable.');
          rethrow;
        }
      }
    } finally {
      busy = false;
    }
  }
}
