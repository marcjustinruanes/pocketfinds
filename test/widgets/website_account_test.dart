import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/screens/buyer/website_account_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';

void main() {
  testWidgets(
    'profile validation retains edits and successful retry waits for review',
    (tester) async {
      var attempts = 0;
      var pending = false;
      final client = BackendClient(
        transport: MockClient((request) async {
          if (request.url.path.endsWith('/profile')) {
            attempts++;
            expect((jsonDecode(request.body) as Map)['given_names'], 'Updated');
            if (attempts == 1) {
              return http.Response(
                '{"message":"Please check the contact number"}',
                422,
              );
            }
            pending = true;
            return http.Response('{"submitted":true}', 200);
          }
          return http.Response(
            jsonEncode({
              'profile': {
                'given_names': 'Buyer',
                'last_name': 'Fixture',
                'email': 'buyer@gmail.com',
                'status': 'approved',
                'contact_no': '09123456789',
              },
              'pending_request': pending ? {'status': 'pending'} : null,
              'payment_accounts': [],
            }),
            200,
          );
        }),
      );
      const user = AppUser(
        id: 42,
        accountType: 'buyer',
        status: 'approved',
        email: 'buyer@gmail.com',
        givenNames: 'Buyer',
        lastName: 'Fixture',
      );
      await tester.pumpWidget(
        MaterialApp(
          home: WebsiteAccountScreen(user: user, client: client),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('My profile'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Request profile changes'));
      await tester.tap(find.text('Request profile changes'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'Updated');
      await tester.tap(find.text('Submit for review'));
      await tester.pumpAndSettle();
      expect(find.text('Please check the contact number'), findsOneWidget);
      expect(find.text('Updated'), findsOneWidget);
      await tester.tap(find.text('Submit for review'));
      await tester.pumpAndSettle();
      expect(find.byType(TextField), findsNothing);
      tester
          .state<ScrollableState>(find.byType(Scrollable).first)
          .position
          .jumpTo(0);
      await tester.pumpAndSettle();
      expect(find.text('Update Request Pending'), findsOneWidget);
      expect(find.text('Buyer Fixture'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );
}
