import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/material.dart';

import 'backend_service.dart';

class DeliveryService {
  static final updates = StreamController<void>.broadcast();
  final BackendClient client;
  final _requests = <String, String>{};
  DeliveryService({BackendClient? client})
    : client = client ?? BackendService.client;
  static String requestId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  Future<Map<String, dynamic>> details(String id) async =>
      Map<String, dynamic>.from(
        await client.request('delivery/details', {'shipment_id': id}) as Map,
      );
  Future<Map<String, dynamic>> act(
    String id,
    String action, [
    Map<String, dynamic> fields = const {},
  ]) async {
    final key = jsonEncode([id, action, fields]);
    final request = _requests.putIfAbsent(key, requestId);
    final result = Map<String, dynamic>.from(
      await client.request('delivery/$action', {
        'shipment_id': id,
        'request_id': request,
        ...fields,
      }) as Map,
    );
    _requests.remove(key);
    updates.add(null);
    return result;
  }
}

/// Poll the visible REST-backed view, refresh after actions, and on app resume.
mixin DeliveryRefresh<T extends StatefulWidget>
    on State<T>, WidgetsBindingObserver {
  Timer? _deliveryTimer;
  StreamSubscription<void>? _deliveryUpdates;
  bool _refreshingDelivery = false;
  bool _deliveryVisible = false;
  Future<void> refreshDeliveryData();
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final visible =
        TickerMode.valuesOf(context).enabled &&
        ModalRoute.of(context)?.isCurrent != false;
    final becameVisible = visible && !_deliveryVisible;
    _deliveryVisible = visible;
    if (becameVisible) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _refreshDelivery());
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _deliveryTimer = Timer.periodic(
      const Duration(seconds: 20),
      (_) => _refreshDelivery(),
    );
    _deliveryUpdates = DeliveryService.updates.stream.listen(
      (_) => _refreshDelivery(),
    );
  }

  Future<void> _refreshDelivery() async {
    if (!mounted ||
        _refreshingDelivery ||
        WidgetsBinding.instance.lifecycleState == AppLifecycleState.paused ||
        ModalRoute.of(context)?.isCurrent == false ||
        !TickerMode.valuesOf(context).enabled) {
      return;
    }
    _refreshingDelivery = true;
    try {
      await refreshDeliveryData();
    } catch (_) {
      /* Each view retains its own error UI. */
    } finally {
      _refreshingDelivery = false;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _refreshDelivery();
  }

  @override
  void dispose() {
    _deliveryTimer?.cancel();
    _deliveryUpdates?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }
}
