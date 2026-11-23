import 'dart:async';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'backend_service.dart';
import 'delivery_service.dart';

/// Firebase client configuration is supplied at build time, never invented.
class PushService {
  static const _key = String.fromEnvironment('FIREBASE_API_KEY');
  static const _app = String.fromEnvironment('FIREBASE_APP_ID');
  static const _sender = String.fromEnvironment('FIREBASE_SENDER_ID');
  static const _project = String.fromEnvironment('FIREBASE_PROJECT_ID');
  static bool get configured =>
      _key.isNotEmpty &&
      _app.isNotEmpty &&
      _sender.isNotEmpty &&
      _project.isNotEmpty;
  final Future<void> Function(Map<String, dynamic>) onOpen;
  final void Function(String title, String body)? onForeground;
  static PushService? instance;
  PushService({required this.onOpen, this.onForeground}) {
    instance = this;
  }
  StreamSubscription<RemoteMessage>? _opened, _foreground;
  StreamSubscription<String>? _tokens;
  String? _token;
  Future<void> initialize() async {
    if (kIsWeb) return;
    try {
      if (configured) {
        await Firebase.initializeApp(
          options: const FirebaseOptions(
            apiKey: _key,
            appId: _app,
            messagingSenderId: _sender,
            projectId: _project,
          ),
        );
      } else {
        await Firebase.initializeApp();
      }
      _opened = FirebaseMessaging.onMessageOpenedApp.listen(
        (m) => onOpen(m.data),
      );
      _foreground = FirebaseMessaging.onMessage.listen((m) async {
        final id = '${m.data['notification_id'] ?? m.messageId ?? ''}';
        final prefs = await SharedPreferences.getInstance();
        final seen = prefs.getStringList('seen_push_ids') ?? [];
        if (seen.contains(id)) return;
        await prefs.setStringList(
          'seen_push_ids',
          [...seen, id].reversed.take(200).toList().reversed.toList(),
        );
        DeliveryService.updates.add(null);
        onForeground?.call(
          m.notification?.title ?? 'PocketFinds',
          m.notification?.body ?? 'You have an update.',
        );
      });
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) onOpen(initial.data);
      _tokens = FirebaseMessaging.instance.onRegistered.listen((value) {
        _token = value;
        _register();
      });
    } catch (_) {
      /* In-app notifications continue if Firebase is unavailable. */
    }
  }

  Future<void> enable() async {
    if (kIsWeb || Firebase.apps.isEmpty) return;
    try {
      final settings = await FirebaseMessaging.instance.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;
      await FirebaseMessaging.instance.register();
    } catch (_) {
      /* OS permission or provider failure does not block shopping. */
    }
  }

  Future<void> _register() async {
    if (_token == null || BackendService.client.auth.currentUser == null) {
      return;
    }
    try {
      await BackendService.client.request('push/device', {
        'token': _token,
        'platform': Platform.isIOS ? 'ios' : 'android',
        'enabled': true,
      });
    } catch (_) {}
  }

  Future<void> disable() async {
    if (_token != null) {
      try {
        await BackendService.client.request('push/device', {
          'token': _token,
          'platform': Platform.isIOS ? 'ios' : 'android',
          'enabled': false,
        });
      } catch (_) {}
    }
  }

  void dispose() {
    _opened?.cancel();
    _foreground?.cancel();
    _tokens?.cancel();
  }
}
