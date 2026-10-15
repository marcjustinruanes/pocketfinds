import 'package:flutter/foundation.dart';

// Verified over HTTPS: /me -> 401, empty /login -> 422, /up -> 200,
// and the existing Cloudflare connector reports four ready connections.
const verifiedProductionApiBaseUrl = 'https://pocketfindsph.shop/api/v1/mobile';

const configuredApiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: verifiedProductionApiBaseUrl,
);

class ScannerConfiguration {
  final String baseUrl;
  const ScannerConfiguration._(this.baseUrl);

  factory ScannerConfiguration.resolve({
    String address = configuredApiBaseUrl,
    bool debug = kDebugMode,
  }) {
    final value = address.trim();
    final uri = Uri.tryParse(value);
    if (value.isEmpty) {
      throw const ConfigurationError(
        'The service address has not been configured for this build. Contact your administrator.',
      );
    }
    if (uri == null ||
        !uri.hasAuthority ||
        uri.userInfo.isNotEmpty ||
        uri.hasQuery ||
        uri.hasFragment ||
        !(uri.scheme == 'https' || (debug && uri.scheme == 'http')) ||
        !(uri.path == '/api/v1/mobile' || uri.path == '/api/v1/mobile/')) {
      throw const ConfigurationError(
        'This build has an invalid service address. Contact your administrator.',
      );
    }
    return ScannerConfiguration._(
      uri.toString().replaceFirst(RegExp(r'/$'), ''),
    );
  }

  String get namespace => '$baseUrl/';
}

class ConfigurationError implements Exception {
  final String message;
  const ConfigurationError(this.message);
  @override
  String toString() => message;
}

// Never send a saved token to a differently configured server. Legacy sessions
// without an expiry must be validated online before restoration.
Map<String, dynamic>? matchingSession(
  Map<String, dynamic> saved,
  ScannerConfiguration configuration,
  DateTime now,
) {
  if (saved['url'] != configuration.namespace ||
      saved['token'] is! String ||
      (saved['token'] as String).isEmpty ||
      saved['user'] is! Map) {
    return null;
  }
  final expiry = DateTime.tryParse(saved['expires_at']?.toString() ?? '');
  if (expiry != null && !expiry.isAfter(now.toUtc())) return null;
  return saved;
}
