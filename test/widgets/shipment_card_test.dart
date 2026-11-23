import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/models/buyer_order.dart';
import 'package:pocketfinds/models/shipment.dart';
import 'package:pocketfinds/widgets/shipment_card.dart';

void main() {
  final shipment = Shipment(
    id: 's1',
    orderId: 'o1',
    shippingStatus: 'out_for_delivery',
    orderNumber: 'PF-00123',
    items: const [OrderLineItem(name: 'Widget', price: 100, qty: 2)],
    total: 200,
    shippingAddress: const {
      'barangay': 'Barangay 1',
      'municipality': 'Quezon City',
      'province': 'Metro Manila',
    },
    createdAt: DateTime(2026, 1, 1),
  );

  testWidgets('shows order number, status label, and item/total summary', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ShipmentCard(shipment: shipment, onTap: () {}),
        ),
      ),
    );

    expect(find.text('PF-00123'), findsOneWidget);
    expect(find.text('Out For Delivery'), findsOneWidget);
    expect(find.text('1 item(s)'), findsOneWidget);
    expect(find.textContaining('200'), findsOneWidget);
  });

  testWidgets('tapping the card fires onTap', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ShipmentCard(shipment: shipment, onTap: () => tapped = true),
        ),
      ),
    );

    await tester.tap(find.byType(ShipmentCard));
    expect(tapped, isTrue);
  });
}
