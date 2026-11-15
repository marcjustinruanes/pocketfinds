import 'package:flutter/foundation.dart';

/// The phone talks to the local API; MySQL credentials stay on this computer.
class BackendConfig {
  static const String _override = String.fromEnvironment('API_BASE_URL');
  static String get url => _override.isNotEmpty
      ? _override.replaceFirst(RegExp(r'/$'), '')
      : (!kIsWeb && defaultTargetPlatform == TargetPlatform.android
            ? 'http://10.0.2.2:8081'
            : 'http://127.0.0.1:8081');
  static bool get isConfigured => true;
  static const googleServerClientId = String.fromEnvironment(
    'GOOGLE_CLIENT_ID',
  );

  static String mediaUrl(String path, {String bucket = 'products'}) {
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (path.startsWith('/')) return '$url$path';
    return '$url/media/$bucket/${path.split('/').map(Uri.encodeComponent).join('/')}';
  }
}
