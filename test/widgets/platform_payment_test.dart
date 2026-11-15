import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/screens/buyer/gcash_payment_screen.dart';
import 'package:pocketfinds/services/platform_payment_service.dart';
import 'package:pocketfinds/services/backend_service.dart';

void main() {
  testWidgets(
    'pending verification shows backend deadline and never offers fulfillment',
    (tester) async {
      final service = PlatformPaymentService(
        client: BackendClient(
          transport: MockClient((request) async {
            expect(request.url.path, endsWith('/payments/detail'));
            return http.Response(
              jsonEncode({
                'order_number': 'TEST-ORDER',
                'amount_due': '125.50',
                'status': 'pending_verification',
                'can_submit': false,
                'submitted_at': '2026-11-08T01:00:00Z',
                'verification_deadline': '2026-11-09T01:00:00Z',
              }),
              200,
            );
          }),
        ),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: GcashPaymentScreen(
            orderIds: const ['test-order'],
            service: service,
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Awaiting Payment Verification'), findsOneWidget);
      expect(find.textContaining('Expected verification by:'), findsOneWidget);
      expect(
        find.textContaining('PocketFinds admin is checking'),
        findsOneWidget,
      );
      expect(find.text('Next'), findsNothing);
      expect(find.text('Submit for verification'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  test('paid, rejected, refunded and expired labels remain distinct', () {
    expect(paymentStatusLabel('paid'), 'Payment Verified');
    expect(paymentStatusLabel('rejected'), 'Payment Verification Failed');
    expect(paymentStatusLabel('refunded'), 'Refunded');
    expect(paymentStatusLabel('expired'), 'Payment Expired');
  });

  test('payment request sends evidence and authoritative amount without a paid flag', () async {
    final service = PlatformPaymentService(
      client: BackendClient(
        transport: MockClient((request) async {
          final body = jsonDecode(request.body) as Map;
          expect(body['submission_id'], 'stable-retry-id');
          expect(body['amount'], '125.50');
          expect(body['reference'], '1234567890123');
          expect(body.containsKey('status'), isFalse);
          return http.Response('{"status":"pending_verification"}', 200);
        }),
      ),
    );
    expect(
      (await service.submit(
        orderId: 'order',
        submissionId: 'stable-retry-id',
        reference: '1234567890123',
        path: '1/receipt.png',
        amount: '125.50',
      ))['status'],
      'pending_verification',
    );
  });
}
