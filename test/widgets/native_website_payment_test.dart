import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/screens/buyer/gcash_payment_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/website_payment_service.dart';

void main() {
  testWidgets('website payment uses native account and receipt screens', (
    tester,
  ) async {
    final service = WebsitePaymentService(
      client: BackendClient(
        transport: MockClient((request) async {
          expect(request.url.path, endsWith('/gcash/order-payment'));
          return http.Response(
            jsonEncode({
              'order_number': 'TEST-ORDER',
              'amount_due': '125.50',
              'status': 'pending',
              'payment_state': 'awaiting_payment',
              'reference_required': false,
              'can_submit': true,
              'configuration': {
                'account_name': 'Configured account',
                'account_number': '09123456789',
                'instructions': ['Submit a receipt for admin review.'],
              },
            }),
            200,
          );
        }),
      ),
    );
    await tester.pumpWidget(
      MaterialApp(
        home: GcashPaymentScreen(orderIds: const ['order'], service: service),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Configured account'), findsOneWidget);
    expect(find.text('Next'), findsOneWidget);
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('Choose screenshot'), findsOneWidget);
    expect(find.text('Submit for verification'), findsOneWidget);
    expect(find.text('GCash reference number'), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });

  test('native receipt submission delegates to website records without financial overrides', () async {
    final service = WebsitePaymentService(
      client: BackendClient(
        transport: MockClient((request) async {
          expect(request.url.path, endsWith('/gcash/order-proof'));
          expect(jsonDecode(request.body), {
            'order_id': 'order',
            'submission_id': 'submission',
            'path': 'buyer/receipt.jpg',
          });
          return http.Response(
            jsonEncode({'status': 'pending_verification'}),
            200,
          );
        }),
      ),
    );
    final result = await service.submit(
      orderId: 'order',
      submissionId: 'submission',
      reference: '',
      path: 'buyer/receipt.jpg',
      amount: '125.50',
    );
    expect(result['status'], 'pending_verification');
  });
}
