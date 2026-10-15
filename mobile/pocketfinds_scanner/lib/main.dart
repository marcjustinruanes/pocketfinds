import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import 'api.dart';
import 'camera_screen.dart';
import 'scan_queue.dart';
import 'scanner_ui.dart';
import 'scanner_config.dart';
import 'session_startup.dart';

void main() => runApp(const ScannerApp());

class ScannerApp extends StatelessWidget {
  const ScannerApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'PocketFinds Scanner',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xffc43f72)),
      useMaterial3: true,
      scaffoldBackgroundColor: const Color(0xfff8f6f9),
      appBarTheme: const AppBarTheme(
        backgroundColor: Color(0xfff8f6f9),
        surfaceTintColor: Colors.transparent,
        titleTextStyle: TextStyle(
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: Color(0xff29232e),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xfffaf8fb),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xffe5dfe9)),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(50),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
    ),
    home: const ScannerHome(),
  );
}

class ScannerHome extends StatefulWidget {
  const ScannerHome({super.key});
  @override
  State<ScannerHome> createState() => _ScannerHomeState();
}

class _ScannerHomeState extends State<ScannerHome> {
  final storage = const FlutterSecureStorage();
  ScannerConfiguration? configuration;
  final email = TextEditingController(), password = TextEditingController();
  ScannerApi? api;
  ScanQueue? queue;
  ScanSync? sync;
  Map<String, dynamic>? user, parcel, pendingAction;
  List<Map<String, Object?>> recent = [];
  Timer? timer, pairTimer;
  bool checkingPair = false;
  String? pairingNotice;
  int pending = 0;
  bool busy = false, ready = false, syncing = false, online = false;
  bool connectionFailed = false;
  String message = '', mode = 'Home', code = '';
  DateTime? lastSync;
  String? pair, relayCode, relayId;
  int? pairExpires;
  @override
  void initState() {
    super.initState();
    restore();
  }

  Future<void> restore() async {
    try {
      configuration = ScannerConfiguration.resolve();
      queue = await ScanQueue.open();
      final saved = await storage.read(key: 'scanner_session');
      final result = await SessionStartup.resolve(
        configuration: configuration!,
        saved: saved == null
            ? null
            : Map<String, dynamic>.from(jsonDecode(saved)),
        now: DateTime.now(),
        validate: (token) async {
          final probe = ScannerApi(configuration!.baseUrl)..token = token;
          try {
            return await probe.call('me');
          } finally {
            probe.client.close();
          }
        },
      );
      if (result.removeSaved) await storage.delete(key: 'scanner_session');
      message = result.message;
      connectionFailed = result.offline;
      if (result.user != null) {
        api = ScannerApi(configuration!.baseUrl)..token = result.token;
        user = result.user;
        sync = ScanSync(queue!, api!);
        online = !result.offline;
      }
      await refresh();
      timer = Timer.periodic(const Duration(seconds: 20), (_) {
        if (!busy && !syncing && user != null && pair == null) {
          synchronize();
        }
      });
    } on ConfigurationError catch (error) {
      message = error.message;
      connectionFailed = true;
    } catch (_) {
      message =
          'Unable to restore local storage. Pending records have not been deleted.';
      connectionFailed = true;
    }
    if (mounted) setState(() => ready = true);
    if (user != null) synchronize();
  }

  Future<void> refresh() async {
    if (queue != null && user != null && api != null) {
      recent = await queue!.records(api!.namespace, user!['id'].toString());
      pending = await queue!.pendingCount(
        api!.namespace,
        user!['id'].toString(),
      );
    }
    if (mounted) setState(() {});
  }

