import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/screens/auth/register/rider_activation_screen.dart';
import 'package:pocketfinds/services/rider_service.dart';
import 'package:pocketfinds/widgets/rider_availability.dart';
import 'package:pocketfinds/theme/app_theme.dart';

import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/screens/rider/delivery_completion_screen.dart';

void main() {
  testWidgets('unfinished delivery recovers secure values only for its owner', (
    tester,
  ) async {
    FlutterSecureStorage.setMockInitialValues({
      'delivery:42:parcel': jsonEncode({
        'code': '123456',
        'cod_amount': '500.00',
      }),
    });
    BackendService.client.auth.currentSession = Session({
      'access_token': 'fixture',
      'user': {'id': 42},
    });
    Widget page() => const MaterialApp(
      home: DeliveryCompletionScreen(shipmentId: 'parcel', codAmount: 500),
    );
    await tester.pumpWidget(page());
    await tester.pumpAndSettle();
    expect(find.text('123456'), findsNothing);
    expect(find.text('Six-digit delivery OTP'), findsNothing);
    expect(find.text('500.00'), findsOneWidget);
    expect(find.text('Take live proof photo'), findsOneWidget);
    expect(find.text('Gallery'), findsNothing);
    await tester.enterText(find.byType(TextField).first, '146.00');
    await tester.pumpAndSettle();
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpWidget(page());
    await tester.pumpAndSettle();
    expect(find.text('146.00'), findsOneWidget);
    await tester.pumpWidget(const SizedBox.shrink());
    BackendService.client.auth.currentSession = Session({
      'access_token': 'other',
      'user': {'id': 43},
    });
    await tester.pumpWidget(page());
    await tester.pumpAndSettle();
    expect(find.text('146.00'), findsNothing);
    await tester.pumpWidget(const SizedBox.shrink());
    BackendService.client.auth.currentSession = null;
  });
  testWidgets(
    'rider invitation prepopulates activation and offers no public application',
    (tester) async {
      const invitation =
          'https://pocketfindsph.shop/rider/activate/42/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          home: const RiderActivationScreen(invitation: invitation),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text(invitation), findsOneWidget);
      expect(find.text('Apply as rider'), findsNothing);
      expect(find.text('Activate account'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('assignment confirmation does not count down or expire', (
    tester,
  ) async {
    RiderService.offers['parcel'] = {
      'leg': 'pickup',
      'expires_at': DateTime.now()
          .toUtc()
          .add(const Duration(seconds: 40))
          .toIso8601String(),
    };
    Widget page() => const MaterialApp(
      home: Scaffold(body: RiderOfferCountdown(shipmentId: 'parcel')),
    );
    await tester.pumpWidget(page());
    final before = tester.widget<Text>(find.byType(Text)).data!;
    expect(before, contains('No time limit'));
    await tester.pump(const Duration(seconds: 10));
    await tester.pumpWidget(page());
    final after = tester.widget<Text>(find.byType(Text)).data!;
    expect(after, contains('No time limit'));
    expect(after, isNot(contains('60s')));
    await tester.pumpWidget(const SizedBox.shrink());
    RiderService.offers.clear();
  });
}
