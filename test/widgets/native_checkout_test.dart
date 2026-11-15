import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/models/cart_item.dart';
import 'package:pocketfinds/models/product.dart';
import 'package:pocketfinds/screens/buyer/checkout_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';

void main() {
  for (final width in [320.0, 412.0]) {
    testWidgets(
      'native checkout renders website quote and actionable errors at $width',
      (tester) async {
        tester.view.physicalSize = Size(width, 900);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        var addressRequests = 0;
        final client = BackendClient(
          transport: MockClient((request) async {
            final data = jsonDecode(request.body) as Map;
            Object result;
            switch (request.url.path.split('/').last) {
              case 'addresses':
                expect(request.url.path, endsWith('/buyer-api/addresses'));
                if (width == 320 && ++addressRequests == 1) {
                  return http.Response(
                    jsonEncode({'message': 'Checkout unavailable. Retry.'}),
                    500,
                  );
                }
                result = [
                  {
                    'id': 1,
                    'recipient_name': 'Buyer Fixture',
                    'contact_no': '09123456789',
                    'full_address': 'Test address',
                  },
                ];
              case 'query':
                expect(data['table'], 'payment_methods');
                result = [
                  {'id': 2, 'name': 'Cash on Delivery', 'description': ''},
                ];
              case 'quote':
                expect(data['website_payment'], isTrue);
                expect(data['cart_ids'], ['cart']);
                result = {
                  'orders': {
                    '42': {
                      'items': [
                        {
                          'product_id': 'product',
                          'name': 'Shirt',
                          'variation_value': 'Red / M',
                          'qty': 2,
                          'price': 125,
                        },
                      ],
                      'subtotal': 250,
                      'shipping_amount': 50,
                      'vouchers': [],
                      'total': 300,
                    },
                  },
                  'total': 300,
                  'quote_hash': 'server-hash',
                  'warnings': [],
                };
              case 'checkout':
                expect(data['quote_hash'], 'server-hash');
                expect(data['delivery_address_id'], 1);
                expect(data['payment_method_id'], 2);
                return http.Response(
                  jsonEncode({'message': 'Stock changed. Review your order.'}),
                  422,
                );
              default:
                throw StateError('Unexpected endpoint: ${request.url.path}');
            }
            return http.Response(jsonEncode(result), 200);
          }),
        );
        const user = AppUser(
          id: 1,
          accountType: 'buyer',
          status: 'approved',
          email: 'buyer@example.invalid',
          givenNames: 'Buyer',
          lastName: 'Fixture',
        );
        const product = Product(
          id: 'product',
          name: 'Shirt',
          price: 125,
          sellerId: 42,
          sellerName: 'Fixture shop',
          categoryName: 'Clothes',
        );
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.current,
            home: CheckoutScreen(
              client: client,
              user: user,
              items: const [
                CartItem(
                  id: 'cart',
                  productId: 'product',
                  color: 'Options',
                  size: 'Red / M',
                  quantity: 2,
                  product: product,
                ),
              ],
            ),
          ),
        );
        await tester.pumpAndSettle();
        if (width == 320) {
          expect(find.text('Retry checkout'), findsOneWidget);
          expect(find.text('Payment'), findsNothing);
          await tester.tap(find.text('Retry checkout'));
          await tester.pumpAndSettle();
        }
        expect(find.text('Fixture shop'), findsOneWidget);
        expect(find.textContaining('Red / M'), findsOneWidget);
        await tester.scrollUntilVisible(
          find.text('Cash on Delivery'),
          250,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.tap(find.text('Cash on Delivery'));
        await tester.pumpAndSettle();
        await tester.scrollUntilVisible(
          find.text('Place order'),
          250,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.tap(find.text('Place order'));
        await tester.pumpAndSettle();
        expect(find.text('Stock changed. Review your order.'), findsWidgets);
        expect(find.text('Refresh and review summary'), findsOneWidget);
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox());
      },
    );
  }
}