  Future<void> work(Future<void> Function() action) async {
    if (busy || syncing) return;
    setState(() => busy = true);
    try {
      await action();
    } catch (error) {
      connectionFailed =
          error is ConfigurationError ||
          error is ApiError && error.status >= 500;
      message =
          error is ApiError ||
              error is ArgumentError ||
              error is ConfigurationError
          ? error.toString()
          : 'Network unavailable. Retry when connected.';
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> login() async {
    await work(() async {
      if (queue == null) throw StateError('Local storage unavailable.');
      final candidate = ScannerApi(configuration!.baseUrl);
      final result = await candidate.call(
        'login',
        body: {'email': email.text.trim(), 'password': password.text},
      );
      candidate.token = result['token'];
      final identity = Map<String, dynamic>.from(result['user']);
      await storage.write(
        key: 'scanner_session',
        value: jsonEncode({
          'url': candidate.namespace,
          'token': candidate.token,
          'user': identity,
          'expires_at': result['expires_at'],
        }),
      );
      api = candidate;
      user = identity;
      sync = ScanSync(queue!, api!);
      connectionFailed = false;
      password.clear();
      message =
          'Signed in. Offline scans are available; offline actions are disabled.';
      await refresh();
    });
    if (user != null) synchronize();
  }

  Future<void> logout() => work(() async {
    message =
        'Signed out. Pending scans are preserved for their original account.';
    if (pair != null) {
      try {
        await api?.call('pair/$pair/disconnect', body: {});
      } catch (_) {
        // Logout must still revoke the token if pairing is already unavailable.
      }
    }
    try {
      await api?.call('logout', body: {});
    } catch (_) {
      message =
          'Signed out locally. Server revocation was unavailable; the token expires within eight hours. Pending scans are preserved.';
    }
    await storage.delete(key: 'scanner_session');
    pairTimer?.cancel();
    pairingNotice = null;
    user = null;
    api = null;
    sync = null;
    pair = null;
    parcel = null;
    pendingAction = null;
    relayCode = null;
    relayId = null;
    recent = [];
    mode = 'Home';
    online = false;
  });
  Future<void> synchronize({bool manual = false}) async {
    if (syncing || busy || user == null || sync == null) return;
    setState(() => syncing = true);
    try {
      await sync!.run(user!['id'].toString(), manual: manual);
      online = true;
      connectionFailed = false;
      lastSync = DateTime.now();
      message = 'API reachable. Scans synchronized or marked for review.';
    } on ApiError catch (error) {
      online = false;
      connectionFailed = error.status >= 500;
      message =
          '${error.message}${[401, 403].contains(error.status) ? ' Sign out and sign in again; pending scans are preserved.' : ''}';
    } catch (_) {
      online = false;
      connectionFailed = true;
      message = 'Offline / API unreachable. Scans remain stored.';
    } finally {
      if (mounted) {
        setState(() {
          syncing = false;
          if (!manual && pairingNotice != null) message = pairingNotice!;
        });
        await refresh();
      }
    }
  }

  Future<String?> capture(String title) async {
    if (busy || syncing) return null;
    setState(() => busy = true);
    try {
      return await Navigator.push<String>(
        context,
        MaterialPageRoute(builder: (_) => CameraScreen(title: title)),
      );
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> retryConnection() async {
    if (busy || syncing) return;
    if (configuration == null || queue == null) {
      timer?.cancel();
      pairTimer?.cancel();
      setState(() => ready = false);
      await restore();
      return;
    }
    if (user != null) {
      await synchronize(manual: true);
      return;
    }
    final saved = await storage.read(key: 'scanner_session');
    if (saved != null &&
        matchingSession(
              Map<String, dynamic>.from(jsonDecode(saved)),
              configuration!,
              DateTime.now(),
            ) !=
            null) {
      timer?.cancel();
      pairTimer?.cancel();
      setState(() => ready = false);
      await restore();
      return;
    }
    await work(() async {
      final probe = ScannerApi(configuration!.baseUrl);
      try {
        await probe.call('me');
      } on ApiError catch (error) {
        if (error.status != 401) rethrow;
      } finally {
        probe.client.close();
      }
      connectionFailed = false;
      message = 'Connected to PocketFinds. Please sign in.';
    });
  }

  Future<void> scanStandalone() async {
    final value = await capture('Standalone Scanner');
    if (value == null || !mounted) return;
    await work(() async {
      code = value;
      parcel = null;
      pendingAction = null;
      await queue!.add(api!.namespace, user!, value);
      message = 'Saved as Pending Sync. No parcel action was performed.';
      try {
        parcel = await api!.call('parcels/lookup', body: {'code': code});
        online = true;
      } catch (_) {
        online = false;
        message =
            'Barcode saved. Details unverified; retry lookup when online.';
      }
      await refresh();
    });
    synchronize();
  }

  Future<void> lookupAgain() => work(() async {
    parcel = null;
    parcel = await api!.call('parcels/lookup', body: {'code': code});
    online = true;
  });
  Future<void> operate(Map<String, dynamic> action) async {
    if (pendingAction == null) {
      bool reached = false;
      await work(() async {
        await api!.call('me');
        reached = true;
      });
      if (!reached || !mounted) {
        if (mounted) setState(() => online = false);
        return;
      }
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(action['label']),
        content: const Text(
          'Confirm this operation? It is complete only after server acceptance.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await work(() async {
      pendingAction ??= {
        'operation_id': const Uuid().v4(),
        'code': code,
        'action': action['id'],
        'expected_status': parcel!['parcel']['status'],
      };
      try {
        await api!.call('operations', body: pendingAction);
        pendingAction = null;
        message = 'Server accepted the operation.';
        parcel = null;
        parcel = await api!.call('parcels/lookup', body: {'code': code});
      } on ApiError catch (error) {
        if ([400, 404, 409, 422].contains(error.status)) {
          pendingAction = null;
          parcel = null;
        }
        rethrow;
      } catch (_) {
        message =
            'Result unknown. Retry the same operation before scanning another parcel. Offline actions are disabled.';
      }
    });
  }

  Future<void> pairComputer() async {
    final value = await capture('Pair with Computer');
    if (value == null || !mounted) return;
    await work(() async {
      final token = pairingToken(value, api!.base);
      final result = await api!.call('pair/$token', body: {});
      pair = token;
      pairingNotice = null;
      pairTimer?.cancel();
      pairTimer = Timer.periodic(
        const Duration(seconds: 5),
        (_) => checkPair(),
      );
      pairExpires = result['expires_at'];
      relayCode = null;
      relayId = null;
      mode = 'Paired';
      parcel = null;
      message = 'Paired. Review and confirm parcels on the computer.';
    });
  }

  void endPair(String notice) {
    pairTimer?.cancel();
    pair = null;
    pairExpires = null;
    relayCode = null;
    relayId = null;
    mode = 'Home';
    pairingNotice = notice;
    message = notice;
  }

  Future<void> checkPair() async {
    final token = pair;
    if (token == null || checkingPair) return;
    checkingPair = true;
    try {
      final result = await api!.call('pair/$token/status');
      if (!mounted || pair != token) return;
      connectionFailed = false;
      if (result['state'] == 'connected' &&
          message.startsWith('Cannot check')) {
        setState(
          () => message = 'Paired. Review and confirm parcels on the computer.',
        );
      }
      if (result['state'] == 'disconnected') {
        setState(
          () => endPair(
            result['disconnected_by'] == 'computer'
                ? 'Your computer disconnected this phone.'
                : 'You disconnected from the computer on this phone.',
          ),
        );
      }
    } on ApiError catch (error) {
      if (!mounted || pair != token) return;
      setState(() {
        if ([401, 403, 410].contains(error.status)) {
          endPair(error.message);
        } else {
          message =
              'Cannot check the computer connection. Retrying automatically; you can also tap Retry.';
          connectionFailed = true;
        }
      });
    } finally {
      checkingPair = false;
    }
  }

  Future<void> disconnectPair() => work(() async {
    final token = pair;
    if (token == null) return;
    final result = await api!.call('pair/$token/disconnect', body: {});
    if (pair == token) {
      endPair(
        result['disconnected_by'] == 'computer'
            ? 'Your computer disconnected this phone.'
            : 'You disconnected from the computer on this phone.',
      );
    }
  });

  Future<void> relay() async {
    if (pair == null) return;
    if (DateTime.now().millisecondsSinceEpoch >= pairExpires! * 1000) {
      setState(() {
        endPair('Pairing expired. Create a new connection on the computer.');
      });
      return;
    }
    final activePair = pair;
    final value = relayCode ?? await capture('Paired — send to computer');
    if (value == null || !mounted || pair != activePair) return;
    await work(() async {
      relayCode = value;
      relayId ??= const Uuid().v4().replaceAll('-', '');
      try {
        await api!.call(
          'pair/$pair/codes',
          body: {'code': value, 'scan_id': relayId},
        );
        if (pair != activePair) return;
        relayCode = null;
        relayId = null;
        message =
            'Code sent. Review it and choose Next phone scan on the computer.';
      } on ApiError catch (error) {
        if ([400, 404, 409, 410, 422].contains(error.status)) {
          relayCode = null;
          relayId = null;
        }
        if (pair != activePair) return;
        if (error.status == 410) {
          await checkPair();
        }
        rethrow;
      }
    });
  }

  @override
  void dispose() {
    timer?.cancel();
    pairTimer?.cancel();
    email.dispose();
    password.dispose();
    api?.client.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final locked = busy || syncing;
    if (!ready) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (user == null) {
      return ScannerLogin(
        email: email,
        password: password,
        busy: locked,
        enabled: queue != null,
        message: message,
        onLogin: login,
        onRetry: connectionFailed ? retryConnection : null,
      );
    }
    return ScannerDashboard(
      user: user!,
      parcel: parcel,
      recent: recent,
      mode: mode,
      code: code,
      message: message,
      locked: locked,
      online: online,
      syncing: syncing,
      uncertainAction: pendingAction != null,
      retryRelay: relayCode != null,
      pendingActionId: pendingAction?['action'],
      pending: pending,
      lastSync: lastSync,
      onLogout: logout,
      onStandalone: () => setState(() {
        pairingNotice = null;
        mode = 'Standalone';
        parcel = null;
      }),
      onPair: pairComputer,
      onScan: scanStandalone,
      onLookup: lookupAgain,
      onRelay: relay,
      onHome: () {
        if (pair != null) {
          disconnectPair();
          return;
        }
        setState(() {
          mode = 'Home';
          parcel = null;
          pair = null;
          relayCode = null;
          relayId = null;
        });
      },
      onSync: () => synchronize(manual: true),
      onAction: operate,
      onRetry: connectionFailed
          ? (pair != null ? checkPair : retryConnection)
          : null,
    );
  }
}
