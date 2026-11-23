import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:pocketfinds/models/app_user.dart';
import 'package:pocketfinds/screens/buyer/cart_tab.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';

void main() {
  for (final width in [320.0, 393.0, 430.0]) {
    testWidgets('cart selections, stock controls and removal at $width', (
      tester,
    ) async {
      SharedPreferences.setMockInitialValues({});
      tester.view.physicalSize = Size(width, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final rows = <Map<String, dynamic>>[
        {
          'id': 'a',
          'product_id': 'p1',
          'qty': 1,
          'price': 100,
          'variation_group': '',
          'variation_value': '',
          'seller': 'Shop One',
          'name': 'Long product name with details',
          'img': '',
        },
        {
          'id': 'b',
          'product_id': 'p2',
          'qty': 1,
          'price': 25,
          'variation_group': '',
          'variation_value': '',
          'seller': 'Shop Two',
          'name': 'Second item',
          'img': '',
        },
        {
          'id': 'c',
          'product_id': 'p3',
          'qty': 1,
          'price': 25,
          'variation_group': '',
          'variation_value': '',
          'seller': 'Shop Two',
          'name': 'Unavailable item',
          'img': '',
        },
      ];
      var updates = 0;
      var secondPrice = 25;
      final client = BackendClient(
        transport: MockClient((request) async {
          final data = jsonDecode(request.body) as Map;
          Object result = [];
          if (data['table'] == 'cart_items') {
            if (data['action'] == 'select') result = rows;
            if (data['action'] == 'update') {
              rows[0]['qty'] = (data['data'] as Map)['qty'];
              updates++;
            }
            if (data['action'] == 'delete') {
              rows.removeWhere((r) => r['id'] == 'a');
            }
          } else if (data['table'] == 'products') {
            result = [
              {
                'id': 'p1',
                'name': 'Long product name with details',
                'price': 120,
                'discount_price': 100,
                'stock': 2,
                'seller_id': 10,
                'status': 'active',
              },
              {
                'id': 'p2',
                'name': 'Second item',
                'price': secondPrice,
                'stock': 5,
                'seller_id': 20,
                'status': 'active',
              },
              {
                'id': 'p3',
                'name': 'Unavailable item',
                'price': 25,
                'stock': 0,
                'seller_id': 20,
                'status': 'active',
              },
            ];
          } else if (data['table'] == 'users') {
            result = [
              {'id': 10, 'business_name': 'Shop One'},
              {'id': 20, 'business_name': 'Shop Two'},
            ];
          }
          return http.Response(jsonEncode(result), 200);
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: const TextScaler.linear(1.4)),
            child: child!,
          ),
          home: CartTab(
            user: const AppUser(
              id: 1,
              accountType: 'buyer',
              status: 'approved',
              email: 'test@example.invalid',
              givenNames: 'Test',
              lastName: 'Buyer',
            ),
            client: client,
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.text('Shop One'), findsOneWidget);
      expect(find.text('Shop Two'), findsOneWidget);
      expect(find.text('2 products · 2 items selected'), findsOneWidget);
      await tester.ensureVisible(find.bySemanticsLabel('Select Second item'));
      await tester.pumpAndSettle();
      await tester.tap(find.bySemanticsLabel('Select Second item'));
      await tester.pumpAndSettle();
      expect(find.text('1 products · 1 items selected'), findsOneWidget);
      await tester.ensureVisible(find.byKey(const ValueKey('qty_a_1')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('qty_a_1')));
      await tester.pumpAndSettle();
      expect(updates, 1);
      expect(find.text('1 products · 2 items selected'), findsOneWidget);
      expect(
        tester
            .widget<IconButton>(find.byKey(const ValueKey('qty_a_1')))
            .onPressed,
        isNull,
      );
      await tester.drag(find.byType(ListView).first, const Offset(0, 1500));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Remove selected'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Remove selected'));
      await tester.pumpAndSettle();
      expect(find.text('Remove this item?'), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Remove'));
      await tester.pumpAndSettle();
      expect(find.text('Shop One'), findsNothing);
      expect(find.text('0 products · 0 items selected'), findsOneWidget);
      expect(
        tester
            .widget<FilledButton>(find.widgetWithText(FilledButton, 'Checkout'))
            .onPressed,
        isNull,
      );
      expect(tester.takeException(), isNull);
      await tester.ensureVisible(find.bySemanticsLabel('Select Shop Two'));
      await tester.pumpAndSettle();
      await tester.tap(find.bySemanticsLabel('Select Shop Two'));
      await tester.pumpAndSettle();
      expect(find.text('1 products · 1 items selected'), findsOneWidget);
      secondPrice = 30;
      await tester.tap(find.widgetWithText(FilledButton, 'Checkout'));
      await tester.pumpAndSettle();
      expect(
        find.text(
          'Prices, quantities or availability changed. Review your updated cart before checkout.',
        ),
        findsOneWidget,
      );
      expect(find.text('My Cart'), findsOneWidget);
      expect(tester.takeException(), isNull);
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getStringList('cart_selection_1'), ['b']);
    });
  }
  testWidgets('empty cart offers Continue Shopping', (tester) async {
    SharedPreferences.setMockInitialValues({});
    var continued = false;
    final client = BackendClient(
      transport: MockClient((_) async => http.Response('[]', 200)),
    );
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: CartTab(
          user: const AppUser(
            id: 1,
            accountType: 'buyer',
            status: 'approved',
            email: 'test@example.invalid',
            givenNames: 'Test',
            lastName: 'Buyer',
          ),
          client: client,
          onContinueShopping: () {
            continued = true;
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Your Cart is Empty'), findsOneWidget);
    expect(find.text('Checkout'), findsNothing);
    await tester.tap(find.text('Continue Shopping'));
    expect(continued, isTrue);
    expect(tester.takeException(), isNull);
  });
}
