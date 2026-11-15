import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/screens/buyer/deals_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';
import 'package:pocketfinds/widgets/logout_confirmation.dart';

void main() {
  testWidgets('logout cancel keeps session; confirmation authorizes sign-out', (
    tester,
  ) async {
    var signouts = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () async {
                if (await confirmLogout(context)) signouts++;
              },
              child: const Text('Account logout'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Account logout'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();
    expect(signouts, 0);
    await tester.tap(find.text('Account logout'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Log out'));
    await tester.pumpAndSettle();
    expect(signouts, 1);
  });
  const buyer = AppUser(
    id: 42,
    email: 'buyer@example.invalid',
    accountType: 'buyer',
    status: 'approved',
    givenNames: 'Buyer',
    lastName: 'Fixture',
  );
  for (final width in [320.0, 412.0]) {
    testWidgets(
      'Deals retries failures and saves upcoming website reminder at $width',
      (tester) async {
        tester.view.physicalSize = Size(width, 1000);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        var loads = 0, reminders = 0;
        final client = BackendClient(
          transport: MockClient((request) async {
            final data = jsonDecode(request.body) as Map;
            if (request.url.path.endsWith('/market/reminder')) {
              expect(data['sale_id'], 123);
              reminders++;
              return http.Response(jsonEncode({'saved': true}), 200);
            }
            expect(request.url.path, endsWith('/catalog/deals'));
            expect(data['page'], 1);
            if (++loads == 1) {
              return http.Response(
                jsonEncode({'message': 'Deals temporarily unavailable'}),
                503,
              );
            }
            return http.Response(
              jsonEncode({
                'page': 1,
                'has_more': false,
                'items': [
                  {
                    'id': 'product',
                    'name': 'Upcoming offer',
                    'image': null,
                    'seller': 'Fixture shop',
                    'price': 100,
                    'sale': {
                      'id': 123,
                      'price': 50,
                      'starts_at': DateTime.now()
                          .add(const Duration(hours: 1))
                          .toIso8601String(),
                    },
                  },
                ],
              }),
              200,
            );
          }),
        );
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.current,
            home: DealsScreen(user: buyer, client: client),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Retry'), findsOneWidget);
        await tester.tap(find.text('Retry'));
        await tester.pumpAndSettle();
        expect(find.text('Upcoming offer'), findsOneWidget);
        expect(find.textContaining('Upcoming flash sale:'), findsOneWidget);
        await tester.tap(find.text('Remind me'));
        await tester.pumpAndSettle();
        expect(reminders, 1);
        expect(find.text('Reminder saved'), findsOneWidget);
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox.shrink());
      },
    );
  }
}
