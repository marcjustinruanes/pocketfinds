import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/models/cart_item.dart';
import 'package:pocketfinds/models/product.dart';
import 'package:pocketfinds/screens/buyer/checkout_screen.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);
  for (final width in [320.0, 393.0, 430.0]) {
    testWidgets('seller shipping, vouchers and safe checkout retry at $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 850);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final submissions = <Map<String, dynamic>>[];
      var lastAddress = 0;
      final client = BackendClient(
        transport: MockClient((request) async {
          final data = Map<String, dynamic>.from(
            jsonDecode(request.body) as Map,
          );
          Object result;
          switch (request.url.path.split('/').last) {
            case 'addresses':
              result = [
                {
                  'id': 1,
                  'recipient_name': 'Other address',
                  'contact_no': '09123456789',
                  'full_address': 'Other valid address',
                },
                {
                  'id': 2,
                  'is_default': true,
                  'recipient_name': 'Buyer with a long full name',
                  'contact_no': '09123456789',
                  'full_address': 'House 10, Long street name, Barangay Banlic, City of Calamba, Laguna',
                  'postal_code': '4027',
                },
              ];
            case 'query':
              result = [
                {
                  'id': 1,
                  'name': 'Cash on Delivery',
                  'description': 'Pay on delivery',
                },
              ];
            case 'quote':
              lastAddress = data['delivery_address_id'] as int;
              result = {
                'orders': {
                  '10': {
                    'items': [
                      {
                        'product_id': 'a',
                        'name': 'A product with a complete long name for mobile checkout',
                        'variation_value': 'Blue / Medium',
                        'qty': 3,
                        'price': 12.35,
                      },
                    ],
                    'subtotal': 37.05,
                    'shipping_amount': 50,
                    'discount_amount': 50,
                    'seller_discount': 0,
                    'vouchers': [
                      {'code': 'FREE50', 'kind': 'shipping', 'amount': 50},
                    ],
                    'total': 37.05,
                  },
                  '20': {
                    'items': [
                      {
                        'product_id': 'b',
                        'name': 'Second shop product',
                        'qty': 2,
                        'price': 49.95,
                      },
                    ],
                    'subtotal': 99.90,
                    'shipping_amount': 50,
                    'discount_amount': 10,
                    'vouchers': [
                      {'code': 'SAVE10', 'kind': 'store', 'amount': 10},
                    ],
                    'total': 139.90,
                  },
                },
                'total': 176.95,
                'quote_hash': 'verified-quote',
                'warnings': [],
                'voucher_options': [
                  {
                    'code': 'MINIMUM',
                    'eligible': false,
                    'reason': 'Minimum spend not reached',
                  },
                ],
              };
            case 'checkout':
              submissions.add(data);
              if (submissions.length == 1) {
                return http.Response(
                  '{"message":"Connection interrupted. Retry the same checkout."}',
                  503,
                );
              }
              result = [
                {'id': 'order-a', 'order_number': 'PF-A'},
                {'id': 'order-b', 'order_number': 'PF-B'},
              ];
            default:
              throw StateError(request.url.path);
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
        lastName: 'Test',
      );
      const productA = Product(
        id: 'a',
        name: 'A',
        price: 12.35,
        sellerId: 10,
        sellerName: 'First shop',
        categoryName: 'Goods',
      );
      const productB = Product(
        id: 'b',
        name: 'B',
        price: 49.95,
        sellerId: 20,
        sellerName: 'Second shop',
        categoryName: 'Goods',
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          builder: (c, child) => MediaQuery(
            data: MediaQuery.of(c)
                .copyWith(textScaler: const TextScaler.linear(1.5)),
            child: child!,
          ),
          home: CheckoutScreen(
            client: client,
            user: user,
            initialVoucherCodes: const ['FREE50', 'SAVE10'],
            items: const [
              CartItem(
                id: 'cart-a',
                productId: 'a',
                color: 'Color',
                size: 'Blue',
                quantity: 3,
                product: productA,
              ),
              CartItem(
                id: 'cart-b',
                productId: 'b',
                color: '',
                size: '',
                quantity: 2,
                product: productB,
              ),
            ],
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(lastAddress, 2);
      expect(find.text('Default'), findsOneWidget);
      expect(find.textContaining('176.95'), findsWidgets);
      Future<void> reveal(String text) async {
        await tester.scrollUntilVisible(
          find.text(text),
          220,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
      }

      await reveal('First shop');
      await reveal('Second shop');
      expect(tester.takeException(), isNull);
      await reveal('FREE50 applied');
      await reveal('Minimum spend not reached');
      await reveal('Cash on Delivery');
      await tester.tap(find.text('Cash on Delivery'));
      await tester.pumpAndSettle();
      await reveal('Optional note');
      await tester.enterText(
        find.widgetWithText(TextField, 'Optional note'),
        'Please call on arrival',
      );
      await tester.testTextInput.receiveAction(TextInputAction.done);
      FocusManager.instance.primaryFocus?.unfocus();
      await tester.pumpAndSettle();
      await reveal('Payment details');
      expect(find.text('2 products  /  5 items  /  2 shops'), findsOneWidget);
      expect(find.textContaining('100.00'), findsWidgets);
      expect(find.textContaining('60.00'), findsWidgets);
      await tester.tap(find.text('Place order'));
      await tester.pumpAndSettle();
      expect(find.text('Retry the same checkout'), findsOneWidget);
      await tester.tap(find.text('Retry the same checkout'));
      await tester.pumpAndSettle();
      expect(submissions.length, 2);
      expect(submissions[0], submissions[1]);
      expect(submissions[1]['buyer_note'], 'Please call on arrival');
      expect(submissions[1]['delivery_address_id'], 2);
      expect(find.textContaining('PF-A, PF-B'), findsOneWidget);
      expect(find.text('Payment: Cash on Delivery'), findsOneWidget);
      expect(find.textContaining('176.95'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
  testWidgets('no saved address cannot submit a checkout', (tester) async {
    var submits = 0;
    final client = BackendClient(
      transport: MockClient((request) async {
        final path = request.url.path;
        if (path.endsWith('/addresses')) return http.Response('[]', 200);
        if (path.endsWith('/query')) {
          return http.Response('[{"id":1,"name":"Cash on Delivery"}]', 200);
        }
        if (path.endsWith('/checkout')) {
          submits++;
          return http.Response('[]', 200);
        }
        return http.Response(
          '{"orders":{"10":{"items":[{"product_id":"a","name":"Product","qty":1,"price":100}],"subtotal":100,"shipping_amount":0,"shipping_pending":true,"total":100}},"total":100,"quote_hash":"preview","warnings":[]}',
          200,
        );
      }),
    );
    const user = AppUser(
      id: 1,
      accountType: 'buyer',
      status: 'approved',
      email: 'buyer@example.invalid',
      givenNames: 'Buyer',
      lastName: 'Test',
    );
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: CheckoutScreen(
          client: client,
          user: user,
          items: const [
            CartItem(
              id: 'cart-a',
              productId: 'a',
              color: '',
              size: '',
              quantity: 1,
              product: Product(
                id: 'a',
                name: 'Product',
                price: 100,
                sellerId: 10,
                sellerName: 'Shop',
                categoryName: 'Goods',
              ),
            ),
          ],
          initialPaymentMethodId: 1,
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.textContaining('No delivery address selected.'),
      findsOneWidget,
    );
    expect(find.text('Add new address'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(
            find.widgetWithText(FilledButton, 'Place order'),
          )
          .onPressed,
      isNull,
    );
    expect(submits, 0);
    expect(tester.takeException(), isNull);
  });
}
