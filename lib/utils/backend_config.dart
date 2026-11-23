
/// Phones use the public HTTPS API; MySQL credentials stay on the server.
/// To set the API URL without editing source:
///   Pass `--dart-define=API_BASE_URL=http://YOUR_IP:8081` for local development.
class BackendConfig {
  static const String _override = String.fromEnvironment('API_BASE_URL');
  static const String _productionUrl = String.fromEnvironment(
    'PRODUCTION_URL',
    defaultValue: 'https://pocketfindsph.shop/app-api',
  );
  static String get url => _override.isNotEmpty
      ? _override.replaceFirst(RegExp(r'/$'), '')
      : _productionUrl.isNotEmpty
          ? _productionUrl
          : 'http://127.0.0.1:8081';
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
