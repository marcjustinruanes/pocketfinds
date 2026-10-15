import 'api.dart';
import 'scanner_config.dart';

class SessionStartup {
  final Map<String, dynamic>? user;
  final String? token;
  final bool removeSaved, offline;
  final String message;
  const SessionStartup({
    this.user,
    this.token,
    this.removeSaved = false,
    this.offline = false,
    this.message = '',
  });

  static Future<SessionStartup> resolve({
    required ScannerConfiguration configuration,
    required Map<String, dynamic>? saved,
    required DateTime now,
    required Future<Map<String, dynamic>> Function(String token) validate,
  }) async {
    if (saved == null) return const SessionStartup();
    final value = matchingSession(saved, configuration, now);
    if (value == null) {
      return const SessionStartup(
        message:
            'Please sign in to this configured server. Saved scans remain with their original account and server.',
      );
    }
    try {
      final current = await validate(value['token']);
      if (current['user'] is! Map || current['user']['id'] == null) {
        throw ApiError(
          503,
          'PocketFinds returned an invalid account response. Please retry.',
        );
      }
      final identity = Map<String, dynamic>.from(current['user']);
      if (identity['id'].toString() != value['user']['id'].toString()) {
        throw ApiError(401, 'Please sign in again.');
      }
      return SessionStartup(user: identity, token: value['token']);
    } on ApiError catch (error) {
      if ([401, 403].contains(error.status)) {
        return const SessionStartup(
          removeSaved: true,
          message:
              'Your session is no longer valid. Please sign in again. Saved scans are preserved.',
        );
      }
      final expiry = DateTime.tryParse(value['expires_at']?.toString() ?? '');
      final allowOffline = expiry != null && expiry.isAfter(now.toUtc());
      return SessionStartup(
        user: allowOffline ? Map<String, dynamic>.from(value['user']) : null,
        token: allowOffline ? value['token'] : null,
        offline: true,
        message: error.message,
      );
    }
  }
}
