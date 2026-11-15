import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/buyer_web_service.dart';

void main() {
  final origin = Uri.parse('http://10.0.2.2:8081');
  test('web handoff uses a one-use ticket instead of putting app bearer into page URL', () async {
    final ticket = List.filled(64, 'a').join();
    final nonce = List.filled(48, 'b').join();
    final client = BackendClient(
      transport: MockClient((request) async {
        expect(request.url.path, endsWith('/buyer-web/session'));
        expect(jsonDecode(request.body)['path'], '/buyer/orders');
        return http.Response(
          jsonEncode({'ticket': ticket, 'bridge_nonce': nonce}),
          200,
        );
      }),
    );
    final service = BuyerWebService(client: client, origin: origin);
    final session = await service.open('/buyer/orders');
    expect(session.uri.path, '/mobile-buyer/session');
    expect(session.uri.queryParameters, {'ticket': ticket});
    expect(session.nonce, nonce);
  });

  test('embedded navigation rejects other origins, ports, admin pages and assistant', () {
    final service = BuyerWebService(origin: origin);
    for (final url in [
      'https://10.0.2.2:8081/buyer/orders',
      'http://10.0.2.2/buyer/orders',
      'http://example.com/buyer/orders',
      'javascript:alert(1)',
    ]) {
      expect(service.trusted(url), false);
    }
    for (final path in [
      '/admin/dashboard',
      '/buyer/assistant',
      '/buyer/../admin',
      '//example.com/buyer/orders',
    ]) {
      expect(() => service.page(path), throwsFormatException);
    }
    expect(service.page('/buyer/account').host, '10.0.2.2');
    for (final path in [
      '/account/cancellation-refunds',
      '/account/security/email/code',
      '/commerce/returns/abc/photos/0',
      '/delivery-proofs/abc',
      '/private-documents/token',
    ]) {
      expect(service.page(path).host, '10.0.2.2');
    }
  });

  test(
    'native bridge requires a matching session nonce on a trusted buyer page',
    () {
      final service = BuyerWebService(origin: origin);
      final message = jsonEncode({
        'action': 'settings',
        'nonce': 'session',
        'cart_ids': ['one'],
      });
      expect(
        service.message(
          message,
          nonce: 'session',
          currentUrl: '${origin.origin}/buyer/checkout',
        )?['action'],
        'settings',
      );
      expect(
        service.message(
          message,
          nonce: 'other',
          currentUrl: '${origin.origin}/buyer/checkout',
        ),
        isNull,
      );
      expect(
        service.message(
          message,
          nonce: 'session',
          currentUrl: 'https://example.com/buyer/checkout',
        ),
        isNull,
      );
      expect(
        service.message(
          '{bad json',
          nonce: 'session',
          currentUrl: '${origin.origin}/buyer/cart',
        ),
        isNull,
      );
      expect(
        service.message(
          jsonEncode({'action': 'mark-paid', 'nonce': 'session'}),
          nonce: 'session',
          currentUrl: '${origin.origin}/buyer/orders',
        ),
        isNull,
      );
    },
  );
}
