import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/screens/buyer/connect_gcash_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';

void main() {
  testWidgets(
    'incorrect GCash code can be retried without restarting connection',
    (tester) async {
      var sends = 0, verifies = 0;
      final client = BackendClient(
        transport: MockClient((request) async {
          if (request.url.path.endsWith('payment-send-code')) {
            sends++;
            return http.Response('{"success":true}', 200);
          }
          verifies++;
          final data = jsonDecode(request.body) as Map;
          return data['otp'] == '123456'
              ? http.Response('{"success":true}', 200)
              : http.Response('{"message":"Incorrect verification code"}', 422);
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => ConnectGcashScreen(client: client),
                  ),
                ),
                child: const Text('Connect'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Connect'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).at(0), 'Test Buyer');
      await tester.enterText(find.byType(TextField).at(1), '09123456789');
      await tester.tap(find.text('Send code'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).at(2), '000000');
      await tester.tap(find.text('Verify and connect'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Incorrect verification code'),
        findsOneWidget,
      );
      expect(find.byType(TextField), findsNWidgets(3));
      await tester.enterText(find.byType(TextField).at(2), '123456');
      await tester.tap(find.text('Verify and connect'));
      await tester.pumpAndSettle();
      expect(find.byType(ConnectGcashScreen), findsNothing);
      expect(sends, 1);
      expect(verifies, 2);
    },
  );
}
