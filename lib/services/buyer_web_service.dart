import 'dart:convert';

import '../utils/backend_config.dart';
import 'backend_service.dart';

class BuyerWebSession {
  final Uri uri;
  final String nonce;
  const BuyerWebSession(this.uri, this.nonce);
}

/// Only an expiring single-use handoff enters the WebView; never the app bearer.
class BuyerWebService {
  final BackendClient client;
  final Uri origin;
  BuyerWebService({BackendClient? client, Uri? origin})
    : client = client ?? BackendService.client,
      origin = origin ?? Uri.parse(BackendConfig.url);

  bool trusted(String url) {
    final uri = Uri.tryParse(url);
    return uri != null &&
        ['http', 'https'].contains(uri.scheme) &&
        uri.scheme == origin.scheme &&
        uri.host == origin.host &&
        uri.port == origin.port &&
        uri.userInfo.isEmpty;
  }

  bool buyerPath(String path) =>
      (path.startsWith('/buyer/') && !path.startsWith('/buyer/assistant')) ||
      path.startsWith('/mobile-buyer/') ||
      path.startsWith('/gcash-purchases') ||
      path.startsWith('/locations/') ||
      path.startsWith('/message-media/') ||
      path.startsWith('/report-evidence/') ||
      path.startsWith('/account/') ||
      path.startsWith('/commerce/returns/') ||
      path.startsWith('/delivery-proofs/') ||
      path.startsWith('/private-documents/') ||
      path == '/support/contact';

  Uri page(String path) {
    final uri = origin.resolve(path);
    if (!trusted(uri.toString()) || !buyerPath(uri.path)) {
      throw const FormatException('This buyer page is unavailable.');
    }
    return uri;
  }

  Future<BuyerWebSession> open(String path) async {
    page(path);
    final data = await client.request('buyer-web/session', {'path': path});
    final ticket = '${data['ticket']}';
    final nonce = '${data['bridge_nonce']}';
    if (!RegExp(r'^[a-f0-9]{64}$').hasMatch(ticket) ||
        !RegExp(r'^[a-f0-9]{48}$').hasMatch(nonce)) {
      throw const FormatException('Could not open your buyer session.');
    }
    return BuyerWebSession(
      origin
          .resolve('/mobile-buyer/session')
          .replace(queryParameters: {'ticket': ticket}),
      nonce,
    );
  }

  Map<String, dynamic>? message(
    String raw, {
    required String nonce,
    required String currentUrl,
  }) {
    if (!trusted(currentUrl) || !buyerPath(Uri.parse(currentUrl).path)) {
      return null;
    }
    try {
      final data = Map<String, dynamic>.from(jsonDecode(raw) as Map);
      if (data['nonce'] != nonce ||
          ![
            'settings',
            'google-email',
            'payments',
            'payment',
            'order',
            'document',
          ].contains(data['action'])) {
        return null;
      }
      return data;
    } catch (_) {
      return null;
    }
  }
}
