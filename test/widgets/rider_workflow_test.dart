import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/models/shipment.dart';
import 'package:pocketfinds/screens/rider/task_detail_screen.dart';
import 'package:pocketfinds/theme/app_theme.dart';
import 'package:pocketfinds/widgets/task_card.dart';

void main() {
  Shipment parcel(String status) => Shipment(
    id: 'parcel',
    orderId: 'order',
    shippingStatus: status,
    orderNumber: 'PF-123',
    trackingNumber: 'PF-TRACK-123',
    items: const [],
    total: 100,
    shippingAddress: const {},
    createdAt: DateTime(2026),
    pickupLocation: 'Famy, Laguna',
  );

  testWidgets('task card fits a narrow screen with readable pickup label', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: Scaffold(
          body: Padding(
            padding: const EdgeInsets.all(16),
            child: TaskCard(
              shipment: parcel('assigned_to_rider'),
              onTap: () {},
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('PICK UP FROM SORTING CENTER'), findsOneWidget);
    expect(find.text('PF-TRACK-123'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('available task never presents unverified delivery actions', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: TaskDetailScreen(shipment: parcel('sorted'), riderId: 42),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Accept task'), findsOneWidget);
    expect(find.text('Enter buyer delivery code'), findsNothing);
    expect(find.text('Scan parcel and confirm pickup'), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
}
