import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/screens/buyer/orders_tab.dart';
import 'package:pocketfinds/screens/buyer/order_presentation.dart';
import 'package:pocketfinds/screens/buyer/buyer_order_actions.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';

void main() {
  const buyer = AppUser(
    id: 1,
    accountType: 'buyer',
    status: 'approved',
    email: 'buyer@example.invalid',
    givenNames: 'Test',
    lastName: 'Buyer',
  );
  testWidgets(
    'actions use server permissions and cancellation needs confirmation',
    (tester) async {
      var cancelled = false;
      var cancellations = 0;
      final client = BackendClient(
        transport: MockClient((request) async {
          final data = jsonDecode(request.body) as Map;
          Object result = [];
          if (request.url.path.endsWith('/buyer-api/order')) {
            result = {
              'status': cancelled ? 'cancelled' : 'pending_payment',
              'can_cancel': !cancelled,
              'can_confirm': false,
              'can_review': false,
              'prepaid': true,
              'payment_verification': {'can_submit': !cancelled},
              'details': {
                'items': [],
                'payment': {'status': 'awaiting_payment'},
              },
            };
          } else if (request.url.path.endsWith('/buyer-api/cancel')) {
            cancellations++;
            expect(data['cancellation_reason'], isNotEmpty);
            cancelled = true;
            result = {'cancelled': true};
          }
          return http.Response(jsonEncode(result), 200);
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          home: Scaffold(
            body: BuyerOrderActions(
              orderId: 'order',
              user: buyer,
              client: client,
              compact: true,
              onViewDetails: () {},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Pay Now'), findsOneWidget);
      expect(find.text('Confirm Received'), findsNothing);
      expect(find.text('Leave a Review'), findsNothing);
      await tester.tap(find.text('Cancel Order'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Keep order'));
      await tester.pumpAndSettle();
      expect(cancellations, 0);
      await tester.tap(find.text('Cancel Order'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(TextButton, 'Cancel order'));
      await tester.pumpAndSettle();
      expect(cancellations, 1);
      expect(find.text('Pay Now'), findsNothing);
      expect(find.text('Cancel Order'), findsNothing);
      expect(find.text('Buy Again'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump(const Duration(seconds: 2));
    },
  );
  testWidgets('orders have a useful empty state and shopping action', (
    tester,
  ) async {
    var shopping = false;
    final client = BackendClient(
      transport: MockClient((_) async => http.Response('[]', 200)),
    );
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: OrdersTab(
          user: buyer,
          client: client,
          onContinueShopping: () {
            shopping = true;
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('No orders yet'), findsOneWidget);
    await tester.tap(find.text('Continue Shopping'));
    expect(shopping, isTrue);
    await tester.tap(find.text('To Pay (0)'));
    await tester.pumpAndSettle();
    expect(find.text('No pending payments'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });
  for (final width in [320.0, 393.0, 430.0]) {
    testWidgets(
      'real order cards, filters and receipt confirmation at $width',
      (tester) async {
        tester.view.physicalSize = Size(width, 1000);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        var received = false;
        var confirmations = 0;
        Map<String, dynamic> row(String id, String status) => {
          'id': id,
          'order_number': 'PF-$id',
          'status': status,
          'items': [
            {
              'product_id': 'p',
              'name': 'Long product name with several important details and a selected size',
              'price': 12.35,
              'qty': 3,
              'variation_group': 'Options',
              'variation_value': 'Blue / Large',
            },
            {
              'product_id': 'q',
              'name': 'Second product',
              'price': 20,
              'qty': 1,
            },
          ],
          'subtotal': 57.05,
          'shipping_amount': 50,
          'discount_amount': 10,
          'total': 97.05,
          'payment_method_id': id == 'pay' ? 2 : 1,
          'app_seller_id': 42,
          'platform_payment': false,
          'gcash_purchase_id': id == 'pay' ? 'purchase' : null,
          'created_at': '2026-11-15T10:00:00+08:00',
          'shipping_address': {},
        };
        final client = BackendClient(
          transport: MockClient((request) async {
            final body = jsonDecode(request.body) as Map;
            final path = request.url.path;
            Object result = [];
            if (path.endsWith('/query')) {
              switch (body['table']) {
                case 'orders':
                  expect(
                    (body['filters'] as List).any(
                      (f) => f['column'] == 'app_buyer_id' && f['value'] == 1,
                    ),
                    isTrue,
                  );
                  result = [
                    row('delivery', received ? 'completed' : 'delivered'),
                    row('pay', 'pending_payment_verification'),
                    row('shipping', 'preparing'),
                  ];
                case 'users':
                  result = [
                    {
                      'id': 42,
                      'business_name': 'A shop with a long business name',
                    },
                  ];
                case 'payment_methods':
                  result = [
                    {'id': 1, 'name': 'Cash on Delivery'},
                    {'id': 2, 'name': 'GCash'},
                  ];
                case 'products':
                  result = [];
              }
            } else if (path.endsWith('/payments/statuses')) {
              result = {'pay': 'pending_verification'};
            } else if (path.endsWith('/buyer-api/order')) {
              final id = body['order_id'];
              result = {
                'status': id == 'delivery'
                    ? (received ? 'completed' : 'delivered')
                    : id == 'pay'
                    ? 'pending_payment_verification'
                    : 'preparing',
                'can_confirm': id == 'delivery' && !received,
                'can_cancel': id == 'shipping',
                'can_review': false,
                'prepaid': id == 'pay',
                'details': {
                  'items': [],
                  'shipping': id == 'delivery' ? {'id': 'shipment'} : null,
                  'payment': {
                    'status': id == 'pay'
                        ? 'pending_verification'
                        : 'due_on_delivery',
                  },
                },
              };
            } else if (path.endsWith('/rpc/confirm_order_received')) {
              confirmations++;
              expect(body['p_shipment_id'], 'shipment');
              received = true;
              result = {'confirmed': true};
            }
            return http.Response(jsonEncode(result), 200);
          }),
        );
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.current,
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context)
                  .copyWith(textScaler: const TextScaler.linear(1.5)),
              child: child!,
            ),
            home: OrdersTab(
              user: const AppUser(
                id: 1,
                accountType: 'buyer',
                status: 'approved',
                email: 'buyer@example.invalid',
                givenNames: 'Test',
                lastName: 'Buyer',
              ),
              client: client,
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('My Orders'), findsOneWidget);
        expect(find.text('All (3)'), findsOneWidget);
        expect(find.text('Blue · Large'), findsNothing);
        expect(find.text('Options · Blue / Large'), findsWidgets);
        await tester.scrollUntilVisible(
          find.text('Confirm Received').first,
          300,
          scrollable: find.byType(Scrollable).last,
        );
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.text('Confirm Received').first);
        await tester.pumpAndSettle();
        await tester.tap(find.text('Confirm Received').first);
        await tester.pumpAndSettle();
        expect(find.text('Confirm order received?'), findsOneWidget);
        await tester.tap(find.text('Not yet'));
        await tester.pumpAndSettle();
        expect(confirmations, 0);
        await tester.tap(find.text('Confirm Received').first);
        await tester.pumpAndSettle();
        await tester.tap(
          find.widgetWithText(FilledButton, 'Confirm Received').last,
        );
        await tester.pumpAndSettle();
        expect(confirmations, 1);
        expect(find.text('Confirm order received?'), findsNothing);
        await tester.tap(find.text('To Pay (1)'));
        await tester.pumpAndSettle();
        expect(find.text('Order PF-pay'), findsOneWidget);
        expect(find.text('Pay Now'), findsNothing);
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox.shrink());
      },
    );
  }
  testWidgets('timeline never marks an upcoming stage complete', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: BuyerDeliveryTimeline(
            status: 'preparing',
            stages: const [
              {'label': 'Payment Confirmed', 'state': 'not_required'},
              {
                'label': 'Preparing Order',
                'state': 'recorded',
                'recorded_at': '2026-11-15T10:00:00+08:00',
              },
              {'label': 'Delivered', 'state': 'upcoming', 'recorded_at': null},
            ],
          ),
        ),
      ),
    );
    expect(find.text('Payment Confirmed'), findsNothing);
    expect(find.text('Awaiting update'), findsOneWidget);
    expect(find.byIcon(Icons.check_circle), findsOneWidget);
    expect(find.byIcon(Icons.radio_button_unchecked), findsOneWidget);
  });
}
