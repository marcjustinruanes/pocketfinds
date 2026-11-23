import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/models/buyer_order.dart';
import 'package:pocketfinds/screens/buyer/order_details_content.dart';
import 'package:pocketfinds/theme/app_theme.dart';

void main() {
  test('order items retain website variation and image snapshot with legacy fallback', () {
    final item = OrderLineItem.fromMap({
      'name': 'Saved item',
      'price': '42.50',
      'qty': 2,
      'variation_group': 'Options',
      'variation_value': 'Red / M',
      'img': 'product_images/saved.jpg',
    });
    expect(item.color, 'Options');
    expect(item.size, 'Red / M');
    expect(item.price, 42.5);
    expect(item.image, 'product_images/saved.jpg');
    expect(OrderLineItem.fromMap({'color': 'Blue', 'size': 'L'}).size, 'L');
  });
  for (final width in [320.0, 412.0]) {
    testWidgets('complete saved order details fit narrow screen at $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final record = <String, dynamic>{
        'order_number': 'PF-SAVED',
        'created_at': '2026-11-14T09:30:00+08:00',
        'status': 'completed',
        'seller_name': 'Saved Shop',
        'items': [
          {
            'name': 'Saved item',
            'price': 100,
            'qty': 2,
            'variation_group': 'Color',
            'variation_value': 'Red',
          },
        ],
        'address': {
          'recipient_name': 'Saved Recipient',
          'contact_no': '09123456789',
          'house_no': '12',
          'street': 'Side Street',
          'barangay': 'Test',
          'municipality': 'Famy',
          'province': 'Laguna',
          'region': 'Region IV-A',
          'postal_code': '4021',
          'delivery_instructions': 'Use the side gate',
        },
        'pricing': {
          'subtotal': 200,
          'shipping': 50,
          'product_discount': 10,
          'shipping_discount': 5,
          'discount_split_known': true,
          'total': 235,
        },
        'payment': {
          'method': 'GCash',
          'status': 'order_placed',
          'total': 235,
          'paid': 235,
          'outstanding': 0,
          'reference': 'VERIFIED-123',
          'verified_at': '2026-11-14T10:00:00+08:00',
        },
        'shipping': {
          'company': 'Shared Logistics',
          'tracking_number': 'TRACK-SAVED',
          'status': 'delivered',
          'estimated_from': '2026-11-14',
          'estimated_by': '2026-11-16',
          'delivered_at': '2026-11-14T12:00:00+08:00',
        },
        'events': [
          {
            'label': 'Origin hub arrival',
            'at': '2026-11-14T11:00:00+08:00',
            'notes': 'Origin to destination',
          },
        ],
        'receipt': {
          'confirmed': false,
          'completed': true,
          'recorded_at': '2026-11-14T13:00:00+08:00',
        },
        'related_orders': [
          {'id': 'other', 'order_number': 'PF-OTHER', 'seller': 'Second Shop'},
        ],
        'return': {
          'status': 'refunded',
          'reason': 'Damaged item',
          'amount': 100,
          'approved_amount': 100,
          'refund_reference': 'REFUND-123',
          'repayment_recorded_at': '2026-11-14T14:00:00+08:00',
        },
      };
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          home: Scaffold(
            body: SingleChildScrollView(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: OrderDetailsContent(
                  record: record,
                  onRelatedOrder: (_) {},
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Color · Red'), findsOneWidget);
      expect(find.textContaining('Side Street'), findsOneWidget);
      expect(find.textContaining('Use the side gate'), findsOneWidget);
      expect(find.text('Product discounts'), findsOneWidget);
      expect(find.text('Shipping discounts'), findsOneWidget);
      expect(find.textContaining('TRACK-SAVED'), findsOneWidget);
      expect(find.textContaining('VERIFIED-123'), findsOneWidget);
      expect(find.textContaining('REFUND-123'), findsOneWidget);
      expect(
        find.textContaining('no buyer confirmation event'),
        findsOneWidget,
      );
      expect(find.textContaining('PF-OTHER'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
}
